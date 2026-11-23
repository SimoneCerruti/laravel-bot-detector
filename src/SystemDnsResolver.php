<?php

declare(strict_types=1);

namespace SimoneCerruti\BotDetector;

use NetDNS2\ENUM\Error;
use NetDNS2\Exception;
use NetDNS2\Resolver;
use NetDNS2\RR;
use NetDNS2\RR\A;
use NetDNS2\RR\AAAA;
use NetDNS2\RR\PTR;
use SimoneCerruti\BotDetector\Contracts\DnsResolver;

/**
 * Resolves through the nameservers in /etc/resolv.conf with netdns2, which tells a missing name from a failed lookup.
 *
 * @internal
 */
final class SystemDnsResolver implements DnsResolver {
    public function lookupHostnames(string $ip): ?array {
        return $this->lookup($ip, 'PTR');
    }

    public function lookupAddresses(string $hostname): ?array {
        $ipv4 = $this->lookup($hostname, 'A');
        $ipv6 = $this->lookup($hostname, 'AAAA');

        return $ipv4 === null || $ipv6 === null ? null : [...$ipv4, ...$ipv6];
    }

    /**
     * The PTR targets or the addresses answered, an empty list when the name does not exist, or null when the lookup failed.
     *
     * @return list<string>|null
     */
    private function lookup(string $name, string $type): ?array {
        try {
            $answer = new Resolver()->query($name, $type)->answer;
        } catch (Exception $exception) {
            return $exception->getCode() === Error::DNS_NXDOMAIN->value ? [] : null;
        }

        // The answer may also carry other records, such as the CNAME the name points to.
        return array_values(array_filter(array_map(fn (RR $record): ?string => match (true) {
            $record instanceof PTR => (string) $record->ptrdname,
            $record instanceof A, $record instanceof AAAA => (string) $record->address,
            default => null,
        }, $answer), fn (?string $value): bool => $value !== null));
    }
}
