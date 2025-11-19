<?php

namespace App\Pages;

use Filament\Pages\Page;

class MerchantGeneralSettingsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog';

    protected static string $view = 'filament.pages.general-settings';

    protected static ?string $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'General Settings';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('View:GeneralSettingsPage') ?? false;
    }

    public function getTitle(): string
    {
        return __('General Settings');
    }
}
