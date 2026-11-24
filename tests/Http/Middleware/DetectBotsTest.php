<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use SimoneCerruti\BotDetector\Reactions\Log;
use SimoneCerruti\BotDetector\Tests\Fixtures\AllowingReaction;
use SimoneCerruti\BotDetector\Tests\Fixtures\DenyingReaction;

const CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

beforeEach(function (): void {
    Route::get('protected', fn (): string => 'content')->middleware('bot-detector');
    Route::get('unprotected', fn (): string => 'content');
    registerLogChannel();
    config(['logging.default' => 'memory']);
});

it('lets a denied request through in dry run by default', function (): void {
    $this->withHeader('User-Agent', 'curl/8.5.0')->get('protected')
        ->assertOk()
        ->assertContent('content');
});

it('blocks an automated request with an empty 403 when dry run is off', function (): void {
    config(['bot-detector.dry_run' => false]);

    $this->withHeader('User-Agent', 'curl/8.5.0')->get('protected')
        ->assertForbidden()
        ->assertContent('');
});

it('lets a human request through', function (): void {
    $this->withHeader('User-Agent', CHROME)->get('protected')
        ->assertOk()
        ->assertContent('content');
});

it('lets a suspicious request through by default', function (): void {
    $this->withHeaders(['User-Agent' => CHROME, 'Accept-Language' => ''])->get('protected')->assertOk();
});

it('lets a verified good bot through by default', function (): void {
    bindFakeDns(['192.0.2.1' => ['crawl-1.googlebot.com']], ['crawl-1.googlebot.com' => ['192.0.2.1']]);

    $this->withHeader('User-Agent', GOOGLEBOT)->withServerVariables(['REMOTE_ADDR' => '192.0.2.1'])->get('protected')->assertOk();
});

it('is not applied to routes without the middleware', function (): void {
    $this->withHeader('User-Agent', 'curl/8.5.0')->get('unprotected')->assertOk();
});

it('denies with the configured status', function (): void {
    config(['bot-detector.dry_run' => false, 'bot-detector.deny_status' => 410]);

    $this->withHeader('User-Agent', 'curl/8.5.0')->get('protected')->assertGone();
});

it('lets the request through when every custom reaction allows it', function (): void {
    config(['bot-detector.reactions.automated' => [AllowingReaction::class]]);

    $this->withHeader('User-Agent', 'curl/8.5.0')->get('protected')->assertOk();
});

it('denies the request when a custom reaction denies it', function (): void {
    config(['bot-detector.dry_run' => false, 'bot-detector.reactions.suspicious' => [AllowingReaction::class, DenyingReaction::class]]);

    $this->withHeaders(['User-Agent' => CHROME, 'Accept-Language' => ''])->get('protected')->assertForbidden();
});

it('runs no reactions removed from config', function (): void {
    config(['bot-detector.reactions.automated' => []]);

    $this->withHeader('User-Agent', 'curl/8.5.0')->get('protected')->assertOk();
});

it('runs the reactions after a denying one, so Log after Block still logs', function (): void {
    config(['bot-detector.dry_run' => false, 'bot-detector.reactions.automated' => [DenyingReaction::class, Log::class]]);

    $this->withHeader('User-Agent', 'curl/8.5.0')->get('protected')->assertForbidden();

    expect(findLogRecords())->toHaveCount(1);
});

it('logs and blocks an automated request by default', function (): void {
    config(['bot-detector.dry_run' => false]);

    $this->withHeader('User-Agent', 'curl/8.5.0')->get('protected')->assertForbidden();

    expect(findLogRecords())->toHaveCount(1)
        ->and(findLogRecords()[0]->context['verdict'])->toBe('automated');
});

it('logs a suspicious request by default and lets it through', function (): void {
    config(['bot-detector.dry_run' => false]);

    $this->withHeaders(['User-Agent' => CHROME, 'Accept-Language' => ''])->get('protected')->assertOk();

    expect(findLogRecords())->toHaveCount(1)
        ->and(findLogRecords()[0]->context['verdict'])->toBe('suspicious');
});

it('logs neither a human request nor a verified good bot by default', function (): void {
    bindFakeDns(['192.0.2.1' => ['crawl-1.googlebot.com']], ['crawl-1.googlebot.com' => ['192.0.2.1']]);

    $this->withHeader('User-Agent', CHROME)->get('protected')->assertOk();
    $this->withHeader('User-Agent', GOOGLEBOT)->withServerVariables(['REMOTE_ADDR' => '192.0.2.1'])->get('protected')->assertOk();

    expect(findLogRecords())->toBeEmpty();
});
