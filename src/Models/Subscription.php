<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Stancl\Tenancy\Database\Concerns\CentralConnection;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Subscription extends Model
{
    use CentralConnection; // 总是使用中央数据库连接

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
        static::creating(function ($subscription) {
            if (tenancy()->initialized && tenant() && !$subscription->tenant_id) {
                $subscription->tenant_id = tenant('id');
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
        'lago_subscription_id',
        'lago_external_id',
        'plan_code',
        'plan_name',
        'status',
        'subscription_at',
        'started_at',
        'ending_at',
        'terminated_at',
        'lago_data',
    ];

    protected $casts = [
        'subscription_at' => 'datetime',
        'started_at' => 'datetime',
        'ending_at' => 'datetime',
        'terminated_at' => 'datetime',
        'lago_data' => 'array',
    ];

    /**
     * Get the tenant that owns this subscription.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the payments for this subscription.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get status options
     */
    public static function getStatusOptions(): array
    {
        return [
            'active' => __('Active'),
            'pending' => __('Pending'),
            'terminated' => __('Terminated'),
            'canceled' => __('Canceled'),
        ];
    }

    /**
     * Get status display label
     */
    public function getStatusLabelAttribute(): string
    {
        return self::getStatusOptions()[$this->status] ?? $this->status;
    }

    /**
     * Check if subscription is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty();
    }
}
