<?php

declare(strict_types=1);

namespace SimoneCerruti\BotDetector\Enums;

enum Verdict: string {
    case Human = 'human';
    case Suspicious = 'suspicious';
}
