<?php

namespace App\Observers;

use Illuminate\Support\Facades\Crypt;
use Joaopaulolndev\FilamentGeneralSettings\Models\GeneralSetting;
use Illuminate\Support\Facades\Log;

class GeneralSettingObserver
{
    /**
     * Handle the GeneralSetting "saving" event.
     * Encrypt sensitive fields before saving to database.
     */
    public function saving(GeneralSetting $generalSetting): void
    {
        $jsonColumns = ['more_configs', 'email_settings', 'social_network', 'seo_metadata'];
        $encryptedFields = $this->getEncryptedFields();

        foreach ($jsonColumns as $column) {
            if ($generalSetting->isDirty($column) && is_array($generalSetting->$column)) {
                $generalSetting->$column = $this->encryptSensitiveFields($generalSetting->$column, $encryptedFields);
            }
        }
    }

    /**
     * Handle the GeneralSetting "retrieved" event.
     * Decrypt sensitive fields after retrieving from database.
     */
    public function retrieved(GeneralSetting $generalSetting): void
    {
        $jsonColumns = ['more_configs', 'email_settings', 'social_network', 'seo_metadata'];
        $encryptedFields = $this->getEncryptedFields();

        foreach ($jsonColumns as $column) {
            if (is_array($generalSetting->$column)) {
                $generalSetting->$column = $this->decryptSensitiveFields($generalSetting->$column, $encryptedFields);
            }
        }
    }

    /**
     * Get list of fields that should be encrypted from config
     */
    protected function getEncryptedFields(): array
    {
        $encryptedFields = config('filament-general-settings.encrypted_fields', []);
        $customTabs = config('filament-general-settings.custom_tabs', []);

        foreach ($customTabs as $tab) {
            if (isset($tab['fields'])) {
                foreach ($tab['fields'] as $key => $fieldConfig) {
                    if (isset($fieldConfig['encrypt']) && $fieldConfig['encrypt'] === true) {
                        $encryptedFields[] = $key;
                    }
                }
            }
        }

        return array_unique($encryptedFields);
    }

    /**
     * Encrypt sensitive fields within the configuration array
     */
    protected function encryptSensitiveFields(array $data, array $encryptedFields): array
    {
        foreach ($encryptedFields as $field) {
            if (isset($data[$field]) && is_string($data[$field]) && $data[$field] !== '') {
                try {
                    // Check if already encrypted by attempting to decrypt
                    try {
                        Crypt::decryptString($data[$field]);
                        // If successful, it's already encrypted, skip
                        continue;
                    } catch (\Exception $e) {
                        // Not encrypted, proceed to encrypt
                        $data[$field] = Crypt::encryptString($data[$field]);
                    }
                } catch (\Exception $e) {
                    // If encryption fails, keep original value
                    Log::error("Failed to encrypt field {$field}: " . $e->getMessage());
                }
            }
        }

        return $data;
    }

    /**
     * Decrypt sensitive fields within the configuration array
     */
    protected function decryptSensitiveFields(array $data, array $encryptedFields): array
    {
        foreach ($encryptedFields as $field) {
            if (isset($data[$field]) && is_string($data[$field]) && $data[$field] !== '') {
                try {
                    $data[$field] = Crypt::decryptString($data[$field]);
                } catch (\Exception $e) {
                    // If decryption fails, keep original value
                    // This handles cases where data might not be encrypted yet
                }
            }
        }

        return $data;
    }
}
