<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;

class SecureLogger
{
    /**
     * Sensitive keys that should be redacted in logs
     */
    private const SENSITIVE_KEYS = [
        'api_key',
        'secret_key',
        'secret',
        'password',
        'token',
        'access_token',
        'refresh_token',
        'card_number',
        'cvv',
        'cvc',
        'ssn',
        'tax_id',
        'bank_account',
        'routing_number',
        'authorization',
        'webhook_secret',
    ];

    /**
     * Sanitize data by removing sensitive information
     *
     * @param mixed $data
     * @return mixed
     */
    public static function sanitize($data)
    {
        if (is_array($data)) {
            return self::sanitizeArray($data);
        }

        if (is_object($data)) {
            return self::sanitizeObject($data);
        }

        return $data;
    }

    /**
     * Sanitize array recursively
     *
     * @param array $data
     * @return array
     */
    private static function sanitizeArray(array $data): array
    {
        array_walk_recursive($data, function (&$value, $key) {
            if (is_string($key) && self::isSensitiveKey($key)) {
                $value = '[REDACTED]';
            }
        });

        return $data;
    }

    /**
     * Sanitize object
     *
     * @param object $data
     * @return object
     */
    private static function sanitizeObject(object $data): object
    {
        $array = json_decode(json_encode($data), true);
        $sanitized = self::sanitizeArray($array);
        return json_decode(json_encode($sanitized));
    }

    /**
     * Check if a key is sensitive
     *
     * @param string $key
     * @return bool
     */
    private static function isSensitiveKey(string $key): bool
    {
        $lowerKey = strtolower($key);

        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if (str_contains($lowerKey, $sensitive)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Log info with sanitized data
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public static function info(string $message, array $context = []): void
    {
        Log::info($message, self::sanitize($context));
    }

    /**
     * Log error with sanitized data
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public static function error(string $message, array $context = []): void
    {
        Log::error($message, self::sanitize($context));
    }

    /**
     * Log warning with sanitized data
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public static function warning(string $message, array $context = []): void
    {
        Log::warning($message, self::sanitize($context));
    }

    /**
     * Log debug with sanitized data
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public static function debug(string $message, array $context = []): void
    {
        Log::debug($message, self::sanitize($context));
    }
}
