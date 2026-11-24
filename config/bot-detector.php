<?php

declare(strict_types=1);

use SimoneCerruti\BotDetector\Reactions\Block;
use SimoneCerruti\BotDetector\Reactions\Log;
use SimoneCerruti\BotDetector\Signals\HeadlessChrome;
use SimoneCerruti\BotDetector\Signals\MissingAccept;
use SimoneCerruti\BotDetector\Signals\MissingAcceptEncoding;
use SimoneCerruti\BotDetector\Signals\MissingAcceptLanguage;
use SimoneCerruti\BotDetector\Signals\MissingSecChUa;
use SimoneCerruti\BotDetector\Signals\MissingSecFetchSite;

return [

    /*
     * The `bot-detector` middleware skips detection entirely, running no reactions, for requests to these
     * paths or from these IPs. Paths have no leading slash and `*` matches anything; IPs are
     * addresses or CIDR ranges, such as `10.0.0.0/8`.
     */
    'except_paths' => [
        'up',
        'robots.txt',
        'sitemap*.xml',
        'llms.txt',
    ],

    'except_ips' => [],

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

    /*
     * Clients whose User-Agent matches a pattern (a regex) are verified by DNS: the IP must point back to
     * a hostname ending with one of the suffixes, and that hostname must resolve to the same IP.
     * Verified gets GoodBot, a mismatch gets Automated, a DNS error gets Suspicious (and is retried).
     * Confirmed hostnames are cached per IP for `cache_ttl` seconds in the default cache store.
     */
    'good_bots' => [
        'cache_ttl' => 60 * 60 * 24,
        'bots' => [
            '/Googlebot/i' => ['.googlebot.com', '.google.com'],
            '/bingbot/i' => ['.search.msn.com'],
            '/Applebot/i' => ['.applebot.apple.com'],
            '/DuckDuckBot/i' => ['.duckduckgo.com'],
            '/YandexBot/i' => ['.yandex.ru', '.yandex.net', '.yandex.com'],
        ],
    ],

    /*
     * The reactions the `bot-detector` middleware runs for each verdict, all of them and in order: the request
     * is denied when any reaction denies. Add your own classes implementing
     * SimoneCerruti\BotDetector\Contracts\Reaction. Human requests run no reactions.
     */
    'reactions' => [
        'good_bot' => [],
        'suspicious' => [Log::class],
        'automated' => [Log::class, Block::class],
    ],

    /*
     * Where the Log reaction writes a detection: the channel (null for the app default) and the level.
     * The IP is logged as an HMAC keyed by the app key, so the same client stays recognisable without
     * storing its address: turn on `raw_ip` to log the address itself.
     */
    'log' => [
        'channel' => null,
        'level' => 'info',
        'raw_ip' => false,
    ],

    /*
     * In dry run the reactions still run, but a denied request is let through: nothing is blocked
     * until you have tuned the thresholds and turned this off.
     */
    'dry_run' => true,

    /*
     * The status of the empty response a denied request gets.
     */
    'deny_status' => 403,

];
