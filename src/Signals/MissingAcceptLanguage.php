<?php

declare(strict_types=1);

namespace SimoneCerruti\BotDetector\Signals;

use SimoneCerruti\BotDetector\Contracts\Signal;
use SimoneCerruti\BotDetector\Data\RequestFingerprint;

/** Every mainstream browser sends Accept-Language; headless Chrome and HTTP libraries usually do not. */
final class MissingAcceptLanguage implements Signal
{
    public function matches(RequestFingerprint $fingerprint): bool
    {
        return blank($fingerprint->acceptLanguage);
    }
}
