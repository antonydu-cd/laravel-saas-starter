<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

use App\Traits\HasGeneralSettings;

class LagoService
{
    use HasGeneralSettings;

    private string $baseUrl;
    private string $apiKey;
    private int $timeout;

    public function __construct()
    {
        // 只从数据库设置读取配置，完全不依赖env或config
        $this->baseUrl = rtrim((string) $this->getSetting('lago_base_url'), '/');
        $this->apiKey = (string) $this->getSetting('lago_api_key', null, true);
        $this->timeout = (int) $this->getSetting('lago_timeout', 30);

        if ($this->baseUrl === '' || $this->apiKey === '') {
            throw new \RuntimeException('Lago configuration is incomplete. Please configure LAGO settings in General Settings.');
        }
    }

    private function toRfc3339(null|\DateTimeInterface|string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->utc()->toIso8601String();
        }

        try {
            return Carbon::parse((string) $value)->utc()->toIso8601String();
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException('Invalid datetime value provided: ' . (string) $value);
        }
    }

    /**
     * 创建 Lago 客户
     */
    public function createCustomer(array $customerData): array
    {
        $payload = [
            'customer' => [
                'external_id' => $customerData['external_id'],
                'name' => $customerData['name'],
                'email' => $customerData['email'],
                'address_line1' => $customerData['address'] ?? null,
                'phone' => $customerData['phone'] ?? null,
                'metadata' => $customerData['metadata'] ?? [],
            ]
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])
        ->timeout($this->timeout)
        ->post($this->baseUrl . '/api/v1/customers', $payload);

        if (!$response->successful()) {
            Log::error('Failed to create Lago customer', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            throw new \RuntimeException('Failed to create Lago customer: ' . $response->body());
        }

        return $response->json('customer', []);
    }

    /**
     * 获取客户信息
     */
    public function getCustomer(string $externalId): ?array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
        ])
        ->timeout($this->timeout)
        ->get($this->baseUrl . '/api/v1/customers/' . $externalId);

        if ($response->status() === 404) {
            return null;
        }

        if (!$response->successful()) {
            Log::error('Failed to get Lago customer', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            throw new \RuntimeException('Failed to get Lago customer: ' . $response->body());
        }

        return $response->json('customer', []);
    }

    /**
     * 更新客户信息
     */
    public function updateCustomer(string $externalId, array $customerData): array
    {
        $payload = [
            'customer' => array_filter([
                'name' => $customerData['name'] ?? null,
                'email' => $customerData['email'] ?? null,
                'address_line1' => $customerData['address'] ?? null,
                'phone' => $customerData['phone'] ?? null,
                'metadata' => $customerData['metadata'] ?? null,
            ])
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])
        ->timeout($this->timeout)
        ->put($this->baseUrl . '/api/v1/customers/' . $externalId, $payload);

        if (!$response->successful()) {
            Log::error('Failed to update Lago customer', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            throw new \RuntimeException('Failed to update Lago customer: ' . $response->body());
        }

        return $response->json('customer', []);
    }

    /**
     * 获取所有计划（Plans）
     */
    public function getPlans(int $page = 1, int $perPage = 20): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
        ])
        ->timeout($this->timeout)
        ->get($this->baseUrl . '/api/v1/plans');
        if (!$response->successful()) {
            Log::error('Failed to get Lago plans', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            throw new \RuntimeException('Failed to get Lago plans: ' . $response->body());
        }

        $data = $response->json();
        return is_array($data) ? $data : ['plans' => []];
    }

    /**
     * 获取单个计划
     */
    public function getPlan(string $planCode): ?array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
        ])
        ->timeout($this->timeout)
        ->get($this->baseUrl . '/api/v1/plans/' . $planCode);

        if ($response->status() === 404) {
            return null;
        }

        if (!$response->successful()) {
            Log::error('Failed to get Lago plan', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            throw new \RuntimeException('Failed to get Lago plan: ' . $response->body());
        }

        return $response->json('plan', []);
    }

    /**
     * 创建订阅
     */
    public function createSubscription(array $subscriptionData): array
    {
        $payload = [
            'subscription' => [
                'external_customer_id' => $subscriptionData['external_customer_id'],
                'plan_code' => $subscriptionData['plan_code'],
                'name' => $subscriptionData['name'] ?? null,
                'external_id' => $subscriptionData['external_id'] ?? null,
                'subscription_at' => $this->toRfc3339($subscriptionData['subscription_at'] ?? now()),
                'ending_at' => $this->toRfc3339($subscriptionData['ending_at'] ?? null),
            ]
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])
        ->timeout($this->timeout)
        ->post($this->baseUrl . '/api/v1/subscriptions', $payload);

        if (!$response->successful()) {
            Log::error('Failed to create Lago subscription', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            throw new \RuntimeException('Failed to create Lago subscription: ' . $response->body());
        }

        return $response->json('subscription', []);
    }

    /**
     * 获取订阅列表
     */
    public function getSubscriptions(string $externalCustomerId, int $page = 1, int $perPage = 20): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
        ])
        ->timeout($this->timeout)
        ->get($this->baseUrl . '/api/v1/subscriptions', [
            'external_customer_id' => $externalCustomerId,
            'page' => $page,
            'per_page' => $perPage,
        ]);

        if (!$response->successful()) {
            Log::error('Failed to get Lago subscriptions', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            throw new \RuntimeException('Failed to get Lago subscriptions: ' . $response->body());
        }

        $data = $response->json();
        return is_array($data) ? $data : ['subscriptions' => []];
    }

    /**
     * 获取所有订阅（所有客户的订阅）
     * 包括所有状态的订阅（active, pending, terminated等）
     */
    public function getAllSubscriptions(int $page = 1, int $perPage = 100): array
    {
        $allSubscriptions = [];

        // Lago API 可能需要分别获取不同状态的订阅
        // 我们尝试获取所有可能的状态
        $statuses = ['terminated', 'active', 'pending', 'canceled'];

        foreach ($statuses as $status) {
            $currentPage = 1;

            // 循环获取当前状态的所有页
            do {
                try {
                    $params = [
                        'page' => $currentPage,
                        'per_page' => $perPage,
                    ];

                    // 如果 status 不是 null，添加到参数中
                    if ($status) {
                        $params['status'] = $status;
                    }

                    $response = Http::withHeaders([
                        'Authorization' => 'Bearer ' . $this->apiKey,
                    ])
                    ->timeout($this->timeout)
                    ->get($this->baseUrl . '/api/v1/subscriptions', $params);
                    if (!$response->successful()) {
                        Log::warning('Failed to get Lago subscriptions for status', [
                            'status_filter' => $status,
                            'http_status' => $response->status(),
                            'body' => $response->body(),
                            'page' => $currentPage,
                        ]);
                        // 继续处理其他状态
                        break;
                    }

                    $data = $response->json();
                    $subscriptions = $data['subscriptions'] ?? [];

                    Log::info('Retrieved subscriptions from Lago', [
                        'status_filter' => $status,
                        'page' => $currentPage,
                        'count' => count($subscriptions),
                    ]);

                    // 使用 external_id 作为键来避免重复
                    foreach ($subscriptions as $sub) {
                        $externalId = $sub['external_id'] ?? null;
                        if ($externalId) {
                            $allSubscriptions[$externalId] = $sub;
                        }
                    }

                    // 检查是否有更多页
                    $meta = $data['meta'] ?? [];
                    $hasMorePages = ($meta['current_page'] ?? 0) < ($meta['total_pages'] ?? 0);

                    if (!$hasMorePages || empty($subscriptions)) {
                        break;
                    }

                    $currentPage++;
                } catch (\Exception $e) {
                    Log::error('Error fetching subscriptions for status', [
                        'status_filter' => $status,
                        'error' => $e->getMessage(),
                    ]);
                    break;
                }
            } while (true);
        }

        Log::info('Total unique subscriptions retrieved', [
            'count' => count($allSubscriptions),
        ]);

        // 将关联数组转换回索引数组
        return ['subscriptions' => array_values($allSubscriptions)];
    }

    /**
     * 取消订阅
     */
    public function terminateSubscription(string $externalId): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
        ])
        ->timeout($this->timeout)
        ->delete($this->baseUrl . '/api/v1/subscriptions/' . $externalId);

        if (!$response->successful()) {
            Log::error('Failed to terminate Lago subscription', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            throw new \RuntimeException('Failed to terminate Lago subscription: ' . $response->body());
        }

        return $response->json('subscription', []);
    }

    /**
     * 获取发票列表
     */
    public function getInvoices(string $externalCustomerId, int $page = 1, int $perPage = 20): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
        ])
        ->timeout($this->timeout)
        ->get($this->baseUrl . '/api/v1/invoices', [
            'external_customer_id' => $externalCustomerId,
            'page' => $page,
            'per_page' => $perPage,
        ]);

        if (!$response->successful()) {
            Log::error('Failed to get Lago invoices', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            throw new \RuntimeException('Failed to get Lago invoices: ' . $response->body());
        }

        $data = $response->json();
        return is_array($data) ? $data : ['invoices' => []];
    }

    /**
     * 同步或创建客户（如果不存在则创建）
     */
    public function syncCustomer(string $externalId, array $customerData): array
    {
        $existingCustomer = $this->getCustomer($externalId);

        if ($existingCustomer) {
            return $this->updateCustomer($externalId, $customerData);
        }

        return $this->createCustomer(array_merge($customerData, ['external_id' => $externalId]));
    }

    /**
     * 同步订阅数据
     * 从 Lago 同步订阅状态到中央平台，并安全清理无效订阅
     *
     * 关键原则：
     * - 终止和取消状态的订阅永远保留（即使 Lago 中不存在）
     * - 只删除明确不在 Lago 中且状态不是终止/取消的订阅
     * - 新创建的订阅有保护期，避免同步时机问题
     */
    public function syncSubscriptions(): array
    {
        $syncStats = [
            'synced' => 0,
            'updated' => 0,
            'created' => 0,
            'deleted' => 0,
            'errors' => [],
        ];

        try {
            Log::info('Starting subscription sync from Lago');

            // 获取所有 Lago 订阅（包括已终止的）
            $lagoSubscriptionsData = $this->getAllSubscriptions(1, 100);
            $lagoSubscriptions = $lagoSubscriptionsData['subscriptions'] ?? [];
            // 统计各状态的订阅数量
            $statusCounts = [];
            foreach ($lagoSubscriptions as $sub) {
                $status = $sub['status'] ?? 'unknown';
                $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
            }

            Log::info('Retrieved subscriptions from Lago', [
                'total_count' => count($lagoSubscriptions),
                'status_breakdown' => $statusCounts,
            ]);

            // 创建 Lago 订阅映射（使用 external_id 作为键）
            $lagoExternalIds = [];
            foreach ($lagoSubscriptions as $lagoSub) {
                $externalId = $lagoSub['external_id'] ?? null;
                if ($externalId) {
                    $lagoExternalIds[$externalId] = $lagoSub;
                }
            }

            // 获取中央平台的所有订阅
            $localSubscriptions = \App\Models\Subscription::all();

            Log::info('Retrieved local subscriptions', [
                'count' => $localSubscriptions->count(),
            ]);

            // 创建本地订阅的 external_id 映射，用于检查哪些订阅在 Lago 中不存在
            $localExternalIds = [];
            foreach ($localSubscriptions as $localSub) {
                if ($localSub->lago_external_id) {
                    $localExternalIds[$localSub->lago_external_id] = $localSub;
                }
            }

            // 同步或更新订阅（只处理 Lago 返回的订阅）
            foreach ($lagoSubscriptions as $lagoSub) {
                try {
                    $externalId = $lagoSub['external_id'] ?? null;
                    $lagoId = $lagoSub['lago_id'] ?? null;
                    $status = $lagoSub['status'] ?? 'pending';

                    if (!$externalId) {
                        Log::warning('Skipping subscription without external_id', [
                            'lago_id' => $lagoId,
                        ]);
                        continue;
                    }

                    // 查找本地订阅
                    $localSub = \App\Models\Subscription::where('lago_external_id', $externalId)
                        ->orWhere('lago_subscription_id', $lagoId)
                        ->first();

                    $subscriptionData = [
                        'lago_subscription_id' => $lagoId,
                        'lago_external_id' => $externalId,
                        'plan_code' => $lagoSub['plan_code'] ?? null,
                        'plan_name' => $lagoSub['name'] ?? $lagoSub['plan_code'] ?? 'Unknown Plan',
                        'status' => $status,
                        'subscription_at' => isset($lagoSub['subscription_at']) ? Carbon::parse($lagoSub['subscription_at']) : null,
                        'started_at' => isset($lagoSub['started_at']) ? Carbon::parse($lagoSub['started_at']) : null,
                        'ending_at' => isset($lagoSub['ending_at']) ? Carbon::parse($lagoSub['ending_at']) : null,
                        'terminated_at' => isset($lagoSub['terminated_at']) ? Carbon::parse($lagoSub['terminated_at']) : null,
                        'lago_data' => $lagoSub,
                    ];

                    if ($localSub) {
                        // 更新现有订阅（包括状态更新为 terminated）
                        $oldStatus = $localSub->status;
                        $localSub->update($subscriptionData);
                        $syncStats['updated']++;

                        if ($oldStatus !== $status) {
                            Log::info('Subscription status updated', [
                                'external_id' => $externalId,
                                'old_status' => $oldStatus,
                                'new_status' => $status,
                            ]);
                        }
                    } else {
                        // 创建新订阅（如果能找到对应的租户）
                        $externalCustomerId = $lagoSub['external_customer_id'] ?? null;
                        if ($externalCustomerId) {
                            // 首先尝试通过ID直接查找
                            $tenant = \App\Models\Tenant::find($externalCustomerId);

                            // 如果找不到，通过email匹配Lago客户信息
                            if (!$tenant) {
                                try {
                                    $lagoCustomer = $this->getCustomer($externalCustomerId);
                                    if ($lagoCustomer && isset($lagoCustomer['email'])) {
                                        // 首先尝试直接通过email字段查找
                                        $tenant = \App\Models\Tenant::where('email', $lagoCustomer['email'])->first();

                                        // 如果找不到，遍历所有租户检查email属性
                                        if (!$tenant) {
                                            $allTenants = \App\Models\Tenant::all();
                                            foreach ($allTenants as $t) {
                                                if ($t->email === $lagoCustomer['email']) {
                                                    $tenant = $t;
                                                    break;
                                                }
                                            }
                                        }

                                        Log::info('Found tenant by email match', [
                                            'lago_customer_id' => $externalCustomerId,
                                            'lago_email' => $lagoCustomer['email'],
                                            'tenant_id' => $tenant ? $tenant->id : null,
                                            'matched_from' => $tenant ? 'data_field' : 'not_found',
                                        ]);
                                    }
                                } catch (\Exception $e) {
                                    Log::warning('Failed to get Lago customer details', [
                                        'external_customer_id' => $externalCustomerId,
                                        'error' => $e->getMessage(),
                                    ]);
                                }
                            }

                            if ($tenant) {
                                $subscriptionData['tenant_id'] = $tenant->id;
                                \App\Models\Subscription::create($subscriptionData);
                                $syncStats['created']++;

                                Log::info('Created new subscription', [
                                    'external_id' => $externalId,
                                    'status' => $status,
                                    'tenant_id' => $tenant->id,
                                    'matched_by' => isset($lagoCustomer) ? 'email' : 'id',
                                ]);
                            } else {
                                Log::warning('Tenant not found for subscription', [
                                    'external_customer_id' => $externalCustomerId,
                                    'external_id' => $externalId,
                                    'tried_email_match' => true,
                                ]);
                            }
                        }
                    }

                    $syncStats['synced']++;
                } catch (\Exception $e) {
                    $syncStats['errors'][] = 'Error syncing subscription ' . ($lagoSub['external_id'] ?? 'unknown') . ': ' . $e->getMessage();
                    Log::error('Error syncing individual subscription', [
                        'subscription' => $lagoSub,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            // 删除中央平台存在但 Lago 不存在的订阅
            // 重要：终止和取消状态的订阅永远保留，无论是否在 Lago 中存在
            // 重新查询本地订阅以获取更新后的状态
            $updatedLocalSubscriptions = \App\Models\Subscription::all();

            foreach ($updatedLocalSubscriptions as $localSub) {
                $externalId = $localSub->lago_external_id;

                // 🚨 关键保护：终止和取消状态的订阅永远不删除
                if (in_array($localSub->status, ['terminated', 'canceled'])) {
                    Log::debug('PROTECTED: Skipping deletion of terminated/canceled subscription', [
                        'subscription_id' => $localSub->id,
                        'external_id' => $externalId,
                        'status' => $localSub->status,
                        'reason' => 'Status is terminated/canceled - always preserved'
                    ]);
                    continue;
                }

                // 对于活跃状态的订阅，如果 Lago 中不存在，则删除
                if ($externalId && !isset($lagoExternalIds[$externalId])) {
                    // 额外检查：如果是刚创建的订阅（1小时内），也保留，避免误删
                    $hoursSinceCreated = $localSub->created_at->diffInHours(now());
                    if ($hoursSinceCreated < 1) {
                        Log::info('PROTECTED: Skipping deletion of recently created subscription', [
                            'subscription_id' => $localSub->id,
                            'external_id' => $externalId,
                            'status' => $localSub->status,
                            'created_hours_ago' => $hoursSinceCreated,
                            'reason' => 'Recently created - preserved to avoid sync timing issues'
                        ]);
                        continue;
                    }

                    try {
                        Log::info('Deleting subscription not found in Lago', [
                            'subscription_id' => $localSub->id,
                            'external_id' => $externalId,
                            'status' => $localSub->status,
                            'reason' => 'Not found in Lago API response'
                        ]);

                        $localSub->delete();
                        $syncStats['deleted']++;
                    } catch (\Exception $e) {
                        $syncStats['errors'][] = 'Error deleting subscription ' . $localSub->id . ': ' . $e->getMessage();
                        Log::error('Error deleting subscription', [
                            'subscription_id' => $localSub->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // 特殊处理：检查在 Lago 结果中找不到的本地订阅
            // 这些订阅可能已经被终止，但在 Lago API 中不可见
            $this->checkMissingSubscriptions($localExternalIds, $lagoExternalIds, $syncStats);

            Log::info('Subscription sync completed', $syncStats);

        } catch (\Exception $e) {
            $syncStats['errors'][] = 'General sync error: ' . $e->getMessage();
            Log::error('Error during subscription sync', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $syncStats;
    }

    /**
     * 检查在 Lago 同步结果中找不到的本地订阅
     * 这些订阅可能已经被终止或状态改变，但在批量 API 中不可见
     */
    private function checkMissingSubscriptions(array $localExternalIds, array $lagoExternalIds, array &$syncStats): void
    {
        $missingExternalIds = array_diff(array_keys($localExternalIds), array_keys($lagoExternalIds));

        if (empty($missingExternalIds)) {
            Log::debug('No missing subscriptions to check');
            return;
        }

        Log::info('Checking missing subscriptions in Lago', [
            'count' => count($missingExternalIds),
            'external_ids' => $missingExternalIds,
        ]);

        foreach ($missingExternalIds as $externalId) {
            try {
                $localSub = $localExternalIds[$externalId];

                // 只检查 active 或 pending 状态的订阅
                // terminated/canceled 状态的订阅我们已经保护不会删除
                if (!in_array($localSub->status, ['active', 'pending'])) {
                    Log::debug('Skipping status check for non-active subscription', [
                        'subscription_id' => $localSub->id,
                        'external_id' => $externalId,
                        'status' => $localSub->status,
                    ]);
                    continue;
                }

                // 尝试单独查询这个订阅的当前状态
                $subscriptionDetails = $this->getSubscriptionDetails($externalId);

                if ($subscriptionDetails) {
                    $lagoStatus = $subscriptionDetails['status'] ?? 'unknown';
                    $oldStatus = $localSub->status;

                    if ($lagoStatus !== $oldStatus) {
                        // 状态已改变，更新本地订阅
                        $updateData = [
                            'status' => $lagoStatus,
                            'lago_data' => $subscriptionDetails,
                        ];

                        // 如果状态变为 terminated，设置 terminated_at
                        if ($lagoStatus === 'terminated' && isset($subscriptionDetails['terminated_at'])) {
                            $updateData['terminated_at'] = Carbon::parse($subscriptionDetails['terminated_at']);
                        }

                        $localSub->update($updateData);
                        $syncStats['updated']++;

                        Log::info('Updated missing subscription status', [
                            'subscription_id' => $localSub->id,
                            'external_id' => $externalId,
                            'old_status' => $oldStatus,
                            'new_status' => $lagoStatus,
                        ]);
                    } else {
                        Log::debug('Subscription status unchanged', [
                            'subscription_id' => $localSub->id,
                            'external_id' => $externalId,
                            'status' => $lagoStatus,
                        ]);
                    }
                } else {
                    // 查询失败，保持现状
                    Log::warning('Failed to get details for missing subscription', [
                        'subscription_id' => $localSub->id,
                        'external_id' => $externalId,
                        'status' => $localSub->status,
                    ]);
                }

            } catch (\Exception $e) {
                Log::error('Error checking missing subscription', [
                    'external_id' => $externalId,
                    'error' => $e->getMessage(),
                ]);
                $syncStats['errors'][] = 'Error checking subscription ' . $externalId . ': ' . $e->getMessage();
            }
        }
    }

    /**
     * 获取单个订阅的详细信息
     * 尝试通过 external_id 查询订阅的当前状态
     */
    private function getSubscriptionDetails(string $externalId): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])
            ->timeout($this->timeout)
            ->get($this->baseUrl . '/api/v1/subscriptions/' . $externalId);

            if ($response->successful()) {
                $data = $response->json();
                return $data['subscription'] ?? null;
            } elseif ($response->status() === 404) {
                // 订阅不存在，可能已被删除
                Log::info('Subscription not found in Lago (may be deleted)', [
                    'external_id' => $externalId,
                ]);
                return ['status' => 'terminated', 'terminated_at' => now()->toISOString()];
            } else {
                Log::warning('Failed to get subscription details', [
                    'external_id' => $externalId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Error fetching subscription details', [
                'external_id' => $externalId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
