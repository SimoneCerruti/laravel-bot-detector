<?php

declare(strict_types=1);

namespace SimoneCerruti\BotDetector;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use SimoneCerruti\BotDetector\Contracts\DnsResolver;

use function Safe\inet_pton;

/**
 * Checks a good-bot claim by DNS: the IP must point back to a hostname under the bot's suffixes,
 * and that hostname must resolve to the same IP. Confirmed hostnames are cached per IP.
 *
 * @internal
 */
final readonly class GoodBotVerifier {
    public function __construct(
        private DnsResolver $resolver,
    ) {}

    /**
     * Whether the IP belongs to the good bot: null when DNS failed.
     *
     * @param  list<string>  $suffixes
     */
    public function verify(?string $ip, array $suffixes): ?bool {
        if ($ip === null) {
            return false;
        }

        $key = 'bot-detector.good-bots:'.$ip;

        /** @var list<string>|null $hostnames */
        $hostnames = Cache::get($key);

        if ($hostnames === null) {
            $hostnames = $this->resolveConfirmedHostnames($ip);

            if ($hostnames === null) {
                return null;
            }

            Cache::put($key, $hostnames, config()->integer('bot-detector.good_bots.cache_ttl'));
        }

        return array_any($hostnames, fn (string $hostname): bool => Str::endsWith($hostname, $suffixes));
    }

    /**
     * The good-bot hostnames the IP points back to that resolve to the same IP, or null when DNS failed.
     * Only hostnames under a configured suffix are resolved, so a spoofer's own DNS cannot fail the lookup on purpose.
     *
     * @return list<string>|null
     */
    private function resolveConfirmedHostnames(string $ip): ?array {
        /** @var list<string> $suffixes */
        $suffixes = Arr::flatten(config()->array('bot-detector.good_bots.bots'));
        $hostnames = $this->resolver->lookupHostnames($ip);

        if ($hostnames === null) {
            return null;
        }

        $confirmed = [];

        foreach ($hostnames as $hostname) {
            $hostname = Str::lower($hostname);

            if (! Str::endsWith($hostname, $suffixes)) {
                continue;
            }

            $addresses = $this->resolver->lookupAddresses($hostname);

            if ($addresses === null) {
                return null;
            }

            if (\in_array(inet_pton($ip), array_map(inet_pton(...), $addresses), true)) {
                $confirmed[] = $hostname;
            }
        }

        return $confirmed;
    }
}
