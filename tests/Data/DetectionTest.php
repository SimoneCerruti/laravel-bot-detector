<?php

declare(strict_types=1);

use SimoneCerruti\BotDetector\Contracts\DnsResolver;
use SimoneCerruti\BotDetector\Facades\BotDetector;
use SimoneCerruti\BotDetector\Signals\MissingAcceptLanguage;
use SimoneCerruti\BotDetector\Tests\Fixtures\FakeDnsResolver;

it('tells whether the request looks like a bot', function (): void {
    expect(BotDetector::detect(browserRequest())->isBot())->toBeFalse()
        ->and(BotDetector::detect(browserRequest(['Accept-Language' => null]))->isBot())->toBeTrue()
        ->and(BotDetector::detect(browserRequest(['Accept-Language' => null, 'Accept' => null]))->isBot())->toBeTrue();
});

it('tells whether the request is a verified good bot', function (): void {
    app()->instance(DnsResolver::class, new FakeDnsResolver(['192.0.2.1' => ['crawl-1.googlebot.com']], ['crawl-1.googlebot.com' => ['192.0.2.1']]));
    $googlebot = ['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'];

    expect(BotDetector::detect(browserRequest($googlebot, ip: '192.0.2.1')))
        ->isGoodBot()->toBeTrue()
        ->isBot()->toBeTrue()
        ->toArray()->toMatchArray(['verdict' => 'good_bot', 'verified' => true])
        ->and(BotDetector::detect(browserRequest($googlebot, ip: '192.0.2.2'))->isGoodBot())->toBeFalse()
        ->and(BotDetector::detect(browserRequest())->isGoodBot())->toBeFalse();
});

it('serialises what a self-declared bot claims to be', function (): void {
    expect(BotDetector::detect(browserRequest([
        'User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
    ]))->toArray())->toMatchArray([
        'verdict' => 'automated',
        'claimed_as' => 'Googlebot',
        'category' => 'Search bot',
    ]);
});

it('serialises to an array', function (): void {
    expect(BotDetector::detect(browserRequest(['Accept-Language' => null]))->toArray())->toBe([
        'verdict' => 'suspicious',
        'score' => 3,
        'signals' => [MissingAcceptLanguage::class => 3],
        'claimed_as' => null,
        'category' => null,
        'verified' => null,
        'fingerprint' => [
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
            'ip' => '127.0.0.1',
            'secure' => true,
            'accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'accept_language' => null,
            'accept_encoding' => 'gzip, deflate, br, zstd',
            'sec_fetch_site' => 'none',
            'sec_ch_ua' => '"Google Chrome";v="131", "Chromium";v="131", "Not_A Brand";v="24"',
            'path' => '/',
        ],
    ]);
});
