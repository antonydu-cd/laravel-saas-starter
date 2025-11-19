<?php

namespace App\Filament\App\Pages;

use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\Plan;
use App\Services\StripeService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class Pricing extends Page
{
    protected static ?string $navigationLabel;


    protected static string|\UnitEnum|null $navigationGroup;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected string $view = 'filament.app.pages.pricing';

    public array $plans = [];

    public bool $annual = false;

    public function mount(): void
    {
        // Check for payment success message
        if (session()->has('success')) {
            Notification::make()
                ->title('Payment Successful!')
                ->body(session('success'))
                ->success()
                ->duration(5000)
                ->send();
        }

        // Check for payment error message
        if (session()->has('error')) {
            Notification::make()
                ->title('Payment Failed')
                ->body(session('error'))
                ->danger()
                ->duration(8000)
                ->send();
        }

        // Check for payment info message
        if (session()->has('info')) {
            Notification::make()
                ->title('Notice')
                ->body(session('info'))
                ->info()
                ->duration(5000)
                ->send();
        }

        try {
            // Load plans from local database instead of directly from Lago
            $localPlans = Plan::active()->ordered()->get();

            // Normalize plan data for the view
            $this->plans = $localPlans->map(function ($plan) {
                // Extract features from the repeater format
                $features = [];
                if (is_array($plan->features)) {
                    $features = collect($plan->features)
                        ->pluck('feature')
                        ->filter()
                        ->values()
                        ->all();
                }

                // If no features, use default
                if (empty($features)) {
                    $features = [__('Standard AI model access permissions')];
                }

                // Extract highlights from the repeater format
                $highlights = [];
                if (is_array($plan->highlights)) {
                    $highlights = collect($plan->highlights)
                        ->pluck('highlight')
                        ->filter()
                        ->values()
                        ->all();
                }

                return [
                    'code' => $plan->lago_plan_code,
                    'name' => $plan->name,
                    'description' => $plan->description ?: __('AI model subscription service'),
                    'amount_cents' => $plan->amount_cents,
                    'amount_currency' => $plan->amount_currency,
                    'interval' => $plan->interval,
                    'trial_period' => $plan->trial_period,
                    'features' => $features,
                    'highlights' => $highlights,
                    'is_popular' => $plan->is_popular,
                ];
            })->values()->all();
        } catch (\Throwable $e) {
            $this->plans = [];
            Notification::make()->title('Failed to load plans')->body($e->getMessage())->danger()->persistent()->send();
        }
    }

    public function subscribe(string $planCode): void
    {
        try {
            $tenant = Tenant::current();
            if (! $tenant) {
                throw new \RuntimeException('Unable to get current tenant');
            }

            // Find the plan details
            $plan = collect($this->plans)->firstWhere('code', $planCode);
            if (!$plan) {
                throw new \RuntimeException('Plan not found');
            }

            // Create Stripe Checkout Session
            $stripeService = app(StripeService::class);

            $successUrl = route('tenant.payment.success') . '?session_id={CHECKOUT_SESSION_ID}';
            $cancelUrl = route('tenant.payment.cancel');

            $session = $stripeService->createCheckoutSession([
                'plan_name' => $plan['name'],
                'description' => $plan['description'],
                'amount_cents' => $plan['amount_cents'],
                'currency' => $plan['amount_currency'],
                'customer_email' => $tenant->email,
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'metadata' => [
                    'tenant_id' => $tenant->id,
                    'plan_code' => $planCode,
                    'plan_name' => $plan['name'],
                ],
            ]);

            // Log the checkout session creation
            Log::info('Stripe checkout session created', [
                'session_id' => $session['id'],
                'tenant_id' => $tenant->id,
                'plan_code' => $planCode,
            ]);

            // Create a pending payment record
            \App\Models\Payment::create([
                'tenant_id' => $tenant->id,
                'gateway' => 'stripe',
                'session_id' => $session['id'],
                'plan_code' => $planCode,
                'amount' => $plan['amount_cents'] / 100, // Convert cents to dollars
                'currency' => strtoupper($plan['amount_currency']),
                'status' => 'pending',
                'description' => "Payment for {$plan['name']}",
                'metadata' => [
                    'plan_code' => $planCode,
                    'plan_name' => $plan['name'],
                    'tenant_id' => $tenant->id,
                ],
            ]);

            // Redirect to Stripe Checkout page
            $this->redirect($session['url'], navigate: false);

        } catch (\Throwable $e) {
            Log::error('Failed to create payment session', [
                'error' => $e->getMessage(),
                'plan_code' => $planCode,
            ]);
            Notification::make()
                ->title('Payment failed')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }

    public function getTitle(): string
    {
        return __('AI Model Plans');
    }

    public static function getNavigationLabel(): string
    {
        return __('AI Model Plans');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Billing Management');
    }
}
