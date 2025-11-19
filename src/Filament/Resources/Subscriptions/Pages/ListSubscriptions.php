<?php

namespace App\Filament\Resources\Subscriptions\Pages;

use App\Filament\Resources\Subscriptions\SubscriptionResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\Action;
use App\Services\LagoService;
use Filament\Notifications\Notification;

class ListSubscriptions extends ListRecords
{
    protected static string $resource = SubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync_subscriptions')
                ->label(__('Sync from Lago'))
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading(__('Sync Subscriptions from Lago'))
                ->modalDescription(__('This will synchronize all subscription data from Lago. Subscriptions that exist in the central platform but not in Lago will be deleted (except terminated subscriptions).'))
                ->modalSubmitActionLabel(__('Sync Now'))
                ->action(function () {
                    try {
                        $lagoService = app(LagoService::class);
                        $stats = $lagoService->syncSubscriptions();

                        $message = sprintf(
                            'Sync completed: %d synced, %d updated, %d created, %d deleted',
                            $stats['synced'],
                            $stats['updated'],
                            $stats['created'],
                            $stats['deleted']
                        );

                        if (!empty($stats['errors'])) {
                            $message .= '. ' . count($stats['errors']) . ' errors occurred.';
                            Notification::make()
                                ->warning()
                                ->title(__('Sync Completed with Errors'))
                                ->body($message)
                                ->duration(10000)
                                ->send();
                        } else {
                            Notification::make()
                                ->success()
                                ->title(__('Sync Successful'))
                                ->body($message)
                                ->duration(5000)
                                ->send();
                        }

                        // 刷新列表
                        $this->redirect(static::getUrl());

                    } catch (\Exception $e) {
                        Notification::make()
                            ->danger()
                            ->title(__('Sync Failed'))
                            ->body($e->getMessage())
                            ->persistent()
                            ->send();
                    }
                }),
        ];
    }
}
