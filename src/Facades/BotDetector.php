<?php

declare(strict_types=1);

namespace SimoneCerruti\BotDetector\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \SimoneCerruti\BotDetector\Data\Detection detect(\Illuminate\Http\Request $request)
 * @method static void usePolicy(\Closure $policy)
 *
 * @see \SimoneCerruti\BotDetector\BotDetector
 */
final class BotDetector extends Facade {
    protected static function getFacadeAccessor(): string {
        return \SimoneCerruti\BotDetector\BotDetector::class;
    }
}
