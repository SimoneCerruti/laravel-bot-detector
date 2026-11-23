<?php

declare(strict_types=1);

use SimoneCerruti\BotDetector\Facades\BotDetector;
use SimoneCerruti\BotDetector\Signals\MissingAcceptEncoding;

it('scores a request without Accept-Encoding', function (): void {
    expect(BotDetector::detect(browserRequest(['Accept-Encoding' => null]))->signals)
        ->toBe([MissingAcceptEncoding::class => 1]);
});

it('treats an empty Accept-Encoding as missing', function (): void {
    expect(BotDetector::detect(browserRequest(['Accept-Encoding' => '']))->signals)
        ->toHaveKey(MissingAcceptEncoding::class);
});

it('does not match a request with Accept-Encoding', function (): void {
    expect(BotDetector::detect(browserRequest())->signals)->not->toHaveKey(MissingAcceptEncoding::class);
});

it('adds the weight configured for it', function (): void {
    config(['bot-detector.signals' => [MissingAcceptEncoding::class => 7]]);

    expect(BotDetector::detect(browserRequest(['Accept-Encoding' => null]))->score)->toBe(7);
});
