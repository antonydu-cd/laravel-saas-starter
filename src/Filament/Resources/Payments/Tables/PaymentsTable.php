<?php

namespace App\Filament\Resources\Payments\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;

class PaymentsTable
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

                TextColumn::make('subscription.plan_name')
                    ->label(__('Subscription'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('gateway')
                    ->label(__('Gateway'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'stripe' => 'primary',
                        'paypal' => 'warning',
                        'manual' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('transaction_id')
                    ->label(__('Transaction ID'))
                    ->searchable()
                    ->copyable()
                    ->limit(20),

                TextColumn::make('plan_code')
                    ->label(__('Plan'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('amount')
                    ->label(__('Amount'))
                    ->formatStateUsing(fn ($state, $record) => number_format($state, 2) . ' ' . $record->currency)
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        'refunded' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('paid_at')
                    ->label(__('Paid At'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('Created'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label(__('Updated'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options([
                        'pending' => 'Pending',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                        'refunded' => 'Refunded',
                    ]),

                SelectFilter::make('gateway')
                    ->label(__('Gateway'))
                    ->options([
                        'stripe' => 'Stripe',
                        'paypal' => 'PayPal',
                        'manual' => 'Manual',
                    ]),

                SelectFilter::make('currency')
                    ->label(__('Currency'))
                    ->options([
                        'USD' => 'USD',
                        'EUR' => 'EUR',
                        'CNY' => 'CNY',
                        'GBP' => 'GBP',
                    ]),

                Filter::make('paid')
                    ->label(__('Paid'))
                    ->query(fn ($query) => $query->whereNotNull('paid_at')),

                Filter::make('unpaid')
                    ->label(__('Unpaid'))
                    ->query(fn ($query) => $query->whereNull('paid_at')),
            ])
            ->actions([
                //
            ])
            ->bulkActions([
                //
            ])
            ->defaultSort('created_at', 'desc');
    }
}
