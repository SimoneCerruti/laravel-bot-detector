<?php

declare(strict_types=1);

namespace SimoneCerruti\BotDetector\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use SimoneCerruti\BotDetector\BotDetector;
use SimoneCerruti\BotDetector\Contracts\Reaction;
use SimoneCerruti\BotDetector\Enums\Decision;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs all the reactions configured for the request's verdict, in order, and denies the request when any of them denies,
 * unless `bot-detector.dry_run` is on.
 *
 * @internal
 */
final readonly class DetectBots {
    public function __construct(
        private BotDetector $detector,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response {
        $detection = $this->detector->detect($request);

        /** @var list<class-string<Reaction>> $reactions */
        $reactions = config()->array('bot-detector.reactions.'.$detection->verdict->value, []);

        // Every reaction runs, so one after a denying reaction, such as Log after Block, still sees the request.
        $decisions = array_map(fn (string $reaction): Decision => app($reaction)->decide($request, $detection), $reactions);

        if (\in_array(Decision::Deny, $decisions, true) && ! config()->boolean('bot-detector.dry_run')) {
            return response('', config()->integer('bot-detector.deny_status'));
        }

        return $next($request);
    }
}
