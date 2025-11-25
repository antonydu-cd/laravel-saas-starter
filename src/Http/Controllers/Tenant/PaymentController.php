<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentSuccessRequest;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\LagoService;
use App\Services\StripeService;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    /**
     * Handle successful payment callback from Stripe
     */
    public function success(PaymentSuccessRequest $request)
    {
        try {
            $validated = $request->validated();
            $sessionId = $validated['session_id'];

            // 使用数据库事务和行锁防止重复处理
            return DB::transaction(function () use ($sessionId) {
                // 检查是否已处理，使用悲观锁
                $existingPayment = Payment::where('session_id', $sessionId)
                    ->lockForUpdate()
                    ->first();

                if ($existingPayment) {
                    if ($existingPayment->isCompleted()) {
                        Log::warning('Duplicate payment attempt detected', [
                            'session_id' => $sessionId,
                            'payment_id' => $existingPayment->id,
                            'ip' => request()->ip(),
                        ]);
                        return redirect()->route('filament.app.pages.pricing')
                            ->with('info', 'Payment has already been processed.');
                    }

                    // 标记为处理中，防止并发
                    $existingPayment->update(['status' => 'processing']);
                }

                // Retrieve session from Stripe
                $stripeService = app(StripeService::class);
                $sessionData = $stripeService->retrieveSession($sessionId);

                if (!$sessionData) {
                    throw new \RuntimeException('Failed to retrieve payment session');
                }

                // Check if payment is successful
                if ($sessionData['payment_status'] !== 'paid') {
                    if ($existingPayment) {
                        $existingPayment->update(['status' => 'failed']);
                    }
                    return redirect()->route('filament.app.pages.pricing')
                        ->with('error', 'Payment was not completed. Please try again.');
                }

                // Get tenant
                $tenant = Tenant::current();
                if (!$tenant) {
                    throw new \RuntimeException('Unable to get current tenant');
                }

                // Get metadata from session
                $metadata = $sessionData['metadata'] ?? [];
                $planCode = $metadata['plan_code'] ?? null;
                $planName = $metadata['plan_name'] ?? 'Unknown Plan';

                if (!$planCode) {
                    throw new \RuntimeException('Missing plan information');
                }

                return $this->processPayment($tenant, $sessionId, $sessionData, $planCode, $planName, $existingPayment);
            }, 5); // 5次重试

        } catch (\Throwable $e) {
            Log::error('Payment processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('filament.app.pages.pricing')
                ->with('error', 'Payment processing failed. Please contact support if you were charged.');
        }
    }

    /**
     * Process payment logic (extracted for transaction)
     */
    private function processPayment($tenant, $sessionId, $sessionData, $planCode, $planName, $existingPayment = null)
    {
        // Create Lago customer if not exists
        $lagoService = app(LagoService::class);
        if (!$tenant->lago_customer_id) {
            Log::info('Creating Lago customer', ['tenant_id' => $tenant->id]);
            try {
                $customer = $lagoService->createCustomer([
                    'external_id' => $tenant->id,
                    'name' => $tenant->name,
                    'email' => $tenant->email ?? $sessionData['customer_email'],
                    'phone' => $tenant->phone,
                    'address' => $tenant->address,
                ]);

                $lagoCustomerId = $customer['lago_id'] ?? $customer['external_id'] ?? $tenant->id;
                $tenant->update([
                    'lago_customer_id' => $lagoCustomerId,
                ]);

                Log::info('Lago customer created successfully', [
                    'tenant_id' => $tenant->id,
                    'lago_customer_id' => $lagoCustomerId,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to create Lago customer', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                throw new \RuntimeException('Failed to create Lago customer: ' . $e->getMessage());
            }
        } else {
            Log::info('Using existing Lago customer', [
                'tenant_id' => $tenant->id,
                'lago_customer_id' => $tenant->lago_customer_id,
            ]);
        }

        // Create Lago subscription
        $externalId = 'sub_' . strtolower((string) Str::ulid());
        Log::info('Creating Lago subscription', [
            'tenant_id' => $tenant->id,
            'plan_code' => $planCode,
            'external_id' => $externalId,
        ]);

        try {
            $lagoSubscription = $lagoService->createSubscription([
                'external_customer_id' => $tenant->id,
                'plan_code' => $planCode,
                'external_id' => $externalId,
                'subscription_at' => now(),
            ]);

            Log::info('Lago subscription created successfully', [
                'lago_subscription' => $lagoSubscription,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create Lago subscription', [
                'tenant_id' => $tenant->id,
                'plan_code' => $planCode,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \RuntimeException('Failed to create Lago subscription: ' . $e->getMessage());
        }

        // Create local subscription record in central database
        $subscription = Subscription::create([
            'tenant_id' => $tenant->id,
            'lago_subscription_id' => $lagoSubscription['lago_id'] ?? $lagoSubscription['id'] ?? (string) Str::ulid(),
            'lago_external_id' => $externalId,
            'plan_code' => $planCode,
            'plan_name' => $lagoSubscription['plan']['name'] ?? $planName,
            'status' => $lagoSubscription['status'] ?? 'active',
            'subscription_at' => now(),
            'started_at' => $lagoSubscription['started_at'] ?? now(),
            'ending_at' => $lagoSubscription['ending_at'] ?? null,
            'lago_data' => $lagoSubscription,
        ]);

        // Create or update payment record
        if ($existingPayment) {
            $existingPayment->update([
                'subscription_id' => $subscription->id,
                'transaction_id' => $sessionData['payment_intent'],
                'status' => 'completed',
                'paid_at' => now(),
                'metadata' => array_merge($existingPayment->metadata ?? [], $sessionData),
            ]);
            $payment = $existingPayment;
        } else {
            $payment = Payment::create([
                'tenant_id' => $tenant->id,
                'subscription_id' => $subscription->id,
                'gateway' => 'stripe',
                'transaction_id' => $sessionData['payment_intent'],
                'session_id' => $sessionId,
                'plan_code' => $planCode,
                'amount' => ($sessionData['amount_total'] ?? 0) / 100, // Convert cents to dollars
                'currency' => strtoupper($sessionData['currency'] ?? 'USD'),
                'status' => 'completed',
                'description' => "Payment for {$planName}",
                'metadata' => $sessionData,
                'paid_at' => now(),
            ]);
        }

        Log::info('Payment processed successfully', [
            'payment_id' => $payment->id,
            'subscription_id' => $subscription->id,
            'tenant_id' => $tenant->id,
        ]);

        // Create a more detailed success message
        $planName = $subscription->plan_name ?? $planCode;
        $successMessage = "🎉 Payment Successful!\n\n" .
                         "Your {$planName} subscription has been activated.\n" .
                         "Subscription ID: {$subscription->lago_subscription_id}\n" .
                         "Payment Amount: {$payment->amount} {$payment->currency}";

        return redirect()->route('filament.app.pages.pricing')
            ->with('success', $successMessage);
    }

    /**
     * Handle cancelled payment
     */
    public function cancel(Request $request)
    {
        return redirect()->route('filament.app.pages.pricing')
            ->with('info', 'ℹ️ Payment cancelled\n\nYou can return anytime to select a different subscription plan.');
    }

    /**
     * Handle Stripe webhook (optional - for additional payment events)
     * This webhook is optional and can be configured if needed
     */
    public function webhook(Request $request)
    {
        try {
            $stripeService = app(StripeService::class);

            $payload = $request->getContent();
            $signature = $request->header('Stripe-Signature');

            // Verify webhook signature
            $event = $stripeService->constructWebhookEvent($payload, $signature);

            Log::info('Stripe webhook received', [
                'type' => $event->type,
                'id' => $event->id,
            ]);

            // Handle different event types
            switch ($event->type) {
                case 'checkout.session.completed':
                    // This is already handled by the success callback
                    // But you can add additional processing here if needed
                    Log::info('Checkout session completed webhook', [
                        'session_id' => $event->data->object->id,
                    ]);
                    break;

                case 'payment_intent.succeeded':
                    // Payment succeeded
                    Log::info('Payment intent succeeded', [
                        'payment_intent_id' => $event->data->object->id,
                    ]);
                    break;

                case 'payment_intent.payment_failed':
                    // Payment failed
                    $paymentIntentId = $event->data->object->id;
                    $payment = Payment::where('transaction_id', $paymentIntentId)->first();
                    if ($payment) {
                        $payment->markAsFailed();
                    }
                    break;

                default:
                    Log::info('Unhandled webhook event type', ['type' => $event->type]);
            }

            return response()->json(['success' => true]);

        } catch (\Throwable $e) {
            Log::error('Webhook processing failed', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
