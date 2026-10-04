<?php

/**
 * Backup and restore services for PowerDNS-Admin-PHP.
 * Handles database metadata SQL dump, settings JSON export, and PowerDNS zones snapshot.
 */

declare(strict_types=1);

const APP_METADATA_TABLES = [
    'users',
    'accounts',
    'account_user',
    'zones',
    'zone_user',
    'templates',
    'template_records',
    'api_keys',
    'api_key_zone',
    'history',
    'settings',
    'login_attempts',
    'zone_snapshots',
];

/**
 * Split raw SQL text into separate executable statements while respecting quotes and comments.
 *
 * @return list<string>
 */
function splitSqlStatements(string $sql): array
{
    $stmts = [];
    $len = strlen($sql);
    $buf = '';
    $inSingle = false;
    $inDouble = false;
    $inBacktick = false;

    for ($i = 0; $i < $len; $i++) {
        $c = $sql[$i];

        // Handle line comment --
        if (!$inSingle && !$inDouble && !$inBacktick && $c === '-' && isset($sql[$i + 1]) && $sql[$i + 1] === '-') {
            $nl = strpos($sql, "\n", $i);
            if ($nl === false) {
                break;
            }
            $i = $nl;
            continue;
        }

        // Handle block comment /* ... */
        if (!$inSingle && !$inDouble && !$inBacktick && $c === '/' && isset($sql[$i + 1]) && $sql[$i + 1] === '*') {
            $end = strpos($sql, '*/', $i + 2);
            if ($end === false) {
                break;
            }
            $i = $end + 1;
            continue;
        }

        if ($c === "'" && !$inDouble && !$inBacktick) {
            $escapeCount = 0;
            for ($k = $i - 1; $k >= 0 && $sql[$k] === '\\'; $k--) {
                $escapeCount++;
            }
            if ($escapeCount % 2 === 0) {
                $inSingle = !$inSingle;
            }
        } elseif ($c === '"' && !$inSingle && !$inBacktick) {
            $escapeCount = 0;
            for ($k = $i - 1; $k >= 0 && $sql[$k] === '\\'; $k--) {
                $escapeCount++;
            }
            if ($escapeCount % 2 === 0) {
                $inDouble = !$inDouble;
            }
        } elseif ($c === '`' && !$inSingle && !$inDouble) {
            $inBacktick = !$inBacktick;
        } elseif ($c === ';' && !$inSingle && !$inDouble && !$inBacktick) {
            $trimmed = trim($buf);
            if ($trimmed !== '') {
                $stmts[] = $trimmed;
            }
            $buf = '';
            continue;
        }

        $buf .= $c;
    }

    $trimmed = trim($buf);
    if ($trimmed !== '') {
        $stmts[] = $trimmed;
    }

    return $stmts;
}

/**
 * Generate a complete SQL dump of application metadata tables.
 */
function backupDatabaseMetadata(): string
{
    $pdo = db();
    $out = "-- PowerDNS-Admin-PHP Database Metadata Dump\n";
    $out .= "-- Version: 0.2.1\n";
    $out .= "-- Generated: " . gmdate('Y-m-d H:i:s') . " UTC\n";
    $out .= "-- --------------------------------------------------------\n\n";
    $out .= "SET FOREIGN_KEY_CHECKS=0;\n";
    $out .= "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n";

    foreach (APP_METADATA_TABLES as $table) {
        $st = $pdo->query("SELECT * FROM `{$table}`");
        if (!$st) {
            continue;
        }
        /** @var list<array<string, mixed>> $rows */
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        $count = count($rows);
        $out .= "-- Table: `{$table}` ({$count} rows)\n";
        $out .= "TRUNCATE TABLE `{$table}`;\n";
        if ($count === 0) {
            $out .= "\n";
            continue;
        }

        $cols = array_keys($rows[0]);
        $escapedCols = array_map(static fn (string $c): string => "`{$c}`", $cols);
        $colList = implode(', ', $escapedCols);

        $chunkSize = 100;
        $chunks = array_chunk($rows, $chunkSize);
        foreach ($chunks as $chunk) {
            $valueClauses = [];
            foreach ($chunk as $row) {
                $rowValues = [];
                foreach ($cols as $col) {
                    $val = $row[$col] ?? null;
                    if ($val === null) {
                        $rowValues[] = 'NULL';
                    } elseif (is_int($val) || is_float($val)) {
                        $rowValues[] = (string) $val;
                    } else {
                        $rowValues[] = $pdo->quote((string) $val);
                    }
                }
                $valueClauses[] = '(' . implode(', ', $rowValues) . ')';
            }
            $out .= "INSERT INTO `{$table}` ({$colList}) VALUES\n  " . implode(",\n  ", $valueClauses) . ";\n";
        }
        $out .= "\n";
    }

    $out .= "SET FOREIGN_KEY_CHECKS=1;\n";
    return $out;
}

