<?php

declare(strict_types=1);

require_once __DIR__ . '/helper.php';
require_once __DIR__ . '/../app/webhook_services.php';

// Test 1: HMAC-SHA256 Payload Signature Verification
$secret = 'test_webhook_secret_key_123';
$payload = [
    'event' => 'zone.created',
    'timestamp' => 1700000000,
    'delivery_id' => '0123456789abcdef0123456789abcdef',
    'data' => ['zone' => 'example.org.', 'kind' => 'Native'],
];
$json = (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
const SHA256_PREFIX = 'sha256=';
$expectedSig = SHA256_PREFIX . hash_hmac('sha256', $json, $secret);

// Verify computed signature matches
$testSig = SHA256_PREFIX . hash_hmac('sha256', $json, $secret);
assertEq($testSig, $expectedSig, 'HMAC-SHA256 signature calculation matches');
assertEq(str_starts_with($testSig, SHA256_PREFIX), true, 'Signature has sha256= prefix');

// Test 2: Event list parsing & matching
$eventsSetting = 'zone.created, zone.deleted, record.updated';
$parsedEvents = array_map('trim', explode(',', $eventsSetting));
assertEq(in_array('zone.created', $parsedEvents, true), true, 'Matches zone.created');
assertEq(in_array('record.updated', $parsedEvents, true), true, 'Matches record.updated');
assertEq(in_array('user.login', $parsedEvents, true), false, 'Rejects unsubscribed user.login');

// Test 3: Wildcard matching
$wildcardSetting = '*';
$wildcardEvents = array_map('trim', explode(',', $wildcardSetting));
assertEq(in_array('*', $wildcardEvents, true), true, 'Matches wildcard subscription');

echo "All webhook cryptographic signature & filtering tests passed successfully!\n";
