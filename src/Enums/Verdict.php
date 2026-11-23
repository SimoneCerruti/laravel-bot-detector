<?php

declare(strict_types=1);

namespace SimoneCerruti\BotDetector\Enums;

enum Verdict: string {
    case Human = 'human';
    case GoodBot = 'good_bot';
    case Suspicious = 'suspicious';
    case Automated = 'automated';
}