/**
 * Execute a metadata SQL dump in a single transaction with validation.
 *
 * @return array{success: bool, count: int, error?: string}
 */
function restoreDatabaseMetadata(string $sql): array
{
    $trimmed = trim($sql);
    if ($trimmed === '') {
        return ['success' => false, 'count' => 0, 'error' => 'File SQL cadangan kosong.'];
    }

    $statements = splitSqlStatements($trimmed);
    if (empty($statements)) {
        return ['success' => false, 'count' => 0, 'error' => 'Tidak ada perintah SQL yang valid ditemukan.'];
    }

    foreach ($statements as $stmt) {
        $upper = strtoupper(trim($stmt));
        if (str_starts_with($upper, 'SET ') || str_starts_with($upper, '--') || str_starts_with($upper, '/*')) {
            continue;
        }
        if (!preg_match('/^(INSERT|TRUNCATE|DELETE|REPLACE|UPDATE)\b/i', $upper)) {
            return [
                'success' => false,
                'count' => 0,
                'error' => 'Perintah SQL tidak diizinkan dalam restore: ' . substr($stmt, 0, 40) . '...',
            ];
        }
    }

    $pdo = db();
    $inTx = false;
    try {
        $pdo->beginTransaction();
        $inTx = true;
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $executed = 0;
        foreach ($statements as $stmt) {
            $s = trim($stmt);
            if ($s === '') {
                continue;
            }
            $pdo->exec($s);
            $executed++;
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        $pdo->commit();
        $inTx = false;
        return ['success' => true, 'count' => $executed];
    } catch (Throwable $e) {
        if ($inTx) {
            try {
                $pdo->rollBack();
            } catch (Throwable) {
                // Ignore rollback failure if tx already aborted
            }
        }
        return ['success' => false, 'count' => 0, 'error' => $e->getMessage()];
    }
}

/**
 * Export settings table to structured JSON array.
 *
 * @return array{
 *   version: string,
 *   type: string,
 *   exported_at: string,
 *   count: int,
 *   settings: array<string, string>
 * }
 */
function backupConfigSettings(): array
{
    $st = db()->query('SELECT name, value FROM settings ORDER BY name');
    $settings = [];
    if ($st) {
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $settings[(string) $row['name']] = (string) ($row['value'] ?? '');
        }
    }
    return [
        'version' => '0.2.1',
        'type' => 'powerdns-admin-config',
        'exported_at' => gmdate('c'),
        'count' => count($settings),
        'settings' => $settings,
    ];
}

/**
 * Restore settings key-value pairs from JSON array.
 *
 * @param array<string, mixed> $data
 * @return array{success: bool, count: int, error?: string}
 */
function restoreConfigSettings(array $data): array
{
    if (!isset($data['settings']) || !is_array($data['settings'])) {
        return ['success' => false, 'count' => 0, 'error' => 'Format file konfigurasi tidak valid.'];
    }
    /** @var array<string, mixed> $settings */
    $settings = $data['settings'];
    $count = 0;
    foreach ($settings as $key => $val) {
        $k = trim((string) $key);
        if ($k === '') {
            continue;
        }
        settingSet($k, (string) $val);
        $count++;
    }
    return ['success' => true, 'count' => $count];
}

/**
 * Export all PowerDNS zones and their RRsets to structured JSON array.
 *
 * @return array{
 *   version: string,
 *   type: string,
 *   exported_at: string,
 *   count: int,
 *   zones: list<array<string, mixed>>
 * }
 */
