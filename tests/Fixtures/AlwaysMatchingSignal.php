<?php

declare(strict_types=1);

namespace SimoneCerruti\BotDetector\Tests\Fixtures;

use SimoneCerruti\BotDetector\Contracts\Signal;
use SimoneCerruti\BotDetector\Data\RequestFingerprint;

/** A host-defined signal, used to prove custom signals registered in config are evaluated. */
final class AlwaysMatchingSignal implements Signal {
    public function matches(RequestFingerprint $fingerprint): bool {
        return true;
    }
}
