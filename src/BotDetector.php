<?php

declare(strict_types=1);

namespace SimoneCerruti\BotDetector;

use DeviceDetector\DeviceDetector;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use SimoneCerruti\BotDetector\Contracts\Signal;
use SimoneCerruti\BotDetector\Data\Detection;
use SimoneCerruti\BotDetector\Data\RequestFingerprint;
use SimoneCerruti\BotDetector\Enums\Verdict;

final class BotDetector {
    private const string ATTRIBUTE = 'bot-detector.detection';

    /** Bot tokens for the clients Matomo leaves without a device, such as link previews it sees as a mobile app (WhatsApp). */
    private const string FALLBACK_BOT_PATTERN = '/bot|crawler|spider|crawling|slurp|facebookexternalhit|embedly|preview|fetch|whatsapp|telegram|petalbot|bytespider|semrush|ahrefs|mj12bot/i';

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
        $claim = $this->classify($fingerprint->user_agent);

        return new Detection(
            verdict: match (true) {
                $claim !== null => Verdict::Automated,
                $score >= config()->integer('bot-detector.automated_at') => Verdict::Automated,
                $score >= config()->integer('bot-detector.suspicious_at') => Verdict::Suspicious,
                default => Verdict::Human,
            },
            score: $score,
            signals: $signals,
            claimed_as: $claim[0] ?? null,
            category: $claim[1] ?? null,
            fingerprint: $fingerprint,
        );
    }

    /**
     * What a self-declared bot or HTTP library claims to be, and its bot category.
     *
     * @return array{string, ?string}|null
     */
    private function classify(?string $user_agent): ?array {
        if ($user_agent === null || $user_agent === '') {
            return null;
        }

        $detector = new DeviceDetector($user_agent);
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

        return null;
    }
}
