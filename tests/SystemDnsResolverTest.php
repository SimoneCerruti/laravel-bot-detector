<?php

declare(strict_types=1);

use NetDNS2\ENUM\Error;
use NetDNS2\Exception;
use NetDNS2\RR;
use SimoneCerruti\BotDetector\SystemDnsResolver;

/**
 * A resolver whose queries are answered from prepared records or exceptions, keyed by "name type".
 *
 * @param  array<string, list<string>|Exception>  $answers  zone-file lines, or the exception the query throws
 */
function makeResolver(array $answers): SystemDnsResolver {
    return new SystemDnsResolver(function (string $name, string $type) use ($answers): array {
        $answer = $answers["{$name} {$type}"];

        return $answer instanceof Exception ? throw $answer : array_map(RR::fromString(...), $answer);
    });
}

it('maps PTR answers to hostnames', function (): void {
    $resolver = makeResolver(['192.0.2.1 PTR' => ['1.2.0.192.in-addr.arpa. 300 IN PTR crawl-1.googlebot.com.']]);

    expect($resolver->lookupHostnames('192.0.2.1'))->toBe(['crawl-1.googlebot.com']);
});

it('merges A and AAAA answers into addresses', function (): void {
    $resolver = makeResolver([
        'crawl-1.googlebot.com A' => ['crawl-1.googlebot.com. 300 IN A 192.0.2.1'],
        'crawl-1.googlebot.com AAAA' => ['crawl-1.googlebot.com. 300 IN AAAA 2001:db8::1'],
    ]);

    expect($resolver->lookupAddresses('crawl-1.googlebot.com'))->toBe(['192.0.2.1', '2001:db8::1']);
});

it('drops records other than PTR, A and AAAA', function (): void {
    $resolver = makeResolver([
        'www.example.com A' => ['www.example.com. 300 IN CNAME example.com.', 'example.com. 300 IN A 192.0.2.1'],
        'www.example.com AAAA' => ['www.example.com. 300 IN CNAME example.com.'],
    ]);

    expect($resolver->lookupAddresses('www.example.com'))->toBe(['192.0.2.1']);
});

it('answers an empty list when the name does not exist', function (): void {
    $resolver = makeResolver([
        '192.0.2.1 PTR' => new Exception('NXDOMAIN', Error::DNS_NXDOMAIN),
        'missing.example A' => new Exception('NXDOMAIN', Error::DNS_NXDOMAIN),
        'missing.example AAAA' => new Exception('NXDOMAIN', Error::DNS_NXDOMAIN),
    ]);

    expect($resolver->lookupHostnames('192.0.2.1'))->toBe([])
        ->and($resolver->lookupAddresses('missing.example'))->toBe([]);
});

it('answers null when the lookup fails', function (): void {
    $resolver = makeResolver(['192.0.2.1 PTR' => new Exception('timeout', Error::INT_FAILED_SOCKET)]);

    expect($resolver->lookupHostnames('192.0.2.1'))->toBeNull();
});

it('answers null for addresses when either the A or the AAAA lookup fails', function (string $failing): void {
    $resolver = makeResolver([
        'crawl-1.googlebot.com A' => ['crawl-1.googlebot.com. 300 IN A 192.0.2.1'],
        'crawl-1.googlebot.com AAAA' => ['crawl-1.googlebot.com. 300 IN AAAA 2001:db8::1'],
        "crawl-1.googlebot.com {$failing}" => new Exception('SERVFAIL', Error::DNS_SERVFAIL),
    ]);

    expect($resolver->lookupAddresses('crawl-1.googlebot.com'))->toBeNull();
})->with(['A', 'AAAA']);

it('queries with the configured timeout', function (): void {
    config(['bot-detector.good_bots.dns_timeout' => 2.5]);
    $timeouts = [];

    new SystemDnsResolver(function (string $name, string $type, float $timeout) use (&$timeouts): array {
        $timeouts[] = $timeout;

        return [];
    })->lookupHostnames('192.0.2.1');

    expect($timeouts)->toBe([2.5]);
});

describe('real DNS', function (): void {
    it('looks up the PTR of a Googlebot IP and its addresses', function (): void {
        $resolver = app(SystemDnsResolver::class);

        expect($resolver->lookupHostnames('66.249.66.1'))->toBe(['crawl-66-249-66-1.googlebot.com'])
            ->and($resolver->lookupAddresses('crawl-66-249-66-1.googlebot.com'))->toBe(['66.249.66.1']);
    });

    it('answers an empty list for a .invalid name', function (): void {
        expect(app(SystemDnsResolver::class)->lookupAddresses('bot-detector.invalid'))->toBe([]);
    });
})->group('network');
