<?php

declare(strict_types=1);

require_once __DIR__ . '/helper.php';
require_once __DIR__ . '/../app/dns_name.php';
require_once __DIR__ . '/../app/cache.php';

// Test 1: Basic Get, Set, Delete
cacheFlush();
assertEq(cacheGet('test_key'), null, 'Get non-existent key returns null');
assertEq(cacheSet('test_key', 'hello_powerdns', 30), true, 'Set cache key with TTL');
assertEq(cacheGet('test_key'), 'hello_powerdns', 'Get cached key value');

assertEq(cacheDelete('test_key'), true, 'Delete cache key');
assertEq(cacheGet('test_key'), null, 'Get deleted key returns null');

// Test 2: Cache Remember
$calls = 0;
$result = cacheRemember('remember_key', 30, function () use (&$calls) {
    $calls++;
    return ['zones' => 42];
});
assertEq($result, ['zones' => 42], 'Remember executes callback and returns data');
assertEq($calls, 1, 'Callback executed once');

$secondResult = cacheRemember('remember_key', 30, function () use (&$calls) {
    $calls++;
    return ['zones' => 999];
});
assertEq($secondResult, ['zones' => 42], 'Remember returns cached result on second call');
assertEq($calls, 1, 'Callback not re-executed while cached');

// Test 3: Prefix Flush
cacheSet('pdns:zones:srv1', ['zone1'], 60);
cacheSet('pdns:zones:srv2', ['zone2'], 60);
cacheSet('other:key', 'keep_me', 60);

assertEq(cacheFlush('pdns:zones:'), true, 'Flush by prefix');
assertEq(cacheGet('pdns:zones:srv1'), null, 'Prefixed key 1 flushed');
assertEq(cacheGet('pdns:zones:srv2'), null, 'Prefixed key 2 flushed');
assertEq(cacheGet('other:key'), 'keep_me', 'Non-prefixed key preserved');

echo "All cache adapter tests passed successfully!\n";
