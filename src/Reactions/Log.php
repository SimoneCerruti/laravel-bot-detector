<?php

declare(strict_types=1);

namespace SimoneCerruti\BotDetector\Reactions;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log as Logger;
use SimoneCerruti\BotDetector\Contracts\Reaction;
use SimoneCerruti\BotDetector\Data\Detection;
use SimoneCerruti\BotDetector\Enums\Decision;

/** Writes the detection to the `bot-detector.log` channel and level, and lets the request through. */
final class Log implements Reaction {
    public function decide(Request $request, Detection $detection): Decision {
        $fingerprint = $detection->fingerprint;
        $ip = $fingerprint->ip;

        if ($ip !== null && ! config()->boolean('bot-detector.log.raw_ip')) {
            $ip = hash_hmac('sha256', $ip, config()->string('app.key'));
        }

        /** @var string|null $channel */
        $channel = config('bot-detector.log.channel');

        Logger::channel($channel)->log(config()->string('bot-detector.log.level'), 'Bot detected', [
            'ip' => $ip,
            'user_agent' => $fingerprint->user_agent,
            'score' => $detection->score,
            'signals' => $detection->signals,
            'verdict' => $detection->verdict->value,
            'method' => $fingerprint->method,
            'path' => $fingerprint->path,
            'route' => $fingerprint->route,
        ]);

        return Decision::Allow;
    }
}
