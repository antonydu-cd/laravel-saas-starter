<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request and add security headers.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Prevent clickjacking attacks
        $response->headers->set('X-Frame-Options', 'DENY');

        // Prevent MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Enable XSS protection (legacy browsers)
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Control referrer information
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Restrict browser features
        $response->headers->set('Permissions-Policy',
            'geolocation=(), microphone=(), camera=(), payment=()'
        );

        // Content Security Policy
        $csp = $this->buildContentSecurityPolicy();
        $response->headers->set('Content-Security-Policy', $csp);

        // Strict Transport Security (HSTS) - only in production with HTTPS
        if (app()->environment('production') && $request->secure()) {
            $response->headers->set('Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        return $response;
    }

    /**
     * Build Content Security Policy header value
     */
    private function buildContentSecurityPolicy(): string
    {
        $directives = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://js.stripe.com",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: https: blob:",
            "font-src 'self' data:",
            "connect-src 'self' https://api.stripe.com",
            "frame-src 'self' https://js.stripe.com https://hooks.stripe.com",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "upgrade-insecure-requests",
        ];

        // Remove upgrade-insecure-requests in development
        if (!app()->environment('production')) {
            $directives = array_filter($directives, function($directive) {
                return $directive !== 'upgrade-insecure-requests';
            });
        }

        return implode('; ', $directives);
    }
}
