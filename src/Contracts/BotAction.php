<?php

declare(strict_types=1);

namespace SimoneCerruti\BotDetector\Contracts;

use Illuminate\Http\Request;
use SimoneCerruti\BotDetector\Data\Detection;
use SimoneCerruti\BotDetector\Enums\Decision;

/**
 * What the `bot-detector` middleware does with a request, configured per verdict in `bot-detector.actions`.
 */
interface BotAction {
    public function decide(Request $request, Detection $detection): Decision;
}
