<?php

namespace Tests\Feature;

use App\Support\HttpsUrlConfigurator;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class HttpsUrlGenerationTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('APP_URL');
        putenv('FORCE_HTTPS');
        putenv('APP_ENV');

        parent::tearDown();
    }

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

    public function test_runtime_app_url_from_getenv_forces_https_despite_cached_http_config(): void
    {
        config(['app.url' => 'http://localhost', 'app.force_https' => false]);

        putenv('APP_URL=https://ht.example.nip.io');
        putenv('APP_ENV=production');

        HttpsUrlConfigurator::apply();

        $this->assertSame('https://ht.example.nip.io/favicon.svg', asset('favicon.svg'));
    }
}
