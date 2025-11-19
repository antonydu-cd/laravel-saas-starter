<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Plan extends Model
{
    use CentralConnection; // 总是使用中央数据库连接
    use LogsActivity;
    protected $fillable = [
        'lago_plan_code',
        'name',
        'description',
        'amount_cents',
        'amount_currency',
        'interval',
        'trial_period',
        'features',
        'highlights',
        'is_active',
        'is_popular',
        'sort_order',
        'lago_data',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'trial_period' => 'integer',
        'features' => 'array',
        'highlights' => 'array',
        'is_active' => 'boolean',
        'is_popular' => 'boolean',
        'sort_order' => 'integer',
        'lago_data' => 'array',
    ];

    /**
     * Get interval options
     */
    public static function getIntervalOptions(): array
    {
        return [
            'monthly' => 'Monthly',
            'yearly' => 'Yearly',
            'weekly' => 'Weekly',
        ];
    }

    /**
     * Get currency options
     */
    public static function getCurrencyOptions(): array
    {
        return [
            'CNY' => 'CNY (¥)',
            'USD' => 'USD ($)',
            'EUR' => 'EUR (€)',
            'GBP' => 'GBP (£)',
        ];
    }

    /**
     * Get formatted price
     */
    public function getFormattedPriceAttribute(): string
    {
        $price = $this->amount_cents / 100;
        return $this->amount_currency . ' ' . number_format($price, 2);
    }

    /**
     * Scope for active plans only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for ordered plans
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('amount_cents');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty();
    }
}
