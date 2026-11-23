<?php

declare(strict_types=1);

namespace SimoneCerruti\BotDetector\Reactions;

use Illuminate\Http\Request;
use SimoneCerruti\BotDetector\Contracts\Reaction;
use SimoneCerruti\BotDetector\Data\Detection;
use SimoneCerruti\BotDetector\Enums\Decision;

/** Denies the request with the `bot-detector.deny_status` response. */
final class Block implements Reaction {
    public function decide(Request $request, Detection $detection): Decision {
        return Decision::Deny;
    }
}
