<?php

declare(strict_types=1);

namespace SimoneCerruti\BotDetector\Signals;

use Illuminate\Support\Str;
use SimoneCerruti\BotDetector\Contracts\Signal;
use SimoneCerruti\BotDetector\Data\RequestFingerprint;

/** Chromium 89+ always sends Sec-CH-UA over HTTPS; Firefox, Safari and Android WebView (`; wv)`) never do. */
final class MissingSecChUa implements Signal {
    public function matches(RequestFingerprint $fingerprint): bool {
        $user_agent = (string) $fingerprint->user_agent;

        return $fingerprint->secure
            && blank($fingerprint->sec_ch_ua)
            && ! str_contains($user_agent, '; wv)')
            && (int) Str::match('~Chrome/(\d+)~', $user_agent) >= 89;
    }
}
