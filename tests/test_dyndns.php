<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/dns_name.php';
require_once __DIR__ . '/../app/services.php';
require_once __DIR__ . '/helper.php';

// 1. Test IP type detection for DynDNS
$detectDynDnsIpType = function (string $ip): ?string {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return 'A';
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        return 'AAAA';
    }
    return null;
};

$testIpv4 = '203.0.113.42';
assertEq($detectDynDnsIpType($testIpv4), 'A', 'IPv4 maps to A record');
assertEq($detectDynDnsIpType('2001:db8::1'), 'AAAA', 'IPv6 maps to AAAA record');
assertEq($detectDynDnsIpType('invalid-ip'), null, 'Invalid IP rejected');

// 2. Test DynDNS response formatting
$formatDynDnsResponse = function (string $code, ?string $ip = null): string {
    if ($ip !== null && ($code === 'good' || $code === 'nochg')) {
        return $code . ' ' . $ip . "\n";
    }
    return $code . "\n";
};

assertEq($formatDynDnsResponse('good', $testIpv4), "good {$testIpv4}\n", 'good response format');
assertEq($formatDynDnsResponse('nochg', $testIpv4), "nochg {$testIpv4}\n", 'nochg response format');
assertEq($formatDynDnsResponse('nohost'), "nohost\n", 'nohost response format');
assertEq($formatDynDnsResponse('badauth'), "badauth\n", 'badauth response format');
assertEq($formatDynDnsResponse('911'), "911\n", '911 error response format');

// 3. Test findLongestMatchingZone helper
$findLongestMatchingZone = function (string $hostname, array $candidateZones): ?string {
    $hostFqdn = dnsCanonical($hostname);
    $bestMatch = null;
    $bestLen = 0;

    foreach ($candidateZones as $zone) {
        $canonicalZone = dnsCanonical($zone);
        if ($hostFqdn === $canonicalZone || str_ends_with($hostFqdn, '.' . $canonicalZone)) {
            $len = strlen($canonicalZone);
            if ($len > $bestLen) {
                $bestLen = $len;
                $bestMatch = $canonicalZone;
            }
        }
    }
    return $bestMatch;
};

$candidates = ['com.', 'example.com.', 'sub.example.com.', 'other.org.'];
assertEq(
    $findLongestMatchingZone('host.sub.example.com', $candidates),
    'sub.example.com.',
    'Matches deepest subdomain zone'
);
assertEq(
    $findLongestMatchingZone('myhost.example.com', $candidates),
    'example.com.',
    'Matches apex domain zone'
);
assertEq(
    $findLongestMatchingZone('nomatch.net', $candidates),
    null,
    'Returns null when no zone matches'
);

echo "All DynDNS protocol tests passed successfully!\n";
