<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use SimoneCerruti\BotDetector\Tests\Fixtures\AllowingAction;
use SimoneCerruti\BotDetector\Tests\Fixtures\DenyingAction;

const CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

beforeEach(function (): void {
    Route::get('protected', fn (): string => 'content')->middleware('bot-detector');
    Route::get('unprotected', fn (): string => 'content');
});

it('blocks an automated request with an empty 403', function (): void {
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
    config(['bot-detector.deny_status' => 410]);

    $this->withHeader('User-Agent', 'curl/8.5.0')->get('protected')->assertGone();
});

it('lets the request through when every custom action allows it', function (): void {
    config(['bot-detector.actions.automated' => [AllowingAction::class]]);

    $this->withHeader('User-Agent', 'curl/8.5.0')->get('protected')->assertOk();
});

it('denies the request when a custom action denies it', function (): void {
    config(['bot-detector.actions.suspicious' => [AllowingAction::class, DenyingAction::class]]);

    $this->withHeaders(['User-Agent' => CHROME, 'Accept-Language' => ''])->get('protected')->assertForbidden();
});

it('runs no actions removed from config', function (): void {
    config(['bot-detector.actions.automated' => []]);

    $this->withHeader('User-Agent', 'curl/8.5.0')->get('protected')->assertOk();
});
