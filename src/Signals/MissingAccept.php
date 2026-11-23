<?php

declare(strict_types=1);

namespace SimoneCerruti\BotDetector\Signals;

use SimoneCerruti\BotDetector\Contracts\Signal;
use SimoneCerruti\BotDetector\Data\RequestFingerprint;

/** Browsers always send Accept; many HTTP libraries and scripts do not. */
final class MissingAccept implements Signal {
    public function matches(RequestFingerprint $fingerprint): bool {
        return blank($fingerprint->accept);
    }
}
