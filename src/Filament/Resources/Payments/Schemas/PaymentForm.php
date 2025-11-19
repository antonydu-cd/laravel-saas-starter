<?php

namespace App\Filament\Resources\Payments\Schemas;

use App\Models\Payment;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Schema;

class PaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('tenant_id')
                ->label(__('Tenant'))
                ->relationship('tenant', 'name')
                ->getOptionLabelFromRecordUsing(fn ($record) => $record->name ?? 'Tenant ' . $record->id)
                ->searchable()
                ->preload()
                ->required(),

            Select::make('subscription_id')
                ->label(__('Subscription'))
                ->relationship('subscription', 'lago_external_id')
                ->getOptionLabelFromRecordUsing(fn ($record) => $record->plan_name . ' (' . $record->lago_external_id . ')')
                ->searchable()
                ->preload(),

            Select::make('gateway')
                ->label(__('Gateway'))
                ->options([
                    'stripe' => 'Stripe',
                    'paypal' => 'PayPal',
                    'manual' => 'Manual',
                ])
                ->default('stripe')
                ->required(),

            TextInput::make('transaction_id')
                ->label(__('Transaction ID'))
                ->maxLength(255),

            TextInput::make('session_id')
                ->label(__('Session ID'))
                ->maxLength(255),

            TextInput::make('plan_code')
                ->label(__('Plan Code'))
                ->maxLength(255),

            TextInput::make('amount')
                ->label(__('Amount'))
                ->numeric()
                ->default(0)
                ->required(),

            Select::make('currency')
                ->label(__('Currency'))
                ->options([
                    'USD' => 'USD ($)',
                    'EUR' => 'EUR (€)',
                    'CNY' => 'CNY (¥)',
                    'GBP' => 'GBP (£)',
                ])
                ->default('USD')
                ->required(),

            Select::make('status')
                ->label(__('Status'))
                ->options(Payment::getStatusOptions())
                ->required()
                ->default('pending'),

            Textarea::make('description')
                ->label(__('Description'))
                ->rows(3)
                ->maxLength(65535),

            DateTimePicker::make('paid_at')
                ->label(__('Paid At')),
        ]);
    }
}
