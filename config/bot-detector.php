<?php

declare(strict_types=1);

use SimoneCerruti\BotDetector\Signals\MissingAcceptLanguage;

return [

    /*
     * The signals evaluated on every request, each with the weight it adds to the score when it matches.
     * Add your own classes implementing SimoneCerruti\BotDetector\Contracts\Signal, remove a signal
     * to disable it, or change a weight to tune it.
     */
    'signals' => [
        MissingAcceptLanguage::class => 3,
    ],

    /*
     * The score from which a request is considered Suspicious.
     */
    'suspicious_at' => 3,

];
