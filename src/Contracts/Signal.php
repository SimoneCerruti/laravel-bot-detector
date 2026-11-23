<?php

declare(strict_types=1);

namespace SimoneCerruti\BotDetector\Contracts;

use SimoneCerruti\BotDetector\Data\RequestFingerprint;

/**
 * A piece of evidence that a request comes from an automated client.
 * Its weight lives in the `bot-detector.signals` config, keyed by the signal class.
 */
interface Signal
{
    public function matches(RequestFingerprint $fingerprint): bool;
}
