<?php

declare(strict_types=1);

namespace SimoneCerruti\BotDetector\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use SimoneCerruti\BotDetector\BotDetector;
use SimoneCerruti\BotDetector\Contracts\Reaction;
use SimoneCerruti\BotDetector\Contracts\RespondsToDeny;
use SimoneCerruti\BotDetector\Enums\Decision;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs all the reactions configured for the request's verdict, in order, and denies the request with the first denying reaction's response when any of them denies,
 * unless `bot-detector.dry_run` is on. Requests matching `bot-detector.except_paths` or `bot-detector.except_ips` skip detection entirely.
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
        /** @var list<string> $except_paths */
        $except_paths = config()->array('bot-detector.except_paths', []);
        /** @var list<string> $except_ips */
        $except_ips = config()->array('bot-detector.except_ips', []);

        if (Str::is($except_paths, $request->path()) || IpUtils::checkIp((string) $request->ip(), $except_ips)) {
            return $next($request);
        }

        $detection = $this->detector->detect($request);

        /** @var list<class-string<Reaction>> $classes */
        $classes = config()->array('bot-detector.reactions.'.$detection->verdict->value, []);
        $reactions = array_map(fn (string $class): Reaction => app($class), $classes);

        // Every reaction runs, so one after a denying reaction, such as Log after Block, still sees the request.
        $decisions = array_map(fn (Reaction $reaction): Decision => $reaction->decide($request, $detection), $reactions);

        $denying = array_search(Decision::Deny, $decisions, true);

        if ($denying === false || config()->boolean('bot-detector.dry_run')) {
            return $next($request);
        }

        if ($reactions[$denying] instanceof RespondsToDeny) {
            return $reactions[$denying]->denyResponse($request, $detection);
        }

        return response('', config()->integer('bot-detector.deny_status'));
    }
}
