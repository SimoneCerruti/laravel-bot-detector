<?php

declare(strict_types=1);

use Illuminate\Support\Arr;

/**
 * Load the package config file as the app would, with the given environment variables set.
 *
 * @param  array<string, string>  $env
 * @return array<string, mixed>
 */
function loadConfig(array $env = []): array {
    foreach ($env as $name => $value) {
        $_SERVER[$name] = $_ENV[$name] = $value;
    }

    try {
        return require __DIR__.'/../config/bot-detector.php';
    } finally {
        foreach (array_keys($env) as $name) {
            unset($_SERVER[$name], $_ENV[$name]);
        }
    }
}

it('reads a setting from its environment variable', function (string $variable, string $value, string $key, mixed $expected): void {
    expect(Arr::get(loadConfig([$variable => $value]), $key))->toBe($expected);
})->with([
    'except_ips' => ['BOT_DETECTOR_EXCEPT_IPS', '10.0.0.1', 'except_ips', ['10.0.0.1']],
    'dry_run.enabled' => ['BOT_DETECTOR_DRY_RUN', 'false', 'dry_run.enabled', false],
    'dry_run.dispatch_events' => ['BOT_DETECTOR_DRY_RUN_DISPATCH_EVENTS', 'false', 'dry_run.dispatch_events', false],
    'log.channel' => ['BOT_DETECTOR_LOG_CHANNEL', 'stack', 'log.channel', 'stack'],
    'log.level' => ['BOT_DETECTOR_LOG_LEVEL', 'warning', 'log.level', 'warning'],
    'log.raw_ip' => ['BOT_DETECTOR_LOG_RAW_IP', 'true', 'log.raw_ip', true],
    'throttle.max_requests' => ['BOT_DETECTOR_THROTTLE_MAX_REQUESTS', '100', 'throttle.max_requests', 100],
    'throttle.per_seconds' => ['BOT_DETECTOR_THROTTLE_PER_SECONDS', '300', 'throttle.per_seconds', 300],
    'good_bots.cache_ttl' => ['BOT_DETECTOR_GOOD_BOTS_CACHE_TTL', '3600', 'good_bots.cache_ttl', 3600],
    'good_bots.dns_timeout' => ['BOT_DETECTOR_DNS_TIMEOUT', '0.5', 'good_bots.dns_timeout', 0.5],
    'device_detector.cache_store' => ['BOT_DETECTOR_CACHE_STORE', 'redis', 'device_detector.cache_store', 'redis'],
    'probe_paths.enabled' => ['BOT_DETECTOR_PROBE_PATHS', 'true', 'probe_paths.enabled', true],
]);

it('parses a comma-separated IP list, trimming spaces and dropping empty items', function (): void {
    expect(loadConfig(['BOT_DETECTOR_EXCEPT_IPS' => ' 10.0.0.1 , ,192.168.0.0/16,'])['except_ips'])
        ->toBe(['10.0.0.1', '192.168.0.0/16']);
});

it('reads an empty IP list as no IPs', function (): void {
    expect(loadConfig(['BOT_DETECTOR_EXCEPT_IPS' => ''])['except_ips'])->toBe([]);
});

it('keeps the defaults when no variable is set', function (): void {
    expect(Arr::only(Arr::dot(loadConfig()), [
        'dry_run.enabled', 'dry_run.dispatch_events', 'log.channel', 'log.level', 'log.raw_ip',
        'throttle.max_requests', 'throttle.per_seconds', 'good_bots.cache_ttl', 'good_bots.dns_timeout',
        'device_detector.cache_store', 'probe_paths.enabled',
    ]))->toBe([
        'probe_paths.enabled' => false,
        'good_bots.cache_ttl' => 86400,
        'good_bots.dns_timeout' => 2.0,
        'device_detector.cache_store' => 'file',
        'log.channel' => null,
        'log.level' => 'info',
        'log.raw_ip' => false,
        'throttle.max_requests' => 30,
        'throttle.per_seconds' => 60,
        'dry_run.enabled' => true,
        'dry_run.dispatch_events' => true,
    ])->and(loadConfig()['except_ips'])->toBe([]);
});

it('lets the app read every numeric setting with its typed config getter', function (): void {
    expect(config()->integer('bot-detector.throttle.max_requests'))->toBe(30)
        ->and(config()->integer('bot-detector.throttle.per_seconds'))->toBe(60)
        ->and(config()->integer('bot-detector.good_bots.cache_ttl'))->toBe(86400)
        ->and(config()->float('bot-detector.good_bots.dns_timeout'))->toBe(2.0);
});
