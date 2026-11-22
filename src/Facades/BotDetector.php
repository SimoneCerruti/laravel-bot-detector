<?php

declare(strict_types=1);

namespace SimoneCerruti\BotDetector\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \SimoneCerruti\BotDetector\BotDetector
 */
class BotDetector extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \SimoneCerruti\BotDetector\BotDetector::class;
    }
}
