<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/dns_name.php';
require_once __DIR__ . '/helper.php';

// 1. IPv4 /24 Subnet Reverse Zone
assertEq(
    ipv4ToReverseZone24('192.0.2.0/24'),
    '2.0.192.in-addr.arpa.',
    'IPv4 /24 Subnet converts to 2.0.192.in-addr.arpa.'
);
assertEq(
    ipv4ToReverseZone24('10.50.100.12'),
    '100.50.10.in-addr.arpa.',
    'IPv4 single IP extracts /24 reverse zone'
);
assertEq(
    ipv4ToReverseZone24('invalid.ip'),
    null,
    'Invalid IPv4 returns null'
);

// 2. IPv4 Relative & Full PTR Name
assertEq(
    ipv4ToRelativePtr24('192.0.2.15'),
    '15',
    'IPv4 relative PTR name in /24 is 4th octet'
);
assertEq(
    ipv4ToPtrFqdn('192.0.2.15'),
    '15.2.0.192.in-addr.arpa.',
    'IPv4 full PTR FQDN'
);

// 3. IPv6 /64 Prefix Reverse Zone (RFC 3596 Nibble Format)
assertEq(
    ipv6ToReverseZone64('2001:db8:1234:5678::/64'),
    '8.7.6.5.4.3.2.1.8.b.d.0.1.0.0.2.ip6.arpa.',
    'IPv6 /64 converts to RFC 3596 reversed 16 nibbles'
);
assertEq(
    ipv6ToReverseZone64('2001:DB8:1234:5678::1'),
    '8.7.6.5.4.3.2.1.8.b.d.0.1.0.0.2.ip6.arpa.',
    'IPv6 single IP extracts /64 reverse zone'
);
assertEq(
    ipv6ToReverseZone64('not:an:ipv6'),
    null,
    'Invalid IPv6 returns null'
);

// 4. IPv6 Relative & Full PTR Name
assertEq(
    ipv6ToRelativePtr64('2001:db8:1234:5678::1'),
    '1.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0',
    'IPv6 ::1 relative PTR name in /64'
);
assertEq(
    ipv6ToRelativePtr64('2001:db8:1234:5678:abcd:ef01:2345:6789'),
    '9.8.7.6.5.4.3.2.1.0.f.e.d.c.b.a',
    'IPv6 arbitrary host relative PTR name in /64'
);
assertEq(
    ipv6ToPtrFqdn('2001:db8:1234:5678::1'),
    '1.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.8.7.6.5.4.3.2.1.8.b.d.0.1.0.0.2.ip6.arpa.',
    'IPv6 full 32-nibble PTR FQDN'
);

echo "All rDNS math tests passed successfully!\n";
