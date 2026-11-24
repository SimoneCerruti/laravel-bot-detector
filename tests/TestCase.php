<?php

declare(strict_types=1);

namespace SimoneCerruti\BotDetector\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use SimoneCerruti\BotDetector\BotDetectorServiceProvider;
use SimoneCerruti\BotDetector\Contracts\DnsResolver;
use SimoneCerruti\BotDetector\Tests\Fixtures\FakeDnsResolver;
use Spatie\LaravelData\LaravelDataServiceProvider;

abstract class TestCase extends Orchestra {
    protected function getPackageProviders($app): array {
        return [
            LaravelDataServiceProvider::class,
            BotDetectorServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void {
        // No test touches the network: every IP has no DNS records unless a test binds its own answers.
        $app->instance(DnsResolver::class, new FakeDnsResolver);

        // Testbench ships without an app key, which every real app has and the Log reaction hashes IPs with.
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
    }
}
