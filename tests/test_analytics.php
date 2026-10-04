<?php

declare(strict_types=1);

require_once __DIR__ . '/helper.php';
require_once __DIR__ . '/../app/analytics.php';

// Test 1: Packet cache hit ratio math
assertEq(calculatePacketCacheRatio(80, 20), 80.0, '80 hits and 20 misses is 80.0%');
assertEq(calculatePacketCacheRatio(0, 0), 0.0, 'Zero hits and zero misses is 0.0%');
assertEq(calculatePacketCacheRatio(50, 50), 50.0, '50 hits and 50 misses is 50.0%');

// Test 2: Ring buffer aggregation
$sampleRing = [
    ['name' => 'example.com'],
    ['name' => 'example.com'],
    ['name' => 'example.com'],
    ['name' => 'test.org'],
    ['name' => 'test.org'],
    ['name' => 'api.dev'],
];
$aggregated = aggregateRingBuffer($sampleRing, 2);
assertEq(count($aggregated), 2, 'Top 2 items sliced correctly');
assertEq($aggregated[0]['item'], 'example.com', 'Most frequent item first');
assertEq($aggregated[0]['count'], 3, 'Frequency count matches');
assertEq($aggregated[0]['percentage'], 50.0, 'Percentage is 50.0%');
assertEq($aggregated[1]['item'], 'test.org', 'Second item matches');
assertEq($aggregated[1]['count'], 2, 'Second item count matches');

// Test 3: Client IP anonymization for privacy
assertEq(anonymizeClientIp('192.168.1.100'), '192.168.1.0/24', 'Anonymizes IPv4 to /24');
assertEq(anonymizeClientIp('2001:db8:85a3::8a2e:370:7334'), '2001:db8:85a3::/64', 'Anonymizes IPv6 to /64');

// Test 4: Pure vector SVG gauges
$donutSvg = renderSvgDonutGauge(87.5, 'Cache Hit', '#10b981', 180);
assertEq(str_starts_with($donutSvg, '<svg '), true, 'Donut gauge renders SVG start');
assertEq(str_contains($donutSvg, '87.5%'), true, 'Donut gauge displays percentage text');
assertEq(str_contains($donutSvg, '</svg>'), true, 'Donut gauge renders complete element');

$protoSvg = renderSvgProtocolRatio(900, 100);
assertEq(str_starts_with($protoSvg, '<svg '), true, 'Protocol ratio renders SVG start');
assertEq(str_contains($protoSvg, '</svg>'), true, 'Protocol ratio renders complete element');

echo "All DNS telemetry and analytics vector tests passed successfully!\n";
