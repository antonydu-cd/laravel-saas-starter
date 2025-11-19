<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Contracts\Tenant;

class InitializeTenantSettings implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected Tenant $tenant
    ) {}

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return $this->tenant->getTenantKey() . ':init-settings';
    }

    /**
     * Execute the job.
     *
     * This job initializes general_settings for the tenant with default API configurations.
     */
    public function handle(): void
    {
        // Explicitly run in tenant context
        $this->tenant->run(function () {
            // Check if general_settings already exists
            $existing = DB::table('general_settings')->first();

            if (!$existing) {
                // Create default general_settings with API configurations
                DB::table('general_settings')->insert([
                    'site_name' => 'My SaaS App',
                    'site_description' => 'A powerful SaaS application built with Laravel and Filament',
                    'theme_color' => '#3b82f6',
                    'support_email' => 'support@example.com',
                    'support_phone' => '',
                    'google_analytics_id' => '',
                    'posthog_html_snippet' => '',
                    'seo_title' => 'My SaaS App',
                    'seo_keywords' => 'saas,laravel,filament',
                    'seo_metadata' => '',
                    'email_settings' => '{}',
                    'email_from_address' => 'noreply@example.com',
                    'email_from_name' => 'My SaaS App',
                    'social_network' => '{}',
                    'more_configs' => json_encode([
                        'lago_base_url' => 'http://localhost:3000',
                        'lago_api_key' => '',
                        'lago_timeout' => '30',
                        'stripe_secret_key' => '',
                        'stripe_publishable_key' => '',
                    ]),
                    'site_logo' => '',
                    'site_favicon' => '',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }
}
