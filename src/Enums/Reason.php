<?php

declare(strict_types=1);

namespace SimoneCerruti\BotDetector\Enums;

/** Why the detection got its verdict. */
enum Reason: string {
    /** A good-bot claim confirmed by reverse and forward DNS. */
    case VerifiedGoodBot = 'verified_good_bot';
    /** A good-bot claim whose IP fails DNS verification. */
    case SpoofedGoodBot = 'spoofed_good_bot';
    /** A good-bot claim that DNS could not check because of an error. */
    case UnverifiableGoodBot = 'unverifiable_good_bot';
    /** A self-declared bot or HTTP library. */
    case SelfDeclared = 'self_declared';
    /** A request to a probe path, with probe paths enabled. */
    case ProbePath = 'probe_path';
    /** The score of the matched signals. */
    case Score = 'score';
}
