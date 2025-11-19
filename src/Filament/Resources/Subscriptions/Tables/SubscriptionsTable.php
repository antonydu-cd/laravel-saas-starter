<?php

namespace App\Filament\Resources\Subscriptions\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\ViewAction;
use App\Models\Subscription;

class SubscriptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(__('ID'))
                    ->sortable(),

                TextColumn::make('tenant.name')
                    ->label(__('Tenant'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('plan_code')
                    ->label(__('Plan Code'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('plan_name')
                    ->label(__('Plan Name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'pending' => 'warning',
                        'terminated' => 'danger',
                        'canceled' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => Subscription::getStatusOptions()[$state] ?? $state),

                TextColumn::make('subscription_at')
                    ->label(__('Subscription Date'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('started_at')
                    ->label(__('Started At'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('ending_at')
                    ->label(__('Ending At'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label(__('Updated At'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(Subscription::getStatusOptions()),

                SelectFilter::make('tenant_id')
                    ->label(__('Tenant'))
                    ->relationship('tenant', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name ?? 'Tenant ' . $record->id)
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->bulkActions([
                //
            ])
            ->defaultSort('created_at', 'desc');
    }
}
