<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use SimoneCerruti\BotDetector\Reactions\Throttle;

beforeEach(function (): void {
    Route::get('protected', fn (): string => 'content')->middleware('bot-detector');
    config(['bot-detector.dry_run' => false, 'bot-detector.reactions.automated' => [Throttle::class]]);
});

it('lets requests through up to the limit', function (): void {
    config(['bot-detector.throttle.limit' => 2]);

    $this->withHeader('User-Agent', 'curl/8.5.0')->get('protected')->assertOk();
    $this->withHeader('User-Agent', 'curl/8.5.0')->get('protected')->assertOk();
});

it('denies with an empty 429 and Retry-After over the limit', function (): void {
    config(['bot-detector.throttle.limit' => 2, 'bot-detector.throttle.window' => 120]);

    $this->withHeader('User-Agent', 'curl/8.5.0')->get('protected');
    $this->withHeader('User-Agent', 'curl/8.5.0')->get('protected');

    $this->withHeader('User-Agent', 'curl/8.5.0')->get('protected')
        ->assertTooManyRequests()
        ->assertContent('')
        ->assertHeader('Retry-After', '120');
});

it('allows 30 requests per minute by default', function (): void {
    foreach (range(1, 30) as $i) {
        $this->withHeader('User-Agent', 'curl/8.5.0')->get('protected')->assertOk();
    }

    $this->withHeader('User-Agent', 'curl/8.5.0')->get('protected')
        ->assertTooManyRequests()
        ->assertHeader('Retry-After', '60');
});

it('counts each IP and User-Agent separately', function (): void {
    config(['bot-detector.throttle.limit' => 1]);

    $this->withHeader('User-Agent', 'curl/8.5.0')->withServerVariables(['REMOTE_ADDR' => '192.0.2.1'])->get('protected')->assertOk();

    $this->withHeader('User-Agent', 'Wget/1.21')->withServerVariables(['REMOTE_ADDR' => '192.0.2.1'])->get('protected')->assertOk();
    $this->withHeader('User-Agent', 'curl/8.5.0')->withServerVariables(['REMOTE_ADDR' => '192.0.2.2'])->get('protected')->assertOk();
    $this->withHeader('User-Agent', 'curl/8.5.0')->withServerVariables(['REMOTE_ADDR' => '192.0.2.1'])->get('protected')->assertTooManyRequests();
});

it('lets a throttled request through in dry run', function (): void {
    config(['bot-detector.dry_run' => true, 'bot-detector.throttle.limit' => 1]);

    $this->withHeader('User-Agent', 'curl/8.5.0')->get('protected')->assertOk();
    $this->withHeader('User-Agent', 'curl/8.5.0')->get('protected')->assertOk();
});
