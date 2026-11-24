<?php

declare(strict_types=1);

namespace SimoneCerruti\BotDetector\Events;

use Illuminate\Http\Request;
use SimoneCerruti\BotDetector\Data\Detection;

/** Dispatched once per request by the `bot-detector` middleware for every non-human verdict. */
final readonly class BotDetected {
    /**
     * @param  bool  $dry_run  whether `bot-detector.dry_run` is on, so a listener can skip irreversible actions while tuning
     */
    public function __construct(
        public Detection $detection,
        public Request $request,
        public bool $dry_run,
    ) {}
}
