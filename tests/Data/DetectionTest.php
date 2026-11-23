<?php

declare(strict_types=1);

use SimoneCerruti\BotDetector\Facades\BotDetector;
use SimoneCerruti\BotDetector\Signals\MissingAcceptLanguage;

it('tells whether the request looks like a bot', function (): void {
    expect(BotDetector::detect(browserRequest())->isBot())->toBeFalse()
        ->and(BotDetector::detect(browserRequest(['Accept-Language' => null]))->isBot())->toBeTrue();
});

it('serialises to an array', function (): void {
    expect(BotDetector::detect(browserRequest(['Accept-Language' => null]))->toArray())->toBe([
        'verdict' => 'suspicious',
        'score' => 3,
        'signals' => [MissingAcceptLanguage::class => 3],
        'fingerprint' => ['acceptLanguage' => null],
    ]);
});
