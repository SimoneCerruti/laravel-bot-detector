<?php

declare(strict_types=1);

namespace SimoneCerruti\BotDetector\Tests\Fixtures;

use Illuminate\Http\Request;
use SimoneCerruti\BotDetector\Contracts\Reaction;
use SimoneCerruti\BotDetector\Data\Detection;
use SimoneCerruti\BotDetector\Enums\Decision;

/** A host-defined reaction that denies every request. */
final class DenyingReaction implements Reaction {
    public function decide(Request $request, Detection $detection): Decision {
        return Decision::Deny;
    }
}
