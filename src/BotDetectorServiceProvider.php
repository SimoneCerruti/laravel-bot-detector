<?php

declare(strict_types=1);

namespace BotDetector\BotDetector;

use BotDetector\BotDetector\Console\Commands\BotDetectorCommand;
use Illuminate\Support\ServiceProvider;

class BotDetectorServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laravel-bot-detector.php', 'laravel-bot-detector');

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
            __DIR__.'/../config/laravel-bot-detector.php' => config_path('laravel-bot-detector.php'),
        ], ['laravel-bot-detector', 'laravel-bot-detector-config']);

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], ['laravel-bot-detector', 'laravel-bot-detector-migrations']);

        $this->commands([
            BotDetectorCommand::class,
        ]);
    }
}
