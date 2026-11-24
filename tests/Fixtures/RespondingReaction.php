<?php

declare(strict_types=1);

namespace SimoneCerruti\BotDetector\Tests\Fixtures;

use Illuminate\Http\Request;
use SimoneCerruti\BotDetector\Contracts\Reaction;
use SimoneCerruti\BotDetector\Contracts\RespondsToDeny;
use SimoneCerruti\BotDetector\Data\Detection;
use SimoneCerruti\BotDetector\Enums\Decision;
use Symfony\Component\HttpFoundation\Response;

/** A host-defined reaction that denies every request with its own challenge page. */
final class RespondingReaction implements Reaction, RespondsToDeny {
    public function decide(Request $request, Detection $detection): Decision {
        return Decision::Deny;
    }

    public function denyResponse(Request $request, Detection $detection): Response {
        return response('challenge', 401);
    }
}
