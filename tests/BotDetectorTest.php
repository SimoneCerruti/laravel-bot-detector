<?php

declare(strict_types=1);

use DeviceDetector\DeviceDetector;
use Illuminate\Support\Facades\Cache;
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
    ], path: '/blog/post', ip: '203.0.113.7'))->fingerprint;

    expect($fingerprint->user_agent)->toBe('curl/8.5.0')
        ->and($fingerprint->ip)->toBe('203.0.113.7')
        ->and($fingerprint->secure)->toBeTrue()
        ->and($fingerprint->accept)->toBe('text/html')
        ->and($fingerprint->accept_language)->toBe('en-US')
        ->and($fingerprint->accept_encoding)->toBe('gzip')
        ->and($fingerprint->sec_fetch_site)->toBe('same-origin')
        ->and($fingerprint->sec_ch_ua)->toBe('"Chromium";v="131"')
        ->and($fingerprint->path)->toBe('blog/post')
        ->and($fingerprint->method)->toBe('GET')
        ->and($fingerprint->route)->toBeNull();
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

it('does not classify probe paths by default', function (): void {
    expect(BotDetector::detect(browserRequest(path: '/.env'))->verdict)->toBe(Verdict::Human);
});

it('classifies a request to a probe path as automated when enabled', function (string $path): void {
    config(['bot-detector.probe_paths.enabled' => true]);

    expect(BotDetector::detect(browserRequest(path: $path))->verdict)->toBe(Verdict::Automated);
})->with([
    '/.env',
    '/.git/config',
    '/wp-login.php',
    '/xmlrpc.php',
    '/phpmyadmin',
    '/vendor/phpunit/phpunit/src/Util/PHP/eval-stdin.php',
    '/.aws/credentials',
    '/server-status',
    '/actuator',
]);

it('keeps a request to any other path human when probe paths are enabled', function (string $path): void {
    config(['bot-detector.probe_paths.enabled' => true]);

    expect(BotDetector::detect(browserRequest(path: $path))->verdict)->toBe(Verdict::Human);
})->with(['/', '/blog/wp-login', '/vendor/phpunit', '/.env.example']);

it('matches probe paths with wildcards', function (): void {
    config(['bot-detector.probe_paths' => ['enabled' => true, 'paths' => ['wp-*']]]);

    expect(BotDetector::detect(browserRequest(path: '/wp-admin/install.php'))->verdict)->toBe(Verdict::Automated);
});

it('does not classify a path removed from the probe paths', function (): void {
    config(['bot-detector.probe_paths' => ['enabled' => true, 'paths' => ['.env']]]);

    expect(BotDetector::detect(browserRequest(path: '/wp-login.php'))->verdict)->toBe(Verdict::Human);
});

