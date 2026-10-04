<?php

declare(strict_types=1);

require_once __DIR__ . '/helper.php';
require_once __DIR__ . '/../app/dns_name.php';
require_once __DIR__ . '/../app/zone_linter.php';

// Test 1: Apex CNAME Conflict
$zone = 'example.com.';
$soaContent = 'ns1.example.com. admin.example.com. 1 10800 3600 604800 3600';
$rrsetsWithApexCname = [
    ['name' => 'example.com.', 'type' => 'SOA', 'records' => [['content' => $soaContent]]],
    ['name' => 'example.com.', 'type' => 'NS', 'records' => [['content' => 'ns1.example.com.']]],
    ['name' => 'example.com.', 'type' => 'CNAME', 'records' => [['content' => 'ghs.googlehosted.com.']]],
];
$issues = lintZoneRrsets($zone, $rrsetsWithApexCname);
$hasApexCname = false;
foreach ($issues as $iss) {
    if ($iss['code'] === 'RFC-1912-APEX-CNAME') {
        $hasApexCname = true;
    }
}
assertEq($hasApexCname, true, 'Detects apex CNAME conflict');

// Test 2: Missing Internal Glue Record for NS
$rrsetsMissingGlue = [
    ['name' => 'example.com.', 'type' => 'SOA', 'records' => [['content' => $soaContent]]],
    ['name' => 'example.com.', 'type' => 'NS', 'records' => [['content' => 'ns1.example.com.']]],
    // No A or AAAA record for ns1.example.com.
];
$glueIssues = lintZoneRrsets($zone, $rrsetsMissingGlue);
assertEq(count($glueIssues), 1, 'Detects missing glue record for internal nameserver');
assertEq($glueIssues[0]['code'], 'RFC-1035-MISSING-GLUE', 'Missing glue RFC code');

// Test 3: MX pointing to CNAME
$rrsetsMxCname = [
    ['name' => 'example.com.', 'type' => 'SOA', 'records' => [['content' => $soaContent]]],
    ['name' => 'example.com.', 'type' => 'MX', 'records' => [['content' => '10 mail.example.com.']]],
    ['name' => 'mail.example.com.', 'type' => 'CNAME', 'records' => [['content' => 'smtp.google.com.']]],
];
$mxIssues = lintZoneRrsets($zone, $rrsetsMxCname);
$hasMxCname = false;
foreach ($mxIssues as $iss) {
    if ($iss['code'] === 'RFC-2181-MX-CNAME') {
        $hasMxCname = true;
    }
}
assertEq($hasMxCname, true, 'Detects MX pointing to CNAME');

// Test 4: Clean Zone without violations
$cleanRrsets = [
    ['name' => 'example.com.', 'type' => 'SOA', 'records' => [['content' => $soaContent]]],
    ['name' => 'example.com.', 'type' => 'NS', 'records' => [['content' => 'ns1.example.com.']]],
    ['name' => 'ns1.example.com.', 'type' => 'A', 'records' => [['content' => '192.0.2.1']]],
    ['name' => 'example.com.', 'type' => 'A', 'records' => [['content' => '192.0.2.10']]],
    ['name' => 'www.example.com.', 'type' => 'CNAME', 'records' => [['content' => 'example.com.']]],
];
$cleanIssues = lintZoneRrsets($zone, $cleanRrsets);
assertEq(count($cleanIssues), 0, 'Clean zone produces zero RFC warnings');

echo "All Zone RFC Compliance & Linting Engine tests passed successfully!\n";
