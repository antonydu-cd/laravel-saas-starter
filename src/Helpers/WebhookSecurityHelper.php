<?php

namespace App\Helpers;

class WebhookSecurityHelper
{
    /**
     * Stripe webhook IP ranges (updated as of 2024)
     * Source: https://stripe.com/docs/ips
     */
    private const STRIPE_IP_RANGES = [
        '3.18.12.0/23',
        '3.130.192.0/23',
        '13.235.14.0/23',
        '13.235.122.0/23',
        '18.211.135.69/32',
        '35.154.171.200/32',
        '52.15.183.38/32',
        '54.187.174.169/32',
        '54.187.205.235/32',
        '54.187.216.72/32',
    ];

    /**
     * Check if an IP address is in Stripe's authorized IP ranges
     *
     * @param string $ip
     * @return bool
     */
    public static function isValidStripeIp(string $ip): bool
    {
        // In development, allow all IPs for testing
        if (app()->environment('local', 'testing')) {
            return true;
        }

        foreach (self::STRIPE_IP_RANGES as $range) {
            if (self::ipInRange($ip, $range)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if an IP address is within a CIDR range
     *
     * @param string $ip
     * @param string $range
     * @return bool
     */
    public static function ipInRange(string $ip, string $range): bool
    {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }

        list($subnet, $bits) = explode('/', $range);
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - $bits);
        $subnet &= $mask; // Normalize subnet

        return ($ip & $mask) == $subnet;
    }

    /**
     * Get the real IP address from request, considering proxies
     *
     * @param \Illuminate\Http\Request $request
     * @return string
     */
    public static function getRealIp($request): string
    {
        // Check for forwarded IP (from load balancer/proxy)
        if ($request->header('X-Forwarded-For')) {
            $ips = explode(',', $request->header('X-Forwarded-For'));
            return trim($ips[0]);
        }

        if ($request->header('X-Real-IP')) {
            return $request->header('X-Real-IP');
        }

        return $request->ip();
    }

    /**
     * Sanitize webhook payload for logging
     * Removes sensitive data before logging
     *
     * @param array $payload
     * @return array
     */
    public static function sanitizePayloadForLogging(array $payload): array
    {
        $sensitiveKeys = ['card', 'bank_account', 'password', 'secret', 'token'];

        array_walk_recursive($payload, function (&$value, $key) use ($sensitiveKeys) {
            if (is_string($key)) {
                $lowerKey = strtolower($key);
                foreach ($sensitiveKeys as $sensitive) {
                    if (str_contains($lowerKey, $sensitive)) {
                        $value = '[REDACTED]';
                        break;
                    }
                }
            }
        });

        return $payload;
    }
}
