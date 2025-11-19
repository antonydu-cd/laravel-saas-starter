<?php

namespace App\Filament\Resources\Plans\Pages;

use App\Filament\Resources\Plans\PlanResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;
use App\Services\LagoService;
use App\Models\Plan;

class ListPlans extends ListRecords
{
    protected static string $resource = PlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('sync_from_lago')
                ->label(__('Sync from Lago'))
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Sync Plans from Lago')
                ->modalDescription('This will fetch all plans from Lago and sync them to the local database. Existing plans will be updated.')
                ->action(function () {
                    try {
                        $lagoService = app(LagoService::class);
                        $response = $lagoService->getPlans();
                        $plans = $response['plans'] ?? [];

                        // Get all Lago plan codes
                        $lagoPlanCodes = collect($plans)->pluck('code')->filter()->values()->toArray();

                        $synced = 0;
                        $created = 0;
                        $updated = 0;
                        $deleted = 0;

                        // Delete plans that exist locally but not in Lago
                        $localPlans = Plan::all();
                        foreach ($localPlans as $localPlan) {
                            if (!in_array($localPlan->lago_plan_code, $lagoPlanCodes)) {
                                $localPlan->delete();
                                $deleted++;
                            }
                        }

                        // Sync/create plans from Lago
                        foreach ($plans as $lagoPlan) {
                            $planCode = $lagoPlan['code'] ?? null;
                            if (!$planCode) {
                                continue;
                            }

                            // Extract features from metadata
                            $features = [];
                            if (isset($lagoPlan['metadata']) && is_array($lagoPlan['metadata'])) {
                                $metadataFeatures = $lagoPlan['metadata']['features'] ?? $lagoPlan['metadata']['特性'] ?? [];
                                if (is_array($metadataFeatures)) {
                                    $features = array_map(fn($f) => ['feature' => $f], $metadataFeatures);
                                } elseif (is_string($metadataFeatures)) {
                                    $features = array_map(fn($f) => ['feature' => trim($f)], explode(',', $metadataFeatures));
                                }
                            }

                            $planData = [
                                'name' => $lagoPlan['name'] ?? $planCode,
                                'description' => $lagoPlan['description'] ?? '',
                                'amount_cents' => (int) ($lagoPlan['amount_cents'] ?? 0),
                                'amount_currency' => $lagoPlan['amount_currency'] ?? 'CNY',
                                'interval' => $lagoPlan['interval'] ?? 'monthly',
                                'trial_period' => (int) ($lagoPlan['trial_period'] ?? 0),
                                'features' => $features,
                                'is_active' => true, // All plans from Lago are active by default
                                'lago_data' => $lagoPlan,
                            ];

                            $plan = Plan::updateOrCreate(
                                ['lago_plan_code' => $planCode],
                                $planData
                            );

                            if ($plan->wasRecentlyCreated) {
                                $created++;
                            } else {
                                $updated++;
                            }
                            $synced++;
                        }

                        Notification::make()
                            ->title('Plans synced successfully')
                            ->body("Synced {$synced} plans ({$created} created, {$updated} updated, {$deleted} deleted)")
                            ->success()
                            ->send();

                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Failed to sync plans')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
