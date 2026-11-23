<?php

declare(strict_types=1);

use SimoneCerruti\BotDetector\Enums\Verdict;
use SimoneCerruti\BotDetector\Facades\BotDetector;
use SimoneCerruti\BotDetector\Signals\HeadlessChrome;

it('scores HeadlessChrome in the User-Agent', function (): void {
    $detection = BotDetector::detect(browserRequest([
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/131.0.0.0 Safari/537.36',
    ]));

    expect($detection->signals)->toBe([HeadlessChrome::class => 5])
        ->and($detection->verdict)->toBe(Verdict::Automated);
});

it('scores HeadlessChrome in the Sec-CH-UA brands', function (): void {
    expect(BotDetector::detect(browserRequest([
        'Sec-CH-UA' => '"HeadlessChrome";v="131", "Chromium";v="131", "Not_A Brand";v="24"',
    ]))->signals)->toBe([HeadlessChrome::class => 5]);
});

it('does not match a regular Chrome', function (): void {
    expect(BotDetector::detect(browserRequest())->signals)->not->toHaveKey(HeadlessChrome::class);
});

it('does not match without User-Agent and Sec-CH-UA', function (): void {
    expect(BotDetector::detect(browserRequest(['User-Agent' => null, 'Sec-CH-UA' => null]))->signals)
        ->not->toHaveKey(HeadlessChrome::class);
});

it('adds the weight configured for it', function (): void {
    config(['bot-detector.signals' => [HeadlessChrome::class => 7]]);

    expect(BotDetector::detect(browserRequest(['User-Agent' => 'HeadlessChrome/131.0.0.0']))->score)->toBe(7);
});
