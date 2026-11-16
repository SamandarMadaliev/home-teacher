<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline browser protections for every response:
 * - no framing by other sites (clickjacking); the app's own lesson iframes
 *   are same-origin, so they keep working;
 * - no MIME sniffing (an uploaded-looking file can't be run as a script);
 * - referrers don't leak full URLs (which can contain share links) to other sites;
 * - powerful browser features are off.
 * HSTS is only sent over HTTPS in production, where it can't lock out a
 * local http:// setup. Over HTTPS the session cookie is also marked Secure.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Over real https, the session cookie must never travel over plain
        // http. (Runs before the session starts; respects an explicit
        // SESSION_SECURE_COOKIE and keeps local http:// logins working.)
        if ($request->isSecure() && config('session.secure') === null) {
            config(['session.secure' => true]);
        }

        $response = $next($request);

        $headers = [
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
            'Cross-Origin-Opener-Policy' => 'same-origin-allow-popups',
        ];
        // Problem statements are markdown written by other members; a remote
        // image (![](https://…)) would leak each viewer's IP to its host.
        if ($request->routeIs('problems.*')) {
            $headers['Content-Security-Policy'] = "img-src 'self' data:";
        }
        if ($request->isSecure() && app()->isProduction()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000';
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}
