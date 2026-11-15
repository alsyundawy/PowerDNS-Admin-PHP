<?php

declare(strict_types=1);

require_once __DIR__ . '/helper.php';
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/dns_name.php';
require_once dirname(__DIR__) . '/app/services.php';

// Test 1: IPv4 larger subnets to reverse zone calculation
$zone24 = ipv4SubnetToReverseZone('192.168.1.0/24');
assertEq($zone24, '1.168.192.in-addr.arpa.', 'IPv4 /24 subnet converts to 3 octets reverse zone');

$zone16 = ipv4SubnetToReverseZone('172.16.0.0/16');
assertEq($zone16, '16.172.in-addr.arpa.', 'IPv4 /16 subnet converts to 2 octets reverse zone');

$zone8 = ipv4SubnetToReverseZone('10.0.0.0/8');
assertEq($zone8, '10.in-addr.arpa.', 'IPv4 /8 subnet converts to 1 octet reverse zone');

// Test 2: IPv4 relative PTR name within larger reverse zone
$relIn24 = ipv4ToRelativePtr('192.168.1.55', '1.168.192.in-addr.arpa.');
assertEq($relIn24, '55', 'Host 192.168.1.55 relative name in /24 zone is 4th octet');

$relIn16 = ipv4ToRelativePtr('172.16.5.10', '16.172.in-addr.arpa.');
assertEq($relIn16, '10.5', 'Host 172.16.5.10 relative name in /16 zone is 4th.3rd octets');

// Test 3: IPv6 arbitrary prefix lengths to reverse zone calculation
$v6_64 = ipv6PrefixToReverseZone('2001:db8:1234:5678::/64');
assertEq($v6_64, '8.7.6.5.4.3.2.1.8.b.d.0.1.0.0.2.ip6.arpa.', 'IPv6 /64 prefix has 16 reversed nibbles');

$v6_56 = ipv6PrefixToReverseZone('2001:db8:1234:5600::/56');
assertEq($v6_56, '6.5.4.3.2.1.8.b.d.0.1.0.0.2.ip6.arpa.', 'IPv6 /56 prefix has 14 reversed nibbles');

$v6_48 = ipv6PrefixToReverseZone('2001:db8:1234::/48');
assertEq($v6_48, '4.3.2.1.8.b.d.0.1.0.0.2.ip6.arpa.', 'IPv6 /48 prefix has 12 reversed nibbles');

$v6_32 = ipv6PrefixToReverseZone('2001:db8::/32');
assertEq($v6_32, '8.b.d.0.1.0.0.2.ip6.arpa.', 'IPv6 /32 prefix has 8 reversed nibbles');

// Test 4: Batch generation with target zone specification
$batch16 = generateIpv4SubnetPtrBatch(
    '172.16.0.0/16',
    'internal.net',
    'node-[OCTET4].[DOMAIN]',
    3600,
    1,
    3,
    '16.172.in-addr.arpa.'
);
assertEq(count($batch16), 3, 'Batch generates 3 records');
assertEq($batch16[0]['name'], '1.0', 'First host in /16 has relative name 1.0');
assertEq($batch16[0]['content'], 'node-1.internal.net.', 'First host canonical target content');

// Test 5: Preview batch generator
$preview = previewSubnetPtrBatch(
    'ipv4',
    '192.168.1.0/24',
    'example.com',
    '1.168.192.in-addr.arpa.',
    'host-[ID].[DOMAIN]',
    3600,
    1,
    50,
    5
);
assertEq($preview['total'], 50, 'Preview calculates total 50 records in offset range');
assertEq(count($preview['preview']), 5, 'Preview slices exactly 5 sample records');
assertEq($preview['preview'][0]['name'], '1', 'First sample record relative name');
assertEq($preview['preview'][0]['fqdn'], '1.1.168.192.in-addr.arpa.', 'First sample record full FQDN');
assertEq($preview['preview'][0]['content'], 'host-1.example.com.', 'First sample record content');

echo "All large rDNS subnet and preview tests passed successfully!\n";
