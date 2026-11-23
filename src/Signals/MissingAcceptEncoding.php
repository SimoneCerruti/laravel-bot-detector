<?php

declare(strict_types=1);

namespace SimoneCerruti\BotDetector\Signals;

use SimoneCerruti\BotDetector\Contracts\Signal;
use SimoneCerruti\BotDetector\Data\RequestFingerprint;

/** Browsers always advertise compression; some HTTP libraries do not, but a few proxies strip it, hence its low weight. */
final class MissingAcceptEncoding implements Signal {
    public function matches(RequestFingerprint $fingerprint): bool {
        return blank($fingerprint->accept_encoding);
    }
}
