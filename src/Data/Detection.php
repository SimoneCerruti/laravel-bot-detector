<?php

declare(strict_types=1);

namespace SimoneCerruti\BotDetector\Data;

use SimoneCerruti\BotDetector\Contracts\Signal;
use SimoneCerruti\BotDetector\Enums\Verdict;
use Spatie\LaravelData\Data;

final class Detection extends Data {
    /**
     * @param  array<class-string<Signal>, int>  $signals  the matched signals and their weight
     * @param  string|null  $claimed_as  what a self-declared bot or HTTP library claims to be
     * @param  string|null  $category  the bot category, such as `Search bot` or `Crawler`
     * @param  bool|null  $verified  whether reverse and forward DNS confirmed a good-bot claim: null when there was no claim or DNS failed
     */
    public function __construct(
        public readonly Verdict $verdict,
        public readonly int $score,
        public readonly array $signals,
        public readonly ?string $claimed_as,
        public readonly ?string $category,
        public readonly ?bool $verified,
        public readonly RequestFingerprint $fingerprint,
    ) {}

    public function isBot(): bool {
        return $this->verdict !== Verdict::Human;
    }

    public function isGoodBot(): bool {
        return $this->verdict === Verdict::GoodBot;
    }
}
