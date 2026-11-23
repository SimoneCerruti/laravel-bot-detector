<?php

declare(strict_types=1);

use SimoneCerruti\BotDetector\Enums\Verdict;
use SimoneCerruti\BotDetector\Facades\BotDetector;
use SimoneCerruti\BotDetector\Signals\MissingAcceptLanguage;
use SimoneCerruti\BotDetector\Tests\Fixtures\AlwaysMatchingSignal;

it('computes the detection once per request', function (): void {
    $request = browserRequest(['Accept-Language' => null]);

    expect(BotDetector::detect($request))->toBe(BotDetector::detect($request));
});

it('returns the same detection from the request macro and the facade', function (): void {
    $request = browserRequest();

    expect($request->botDetection())->toBe(BotDetector::detect($request));
});

it('computes a new detection for another request', function (): void {
    expect(BotDetector::detect(browserRequest()))->not->toBe(BotDetector::detect(browserRequest()));
});

it('becomes suspicious exactly at the suspicious threshold', function (int $threshold, Verdict $verdict): void {
    config(['bot-detector.suspicious_at' => $threshold]);

    expect(BotDetector::detect(browserRequest(['Accept-Language' => null]))->verdict)->toBe($verdict);
})->with([
    'below the score' => [2, Verdict::Suspicious],
    'equal to the score' => [3, Verdict::Suspicious],
    'above the score' => [4, Verdict::Human],
]);

it('becomes automated exactly at the automated threshold', function (int $threshold, Verdict $verdict): void {
    config(['bot-detector.automated_at' => $threshold]);

    expect(BotDetector::detect(browserRequest(['Accept-Language' => null]))->verdict)->toBe($verdict);
})->with([
    'below the score' => [2, Verdict::Automated],
    'equal to the score' => [3, Verdict::Automated],
    'above the score' => [4, Verdict::Suspicious],
]);

it('keeps a request missing only Accept-Encoding human', function (): void {
    expect(BotDetector::detect(browserRequest(['Accept-Encoding' => null]))->verdict)->toBe(Verdict::Human);
});

it('marks a request missing Accept-Language and Accept as automated', function (): void {
    $detection = BotDetector::detect(browserRequest(['Accept-Language' => null, 'Accept' => null]));

    expect($detection->verdict)->toBe(Verdict::Automated)
        ->and($detection->score)->toBe(5);
});

it('marks a Chrome without Sec-Fetch-Site and Sec-CH-UA as automated', function (): void {
    $detection = BotDetector::detect(browserRequest(['Sec-Fetch-Site' => null, 'Sec-CH-UA' => null]));

    expect($detection->verdict)->toBe(Verdict::Automated)
        ->and($detection->score)->toBe(6);
});

it('evaluates a custom signal registered in config', function (): void {
    config(['bot-detector.signals' => [MissingAcceptLanguage::class => 3, AlwaysMatchingSignal::class => 1]]);

    $detection = BotDetector::detect(browserRequest());

    expect($detection->signals)->toBe([AlwaysMatchingSignal::class => 1])
        ->and($detection->score)->toBe(1);
});

it('does not evaluate a signal removed from config', function (): void {
    config(['bot-detector.signals' => []]);

    expect(BotDetector::detect(browserRequest(['Accept-Language' => null]))->verdict)->toBe(Verdict::Human);
});

it('exposes what arrived in the fingerprint', function (): void {
    $fingerprint = BotDetector::detect(browserRequest([
        'User-Agent' => 'curl/8.5.0',
        'Accept' => 'text/html',
        'Accept-Language' => 'en-US',
        'Accept-Encoding' => 'gzip',
        'Sec-Fetch-Site' => 'same-origin',
        'Sec-CH-UA' => '"Chromium";v="131"',
    ]))->fingerprint;

    expect($fingerprint->user_agent)->toBe('curl/8.5.0')
        ->and($fingerprint->secure)->toBeTrue()
        ->and($fingerprint->accept)->toBe('text/html')
        ->and($fingerprint->accept_language)->toBe('en-US')
        ->and($fingerprint->accept_encoding)->toBe('gzip')
        ->and($fingerprint->sec_fetch_site)->toBe('same-origin')
        ->and($fingerprint->sec_ch_ua)->toBe('"Chromium";v="131"');
});

it('exposes a plain-HTTP request as not secure', function (): void {
    expect(BotDetector::detect(browserRequest(secure: false))->fingerprint->secure)->toBeFalse();
});

it('classifies self-declared bots and HTTP libraries as automated', function (string $user_agent, string $claimed_as, ?string $category): void {
    $detection = BotDetector::detect(browserRequest(['User-Agent' => $user_agent]));

    expect($detection->verdict)->toBe(Verdict::Automated)
        ->and($detection->claimed_as)->toBe($claimed_as)
        ->and($detection->category)->toBe($category);
})->with([
    'Googlebot' => ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', 'Googlebot', 'Search bot'],
    'AhrefsBot' => ['Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 'aHrefs Bot', 'Crawler'],
    'curl' => ['curl/8.5.0', 'curl', null],
    'python-requests' => ['python-requests/2.31.0', 'Python Requests', null],
    'Go-http-client' => ['Go-http-client/1.1', 'Go-http-client', null],
    'WhatsApp link preview, via the fallback regex' => ['WhatsApp/2.23.20.0', 'WhatsApp', null],
]);

it('does not classify clients that do not declare themselves bots', function (?string $user_agent): void {
    $detection = BotDetector::detect(browserRequest(['User-Agent' => $user_agent]));

    expect($detection->verdict)->toBe(Verdict::Human)
        ->and($detection->claimed_as)->toBeNull()
        ->and($detection->category)->toBeNull();
})->with([
    'Chrome' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36'],
    'Opera Mini, not a bot for the fallback regex' => ['Opera/9.80 (Series 60; Opera Mini/7.1.32444/191.300; U; en) Presto/2.12.423 Version/12.16'],
    'an unknown client' => ['lorem-ipsum-foo'],
    'an empty User-Agent' => [''],
    'a missing User-Agent' => [null],
]);

it('does not apply the fallback regex to a client Matomo resolves a device for', function (): void {
    expect(BotDetector::detect(browserRequest([
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36 Preview',
    ]))->claimed_as)->toBeNull();
});

it('classifies a self-declared bot regardless of the score', function (): void {
    $detection = BotDetector::detect(browserRequest(['User-Agent' => 'curl/8.5.0']));

    expect($detection->score)->toBe(0)
        ->and($detection->verdict)->toBe(Verdict::Automated);
});

it('still computes the score and signals of a self-declared bot', function (): void {
    $detection = BotDetector::detect(browserRequest(['User-Agent' => 'curl/8.5.0', 'Accept-Language' => null]));

    expect($detection->signals)->toBe([MissingAcceptLanguage::class => 3])
        ->and($detection->score)->toBe(3)
        ->and($detection->verdict)->toBe(Verdict::Automated);
});
