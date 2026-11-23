<?php

declare(strict_types=1);

namespace SimoneCerruti\BotDetector\Data;

use Illuminate\Http\Request;
use Spatie\LaravelData\Data;

/**
 * The parts of a request the signals look at, read from the request in this one place
 * so that every signal stays a pure function of the fingerprint.
 */
final class RequestFingerprint extends Data
{
    public function __construct(
        public readonly ?string $acceptLanguage,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            acceptLanguage: $request->headers->get('Accept-Language'),
        );
    }
}
