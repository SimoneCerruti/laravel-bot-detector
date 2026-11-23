<?php

declare(strict_types=1);

namespace SimoneCerruti\BotDetector\Tests\Fixtures;

use Illuminate\Http\Request;
use SimoneCerruti\BotDetector\Contracts\BotAction;
use SimoneCerruti\BotDetector\Data\Detection;
use SimoneCerruti\BotDetector\Enums\Decision;

/** A host-defined action that lets every request through. */
final class AllowingAction implements BotAction {
    public function decide(Request $request, Detection $detection): Decision {
        return Decision::Allow;
    }
}
