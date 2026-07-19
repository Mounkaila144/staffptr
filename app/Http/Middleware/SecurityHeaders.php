<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    private const HSTS = 'max-age=31536000; includeSubDomains; preload';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('request_id', (string) Str::uuid());

        $vite = app(Vite::class);
        $nonce = $vite->useCspNonce(base64_encode(random_bytes(24)));
        $request->attributes->set('csp_nonce', $nonce);

        return $this->apply($request, $next($request));
    }

    public function apply(Request $request, Response $response): Response
    {
        $vite = app(Vite::class);
        $nonce = (string) $request->attributes->get('csp_nonce');

        if ($nonce === '') {
            $nonce = $vite->useCspNonce(base64_encode(random_bytes(24)));
            $request->attributes->set('csp_nonce', $nonce);
        }

        if (! $request->attributes->has('request_id')) {
            $request->attributes->set('request_id', (string) Str::uuid());
        }

        $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy($vite, $nonce));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), camera=(), microphone=()');
        $response->headers->set('X-Request-ID', (string) $request->attributes->get('request_id'));

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', self::HSTS);
        } else {
            $response->headers->remove('Strict-Transport-Security');
        }

        return $response;
    }

    private function contentSecurityPolicy(Vite $vite, string $nonce): string
    {
        if (app()->environment('local') && $vite->isRunningHot()) {
            $hotServer = rtrim((string) file_get_contents($vite->hotFile()), '/');
            $webSocketServer = preg_replace('/^http/', 'ws', $hotServer) ?: $hotServer;

            return implode('; ', [
                "default-src 'self' {$hotServer}",
                "script-src 'self' 'nonce-{$nonce}' {$hotServer}",
                "style-src 'self' 'unsafe-inline' {$hotServer}",
                "connect-src 'self' {$hotServer} {$webSocketServer}",
                "img-src 'self' data:",
                "object-src 'none'",
                "frame-ancestors 'none'",
                "base-uri 'self'",
            ]);
        }

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
            "style-src 'self'",
            "img-src 'self' data:",
            "object-src 'none'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
        ]);
    }
}
