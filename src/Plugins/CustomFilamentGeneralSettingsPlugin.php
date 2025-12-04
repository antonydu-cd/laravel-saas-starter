<?php

namespace App\Plugins;

use Joaopaulolndev\FilamentGeneralSettings\FilamentGeneralSettingsPlugin;
use App\Filament\Pages\GeneralSettingsPage;

class CustomFilamentGeneralSettingsPlugin extends FilamentGeneralSettingsPlugin
{
    /**
     * Prepare the pages for the plugin
     */
    protected function preparePages(): array
    {
        return [
            GeneralSettingsPage::class,
        ];
    }
}

