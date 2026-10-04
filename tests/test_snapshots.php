<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/dns_name.php';
require_once __DIR__ . '/../app/services.php';
require_once __DIR__ . '/helper.php';

// Test diffSnapshotRrsets logic
$current = [
    [
        'name' => 'example.com.',
        'type' => 'SOA',
        'ttl' => 3600,
        'records' => [[
            'content' => 'ns1.example.com. hostmaster.example.com. 2026100401 10800 3600 604800 3600',
            'disabled' => false,
        ]],
    ],
    [
        'name' => 'example.com.',
        'type' => 'A',
        'ttl' => 300,
        'records' => [['content' => '1.2.3.4', 'disabled' => false]],
    ],
    [
        'name' => 'old.example.com.',
        'type' => 'A',
        'ttl' => 300,
        'records' => [['content' => '5.6.7.8', 'disabled' => false]],
    ],
];

$target = [
    [
        'name' => 'example.com.',
        'type' => 'SOA',
        'ttl' => 3600,
        'records' => [[
            'content' => 'ns1.example.com. hostmaster.example.com. 2026100402 10800 3600 604800 3600',
            'disabled' => false,
        ]],
    ],
    [
        'name' => 'example.com.',
        'type' => 'A',
        'ttl' => 300,
        'records' => [['content' => '1.2.3.5', 'disabled' => false]],
    ],
    [
        'name' => 'new.example.com.',
        'type' => 'CNAME',
        'ttl' => 300,
        'records' => [['content' => 'example.com.', 'disabled' => false]],
    ],
];

$patch = diffSnapshotRrsets($current, $target);

// We expect:
// 1. DELETE old.example.com.|A
// 2. REPLACE example.com.|SOA
// 3. REPLACE example.com.|A
// 4. REPLACE new.example.com.|CNAME
// Note: SOA is never deleted even if missing from target, but here it's in target so it's replaced.

assertEq(count($patch), 4, 'Patch contains 1 DELETE and 3 REPLACE items');
assertEq($patch[0]['changetype'], 'DELETE', 'First action is DELETE');
assertEq($patch[0]['name'], 'old.example.com.', 'Deleted record is old.example.com.');
assertEq($patch[0]['type'], 'A', 'Deleted record type is A');

assertEq($patch[1]['changetype'], 'REPLACE', 'Second action is REPLACE');
assertEq($patch[1]['name'], 'example.com.', 'Replaced SOA name');
assertEq($patch[1]['type'], 'SOA', 'Replaced SOA type');

assertEq($patch[3]['changetype'], 'REPLACE', 'Fourth action is REPLACE');
assertEq($patch[3]['name'], 'new.example.com.', 'New record name');
assertEq($patch[3]['type'], 'CNAME', 'New record type');

echo "All snapshot diff tests passed successfully!\n";
