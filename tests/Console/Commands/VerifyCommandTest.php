<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use SimoneCerruti\BotDetector\Events\BotDetected;
use SimoneCerruti\BotDetector\Reactions\Block;
use SimoneCerruti\BotDetector\Reactions\Log;
use SimoneCerruti\BotDetector\Signals\MissingAccept;
use SimoneCerruti\BotDetector\Signals\MissingAcceptLanguage;
use SimoneCerruti\BotDetector\Signals\MissingSecFetchSite;

it('prints the detection of a browser User-Agent sent without headers', function (): void {
    $this->artisan('bot-detector:verify', ['ip' => '203.0.113.7', 'ua' => CHROME])
        ->expectsOutputToContain('automated')
        ->expectsOutputToContain('12')
        ->expectsOutputToContain(MissingSecFetchSite::class)
        ->expectsOutputToContain(MissingAcceptLanguage::class)
        ->expectsOutputToContain(MissingAccept::class)
        ->assertSuccessful();
});

it('applies every repeated --header to the replayed request', function (): void {
    $this->artisan('bot-detector:verify', [
        'ip' => '203.0.113.7',
        'ua' => CHROME,
        '--header' => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: it-IT,it;q=0.9,en;q=0.8',
            'Accept-Encoding: gzip, deflate, br, zstd',
            'Sec-Fetch-Site: none',
            'Sec-CH-UA: "Google Chrome";v="131", "Chromium";v="131", "Not_A Brand";v="24"',
        ],
    ])
        ->expectsOutputToContain('human')
        ->expectsOutputToContain('none')
        ->doesntExpectOutputToContain(MissingAccept::class)
        ->assertSuccessful();
});

it('verifies a good-bot claim by DNS', function (): void {
    bindFakeDns(['66.249.66.1' => ['crawl-66-249-66-1.googlebot.com']], ['crawl-66-249-66-1.googlebot.com' => ['66.249.66.1']]);

    $this->artisan('bot-detector:verify', ['ip' => '66.249.66.1', 'ua' => GOOGLEBOT])
        ->expectsOutputToContain('good_bot')
        ->expectsOutputToContain('Googlebot')
        ->expectsOutputToContain('yes')
        ->assertSuccessful();
});

it('rejects a header without a colon', function (): void {
    $this->artisan('bot-detector:verify', ['ip' => '203.0.113.7', 'ua' => CHROME, '--header' => ['Accept-Language']])
        ->expectsOutputToContain('Invalid header [Accept-Language]')
        ->assertFailed();
});

it('runs no reactions, writes no log and dispatches no event', function (): void {
    Event::fake();
    registerLogChannel();
    config([
        'bot-detector.log.channel' => 'memory',
        'bot-detector.reactions.automated' => [Log::class, Block::class],
    ]);

    $this->artisan('bot-detector:verify', ['ip' => '203.0.113.7', 'ua' => CHROME])
        ->expectsOutputToContain('automated')
        ->assertSuccessful();

    expect(findLogRecords())->toBeEmpty();
    Event::assertNotDispatched(BotDetected::class);
});
