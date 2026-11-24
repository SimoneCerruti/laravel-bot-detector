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
}
