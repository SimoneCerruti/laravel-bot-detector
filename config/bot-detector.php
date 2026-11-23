<?php

declare(strict_types=1);

use SimoneCerruti\BotDetector\Signals\HeadlessChrome;
use SimoneCerruti\BotDetector\Signals\MissingAccept;
use SimoneCerruti\BotDetector\Signals\MissingAcceptEncoding;
use SimoneCerruti\BotDetector\Signals\MissingAcceptLanguage;
use SimoneCerruti\BotDetector\Signals\MissingSecChUa;
use SimoneCerruti\BotDetector\Signals\MissingSecFetchSite;

return [

    /*
     * The signals evaluated on every request, each with the weight it adds to the score when it matches.
     * Add your own classes implementing SimoneCerruti\BotDetector\Contracts\Signal, remove a signal
     * to disable it, or change a weight to tune it.
     */
    'signals' => [
        HeadlessChrome::class => 5,
        MissingSecFetchSite::class => 3,
        MissingSecChUa::class => 3,
        MissingAcceptLanguage::class => 3,
        MissingAccept::class => 2,
        MissingAcceptEncoding::class => 1,
    ],

    /*
     * The score from which a request is considered Suspicious.
     */
    'suspicious_at' => 3,

    /*
     * The score from which a request is considered Automated.
     */
    'automated_at' => 5,

    /*
     * Requests to these paths are classified Automated when enabled: vulnerability scanners probe them on
     * their first hit. Paths have no leading slash and `*` matches anything. Off by default because
     * a legitimate app may serve one of them, such as WordPress under a sub-path.
     */
    'probe_paths' => [
        'enabled' => false,
        'paths' => [
            '.env',
            '.git/config',
            'wp-login.php',
            'xmlrpc.php',
            'phpmyadmin',
            'vendor/phpunit/*',
            '.aws/credentials',
            'server-status',
            'actuator',
        ],
    ],

];
