<?php

declare(strict_types=1);

namespace SimoneCerruti\BotDetector\Contracts;

/**
 * DNS lookups for good-bot verification, bound in the container so tests never touch the network.
 *
 * @internal
 */
interface DnsResolver {
    /**
     * The hostnames the IP points back to (PTR records), or null when the lookup failed.
     *
     * @return list<string>|null
     */
    public function hostnames(string $ip): ?array;

    /**
     * The IPv4 and IPv6 addresses of the hostname (A and AAAA records), or null when the lookup failed.
     *
     * @return list<string>|null
     */
    public function addresses(string $hostname): ?array;
}
