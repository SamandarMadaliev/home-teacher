<?php

namespace App\Providers;

use App\Models\Course;
use App\Models\Roadmap;
use App\Models\Video;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureHttpsUrls();

        Route::bind('course', fn (string $value) => Course::query()
            ->forCurrentUser()
            ->findOrFail($value));

        Route::bind('roadmap', fn (string $value) => Roadmap::query()
            ->forCurrentUser()
            ->findOrFail($value));

        Route::bind('video', fn (string $value) => Video::query()
            ->whereHas('course', fn ($query) => $query->forCurrentUser())
            ->findOrFail($value));
    }

    /**
     * Ensure asset(), @vite, and route() URLs use https:// in production behind
     * TLS-terminating proxies (Traefik, nginx, etc.), even when config is cached
     * with a stale http APP_URL from image build time.
     */
    private function configureHttpsUrls(): void
    {
        if (config('app.force_https')) {
            URL::forceScheme('https');

            return;
        }

        if ($this->app->runningInConsole()) {
            return;
        }

        $request = request();

        if ($request->header('X-Forwarded-Proto') === 'https' || $request->isSecure()) {
            URL::forceScheme('https');
        }
    }
}
