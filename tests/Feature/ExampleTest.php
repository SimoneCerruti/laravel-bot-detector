<?php

declare(strict_types=1);

use BotDetector\BotDetector\BotDetector;

it('resolves the singleton', function () {
    expect(app(BotDetector::class))->toBeInstanceOf(BotDetector::class);
});

it('returns the same instance from the container', function () {
    expect(app(BotDetector::class))->toBe(app(BotDetector::class));
});

it('merges the package config', function () {
    expect(config('laravel-bot-detector.placeholder'))->toBe('default');
});

it('registers the artisan command', function () {
    $this->artisan('laravel-bot-detector:placeholder')
        ->expectsOutputToContain('BotDetector placeholder command executed.')
        ->assertSuccessful();
});
