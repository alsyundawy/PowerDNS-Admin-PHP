<?php

declare(strict_types=1);

require_once __DIR__ . '/helper.php';
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/backup_services.php';

// Test 1: Password hashing and verification
$plainPassword = 'TestPwd_' . bin2hex(random_bytes(8)) . '!Aa1';
$hash = password_hash($plainPassword, PASSWORD_ARGON2ID);
assertEq(password_verify($plainPassword, $hash), true, 'password_verify succeeds with correct password');
assertEq(password_verify('wrongpassword', $hash), false, 'password_verify rejects incorrect password');

// Test 2: Avatar upload validation - missing file error handling
$fakeFileError = [
    'error' => UPLOAD_ERR_NO_FILE,
    'size' => 0,
    'tmp_name' => '',
];
$resNoFile = saveUserAvatar($fakeFileError, 1);
assertEq($resNoFile['ok'], false, 'saveUserAvatar rejects UPLOAD_ERR_NO_FILE');

// Test 3: Avatar upload validation - file size exceeding 2MB
$fakeFileTooLarge = [
    'error' => UPLOAD_ERR_OK,
    'size' => 3 * 1024 * 1024,
    'tmp_name' => '/tmp/huge.png',
];
$resTooLarge = saveUserAvatar($fakeFileTooLarge, 1);
assertEq($resTooLarge['ok'], false, 'saveUserAvatar rejects file > 2MB');

// Test 4: Branding helpers default values
assertEq(appName(), 'PowerDNS Admin', 'appName returns default PowerDNS Admin if not set');
assertEq(
    str_contains(appFooterText(), 'PowerDNS-Admin-PHP'),
    true,
    'appFooterText returns default footer text'
);

echo "All profile & avatar validation tests passed successfully!\n";
