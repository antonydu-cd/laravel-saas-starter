<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

trait HasGeneralSettings
{
    /**
     * 从数据库设置中获取配置值
     * 自动解密敏感字段
     * 
     * @param string $key 配置键名
     * @param mixed $default 默认值
     * @param bool $isEncrypted 是否需要解密
     * @return mixed
     */
    protected function getSetting(string $key, $default = null, bool $isEncrypted = false)
    {
        // 验证key只包含允许的字符，防止注入
        if (!preg_match('/^[a-z_]+$/', $key)) {
            Log::error('Invalid setting key format', ['key' => $key]);
            throw new \InvalidArgumentException('Invalid setting key format');
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
                    return decrypt($value);
                } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                    Log::error('Failed to decrypt setting - may be unencrypted legacy data', [
                        'key' => $key,
                        'error' => $e->getMessage(),
                    ]);
                    // 如果解密失败，可能是旧数据未加密，直接返回
                    // 生产环境应该强制要求加密
                    return $value;
                }
            }

            return $value;
        }
        return $default;
    }
}
