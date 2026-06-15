<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class HttpsUrlGenerationTest extends TestCase
{
    public function test_asset_urls_use_https_when_force_https_is_enabled(): void
    {
        URL::forceRootUrl('https://example.test');
        URL::forceScheme('https');

        $this->assertSame('https://example.test/favicon.svg', asset('favicon.svg'));
    }

    public function test_asset_urls_stay_http_when_force_https_is_disabled(): void
    {
        URL::forceRootUrl('http://localhost:8000');
        URL::forceScheme('http');

        $this->assertSame('http://localhost:8000/favicon.svg', asset('favicon.svg'));
    }
}
