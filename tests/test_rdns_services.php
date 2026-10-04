<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/dns_name.php';
require_once __DIR__ . '/../app/services.php';
require_once __DIR__ . '/helper.php';

// 1. Test IPv4 Batch Generator
$ipv4Batch = generateIpv4SubnetPtrBatch('192.0.2.0/24', 'example.com', 'host-[ID].[DOMAIN]', 3600, 1, 3);
assertEq(count($ipv4Batch), 3, 'IPv4 batch produces 3 records for range 1..3');
assertEq($ipv4Batch[0]['name'], '1', 'First record name is 1');
assertEq($ipv4Batch[0]['content'], 'host-1.example.com.', 'First record content is host-1.example.com.');
assertEq($ipv4Batch[2]['name'], '3', 'Third record name is 3');
assertEq($ipv4Batch[2]['content'], 'host-3.example.com.', 'Third record content is host-3.example.com.');

// 2. Test IPv6 Batch Generator
$ipv6Batch = generateIpv6SubnetPtrBatch('2001:db8:1234:5678::/64', 'example.com', 'ipv6-[HEX].[DOMAIN]', 3600, 1, 2);
assertEq(count($ipv6Batch), 2, 'IPv6 batch produces 2 records for range 1..2');
assertEq($ipv6Batch[0]['name'], '1.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0', 'First IPv6 record relative nibble name');
assertEq($ipv6Batch[0]['content'], 'ipv6-1.example.com.', 'First IPv6 record content');
assertEq($ipv6Batch[1]['name'], '2.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0', 'Second IPv6 record relative nibble name');
assertEq($ipv6Batch[1]['content'], 'ipv6-2.example.com.', 'Second IPv6 record content');

// 3. Test IPv4 Batch Generator with [IP] and [OCTET4] macros
$pattern = 'ptr-[IP_DASH]-[OCTET4].[DOMAIN]';
$ipv4MacroBatch = generateIpv4SubnetPtrBatch('192.0.2.0/24', 'example.com', $pattern, 1800, 10, 11);
assertEq(count($ipv4MacroBatch), 2, 'IPv4 batch produces 2 records for 10..11');
assertEq(
    $ipv4MacroBatch[0]['content'],
    'ptr-192-0-2-10-10.example.com.',
    'IPv4 [IP_DASH] and [OCTET4] macro expansion'
);
assertEq(
    $ipv4MacroBatch[1]['content'],
    'ptr-192-0-2-11-11.example.com.',
    'IPv4 second item macro expansion'
);

// 4. Test DNS Record Validation for All Supported Types
assertEq(validateRecord('ALIAS', 'lb.example.com.'), null, 'Valid ALIAS record');
assertEq(validateRecord('DNAME', 'target.example.org.'), null, 'Valid DNAME record');
assertEq(validateRecord('DNSKEY', '257 3 13 mdsswDd8EkJWqa9Qn...'), null, 'Valid DNSKEY record');
$cdsDigest = '2371 13 2 4607DC3EC93F032C495F8D34A7B884DA39E5F33334F9E7C6E901C56841D0FF9D';
assertEq(validateRecord('CDS', $cdsDigest), null, 'Valid CDS record');
assertEq(validateRecord('CDNSKEY', '257 3 13 mdsswDd8EkJWqa9Qn...'), null, 'Valid CDNSKEY record');
$tlsaContent = '3 1 1 d2abde240d7cd3ee6b4b28c54df034b97983a132eef3429d8b2ce2532d2952ac';
assertEq(validateRecord('TLSA', $tlsaContent), null, 'Valid TLSA record');
assertEq(validateRecord('SSHFP', '2 1 123456789abcdef67890123456789abcdef67890'), null, 'Valid SSHFP record');
assertEq(validateRecord('URI', '10 1 "https://example.com/api"'), null, 'Valid URI record');
assertEq(validateRecord('CERT', '1 0 0 m359...'), null, 'Valid CERT record');
assertEq(validateRecord('CSYNC', '66 3 A AAAA'), null, 'Valid CSYNC record');
assertEq(validateRecord('HINFO', '"Intel Core" "Linux"'), null, 'Valid HINFO record');
assertEq(validateRecord('RP', 'admin.example.com. info.example.com.'), null, 'Valid RP record');
assertEq(validateRecord('INVALID_TYPE', 'foo'), 'Record type is not allowed.', 'Unknown record type rejected');

echo "All rDNS services and record types validation tests passed successfully!\n";
