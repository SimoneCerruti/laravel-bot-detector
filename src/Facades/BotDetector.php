<?php

declare(strict_types=1);

namespace BotDetector\BotDetector\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \BotDetector\BotDetector\BotDetector
 */
class BotDetector extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \BotDetector\BotDetector\BotDetector::class;
    }
}
