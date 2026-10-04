<?php

declare(strict_types=1);

require_once __DIR__ . '/helper.php';
require_once dirname(__DIR__) . '/app/network_tools.php';

const DOC_NET_IPV6 = '2001:db8::';

// Test 1: IPv4 Calculation Standard /24
$res1 = ipcalcProcessIpv4('192.168.1.50/24');
assertEq(is_array($res1), true, 'ipcalcProcessIpv4 returns array for 192.168.1.50/24');
if ($res1 !== null) {
    assertEq($res1['network'], '192.168.1.0', 'IPv4 network calculation');
    assertEq($res1['broadcast'], '192.168.1.255', 'IPv4 broadcast calculation');
    assertEq($res1['first_usable'], '192.168.1.1', 'IPv4 first usable calculation');
    assertEq($res1['last_usable'], '192.168.1.254', 'IPv4 last usable calculation');
    assertEq($res1['total_hosts'], 256, 'IPv4 total hosts for /24');
    assertEq($res1['usable_hosts'], 254, 'IPv4 usable hosts for /24');
    assertEq($res1['class'], 'Class C', 'IPv4 class detection');
    assertEq($res1['scope'], 'Private (RFC 1918)', 'IPv4 scope detection RFC 1918');
    assertEq($res1['reverse_dns'], '50.1.168.192.in-addr.arpa.', 'IPv4 reverse DNS pointer');
}

// Test 2: IPv4 /32 Single Host
$res2 = ipcalcProcessIpv4('10.20.30.40/32');
assertEq(is_array($res2), true, 'ipcalcProcessIpv4 returns array for /32');
if ($res2 !== null) {
    assertEq($res2['total_hosts'], 1, 'IPv4 /32 total hosts');
    assertEq($res2['usable_hosts'], 1, 'IPv4 /32 usable hosts');
    assertEq($res2['first_usable'], '10.20.30.40', 'IPv4 /32 first usable');
    assertEq($res2['last_usable'], '10.20.30.40', 'IPv4 /32 last usable');
}

// Test 3: IPv4 /31 Point-to-Point (RFC 3021)
$res3 = ipcalcProcessIpv4('192.0.2.0/31');
assertEq(is_array($res3), true, 'ipcalcProcessIpv4 returns array for /31');
if ($res3 !== null) {
    assertEq($res3['total_hosts'], 2, 'IPv4 /31 total hosts');
    assertEq($res3['usable_hosts'], 2, 'IPv4 /31 usable hosts');
    assertEq($res3['first_usable'], '192.0.2.0', 'IPv4 /31 first usable');
    assertEq($res3['last_usable'], '192.0.2.1', 'IPv4 /31 last usable');
}

// Test 4: Invalid IPv4 CIDR strings
assertEq(ipcalcProcessIpv4('999.0.0.1/24'), null, 'ipcalcProcessIpv4 rejects invalid IP');
assertEq(ipcalcProcessIpv4('192.168.1.1/35'), null, 'ipcalcProcessIpv4 rejects mask > 32');
assertEq(ipcalcProcessIpv4('not-an-ip'), null, 'ipcalcProcessIpv4 rejects garbage');

// Test 5: IPv6 Calculation Standard /64
$res6_1 = ipcalcProcessIpv6(DOC_NET_IPV6 . '1/64');
assertEq(is_array($res6_1), true, 'ipcalcProcessIpv6 returns array for 2001:db8::1/64');
if ($res6_1 !== null) {
    assertEq($res6_1['uncompressed'], '2001:0db8:0000:0000:0000:0000:0000:0001', 'IPv6 uncompressed expansion');
    assertEq($res6_1['compressed'], '2001:db8::1', 'IPv6 compressed string');
    assertEq($res6_1['network'], DOC_NET_IPV6, 'IPv6 network address');
    assertEq($res6_1['mask'], 64, 'IPv6 mask integer');
    assertEq($res6_1['subnets_slash_64'], '1', 'IPv6 /64 has 1 subnet of size /64');
    assertEq($res6_1['scope'], 'Documentation (RFC 3849)', 'IPv6 scope detection for 2001:db8::');
    assertEq(str_ends_with($res6_1['reverse_dns'], '.ip6.arpa.'), true, 'IPv6 reverse DNS suffix');
}

