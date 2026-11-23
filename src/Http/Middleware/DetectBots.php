<?php

declare(strict_types=1);

namespace SimoneCerruti\BotDetector\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use SimoneCerruti\BotDetector\BotDetector;
use SimoneCerruti\BotDetector\Contracts\BotAction;
use SimoneCerruti\BotDetector\Enums\Decision;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs the actions configured for the request's verdict, in order, and denies the request at the first denying action.
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

        /** @var list<class-string<BotAction>> $actions */
        $actions = config()->array('bot-detector.actions.'.$detection->verdict->value, []);

        $denied = array_any($actions, fn (string $action): bool => app($action)->decide($request, $detection) === Decision::Deny);

        if ($denied) {
            return response('', config()->integer('bot-detector.deny_status'));
        }

        return $next($request);
    }
}
