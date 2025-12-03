<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;

trait HasGeneralSettings
{
    /**
     * 从数据库设置中获取配置值
     * 自动解密敏感字段
     *
     * @param string $key 配置键名
     * @param mixed $default 默认值
     * @param bool $isEncrypted 是否需要解密
     * @param bool $useCentralDatabase 是否使用中央数据库（用于租户上下文）
     * @return mixed
     */
    protected function getSetting(string $key, $default = null, bool $isEncrypted = false, bool $useCentralDatabase = false)
    {
        // 验证key只包含允许的字符，防止注入
        if (!preg_match('/^[a-z_]+$/', $key)) {
            Log::error('Invalid setting key format', ['key' => $key]);
            throw new \InvalidArgumentException('Invalid setting key format');
        }

        // 如果需要使用中央数据库，切换到中央数据库连接
        if ($useCentralDatabase) {
            return $this->getSettingFromCentralDatabase($key, $default, $isEncrypted);
        }

        $settings = DB::table('general_settings')->first();
        if ($settings && $settings->more_configs) {
            $moreConfigs = json_decode($settings->more_configs, true);
            if (!is_array($moreConfigs)) {
                Log::error('Invalid more_configs format in database');
                return $default;
            }

            $value = $moreConfigs[$key] ?? $default;
            // 如果指定需要解密，且值不为空
            if ($isEncrypted && $value !== null && $value !== '') {
                try {
                    return Crypt::decryptString($value);
                } catch (\Exception $e) {
                    Log::error("Failed to decrypt field {$key}: " . $e->getMessage());
                    return $value;
                }
            }

            return $value;
        }
        return $default;
    }

    /**
     * 从中央数据库获取设置（用于租户上下文）
     *
     * @param string $key 配置键名
     * @param mixed $default 默认值
     * @param bool $isEncrypted 是否需要解密
     * @return mixed
     */
    protected function getSettingFromCentralDatabase(string $key, $default = null, bool $isEncrypted = false)
    {
        // 获取中央数据库连接名称
        $centralConnection = config('tenancy.database.central_connection', 'mysql');
        
        // 切换到中央数据库连接
        $settings = DB::connection($centralConnection)->table('general_settings')->first();
        
        if ($settings && $settings->more_configs) {
            $moreConfigs = json_decode($settings->more_configs, true);
            if (!is_array($moreConfigs)) {
                Log::error('Invalid more_configs format in central database');
                return $default;
            }

            $value = $moreConfigs[$key] ?? $default;
            // 如果指定需要解密，且值不为空
            if ($isEncrypted && $value !== null && $value !== '') {
                try {
                    return Crypt::decryptString($value);
                } catch (\Exception $e) {
                    Log::error("Failed to decrypt field {$key} from central database: " . $e->getMessage());
                    return $value;
                }
            }

            return $value;
        }
        return $default;
    }
}
