<?php

declare(strict_types=1);

namespace SimoneCerruti\BotDetector;

use Illuminate\Support\Arr;
use SimoneCerruti\BotDetector\Contracts\DnsResolver;

use function Safe\inet_pton;

/**
 * Resolves through the system resolver. dns_get_record() tells a failure (false) from a missing record (empty).
 *
 * @internal
 */
final class SystemDnsResolver implements DnsResolver {
    public function hostnames(string $ip): ?array {
        // PTR name: reversed IPv4 octets, or reversed IPv6 nibbles.
        $name = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
            ? implode('.', array_reverse(explode('.', $ip))).'.in-addr.arpa'
            : implode('.', str_split(strrev(bin2hex(inet_pton($ip))))).'.ip6.arpa';

        return $this->lookup($name, DNS_PTR, 'target');
    }

    public function addresses(string $hostname): ?array {
        $ipv4 = $this->lookup($hostname, DNS_A, 'ip');
        $ipv6 = $this->lookup($hostname, DNS_AAAA, 'ipv6');

        return $ipv4 === null || $ipv6 === null ? null : [...$ipv4, ...$ipv6];
    }

    /** @return list<string>|null */
    private function lookup(string $name, int $type, string $field): ?array {
        // Not Safe's variant: it always passes $additional_records, which makes PHP add an ANY query that resolvers refuse (RFC 8482).
        $records = @\dns_get_record($name, $type); // @phpstan-ignore theCodingMachineSafe.function

        return $records === false ? null : array_map(fn (array $record): string => Arr::string($record, $field), $records);
    }
}
