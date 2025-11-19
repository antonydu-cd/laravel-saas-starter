<?php

namespace App\Filament\Resources\Plans\Schemas;

use App\Models\Plan;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Schema;

class PlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('lago_plan_code')
                ->label(__('Lago Plan Code'))
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(255)
                ->disabled() // 从Lago同步的数据，不能修改
                ->helperText(__('Synced from Lago - cannot be modified')),

            TextInput::make('name')
                ->label(__('Plan Name'))
                ->required()
                ->maxLength(255)
                ->disabled() // 从Lago同步的数据，不能修改
                ->helperText(__('Synced from Lago - cannot be modified')),

            Textarea::make('description')
                ->label(__('Description'))
                ->rows(3)
                ->maxLength(65535)
                ->columnSpanFull()
                ->helperText(__('Can be customized locally. Original data synced from Lago.')),

            TextInput::make('amount_cents')
                ->label(__('Price (cents)'))
                ->required()
                ->numeric()
                ->default(0)
                ->disabled() // 从Lago同步的数据，不能修改
                ->helperText(__('Synced from Lago - cannot be modified')),

            Select::make('amount_currency')
                ->label(__('Currency'))
                ->required()
                ->options(Plan::getCurrencyOptions())
                ->default('CNY')
                ->disabled() // 从Lago同步的数据，不能修改
                ->helperText(__('Synced from Lago - cannot be modified')),

            Select::make('interval')
                ->label(__('Billing Interval'))
                ->required()
                ->options(Plan::getIntervalOptions())
                ->default('monthly')
                ->disabled() // 从Lago同步的数据，不能修改
                ->helperText(__('Synced from Lago - cannot be modified')),

            TextInput::make('trial_period')
                ->label(__('Trial Period (days)'))
                ->numeric()
                ->default(0)
                ->minValue(0)
                ->disabled() // 从Lago同步的数据，不能修改
                ->helperText(__('Synced from Lago - cannot be modified')),

            Repeater::make('features')
                ->label(__('Features'))
                ->schema([
                    TextInput::make('feature')
                        ->label(__('Feature'))
                        ->required()
                        ->maxLength(255)
                        ->helperText(__('Can be customized locally. Original data synced from Lago.')),
                ])
                ->defaultItems(0)
                ->helperText(__('Can be customized locally. Original data synced from Lago.'))
                ->columnSpanFull()
                ->reorderable()
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['feature'] ?? null),

            Repeater::make('highlights')
                ->label(__('Highlights / Selling Points'))
                ->schema([
                    TextInput::make('highlight')
                        ->label(__('Highlight'))
                        ->required()
                        ->maxLength(255)
                        ->helperText(__('Add attractive selling points')),
                ])
                ->defaultItems(0)
                ->addActionLabel(__('Add Highlight'))
                ->columnSpanFull()
                ->reorderable()
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['highlight'] ?? null),

            Toggle::make('is_popular')
                ->label(__('Popular / Recommended'))
                ->default(false)
                ->helperText(__('Mark as popular plan')),

            TextInput::make('sort_order')
                ->label(__('Sort Order'))
                ->numeric()
                ->default(0)
                ->helperText(__('Lower numbers appear first')),
        ]);
    }
}
