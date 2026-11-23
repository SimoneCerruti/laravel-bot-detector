<?php

declare(strict_types=1);

use SimoneCerruti\BotDetector\Facades\BotDetector;
use SimoneCerruti\BotDetector\Signals\MissingSecChUa;

it('scores a Chrome without Sec-CH-UA over HTTPS', function (): void {
    expect(BotDetector::detect(browserRequest(['Sec-CH-UA' => null]))->signals)
        ->toBe([MissingSecChUa::class => 3]);
});

it('treats an empty Sec-CH-UA as missing', function (): void {
    expect(BotDetector::detect(browserRequest(['Sec-CH-UA' => '']))->signals)
        ->toHaveKey(MissingSecChUa::class);
});

it('does not match a request with Sec-CH-UA', function (): void {
    expect(BotDetector::detect(browserRequest())->signals)->not->toHaveKey(MissingSecChUa::class);
});

it('never matches over plain HTTP', function (): void {
    expect(BotDetector::detect(browserRequest(['Sec-CH-UA' => null], secure: false))->signals)
        ->not->toHaveKey(MissingSecChUa::class);
});

it('matches only browsers that always send Sec-CH-UA', function (?string $user_agent, bool $matches): void {
    $signals = BotDetector::detect(browserRequest(['User-Agent' => $user_agent, 'Sec-CH-UA' => null]))->signals;

    expect(array_key_exists(MissingSecChUa::class, $signals))->toBe($matches);
})->with([
    'Chrome 89' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.82 Safari/537.36', true],
    'Chrome 88' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.190 Safari/537.36', false],
    'Chrome on Android' => ['Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Mobile Safari/537.36', true],
    'Android WebView' => ['Mozilla/5.0 (Linux; Android 14; Pixel 8; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/131.0.0.0 Mobile Safari/537.36', false],
    'Firefox' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:133.0) Gecko/20100101 Firefox/133.0', false],
    'Safari' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Safari/605.1.15', false],
    'a non-browser client' => ['curl/8.5.0', false],
    'no User-Agent' => [null, false],
]);

it('adds the weight configured for it', function (): void {
    config(['bot-detector.signals' => [MissingSecChUa::class => 7]]);

    expect(BotDetector::detect(browserRequest(['Sec-CH-UA' => null]))->score)->toBe(7);
});
