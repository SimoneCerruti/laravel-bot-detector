<?php

declare(strict_types=1);

use SimoneCerruti\BotDetector\Enums\Verdict;
use SimoneCerruti\BotDetector\Facades\BotDetector;
use SimoneCerruti\BotDetector\Signals\MissingAcceptLanguage;

it('marks a request without Accept-Language as suspicious', function (): void {
    $detection = BotDetector::detect(browserRequest(['Accept-Language' => null]));

    expect($detection->verdict)->toBe(Verdict::Suspicious)
        ->and($detection->score)->toBe(3)
        ->and($detection->signals)->toBe([MissingAcceptLanguage::class => 3]);
});

it('treats an empty Accept-Language as missing', function (): void {
    expect(BotDetector::detect(browserRequest(['Accept-Language' => '']))->signals)
        ->toHaveKey(MissingAcceptLanguage::class);
});

it('does not match a request with Accept-Language', function (): void {
    $detection = BotDetector::detect(browserRequest());

    expect($detection->verdict)->toBe(Verdict::Human)
        ->and($detection->score)->toBe(0)
        ->and($detection->signals)->toBeEmpty();
});

it('adds the weight configured for it', function (): void {
    config(['bot-detector.signals' => [MissingAcceptLanguage::class => 7]]);

    expect(BotDetector::detect(browserRequest(['Accept-Language' => null]))->score)->toBe(7);
});
