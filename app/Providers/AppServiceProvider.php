<?php

namespace App\Providers;

use App\Models\Course;
use App\Models\Roadmap;
use App\Models\Video;
use Illuminate\Support\Facades\Route;
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
}
