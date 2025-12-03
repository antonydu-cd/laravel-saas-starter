<?php

namespace App\Filament\Pages;

use Filament\Facades\Filament;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Joaopaulolndev\FilamentGeneralSettings\Forms\AnalyticsFieldsForm;
use Joaopaulolndev\FilamentGeneralSettings\Forms\ApplicationFieldsForm;
use Joaopaulolndev\FilamentGeneralSettings\Forms\CustomForms;
use Joaopaulolndev\FilamentGeneralSettings\Forms\EmailFieldsForm;
use Joaopaulolndev\FilamentGeneralSettings\Forms\SeoFieldsForm;
use Joaopaulolndev\FilamentGeneralSettings\Forms\SocialNetworkFieldsForm;
use Joaopaulolndev\FilamentGeneralSettings\Pages\GeneralSettingsPage as BaseGeneralSettingsPage;

class GeneralSettingsPage extends BaseGeneralSettingsPage
{
    public function form(Schema $schema): Schema
    {
        $arrTabs = [];
        $currentPanelId = Filament::getCurrentPanel()?->getId() ?? 'admin';

        if (config('filament-general-settings.show_application_tab')) {
            $arrTabs[] = Tab::make('Application Tab')
                ->label(__('filament-general-settings::default.application'))
                ->icon('heroicon-o-tv')
                ->schema(ApplicationFieldsForm::get())
                ->columns(3);
        }

        if (config('filament-general-settings.show_analytics_tab')) {
            $arrTabs[] = Tab::make('Analytics Tab')
                ->label(__('filament-general-settings::default.analytics'))
                ->icon('heroicon-o-globe-alt')
                ->schema(AnalyticsFieldsForm::get());
        }

        if (config('filament-general-settings.show_seo_tab')) {
            $arrTabs[] = Tab::make('Seo Tab')
                ->label(__('filament-general-settings::default.seo'))
                ->icon('heroicon-o-window')
                ->schema(SeoFieldsForm::get($this->data))
                ->columns(1);
        }

        if (config('filament-general-settings.show_email_tab')) {
            $arrTabs[] = Tab::make('Email Tab')
                ->label(__('filament-general-settings::default.email'))
                ->icon('heroicon-o-envelope')
                ->schema(EmailFieldsForm::get())
                ->columns(3);
        }

        if (config('filament-general-settings.show_social_networks_tab')) {
            $arrTabs[] = Tab::make('Social Network Tab')
                ->label(__('filament-general-settings::default.social_networks'))
                ->icon('heroicon-o-heart')
                ->schema(SocialNetworkFieldsForm::get())
                ->columns(2)
                ->statePath('social_network');
        }

        if (config('filament-general-settings.show_custom_tabs')) {
            foreach (config('filament-general-settings.custom_tabs') as $key => $customTab) {
                // 过滤字段，只显示当前面板允许的字段
                $filteredFields = $this->filterFieldsByPanel($customTab['fields'] ?? [], $currentPanelId);

                if (empty($filteredFields)) {
                    continue; // 如果没有字段可显示，跳过这个标签页
                }

                $arrTabs[] = Tab::make($customTab['label'])
                    ->label(__($customTab['label']))
                    ->icon($customTab['icon'])
                    ->schema(CustomForms::get($filteredFields))
                    ->columns($customTab['columns'])
                    ->statePath('more_configs');
            }
        }

        return $schema
            ->components([
                Tabs::make('Tabs')
                    ->tabs($arrTabs),
            ])
            ->statePath('data');
    }

    /**
     * 根据当前面板过滤字段
     *
     * @param array $fields
     * @param string $currentPanelId
     * @return array
     */
    protected function filterFieldsByPanel(array $fields, string $currentPanelId): array
    {
        $filteredFields = [];

        foreach ($fields as $fieldKey => $fieldConfig) {
            // 如果字段没有 visible_in_panels 配置，默认在所有面板显示
            if (!isset($fieldConfig['visible_in_panels'])) {
                $filteredFields[$fieldKey] = $fieldConfig;
                continue;
            }

            // 如果字段配置了 visible_in_panels，检查当前面板是否在允许列表中
            $visiblePanels = is_array($fieldConfig['visible_in_panels'])
                ? $fieldConfig['visible_in_panels']
                : [$fieldConfig['visible_in_panels']];

            if (in_array($currentPanelId, $visiblePanels)) {
                // 移除 visible_in_panels 配置，因为 CustomForms 不需要这个字段
                $fieldConfigWithoutVisibility = $fieldConfig;
                unset($fieldConfigWithoutVisibility['visible_in_panels']);
                $filteredFields[$fieldKey] = $fieldConfigWithoutVisibility;
            }
        }

        return $filteredFields;
    }
}

