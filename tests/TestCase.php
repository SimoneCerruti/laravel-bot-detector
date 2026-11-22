<?php

declare(strict_types=1);

namespace BotDetector\BotDetector\Tests;

use BotDetector\BotDetector\BotDetectorServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            BotDetectorServiceProvider::class,
        ];
    }
}
