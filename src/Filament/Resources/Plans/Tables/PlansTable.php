<?php

namespace App\Filament\Resources\Plans\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;

class PlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('lago_plan_code')
                    ->label(__('Plan Code'))
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('amount_cents')
                    ->label(__('Price'))
                    ->formatStateUsing(fn ($state, $record) => $record->formatted_price)
                    ->sortable(),

                TextColumn::make('interval')
                    ->label(__('Interval'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'monthly' => 'success',
                        'yearly' => 'warning',
                        'weekly' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),

                IconColumn::make('is_popular')
                    ->label(__('Popular'))
                    ->boolean()
                    ->sortable(),

                TextColumn::make('sort_order')
                    ->label(__('Order'))
                    ->sortable(),

                TextColumn::make('features')
                    ->label(__('Features'))
                    ->formatStateUsing(fn ($state) => is_array($state) ? count($state) . ' features' : '0 features')
                    ->color('gray'),

                TextColumn::make('highlights')
                    ->label(__('Highlights'))
                    ->formatStateUsing(fn ($state) => is_array($state) ? count($state) . ' highlights' : '0 highlights')
                    ->color('primary'),

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
                TernaryFilter::make('is_popular')
                    ->label(__('Popular'))
                    ->placeholder('All plans')
                    ->trueLabel('Popular only')
                    ->falseLabel('Non-popular only'),

                SelectFilter::make('interval')
                    ->label(__('Interval'))
                    ->options([
                        'monthly' => 'Monthly',
                        'yearly' => 'Yearly',
                        'weekly' => 'Weekly',
                    ]),
            ])
            ->actions([
                //
            ])
            ->bulkActions([
                //
            ])
            ->defaultSort('sort_order', 'asc');
    }
}
