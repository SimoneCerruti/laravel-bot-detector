<?php

declare(strict_types=1);

namespace SimoneCerruti\BotDetector\Tests\Fixtures;

use SimoneCerruti\BotDetector\Contracts\DnsResolver;

/** Prepared DNS answers, so no test touches the network: a missing name has no records, a null answer is a DNS error. */
final class FakeDnsResolver implements DnsResolver {
    public int $lookups = 0;

    /**
     * @param  array<string, list<string>|null>  $hostnames  PTR answers by IP
     * @param  array<string, list<string>|null>  $addresses  A and AAAA answers by hostname
     */
    public function __construct(
        private readonly array $hostnames = [],
        private readonly array $addresses = [],
    ) {}

    public function lookupHostnames(string $ip): ?array {
        $this->lookups++;

        return \array_key_exists($ip, $this->hostnames) ? $this->hostnames[$ip] : [];
    }

    public function lookupAddresses(string $hostname): ?array {
        $this->lookups++;

        return \array_key_exists($hostname, $this->addresses) ? $this->addresses[$hostname] : [];
    }
}
