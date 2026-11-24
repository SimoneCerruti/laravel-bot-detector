<?php

declare(strict_types=1);

namespace SimoneCerruti\BotDetector;

use Closure;
use DeviceDetector\Cache\PSR16Bridge;
use DeviceDetector\DeviceDetector;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use SimoneCerruti\BotDetector\Contracts\Reaction;
use SimoneCerruti\BotDetector\Contracts\Signal;
use SimoneCerruti\BotDetector\Data\Detection;
use SimoneCerruti\BotDetector\Data\RequestFingerprint;
use SimoneCerruti\BotDetector\Enums\Verdict;

final class BotDetector {
    private const string ATTRIBUTE = 'bot-detector.detection';

    /** Bot tokens for the clients Matomo leaves without a device, such as link previews it sees as a mobile app (WhatsApp). */
    private const string FALLBACK_BOT_PATTERN = '/bot|crawler|spider|crawling|slurp|facebookexternalhit|embedly|preview|fetch|whatsapp|telegram|petalbot|bytespider|semrush|ahrefs|mj12bot/i';

    /** @var (Closure(Request, Detection, list<class-string<Reaction>>): list<class-string<Reaction>>)|null */
    private ?Closure $policy = null;

    /**
     * Register the single policy that receives the reactions configured for the verdict and returns the reactions to run.
     *
     * @param  Closure(Request, Detection, list<class-string<Reaction>>): list<class-string<Reaction>>  $policy
     */
    public function usePolicy(Closure $policy): void {
        $this->policy = $policy;
    }

    /**
     * The reactions to run for the detection: those configured for its verdict, passed through the policy when one is registered.
     *
     * @internal Called by the bot-detector middleware; not covered by semver.
     *
     * @return list<class-string<Reaction>>
     */
    public function resolveReactions(Request $request, Detection $detection): array {
        /** @var list<class-string<Reaction>> $reactions */
        $reactions = config()->array('bot-detector.reactions.'.$detection->verdict->value, []);

        return $this->policy instanceof Closure ? ($this->policy)($request, $detection, $reactions) : $reactions;
    }

    /** Inspect the request, computing the detection once and reusing it for the rest of the request. */
    public function detect(Request $request): Detection {
        $detection = $request->attributes->get(self::ATTRIBUTE);

        if ($detection instanceof Detection) {
            return $detection;
        }

        $detection = $this->inspect(RequestFingerprint::fromRequest($request));
        $request->attributes->set(self::ATTRIBUTE, $detection);

        return $detection;
    }

    private function inspect(RequestFingerprint $fingerprint): Detection {
        /** @var array<class-string<Signal>, int> $weights */
        $weights = config()->array('bot-detector.signals');

        $signals = array_filter(
            $weights,
            fn (string $signal): bool => app($signal)->matches($fingerprint),
            ARRAY_FILTER_USE_KEY,
        );

        $score = array_sum($signals);
        [$claimed_as, $category] = $this->classify($fingerprint->user_agent);

        /** @var list<string> $probe_paths */
        $probe_paths = config()->array('bot-detector.probe_paths.paths');
        $probed = config()->boolean('bot-detector.probe_paths.enabled') && Str::is($probe_paths, $fingerprint->path);

        $suffixes = $this->findGoodBotSuffixes($fingerprint->user_agent);
        $verified = $suffixes === null ? null : app(GoodBotVerifier::class)->verify($fingerprint->ip, $suffixes);

        $verdict = match (true) {
            $verified === true => Verdict::GoodBot,
            $verified === false => Verdict::Automated,
            // A DNS error on a good-bot claim must never block the real crawler.
            $suffixes !== null => Verdict::Suspicious,
            $claimed_as !== null, $probed => Verdict::Automated,
            $score >= config()->integer('bot-detector.automated_at') => Verdict::Automated,
            $score >= config()->integer('bot-detector.suspicious_at') => Verdict::Suspicious,
            default => Verdict::Human,
        };

        return new Detection(
            verdict: $verdict,
            score: $score,
            signals: $signals,
            claimed_as: $claimed_as,
            category: $category,
            verified: $verified,
            fingerprint: $fingerprint,
        );
    }

    /**
     * What a self-declared bot or HTTP library claims to be, and its bot category: both null for any other client.
     *
     * @return array{?string, ?string}
     */
    private function classify(?string $user_agent): array {
        if ($user_agent === null || $user_agent === '') {
            return [null, null];
        }

        /** @var ?string $store */
        $store = config('bot-detector.device_detector.cache_store');

        $detector = new DeviceDetector($user_agent);
        $detector->setCache(new PSR16Bridge(Cache::store($store)));
        $detector->parse();

        $bot = $detector->getBot();

        if (\is_array($bot)) {
            // Some Matomo bots have no category.
            return [Arr::string($bot, 'name'), isset($bot['category']) ? Arr::string($bot, 'category') : null];
        }

        $client = $detector->getClient();

        if (\is_array($client) && $client['type'] === 'library') {
            return [Arr::string($client, 'name'), null];
        }

        $token = $detector->getDeviceName() === '' ? Str::match(self::FALLBACK_BOT_PATTERN, $user_agent) : '';

        if ($token !== '') {
            return [$token, null];
        }

        return [null, null];
    }

    /**
     * The hostname suffixes of the configured good bot the User-Agent claims to be, or null for no claim.
     *
     * @return list<string>|null
     */
    private function findGoodBotSuffixes(?string $user_agent): ?array {
        /** @var array<string, list<string>> $bots */
        $bots = config()->array('bot-detector.good_bots.bots');

        return Arr::first($bots, fn (array $suffixes, string $pattern): bool => Str::isMatch($pattern, $user_agent ?? ''));
    }
}