function backupAllZones(PdnsClient $pdns): array
{
    /** @var list<array<string, mixed>> $remoteZones */
    $remoteZones = $pdns->zones();
    $zonesData = [];
    foreach ($remoteZones as $z) {
        $name = (string) ($z['name'] ?? '');
        if ($name === '') {
            continue;
        }
        try {
            $fullZone = $pdns->zone($name);
            $bindText = $pdns->exportZone($name);
            $zonesData[] = [
                'name' => $name,
                'kind' => (string) ($fullZone['kind'] ?? 'Native'),
                'serial' => (int) ($fullZone['serial'] ?? 0),
                'masters' => (array) ($fullZone['masters'] ?? []),
                'rrsets' => (array) ($fullZone['rrsets'] ?? []),
                'bind' => $bindText,
            ];
        } catch (Throwable) {
            // Keep going if a single zone export fails
        }
    }

    return [
        'version' => '0.2.1',
        'type' => 'powerdns-admin-zones',
        'exported_at' => gmdate('c'),
        'count' => count($zonesData),
        'zones' => $zonesData,
    ];
}

/**
 * Restore zones and their RRsets from structured JSON array.
 *
 * @param array<string, mixed> $data
 * @return array{success: bool, total: int, restored: int, errors: list<string>}
 */
function restoreZones(PdnsClient $pdns, array $data): array
{
    if (!isset($data['zones']) || !is_array($data['zones'])) {
        return ['success' => false, 'total' => 0, 'restored' => 0, 'errors' => ['Format data zona tidak valid.']];
    }
    /** @var list<array<string, mixed>> $zones */
    $zones = $data['zones'];
    $restored = 0;
    $errors = [];

    $existing = [];
    try {
        /** @var list<array<string, mixed>> $current */
        $current = $pdns->zones();
        foreach ($current as $ez) {
            $existing[dnsCanonical((string) ($ez['name'] ?? ''))] = true;
        }
    } catch (Throwable $e) {
        $errors[] = 'Gagal membaca daftar zona yang ada: ' . $e->getMessage();
    }

    foreach ($zones as $z) {
        if (!is_array($z) || empty($z['name'])) {
            continue;
        }
        $name = dnsCanonical((string) $z['name']);
        $kind = (string) ($z['kind'] ?? 'Native');
        /** @var list<array<string, mixed>> $rrsets */
        $rrsets = (array) ($z['rrsets'] ?? []);

        try {
            if (!isset($existing[$name])) {
                $payload = [
                    'name' => $name,
                    'kind' => $kind,
                    'nameservers' => [],
                    'rrsets' => $rrsets,
                ];
                $pdns->createZone($payload);
            } else {
                if (!empty($rrsets)) {
                    $patchRrsets = [];
                    foreach ($rrsets as $rr) {
                        if (!is_array($rr) || empty($rr['name']) || empty($rr['type'])) {
                            continue;
                        }
                        $patchRrsets[] = [
                            'name' => dnsCanonical((string) $rr['name']),
                            'type' => (string) $rr['type'],
                            'ttl' => (int) ($rr['ttl'] ?? 3600),
                            'changetype' => 'REPLACE',
                            'records' => (array) ($rr['records'] ?? []),
                        ];
                    }
                    if (!empty($patchRrsets)) {
                        $pdns->patchRrsets($name, $patchRrsets);
                    }
                }
            }
            $restored++;
        } catch (Throwable $e) {
            $errors[] = "Zona {$name}: " . $e->getMessage();
        }
    }

    try {
        syncZonesFromPdns($pdns);
    } catch (Throwable) {
        // Non-fatal if sync encounters network blip
    }

    return [
        'success' => $restored > 0 || empty($errors),
        'total' => count($zones),
        'restored' => $restored,
        'errors' => $errors,
    ];
}

/**
 * Validate and save user avatar file upload.
 *
 * @param array<string, mixed> $file $_FILES['avatar']
 * @return array{ok: bool, path?: string, error?: string}
 */
