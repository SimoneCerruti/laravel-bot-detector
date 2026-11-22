<?php

declare(strict_types=1);

namespace SimoneCerruti\BotDetector\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use SimoneCerruti\BotDetector\BotDetectorServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            BotDetectorServiceProvider::class,
        ];
    }
}
