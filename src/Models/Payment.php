<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Stancl\Tenancy\Database\Concerns\CentralConnection;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Payment extends Model
{
    use CentralConnection; // 总是使用中央数据库连接
    use LogsActivity;

    protected $table = 'subscription_payments';

    /**
     * Boot the model and add global scopes for tenant isolation
     */
    protected static function booted(): void
    {
        // 自动过滤当前租户的数据(仅在租户上下文中)
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (tenancy()->initialized && tenant()) {
                $builder->where('tenant_id', tenant('id'));
            }
        });

        // 创建时自动设置tenant_id
        static::creating(function ($payment) {
            if (tenancy()->initialized && tenant() && !$payment->tenant_id) {
                $payment->tenant_id = tenant('id');
            }
        });
    }

    /**
     * Scope to bypass tenant isolation (for admin access)
     */
    public function scopeAllTenants(Builder $query): Builder
    {
        return $query->withoutGlobalScope('tenant');
    }

    protected $fillable = [
        'tenant_id',
        'subscription_id',
        'gateway',
        'transaction_id',
        'session_id',
        'plan_code',
        'amount',
        'currency',
        'status',
        'description',
        'metadata',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
        'paid_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the tenant that owns the payment.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the subscription that owns the payment.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Check if payment is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if payment is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if payment is failed
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Mark payment as completed
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'paid_at' => now(),
        ]);
    }

    /**
     * Mark payment as failed
     */
    public function markAsFailed(): void
    {
        $this->update([
            'status' => 'failed',
        ]);
    }

    /**
     * Get status options
     */
    public static function getStatusOptions(): array
    {
        return [
            'pending' => __('Pending'),
            'completed' => __('Completed'),
            'failed' => __('Failed'),
            'refunded' => __('Refunded'),
        ];
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        return self::getStatusOptions()[$this->status] ?? $this->status;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty();
    }
}
