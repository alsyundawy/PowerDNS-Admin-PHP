<?php

declare(strict_types=1);

require_once __DIR__ . '/helper.php';
require_once dirname(__DIR__) . '/app/bootstrap.php';

// Test 1: CSV stream generation formatting
$sampleRows = [
    [
        'id' => 101,
        'created_at' => '2026-11-15 10:00:00',
        'username' => 'admin_user',
        'action' => 'create-zone',
        'zone_name' => 'example.com',
        'detail' => 'Created Native zone with 2 nameservers',
        'ip' => '192.0.2.1',
    ],
    [
        'id' => 102,
        'created_at' => '2026-11-15 10:05:00',
        'username' => 'operator_user',
        'action' => 'update-records',
        'zone_name' => 'example.com',
        'detail' => 'Added record "mail, MX, 10 mail.example.com."',
        'ip' => '198.51.100.25',
    ],
    [
        'id' => 103,
        'created_at' => '2026-11-15 10:10:00',
        'username' => '',
        'action' => 'sync',
        'zone_name' => '',
        'detail' => 'Automated background sync completed',
        'ip' => '127.0.0.1',
    ],
];

// Helper to render CSV to memory stream
function renderAuditCsv(array $rows): string
{
    $fp = fopen('php://memory', 'r+');
    if ($fp === false) {
        return '';
    }
    fputcsv($fp, ['ID', 'Timestamp', 'User', 'Action', 'Zone', 'Detail', 'IP Address'], ',', '"', '\\');
    foreach ($rows as $r) {
        fputcsv($fp, [
            (string) ($r['id'] ?? ''),
            (string) ($r['created_at'] ?? ''),
            (string) ($r['username'] ?: 'system'),
            (string) ($r['action'] ?? ''),
            (string) ($r['zone_name'] ?? ''),
            (string) ($r['detail'] ?? ''),
            (string) ($r['ip'] ?? ''),
        ], ',', '"', '\\');
    }
    rewind($fp);
    $csv = stream_get_contents($fp);
    fclose($fp);
    return is_string($csv) ? $csv : '';
}

$csv = renderAuditCsv($sampleRows);
$csvLines = explode("\n", trim($csv));

assertEq(count($csvLines), 4, 'CSV contains header + 3 data rows');
assertEq(str_starts_with($csvLines[0], 'ID,Timestamp,User,Action'), true, 'CSV header contains expected columns');
assertEq(str_contains($csvLines[1], 'admin_user,create-zone,example.com'), true, 'CSV row 1 format matches');
assertEq(str_contains($csvLines[2], 'operator_user,update-records'), true, 'CSV row 2 format matches');
assertEq(str_contains($csvLines[3], 'system,sync'), true, 'Blank username falls back to system');

// Test 2: JSON generation formatting
$json = json_encode($sampleRows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
assertEq(is_string($json), true, 'JSON export successfully encodes');
$decoded = json_decode((string) $json, true);
assertEq(is_array($decoded), true, 'JSON export valid array');
assertEq(count($decoded), 3, 'JSON export contains 3 items');
assertEq($decoded[0]['action'], 'create-zone', 'JSON first element action matches');
assertEq($decoded[1]['ip'], '198.51.100.25', 'JSON second element IP matches');

// Test 3: RBAC verification for audit log export
function canExportAudit(array $user): bool
{
    return in_array($user['role'] ?? '', ['admin', 'operator'], true);
}

assertEq(canExportAudit(['role' => 'admin']), true, 'Admin can export audit log');
assertEq(canExportAudit(['role' => 'operator']), true, 'Operator can export audit log');
assertEq(canExportAudit(['role' => 'user']), false, 'Standard user cannot export audit log');
assertEq(canExportAudit([]), false, 'Anonymous user cannot export audit log');

echo "All exportable audit log (CSV/JSON/RBAC) tests passed successfully!\n";
