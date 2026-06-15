<?php

/**
 * Write .env from the process environment (Kubernetes ConfigMap / Secrets).
 * PHP-FPM and Laravel both read this file; do not bake http://localhost into the image.
 */

$keys = [
    'APP_NAME',
    'APP_ENV',
    'APP_KEY',
    'APP_DEBUG',
    'APP_URL',
    'FORCE_HTTPS',
    'APP_LOCALE',
    'APP_FALLBACK_LOCALE',
    'APP_FAKER_LOCALE',
    'APP_MAINTENANCE_DRIVER',
    'DB_CONNECTION',
    'DB_DATABASE',
    'SESSION_DRIVER',
    'SESSION_LIFETIME',
    'SESSION_ENCRYPT',
    'SESSION_PATH',
    'SESSION_DOMAIN',
    'SESSION_SECURE_COOKIE',
    'CACHE_STORE',
    'QUEUE_CONNECTION',
    'LOG_CHANNEL',
    'LOG_LEVEL',
    'COURSE_VIDEOS_PATH',
    'GOOGLE_CLIENT_ID',
    'GOOGLE_CLIENT_SECRET',
    'GOOGLE_REDIRECT_URI',
    'VITE_APP_NAME',
];

$lines = [];

foreach ($keys as $key) {
    $value = getenv($key);

    if ($value === false || $value === '') {
        continue;
    }

    if (preg_match('/[\s#="\']/', $value)) {
        $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
        $lines[] = $key.'="'.$escaped.'"';
    } else {
        $lines[] = $key.'='.$value;
    }
}

if ($lines === []) {
    exit(0);
}

file_put_contents(__DIR__.'/../.env', implode(PHP_EOL, $lines).PHP_EOL);
