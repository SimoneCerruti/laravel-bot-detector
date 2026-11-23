<?php

declare(strict_types=1);

namespace SimoneCerruti\BotDetector\Signals;

use SimoneCerruti\BotDetector\Contracts\Signal;
use SimoneCerruti\BotDetector\Data\RequestFingerprint;

/** Headless Chrome announces itself as `HeadlessChrome` in the User-Agent and in the Sec-CH-UA brands. */
final class HeadlessChrome implements Signal {
    public function matches(RequestFingerprint $fingerprint): bool {
        return str_contains((string) $fingerprint->user_agent, 'HeadlessChrome')
            || str_contains((string) $fingerprint->sec_ch_ua, 'HeadlessChrome');
    }
}
