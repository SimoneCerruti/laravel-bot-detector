<?php

declare(strict_types=1);

use SimoneCerruti\BotDetector\Facades\BotDetector;
use SimoneCerruti\BotDetector\Signals\MissingAcceptLanguage;

it('tells whether the request looks like a bot', function (): void {
    expect(BotDetector::detect(browserRequest())->isBot())->toBeFalse()
        ->and(BotDetector::detect(browserRequest(['Accept-Language' => null]))->isBot())->toBeTrue()
        ->and(BotDetector::detect(browserRequest(['Accept-Language' => null, 'Accept' => null]))->isBot())->toBeTrue();
});

it('serialises to an array', function (): void {
    expect(BotDetector::detect(browserRequest(['Accept-Language' => null]))->toArray())->toBe([
        'verdict' => 'suspicious',
        'score' => 3,
        'signals' => [MissingAcceptLanguage::class => 3],
        'fingerprint' => [
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
            'secure' => true,
            'accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'accept_language' => null,
            'accept_encoding' => 'gzip, deflate, br, zstd',
            'sec_fetch_site' => 'none',
            'sec_ch_ua' => '"Google Chrome";v="131", "Chromium";v="131", "Not_A Brand";v="24"',
        ],
    ]);
});
