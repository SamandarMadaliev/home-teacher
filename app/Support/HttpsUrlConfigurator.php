<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class HttpsUrlConfigurator
{
    /**
     * Force https:// for url(), route(), asset(), and @vite.
     *
     * Uses getenv() so Kubernetes-injected APP_URL / FORCE_HTTPS still apply when
     * config was cached at Docker build time with local http values.
     */
    public static function apply(?Request $request = null): void
    {
        $appUrl = self::runtimeEnv('APP_URL') ?? (string) config('app.url', '');

        $forceHttps = self::runtimeEnvBool('FORCE_HTTPS')
            || (bool) config('app.force_https')
            || str_starts_with($appUrl, 'https://')
            || self::runtimeEnv('APP_ENV') === 'production';

        if (! $forceHttps && $request !== null) {
            $forceHttps = $request->header('X-Forwarded-Proto') === 'https'
                || $request->isSecure();
        }

        if (! $forceHttps) {
            return;
        }

        if ($appUrl !== '') {
            URL::forceRootUrl(rtrim($appUrl, '/'));
        }

        URL::forceScheme('https');
    }

    private static function runtimeEnv(string $key): ?string
    {
        $value = getenv($key);

        if ($value === false || $value === '') {
            return null;
        }

        return $value;
    }

    private static function runtimeEnvBool(string $key): bool
    {
        return filter_var(self::runtimeEnv($key), FILTER_VALIDATE_BOOLEAN);
    }
}
