<?php

declare(strict_types=1);

namespace SimoneCerruti\BotDetector;

use Illuminate\Http\Request;
use SimoneCerruti\BotDetector\Contracts\Signal;
use SimoneCerruti\BotDetector\Data\Detection;
use SimoneCerruti\BotDetector\Data\RequestFingerprint;
use SimoneCerruti\BotDetector\Enums\Verdict;

final class BotDetector {
    private const string ATTRIBUTE = 'bot-detector.detection';

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

        return new Detection(
            verdict: match (true) {
                $score >= config()->integer('bot-detector.automated_at') => Verdict::Automated,
                $score >= config()->integer('bot-detector.suspicious_at') => Verdict::Suspicious,
                default => Verdict::Human,
            },
            score: $score,
            signals: $signals,
            fingerprint: $fingerprint,
        );
    }
}
