<?php

namespace App\Filament\Resources\Subscriptions\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use App\Models\Tenant;
use App\Models\Subscription;

class SubscriptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('tenant_id')
                    ->label(__('Tenant'))
                    ->relationship('tenant', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name ?? 'Tenant ' . $record->id)
                    ->searchable()
                    ->preload()
                    ->required(),

                TextInput::make('lago_subscription_id')
                    ->label(__('Lago Subscription ID'))
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                TextInput::make('lago_external_id')
                    ->label(__('Lago External ID'))
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                TextInput::make('plan_code')
                    ->label(__('Plan Code'))
                    ->required()
                    ->maxLength(255),

                TextInput::make('plan_name')
                    ->label(__('Plan Name'))
                    ->maxLength(255),

                Select::make('status')
                    ->label(__('Status'))
                    ->options(Subscription::getStatusOptions())
                    ->required()
                    ->default('pending'),

                DateTimePicker::make('subscription_at')
                    ->label(__('Subscription Date')),

                DateTimePicker::make('started_at')
                    ->label(__('Started At')),

                DateTimePicker::make('ending_at')
                    ->label(__('Ending At')),

                DateTimePicker::make('terminated_at')
                    ->label(__('Terminated At')),
            ]);
    }
}
