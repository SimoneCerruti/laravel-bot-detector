<?php

declare(strict_types=1);

use SimoneCerruti\BotDetector\Facades\BotDetector;
use SimoneCerruti\BotDetector\Signals\MissingSecFetchSite;

it('scores a browser without Sec-Fetch-Site over HTTPS', function (): void {
    expect(BotDetector::detect(browserRequest(['Sec-Fetch-Site' => null]))->signals)
        ->toBe([MissingSecFetchSite::class => 3]);
});

it('treats an empty Sec-Fetch-Site as missing', function (): void {
    expect(BotDetector::detect(browserRequest(['Sec-Fetch-Site' => '']))->signals)
        ->toHaveKey(MissingSecFetchSite::class);
});

it('does not match a request with Sec-Fetch-Site', function (): void {
    expect(BotDetector::detect(browserRequest())->signals)->not->toHaveKey(MissingSecFetchSite::class);
});

it('never matches over plain HTTP', function (): void {
    expect(BotDetector::detect(browserRequest(['Sec-Fetch-Site' => null], secure: false))->signals)
        ->not->toHaveKey(MissingSecFetchSite::class);
});

it('matches only browsers that always send Sec-Fetch-Site', function (?string $user_agent, bool $matches): void {
    $signals = BotDetector::detect(browserRequest(['User-Agent' => $user_agent, 'Sec-Fetch-Site' => null]))->signals;

    expect(array_key_exists(MissingSecFetchSite::class, $signals))->toBe($matches);
})->with([
    'Chrome 76' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/76.0.3809.100 Safari/537.36', true],
    'Chrome 75' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/75.0.3770.142 Safari/537.36', false],
    'Firefox 90' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:90.0) Gecko/20100101 Firefox/90.0', true],
    'Firefox 89' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0', false],
    'Safari 17' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15', true],
    'Safari 16.4' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.4 Safari/605.1.15', true],
    'Safari 16.3' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.3 Safari/605.1.15', false],
    'Safari 16' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Safari/605.1.15', false],
    'a non-browser client' => ['curl/8.5.0', false],
    'no User-Agent' => [null, false],
]);

it('adds the weight configured for it', function (): void {
    config(['bot-detector.signals' => [MissingSecFetchSite::class => 7]]);

    expect(BotDetector::detect(browserRequest(['Sec-Fetch-Site' => null]))->score)->toBe(7);
});
