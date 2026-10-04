<?php

declare(strict_types=1);

require_once __DIR__ . '/helper.php';
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/backup_services.php';

// Test 1: splitSqlStatements handles semicolons and comments correctly
$sampleSql = <<<SQL
-- This is a comment
SET FOREIGN_KEY_CHECKS=0;

/* Block comment with ; inside */
INSERT INTO `users` (`id`, `username`, `display_name`) VALUES (1, 'admin', 'Admin; User');

INSERT INTO `settings` (`name`, `value`) VALUES ('app_name', 'PowerDNS; \\'Admin\\'');

TRUNCATE TABLE `login_attempts`;
SQL;

$stmts = splitSqlStatements($sampleSql);
assertEq(count($stmts), 4, 'splitSqlStatements extracts exactly 4 statements');
assertEq($stmts[0], 'SET FOREIGN_KEY_CHECKS=0', 'First statement is SET FOREIGN_KEY_CHECKS');
assertEq(
    $stmts[1],
    "INSERT INTO `users` (`id`, `username`, `display_name`) VALUES (1, 'admin', 'Admin; User')",
    'Second statement preserves semicolon inside string literal'
);
assertEq(
    $stmts[2],
    "INSERT INTO `settings` (`name`, `value`) VALUES ('app_name', 'PowerDNS; \\'Admin\\'')",
    'Third statement preserves escaped quote and semicolon'
);
assertEq($stmts[3], 'TRUNCATE TABLE `login_attempts`', 'Fourth statement is TRUNCATE');

// Test 2: restoreDatabaseMetadata rejects forbidden SQL commands
$evilSql = "DROP DATABASE pdns_admin; SELECT * FROM users;";
$result = restoreDatabaseMetadata($evilSql);
assertEq($result['success'], false, 'restoreDatabaseMetadata rejects DROP DATABASE');
assertEq(
    str_contains((string) ($result['error'] ?? ''), 'not allowed'),
    true,
    'Error message identifies forbidden statement'
);

// Test 3: restoreConfigSettings validates payload structure
$invalidConfig = ['foo' => 'bar'];
$resInvalid = restoreConfigSettings($invalidConfig);
assertEq($resInvalid['success'], false, 'restoreConfigSettings rejects missing settings key');

// Test 4: splitSqlStatements handles empty input
$emptyStmts = splitSqlStatements("   \n\n  -- only comments \n");
assertEq(count($emptyStmts), 0, 'splitSqlStatements returns empty array for blank/comment-only SQL');

echo "All backup & restore services tests passed successfully!\n";
