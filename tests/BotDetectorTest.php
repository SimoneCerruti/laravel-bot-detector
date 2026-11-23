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
        'Accept' => 'text/html',
        'Accept-Language' => 'en-US',
        'Accept-Encoding' => 'gzip',
    ]))->fingerprint;

    expect($fingerprint->accept)->toBe('text/html')
        ->and($fingerprint->accept_language)->toBe('en-US')
        ->and($fingerprint->accept_encoding)->toBe('gzip');
});
