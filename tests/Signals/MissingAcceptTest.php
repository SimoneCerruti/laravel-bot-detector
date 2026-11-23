<?php

declare(strict_types=1);

use SimoneCerruti\BotDetector\Facades\BotDetector;
use SimoneCerruti\BotDetector\Signals\MissingAccept;

it('scores a request without Accept', function (): void {
    expect(BotDetector::detect(browserRequest(['Accept' => null]))->signals)
        ->toBe([MissingAccept::class => 2]);
});

it('treats an empty Accept as missing', function (): void {
    expect(BotDetector::detect(browserRequest(['Accept' => '']))->signals)
        ->toHaveKey(MissingAccept::class);
});

it('does not match a request with Accept', function (): void {
    expect(BotDetector::detect(browserRequest())->signals)->not->toHaveKey(MissingAccept::class);
});

it('adds the weight configured for it', function (): void {
    config(['bot-detector.signals' => [MissingAccept::class => 7]]);

    expect(BotDetector::detect(browserRequest(['Accept' => null]))->score)->toBe(7);
});