function saveUserAvatar(array $file, int $userId): array
{
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['ok' => false, 'error' => 'Parameter berkas tidak valid.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $msgs = [
            UPLOAD_ERR_INI_SIZE => 'Ukuran berkas melebihi batas server.',
            UPLOAD_ERR_FORM_SIZE => 'Ukuran berkas melebihi batas formulir.',
            UPLOAD_ERR_PARTIAL => 'Berkas hanya terunggah sebagian.',
            UPLOAD_ERR_NO_FILE => 'Tidak ada berkas yang diunggah.',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder sementara server hilang.',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menulis berkas ke penyimpanan.',
        ];
        return ['ok' => false, 'error' => $msgs[(int) $file['error']] ?? 'Terjadi kesalahan unggah.'];
    }

    $maxBytes = 2 * 1024 * 1024;
    if ((int) ($file['size'] ?? 0) > $maxBytes) {
        return ['ok' => false, 'error' => 'Ukuran berkas maksimal 2MB.'];
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if (!is_uploaded_file($tmp)) {
        return ['ok' => false, 'error' => 'Berkas unggahan tidak sah.'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $tmp) : '';
    if ($finfo) {
        finfo_close($finfo);
    }

    $allowedMimes = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/svg+xml' => 'svg',
    ];

    if (!isset($allowedMimes[$mime])) {
        return ['ok' => false, 'error' => 'Format gambar harus PNG, JPG, WEBP, GIF, atau SVG.'];
    }

    $ext = $allowedMimes[$mime];
    if ($ext !== 'svg') {
        $imgInfo = @getimagesize($tmp);
        if ($imgInfo === false) {
            return ['ok' => false, 'error' => 'Berkas gambar tidak valid atau korup.'];
        }
    } else {
        $svgContent = (string) file_get_contents($tmp);
        if (preg_match('/<script|javascript:|onload|onerror|onclick/i', $svgContent)) {
            return ['ok' => false, 'error' => 'Berkas SVG mengandung skrip yang tidak diizinkan.'];
        }
    }

    $uploadDir = appRoot() . '/public/uploads/avatars';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = sprintf('avatar_%d_%s.%s', $userId, bin2hex(random_bytes(8)), $ext);
    $targetPath = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($tmp, $targetPath)) {
        return ['ok' => false, 'error' => 'Gagal memindahkan berkas foto profil.'];
    }
    chmod($targetPath, 0644);

    return ['ok' => true, 'path' => '/uploads/avatars/' . $filename];
}

/**
 * Validate and save application branding logo upload.
 *
 * @param array<string, mixed> $file $_FILES['logo_file']
 * @return array{ok: bool, path?: string, error?: string}
 */
function saveBrandLogo(array $file): array
{
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['ok' => false, 'error' => 'Parameter berkas logo tidak valid.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Unggah berkas logo gagal.'];
    }

    $maxBytes = 2 * 1024 * 1024;
    if ((int) ($file['size'] ?? 0) > $maxBytes) {
        return ['ok' => false, 'error' => 'Ukuran berkas logo maksimal 2MB.'];
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if (!is_uploaded_file($tmp)) {
        return ['ok' => false, 'error' => 'Berkas unggahan tidak sah.'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $tmp) : '';
    if ($finfo) {
        finfo_close($finfo);
    }

    $allowedMimes = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
    ];

    if (!isset($allowedMimes[$mime])) {
        return ['ok' => false, 'error' => 'Format logo harus PNG, JPG, WEBP, atau SVG.'];
    }

    $ext = $allowedMimes[$mime];
    if ($ext !== 'svg') {
        $imgInfo = @getimagesize($tmp);
        if ($imgInfo === false) {
            return ['ok' => false, 'error' => 'Berkas logo tidak valid.'];
        }
    } else {
        $svgContent = (string) file_get_contents($tmp);
        if (preg_match('/<script|javascript:|onload|onerror|onclick/i', $svgContent)) {
            return ['ok' => false, 'error' => 'Berkas SVG logo mengandung skrip yang tidak diizinkan.'];
        }
    }

    $uploadDir = appRoot() . '/public/uploads/branding';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = sprintf('logo_%s.%s', bin2hex(random_bytes(8)), $ext);
    $targetPath = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($tmp, $targetPath)) {
        return ['ok' => false, 'error' => 'Gagal memindahkan berkas logo.'];
    }
    chmod($targetPath, 0644);

    return ['ok' => true, 'path' => '/uploads/branding/' . $filename];
}
