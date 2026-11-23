<?php

declare(strict_types=1);

namespace SimoneCerruti\BotDetector\Signals;

use Illuminate\Support\Str;
use SimoneCerruti\BotDetector\Contracts\Signal;
use SimoneCerruti\BotDetector\Data\RequestFingerprint;

/** Chromium 76+, Firefox 90+ and Safari 16.4+ always send Sec-Fetch-Site, but only over HTTPS. */
final class MissingSecFetchSite implements Signal {
    public function matches(RequestFingerprint $fingerprint): bool {
        return $fingerprint->secure
            && blank($fingerprint->sec_fetch_site)
            && $this->claimsBrowserSendingIt((string) $fingerprint->user_agent);
    }

    private function claimsBrowserSendingIt(string $user_agent): bool {
        $chrome = Str::match('~Chrome/(\d+)~', $user_agent);
        $firefox = Str::match('~Firefox/(\d+)~', $user_agent);
        $safari = Str::match('~Version/(\d+(?:\.\d+)?).*Safari/~', $user_agent);

        return match (true) {
            $chrome !== '' => (int) $chrome >= 76,
            $firefox !== '' => (int) $firefox >= 90,
            $safari !== '' => version_compare($safari, '16.4', '>='),
            default => false,
        };
    }
}
