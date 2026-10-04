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

echo "All rDNS services tests passed successfully!\n";
