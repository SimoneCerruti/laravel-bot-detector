<?php

declare(strict_types=1);

namespace SimoneCerruti\BotDetector\Contracts;

use Illuminate\Http\Request;
use SimoneCerruti\BotDetector\Data\Detection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Optional for a reaction: the response a request gets when this reaction is the first to deny it,
 * instead of the `bot-detector.deny_status` response.
 */
interface RespondsToDeny {
    public function denyResponse(Request $request, Detection $detection): Response;
}
