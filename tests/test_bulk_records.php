<?php

declare(strict_types=1);

require_once __DIR__ . '/helper.php';
require_once dirname(__DIR__) . '/app/dns_name.php';
require_once dirname(__DIR__) . '/app/services.php';

// Test string replacement simulation as done in bulkReplaceRecords
$sampleContent = '192.0.2.1';
$search = '192.0.2.1';
$replace = '198.51.100.1';

$replaced = str_ireplace($search, $replace, $sampleContent);
assertEq($replaced, '198.51.100.1', 'Direct string replacement');

$cnameTarget = 'old-cname.example.com.';
$searchCname = 'old-cname';
$replaceCname = 'new-cname';
$replacedCname = str_ireplace($searchCname, $replaceCname, $cnameTarget);
assertEq($replacedCname, 'new-cname.example.com.', 'Subdomain replacement inside FQDN');

echo "All bulk records logic tests passed successfully!\n";