it('verifies a default good bot by reverse and forward DNS', function (string $user_agent, string $hostname): void {
    bindFakeDns(['192.0.2.1' => [$hostname]], [$hostname => ['192.0.2.1']]);

    $detection = BotDetector::detect(browserRequest(['User-Agent' => $user_agent], ip: '192.0.2.1'));

    expect($detection->verdict)->toBe(Verdict::GoodBot)
        ->and($detection->verified)->toBeTrue();
})->with([
    'Googlebot' => [GOOGLEBOT, 'crawl-66-249-66-1.googlebot.com'],
    'Google special crawler' => [GOOGLEBOT, 'rate-limited-proxy-66-249-90-77.google.com'],
    'Bingbot' => ['Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)', 'msnbot-157-55-39-1.search.msn.com'],
    'Applebot' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.1.1 Safari/605.1.15 (Applebot/0.1; +http://www.apple.com/go/applebot)', '17-58-101-179.applebot.apple.com'],
    'DuckDuckBot' => ['DuckDuckBot/1.1; (+http://duckduckgo.com/duckduckbot.html)', 'crawler.duckduckgo.com'],
    'YandexBot on yandex.ru' => ['Mozilla/5.0 (compatible; YandexBot/3.0; +http://yandex.com/bots)', 'spider-5-255-253-1.yandex.ru'],
    'YandexBot on yandex.net' => ['Mozilla/5.0 (compatible; YandexBot/3.0; +http://yandex.com/bots)', 'spider-5-255-253-1.yandex.net'],
    'YandexBot on yandex.com' => ['Mozilla/5.0 (compatible; YandexBot/3.0; +http://yandex.com/bots)', 'spider-5-255-253-1.yandex.com'],
]);

it('verifies a good bot on IPv6 whatever the address notation', function (): void {
    bindFakeDns(
        ['2001:4860:4801:10::1' => ['crawl-2001-4860-4801-10--1.googlebot.com']],
        ['crawl-2001-4860-4801-10--1.googlebot.com' => ['2001:4860:4801:0010:0000:0000:0000:0001']],
    );

    expect(BotDetector::detect(browserRequest(['User-Agent' => GOOGLEBOT], ip: '2001:4860:4801:10::1'))->verdict)->toBe(Verdict::GoodBot);
});

it('matches the reverse DNS hostname regardless of case', function (): void {
    bindFakeDns(['192.0.2.1' => ['CRAWL-66-249-66-1.GOOGLEBOT.COM']], ['crawl-66-249-66-1.googlebot.com' => ['192.0.2.1']]);

    expect(BotDetector::detect(browserRequest(['User-Agent' => GOOGLEBOT], ip: '192.0.2.1'))->verdict)->toBe(Verdict::GoodBot);
});

it('classifies a spoofed good bot as automated', function (array $hostnames, array $addresses): void {
    bindFakeDns($hostnames, $addresses);

    $detection = BotDetector::detect(browserRequest(['User-Agent' => GOOGLEBOT], ip: '192.0.2.1'));

    expect($detection->verdict)->toBe(Verdict::Automated)
        ->and($detection->verified)->toBeFalse()
        ->and($detection->isGoodBot())->toBeFalse();
})->with([
    'no reverse DNS' => [[], []],
    'reverse DNS outside the suffixes' => [['192.0.2.1' => ['host.example.com']], ['host.example.com' => ['192.0.2.1']]],
    'a lookalike domain' => [['192.0.2.1' => ['evilgooglebot.com']], ['evilgooglebot.com' => ['192.0.2.1']]],
    'another bot\'s suffix' => [['192.0.2.1' => ['msnbot-1.search.msn.com']], ['msnbot-1.search.msn.com' => ['192.0.2.1']]],
    'forward DNS to another IP' => [['192.0.2.1' => ['crawl-1.googlebot.com']], ['crawl-1.googlebot.com' => ['192.0.2.2']]],
    'no forward DNS' => [['192.0.2.1' => ['crawl-1.googlebot.com']], []],
]);

it('classifies a good bot as suspicious when DNS fails', function (array $hostnames, array $addresses): void {
    bindFakeDns($hostnames, $addresses);

    $detection = BotDetector::detect(browserRequest(['User-Agent' => GOOGLEBOT], ip: '192.0.2.1'));

    expect($detection->verdict)->toBe(Verdict::Suspicious)
        ->and($detection->verified)->toBeNull();
})->with([
    'reverse DNS error' => [['192.0.2.1' => null], []],
    'forward DNS error' => [['192.0.2.1' => ['crawl-1.googlebot.com']], ['crawl-1.googlebot.com' => null]],
]);

it('does not resolve a reverse DNS hostname outside every good-bot suffix', function (): void {
    // A spoofer controls the DNS of its own domain, so failing that lookup must not soften the verdict.
    bindFakeDns(['192.0.2.1' => ['host.example.com']], ['host.example.com' => null]);

    expect(BotDetector::detect(browserRequest(['User-Agent' => GOOGLEBOT], ip: '192.0.2.1'))->verdict)->toBe(Verdict::Automated);
});

it('does not look up DNS for a client that claims no good bot', function (string $user_agent): void {
    $resolver = bindFakeDns();

    expect(BotDetector::detect(browserRequest(['User-Agent' => $user_agent]))->verified)->toBeNull()
        ->and($resolver->lookups)->toBe(0);
})->with(['Chrome' => [browserRequest()->userAgent()], 'curl' => ['curl/8.5.0']]);

it('caches the verification per IP', function (): void {
    $resolver = bindFakeDns(['192.0.2.1' => ['crawl-1.googlebot.com']], ['crawl-1.googlebot.com' => ['192.0.2.1']]);

    BotDetector::detect(browserRequest(['User-Agent' => GOOGLEBOT], ip: '192.0.2.1'));
    $detection = BotDetector::detect(browserRequest(['User-Agent' => GOOGLEBOT], ip: '192.0.2.1'));

    expect($detection->verdict)->toBe(Verdict::GoodBot)
        ->and($resolver->lookups)->toBe(2);
});

it('does not let an IP cached for one good bot pass as another', function (): void {
    bindFakeDns(['192.0.2.1' => ['crawl-1.googlebot.com']], ['crawl-1.googlebot.com' => ['192.0.2.1']]);

    BotDetector::detect(browserRequest(['User-Agent' => GOOGLEBOT], ip: '192.0.2.1'));

    expect(BotDetector::detect(browserRequest(['User-Agent' => 'Mozilla/5.0 (compatible; bingbot/2.0)'], ip: '192.0.2.1'))->verdict)->toBe(Verdict::Automated);
});

it('caches a spoofed verification too', function (): void {
    $resolver = bindFakeDns();

    BotDetector::detect(browserRequest(['User-Agent' => GOOGLEBOT], ip: '192.0.2.1'));
    BotDetector::detect(browserRequest(['User-Agent' => GOOGLEBOT], ip: '192.0.2.1'));

    expect($resolver->lookups)->toBe(1);
});

it('does not cache a DNS error', function (): void {
    bindFakeDns(['192.0.2.1' => null]);
    BotDetector::detect(browserRequest(['User-Agent' => GOOGLEBOT], ip: '192.0.2.1'));

    bindFakeDns(['192.0.2.1' => ['crawl-1.googlebot.com']], ['crawl-1.googlebot.com' => ['192.0.2.1']]);

    expect(BotDetector::detect(browserRequest(['User-Agent' => GOOGLEBOT], ip: '192.0.2.1'))->verdict)->toBe(Verdict::GoodBot);
});

it('keeps the verification cached for 24 hours by default', function (int $seconds, int $lookups): void {
    $resolver = bindFakeDns();

    BotDetector::detect(browserRequest(['User-Agent' => GOOGLEBOT], ip: '192.0.2.1'));
    $this->travel($seconds)->seconds();
    BotDetector::detect(browserRequest(['User-Agent' => GOOGLEBOT], ip: '192.0.2.1'));

    expect($resolver->lookups)->toBe($lookups);
})->with([
    'just before expiry' => [60 * 60 * 24 - 1, 1],
    'after expiry' => [60 * 60 * 24 + 1, 2],
]);

it('keeps the verification cached for the configured TTL', function (): void {
    config(['bot-detector.good_bots.cache_ttl' => 60]);
    $resolver = bindFakeDns();

    BotDetector::detect(browserRequest(['User-Agent' => GOOGLEBOT], ip: '192.0.2.1'));
    $this->travel(61)->seconds();
    BotDetector::detect(browserRequest(['User-Agent' => GOOGLEBOT], ip: '192.0.2.1'));

    expect($resolver->lookups)->toBe(2);
});

it('verifies a good bot added to config', function (): void {
    config(['bot-detector.good_bots.bots' => ['/ExampleBot/' => ['.crawler.example.com']]]);
    bindFakeDns(['192.0.2.1' => ['a.crawler.example.com']], ['a.crawler.example.com' => ['192.0.2.1']]);

    expect(BotDetector::detect(browserRequest(['User-Agent' => 'ExampleBot/1.0'], ip: '192.0.2.1'))->verdict)->toBe(Verdict::GoodBot);
});

it('does not verify a good bot removed from config', function (): void {
    config(['bot-detector.good_bots.bots' => []]);
    $resolver = bindFakeDns(['192.0.2.1' => ['crawl-1.googlebot.com']], ['crawl-1.googlebot.com' => ['192.0.2.1']]);

    $detection = BotDetector::detect(browserRequest(['User-Agent' => GOOGLEBOT], ip: '192.0.2.1'));

    expect($detection->verdict)->toBe(Verdict::Automated)
        ->and($detection->verified)->toBeNull()
        ->and($resolver->lookups)->toBe(0);
});

it('verifies a good bot regardless of the score', function (): void {
    bindFakeDns(['192.0.2.1' => ['crawl-1.googlebot.com']], ['crawl-1.googlebot.com' => ['192.0.2.1']]);

    $detection = BotDetector::detect(browserRequest(['User-Agent' => GOOGLEBOT, 'Accept-Language' => null, 'Accept' => null], ip: '192.0.2.1'));

    expect($detection->score)->toBe(5)
        ->and($detection->verdict)->toBe(Verdict::GoodBot)
        ->and($detection->claimed_as)->toBe('Googlebot');
});

it('caches Matomo regexes in the file store by default', function (): void {
    Cache::store('file')->flush();

    BotDetector::detect(browserRequest(['User-Agent' => 'curl/8.5.0']));

    expect(Cache::store('file')->has(matomoRegexesKey()))->toBeTrue();
});

it('caches Matomo regexes in the configured store', function (): void {
    Cache::store('file')->flush();
    config(['bot-detector.device_detector.cache_store' => 'array']);

    BotDetector::detect(browserRequest(['User-Agent' => 'curl/8.5.0']));

    expect(Cache::store('array')->has(matomoRegexesKey()))->toBeTrue()
        ->and(Cache::store('file')->has(matomoRegexesKey()))->toBeFalse();
});

/** The key under which Matomo caches its bot regexes. */
function matomoRegexesKey(): string {
    return 'DeviceDetector-'.str_replace('.', '', DeviceDetector::VERSION).'regexes-bot';
}