// Test 6: IPv6 /32 Calculation
$res6_2 = ipcalcProcessIpv6(DOC_NET_IPV6 . '/32');
if ($res6_2 !== null) {
    assertEq($res6_2['subnets_slash_64'], '4,294,967,296', 'IPv6 /32 has 4,294,967,296 /64 subnets');
}

// Test 7: IPv6 Scopes (Loopback & Link-local)
$resLoopback = ipcalcProcessIpv6('::1/128');
if ($resLoopback !== null) {
    assertEq($resLoopback['scope'], 'Loopback (RFC 4291)', 'IPv6 loopback scope');
}

$resLinkLocal = ipcalcProcessIpv6('fe80::1/64');
if ($resLinkLocal !== null) {
    assertEq($resLinkLocal['scope'], 'Link-Local Unicast (RFC 4291)', 'IPv6 link-local scope');
}

// Test 8: IPv6 Splitter Validation
$val1 = ipv6splitValidate(DOC_NET_IPV6 . '/32');
assertEq(is_array($val1), true, 'ipv6splitValidate accepts 2001:db8::/32');
if ($val1 !== null) {
    assertEq($val1['ip'], DOC_NET_IPV6, 'ipv6splitValidate normalized base IP');
    assertEq($val1['mask'], 32, 'ipv6splitValidate extracted mask');
}

assertEq(ipv6splitValidate('invalid-ipv6'), null, 'ipv6splitValidate rejects invalid string');
assertEq(ipv6splitValidate(DOC_NET_IPV6 . '/129'), null, 'ipv6splitValidate rejects mask > 128');

// Test 9: IPv6 Generator (/32 to /36 produces 16 subnets)
$subnets = [];
foreach (ipv6splitGenerate(DOC_NET_IPV6, 32, 36) as $sub) {
    $subnets[] = $sub;
}
assertEq(count($subnets), 16, 'IPv6 split /32 to /36 yields exactly 16 subnets');
assertEq($subnets[0], '2001:db8::/36', 'First subnet /36');
assertEq($subnets[1], '2001:db8:1000::/36', 'Second subnet /36');
assertEq($subnets[15], '2001:db8:f000::/36', 'Last (16th) subnet /36');

// Test 10: IPv6 Generator (/48 to /50 produces 4 subnets)
$subnets50 = [];
foreach (ipv6splitGenerate('2001:db8:cafe::', 48, 50) as $sub) {
    $subnets50[] = $sub;
}
assertEq(count($subnets50), 4, 'IPv6 split /48 to /50 yields exactly 4 subnets');
assertEq($subnets50[0], '2001:db8:cafe::/50', 'First subnet /50');
assertEq($subnets50[1], '2001:db8:cafe:4000::/50', 'Second subnet /50');
assertEq($subnets50[2], '2001:db8:cafe:8000::/50', 'Third subnet /50');
assertEq($subnets50[3], '2001:db8:cafe:c000::/50', 'Fourth subnet /50');

// Test 11: processDnsRecords Parser
$rawMockRecords = [
    ['host' => 'example.com', 'type' => 'A', 'ttl' => 300, 'ip' => '93.184.216.34'],
    ['host' => 'example.com', 'type' => 'AAAA', 'ttl' => 300, 'ipv6' => '2606:2800:220:1:248:1893:25c8:1946'],
    ['host' => 'example.com', 'type' => 'MX', 'ttl' => 3600, 'target' => 'mail.example.com', 'pri' => 10],
    ['host' => 'example.com', 'type' => 'TXT', 'ttl' => 3600, 'txt' => 'v=spf1 -all'],
];

$parsedDns = [];
$parsedDns = array_merge($parsedDns, processDnsRecords('A', [$rawMockRecords[0]]));
$parsedDns = array_merge($parsedDns, processDnsRecords('AAAA', [$rawMockRecords[1]]));
$parsedDns = array_merge($parsedDns, processDnsRecords('MX', [$rawMockRecords[2]]));
$parsedDns = array_merge($parsedDns, processDnsRecords('TXT', [$rawMockRecords[3]]));

assertEq(count($parsedDns), 4, 'processDnsRecords processes 4 records');
assertEq($parsedDns[0]['value'], '93.184.216.34', 'A record value');
assertEq($parsedDns[1]['value'], '2606:2800:220:1:248:1893:25c8:1946', 'AAAA record value');
assertEq($parsedDns[2]['value'], '10 mail.example.com', 'MX record value');
assertEq($parsedDns[3]['value'], 'v=spf1 -all', 'TXT record value');

echo "All Network Tools tests passed successfully!\n";
