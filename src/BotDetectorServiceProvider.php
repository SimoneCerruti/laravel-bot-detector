<?php

declare(strict_types=1);

namespace SimoneCerruti\BotDetector;

use Illuminate\Support\ServiceProvider;

class BotDetectorServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/bot-detector.php', 'bot-detector');

        $this->app->singleton(BotDetector::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/bot-detector.php' => config_path('bot-detector.php'),
        ], ['bot-detector', 'bot-detector-config']);
    }
}
