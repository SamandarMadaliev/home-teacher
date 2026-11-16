<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;

/**
 * Only believe X-Forwarded-* headers (client IP, https) from the proxies in
 * TRUSTED_PROXIES. Trusting every source would let anyone fake their IP —
 * which defeats rate limits — or pretend to be on https.
 *
 * Default: this machine (127.0.0.1, ::1), where a local tunnel or reverse
 * proxy (cloudflared, ngrok, nginx) connects from. "*" trusts everyone.
 */
class TrustProxies extends Middleware
{
    protected function proxies()
    {
        $value = trim((string) config('app.trusted_proxies', ''));
        if ($value === '*' || $value === '**') {
            return $value;
        }

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }
}
