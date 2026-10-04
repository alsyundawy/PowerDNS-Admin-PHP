<?php

declare(strict_types=1);

require_once __DIR__ . '/helper.php';
require_once dirname(__DIR__) . '/app/bootstrap.php';

echo "Running tests/test_logger.php...\n";

// Test 1: Recursive Sensitive Data Masking
$input = [
    'username' => 'admin_user',
    'password' => 'SuperSecret123!',
    'api_key' => 'abcdef-123456-secret',
    'auth_token' => 'jwt.token.here',
    'nested' => [
        'totp_secret' => 'JBSWY3DPEHPK3PXP',
        'safe_data' => 'keep_this',
        'cookie' => 'PHPSESSID=12345',
    ],
    'normal_field' => 'visible_value',
];

$redacted = appRedactSensitive($input);

assertEq($redacted['username'], 'admin_user', 'Username preserved');
assertEq($redacted['password'], '[REDACTED]', 'Password redacted');
assertEq($redacted['api_key'], '[REDACTED]', 'API key redacted');
assertEq($redacted['auth_token'], '[REDACTED]', 'Auth token redacted');
assertEq($redacted['nested']['totp_secret'], '[REDACTED]', 'Nested totp_secret redacted');
assertEq($redacted['nested']['cookie'], '[REDACTED]', 'Nested cookie redacted');
assertEq($redacted['nested']['safe_data'], 'keep_this', 'Nested safe data preserved');
assertEq($redacted['normal_field'], 'visible_value', 'Normal field preserved');

// Test 2: Custom Logger Handler Capture
$captured = [];
setCustomLoggerHandler(function (array $record) use (&$captured): void {
    $captured[] = $record;
});

appLogger('security', 'warning', 'Failed login attempt', [
    'username' => 'attacker',
    'password' => 'injected_pass',
]);

assertEq(count($captured), 1, 'Logger captured 1 event');
assertEq($captured[0]['channel'], 'security', 'Channel is security');
assertEq($captured[0]['level'], 'WARNING', 'Level is WARNING');
assertEq($captured[0]['message'], 'Failed login attempt', 'Message matches');
assertEq($captured[0]['context']['password'], '[REDACTED]', 'Context password redacted');

// Test 3: Channel helper shortcuts
logAuth('User logged in successfully', ['username' => 'john_doe']);
assertEq(count($captured), 2, 'Auth event captured');
assertEq($captured[1]['channel'], 'auth', 'Channel is auth');

logApi('Zone list queried', ['limit' => 50]);
assertEq(count($captured), 3, 'API event captured');
assertEq($captured[2]['channel'], 'api', 'Channel is api');

logPdns('AXFR requested', ['zone' => 'example.com.']);
assertEq(count($captured), 4, 'PDNS event captured');
assertEq($captured[3]['channel'], 'pdns_api', 'Channel is pdns_api');

// Test 4: Invalid channel falls back to 'application'
appLogger('unknown_bogus_channel', 'info', 'Generic message');
assertEq(count($captured), 5, 'Fallback event captured');
assertEq($captured[4]['channel'], 'application', 'Invalid channel normalized to application');

// Clean up handler
setCustomLoggerHandler(null);

echo "All Structured Logging & Data Masking tests passed successfully!\n";
