<?php

declare(strict_types=1);

namespace SimoneCerruti\BotDetector;

use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use SimoneCerruti\BotDetector\Data\Detection;

final class BotDetectorServiceProvider extends ServiceProvider {
    /**
     * Register any application services.
     */
    public function register(): void {
        $this->mergeConfigFrom(__DIR__.'/../config/bot-detector.php', 'bot-detector');

        $this->app->singleton(BotDetector::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void {
        Request::macro('botDetection', function (): Detection {
            /** @var Request $this */
            return app(BotDetector::class)->detect($this);
        });

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/bot-detector.php' => config_path('bot-detector.php'),
        ], ['bot-detector', 'bot-detector-config']);
    }
}
