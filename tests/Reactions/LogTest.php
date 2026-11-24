<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Monolog\Level;
use SimoneCerruti\BotDetector\Reactions\Log;
use SimoneCerruti\BotDetector\Signals\MissingAcceptEncoding;

beforeEach(function (): void {
    Route::post('contact', fn (): string => 'sent')->middleware('bot-detector')->name('contact');
    registerLogChannel();
    config(['logging.default' => 'memory', 'bot-detector.reactions.automated' => [Log::class]]);
});

it('logs the detection with the IP hashed by the app key', function (): void {
    $this->withHeader('User-Agent', 'curl/8.5.0')->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])->post('contact')->assertOk();

    $records = findLogRecords();

    expect($records)->toHaveCount(1)
        ->and($records[0]->level)->toBe(Level::Info)
        ->and($records[0]->message)->toBe('Bot detected')
        ->and($records[0]->context)->toBe([
            'ip' => hash_hmac('sha256', '203.0.113.7', config()->string('app.key')),
            'user_agent' => 'curl/8.5.0',
            'score' => 1,
            'signals' => [MissingAcceptEncoding::class => 1],
            'verdict' => 'automated',
            'reason' => 'self_declared',
            'method' => 'POST',
            'path' => 'contact',
            'route' => 'contact',
        ]);
});

it('logs the raw IP when enabled', function (): void {
    config(['bot-detector.log.raw_ip' => true]);

    $this->withHeader('User-Agent', 'curl/8.5.0')->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])->post('contact');

    expect(findLogRecords()[0]->context['ip'])->toBe('203.0.113.7');
});

it('logs at the configured level', function (): void {
    config(['bot-detector.log.level' => 'warning']);

    $this->withHeader('User-Agent', 'curl/8.5.0')->post('contact');

    expect(findLogRecords()[0]->level)->toBe(Level::Warning);
});

it('logs to the configured channel instead of the default one', function (): void {
    registerLogChannel('bots');
    config(['bot-detector.log.channel' => 'bots']);

    $this->withHeader('User-Agent', 'curl/8.5.0')->post('contact');

    expect(findLogRecords('bots'))->toHaveCount(1)
        ->and(findLogRecords())->toBeEmpty();
});
