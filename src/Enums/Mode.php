<?php

declare(strict_types=1);

namespace SimoneCerruti\BotDetector\Enums;

/**
 * The parameter of the `bot-detector` middleware, as in `bot-detector:log-only`.
 *
 * @internal
 */
enum Mode: string {
    case LogOnly = 'log-only';

    /**
     * Whether a denied request is let through on a route using this mode.
     */
    public function shouldLetThrough(): bool {
        return match ($this) {
            self::LogOnly => true,
        };
    }
}
