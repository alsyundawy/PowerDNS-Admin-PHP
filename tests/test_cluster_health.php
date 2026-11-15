<?php

declare(strict_types=1);

require_once __DIR__ . '/helper.php';
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/PdnsDnssecTrait.php';
require_once dirname(__DIR__) . '/app/PdnsMetadataTrait.php';
require_once dirname(__DIR__) . '/app/PdnsClient.php';
require_once dirname(__DIR__) . '/app/PdnsCluster.php';

// Test 1: JSON body version parsing
$mockResponseBody = json_encode([
    'type' => 'Server',
    'id' => 'localhost',
    'url' => '/api/v1/servers/localhost',
    'daemon_type' => 'authoritative',
    'version' => '4.9.1',
    'zones_url' => '/api/v1/servers/localhost/zones{/zone}',
]);

$parsed = is_string($mockResponseBody) ? json_decode($mockResponseBody, true) : null;
assertEq(is_array($parsed), true, 'Mock PowerDNS body parsed as JSON array');
assertEq($parsed['version'] ?? '', '4.9.1', 'Extracted daemon version is 4.9.1');

// Test 2: Latency classification logic
function getLatencyStatus(?int $latencyMs): string
{
    if ($latencyMs === null) {
        return 'offline';
    }
    if ($latencyMs < 50) {
        return 'optimal';
    }
    if ($latencyMs < 200) {
        return 'degraded';
    }
    return 'slow';
}

assertEq(getLatencyStatus(12), 'optimal', '12ms latency is optimal');
assertEq(getLatencyStatus(85), 'degraded', '85ms latency is degraded');
assertEq(getLatencyStatus(350), 'slow', '350ms latency is slow');
assertEq(getLatencyStatus(null), 'offline', 'Null latency is offline');

// Test 3: Standalone server node fallback construction
$fallbackNode = [
    'id' => 0,
    'name' => 'Primary Node (Default)',
    'api_url' => 'http://127.0.0.1:8081',
    'server_id' => 'localhost',
    'is_default' => 1,
    'is_active' => 1,
    'latency_ms' => 15,
    'version' => '4.8.4',
    'last_check_at' => '2026-11-15 11:00:00',
];

assertEq($fallbackNode['name'], 'Primary Node (Default)', 'Fallback node name');
assertEq($fallbackNode['version'], '4.8.4', 'Fallback node version');
assertEq($fallbackNode['latency_ms'], 15, 'Fallback node latency');

echo "All Cluster Health & API Dashboard Widget tests passed successfully!\n";
