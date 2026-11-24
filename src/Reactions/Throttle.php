<?php

declare(strict_types=1);

namespace SimoneCerruti\BotDetector\Reactions;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use SimoneCerruti\BotDetector\Contracts\Reaction;
use SimoneCerruti\BotDetector\Contracts\RespondsToDeny;
use SimoneCerruti\BotDetector\Data\Detection;
use SimoneCerruti\BotDetector\Enums\Decision;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allows `bot-detector.throttle.max_requests` requests per `bot-detector.throttle.per_seconds` seconds for each IP + User-Agent,
 * then denies with an empty 429 and `Retry-After`.
 */
final class Throttle implements Reaction, RespondsToDeny {
    public function decide(Request $request, Detection $detection): Decision {
        $allowed = RateLimiter::attempt(
            $this->findKey($detection),
            config()->integer('bot-detector.throttle.max_requests'),
            fn (): bool => true,
            config()->integer('bot-detector.throttle.per_seconds'),
        );

        return $allowed ? Decision::Allow : Decision::Deny;
    }

    public function denyResponse(Request $request, Detection $detection): Response {
        return response('', Response::HTTP_TOO_MANY_REQUESTS, [
            'Retry-After' => RateLimiter::availableIn($this->findKey($detection)),
        ]);
    }

    private function findKey(Detection $detection): string {
        return 'bot-detector:throttle:'.hash('sha256', $detection->fingerprint->ip.'|'.$detection->fingerprint->user_agent);
    }
}
