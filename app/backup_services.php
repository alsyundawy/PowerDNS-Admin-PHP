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
 * Determine if character at position in SQL is backslash-escaped.
 */
function isQuoteEscaped(string $sql, int $pos): bool
{
    $escapeCount = 0;
    for ($k = $pos - 1; $k >= 0 && $sql[$k] === '\\'; $k--) {
        $escapeCount++;
    }
    return ($escapeCount % 2) !== 0;
}

/**
 * Check if current position starts a SQL comment and return position to skip to.
 */
function skipSqlComment(string $sql, int $pos, int $len): ?int
{
    $c = $sql[$pos];
    $next = $pos + 1 < $len ? $sql[$pos + 1] : '';

    if ($c === '-' && $next === '-') {
        $nl = strpos($sql, "\n", $pos);
        return $nl === false ? $len : $nl;
    }

    if ($c === '/' && $next === '*') {
        $end = strpos($sql, '*/', $pos + 2);
        return $end === false ? $len : ($end + 1);
    }

    return null;
}

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
    $i = 0;

    while ($i < $len) {
        $c = $sql[$i];

        if (!$inSingle && !$inDouble && !$inBacktick) {
            $skipTo = skipSqlComment($sql, $i, $len);
            if ($skipTo !== null) {
                $i = $skipTo + 1;
                continue;
            }
            if ($c === ';') {
                $trimmed = trim($buf);
                if ($trimmed !== '') {
                    $stmts[] = $trimmed;
                }
                $buf = '';
                $i++;
                continue;
            }
            if ($c === '`') {
                $inBacktick = true;
                $buf .= $c;
                $i++;
                continue;
            }
        }

        if ($c === "'" && !$inDouble && !$inBacktick && !isQuoteEscaped($sql, $i)) {
            $inSingle = !$inSingle;
        } elseif ($c === '"' && !$inSingle && !$inBacktick && !isQuoteEscaped($sql, $i)) {
            $inDouble = !$inDouble;
        } elseif ($c === '`' && $inBacktick) {
            $inBacktick = false;
        }

        $buf .= $c;
        $i++;
    }

    $trimmed = trim($buf);
    if ($trimmed !== '') {
        $stmts[] = $trimmed;
    }

    return $stmts;
}

/**
 * Format a single table row as SQL value tuple.
 *
 * @param array<string, mixed> $row
 * @param list<string> $cols
 */
function formatSqlRow(PDO $pdo, array $row, array $cols): string
{
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
    return '(' . implode(', ', $rowValues) . ')';
}

/**
 * Generate TRUNCATE and INSERT dump statements for a single table.
 */
function dumpTableSql(PDO $pdo, string $table): string
{
    $st = $pdo->query("SELECT * FROM `{$table}`");
    if (!$st) {
        return '';
    }
    /** @var list<array<string, mixed>> $rows */
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    $count = count($rows);
    $out = "-- Table: `{$table}` ({$count} rows)\n" . "TRUNCATE TABLE `{$table}`;\n";
    if ($count === 0) {
        return $out . "\n";
    }

    $cols = array_keys($rows[0]);
    $escapedCols = array_map(static fn (string $c): string => "`{$c}`", $cols);
    $colList = implode(', ', $escapedCols);

    $chunks = array_chunk($rows, 100);
    foreach ($chunks as $chunk) {
        $valueClauses = [];
        foreach ($chunk as $row) {
            $valueClauses[] = formatSqlRow($pdo, $row, $cols);
        }
        $out .= "INSERT INTO `{$table}` ({$colList}) VALUES\n  " . implode(",\n  ", $valueClauses) . ";\n";
    }
    return $out . "\n";
}

/**
 * Generate a complete SQL dump of application metadata tables.
 */
function backupDatabaseMetadata(): string
{
    $pdo = db();
    $out = "-- PowerDNS-Admin-PHP Database Metadata Dump\n"
        . "-- Version: 0.2.1\n"
        . "-- Generated: " . gmdate('Y-m-d H:i:s') . " UTC\n"
        . "-- --------------------------------------------------------\n\n"
        . "SET FOREIGN_KEY_CHECKS=0;\n"
        . "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n";

    foreach (APP_METADATA_TABLES as $table) {
        $out .= dumpTableSql($pdo, $table);
    }

    $out .= "SET FOREIGN_KEY_CHECKS=1;\n";
    return $out;
}

/**
 * Validate that SQL statements to restore only contain permitted operations.
 *
 * @param list<string> $statements
 */
function validateRestoreStatements(array $statements): ?string
{
    foreach ($statements as $stmt) {
        $upper = strtoupper(trim($stmt));
        if (str_starts_with($upper, 'SET ') || str_starts_with($upper, '--') || str_starts_with($upper, '/*')) {
            continue;
        }
        if (!preg_match('/^(INSERT|TRUNCATE|DELETE|REPLACE|UPDATE)\b/i', $upper)) {
            return 'Perintah SQL tidak diizinkan dalam restore: ' . substr($stmt, 0, 40) . '...';
        }
    }
    return null;
}

/**
 * Execute restore statements within a database transaction.
 *
 * @param list<string> $statements
 * @return array{success: bool, count: int, error?: string}
 */
function executeRestoreStatements(PDO $pdo, array $statements): array
{
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
        return ['success' => true, 'count' => $executed];
    } catch (Throwable $e) {
        if ($inTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'count' => 0, 'error' => $e->getMessage()];
    }
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
    $validationError = empty($statements)
        ? 'Tidak ada perintah SQL yang valid ditemukan.'
        : validateRestoreStatements($statements);

    if ($validationError !== null) {
        return ['success' => false, 'count' => 0, 'error' => $validationError];
    }

    return executeRestoreStatements(db(), $statements);
}

/**
 * Export all system settings into an associative array for JSON backup.
 *
 * @return array<string, mixed>
 */
function backupConfigSettings(): array
{
    $pdo = db();
    $st = $pdo->query('SELECT name, value FROM settings ORDER BY name ASC');
    $settings = [];
    if ($st) {
        while ($row = $st->fetch()) {
            $name = (string) $row['name'];
            $val = (string) $row['value'];
            if ($name === 'pdns_api_key' && $val !== '') {
                $val = secretDecrypt($val);
            }
            $settings[$name] = $val;
        }
    }

    return [
        'app' => 'PowerDNS-Admin-PHP',
        'version' => '0.2.1',
        'exported_at' => gmdate('Y-m-d H:i:s') . ' UTC',
        'settings' => $settings,
    ];
}

/**
 * Restore system settings from an associative array.
 *
 * @param array<string, mixed> $data
 * @return array{success: bool, count: int, error?: string}
 */
function restoreConfigSettings(array $data): array
{
    if (!isset($data['settings']) || !is_array($data['settings'])) {
        return ['success' => false, 'count' => 0, 'error' => 'Format file konfigurasi JSON tidak valid.'];
    }

    $pdo = db();
    $inTx = false;
    try {
        $pdo->beginTransaction();
        $inTx = true;
        $st = $pdo->prepare(
            'INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)'
        );
        $count = 0;
        foreach ($data['settings'] as $k => $v) {
            $name = (string) $k;
            $val = (string) $v;
            if ($name === 'pdns_api_key' && $val !== '') {
                $val = secretEncrypt($val);
            }
            $st->execute([$name, $val]);
            $count++;
        }
        $pdo->commit();
        return ['success' => true, 'count' => $count];
    } catch (Throwable $e) {
        if ($inTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'count' => 0, 'error' => $e->getMessage()];
    }
}

/**
 * Backup all PowerDNS zones including full RRsets via API.
 *
 * @return array{app: string, version: string, exported_at: string, count: int, zones: list<array<string, mixed>>}
 */
function backupAllZones(PdnsClient $pdns): array
{
    /** @var list<array<string, mixed>> $zoneSummaries */
    $zoneSummaries = $pdns->zones();
    $fullZones = [];

    foreach ($zoneSummaries as $z) {
        $name = (string) ($z['name'] ?? '');
        if ($name === '') {
            continue;
        }
        try {
            $detail = $pdns->zone($name);
            $fullZones[] = [
                'name' => (string) ($detail['name'] ?? $name),
                'kind' => (string) ($detail['kind'] ?? 'Native'),
                'masters' => (array) ($detail['masters'] ?? []),
                'dnssec' => (bool) ($detail['dnssec'] ?? false),
                'rrsets' => (array) ($detail['rrsets'] ?? []),
            ];
        } catch (Throwable) {
            $fullZones[] = $z;
        }
    }

    return [
        'app' => 'PowerDNS-Admin-PHP',
        'version' => '0.2.1',
        'exported_at' => gmdate('Y-m-d H:i:s') . ' UTC',
        'count' => count($fullZones),
        'zones' => $fullZones,
    ];
}

/**
 * Build sanitized REPLACE RRset patch payload.
 *
 * @param list<array<string, mixed>> $rrsets
 * @return list<array<string, mixed>>
 */
function buildPatchRrsets(array $rrsets): array
{
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
    return $patchRrsets;
}

/**
 * Restore a single zone (create new or patch existing).
 *
 * @param array<string, mixed> $zoneData
 * @param array<string, bool> $existing
 */
function restoreSingleZone(PdnsClient $pdns, array $zoneData, array $existing): void
{
    $name = dnsCanonical((string) $zoneData['name']);
    $kind = (string) ($zoneData['kind'] ?? 'Native');
    /** @var list<array<string, mixed>> $rrsets */
    $rrsets = (array) ($zoneData['rrsets'] ?? []);

    if (!isset($existing[$name])) {
        $payload = [
            'name' => $name,
            'kind' => $kind,
            'nameservers' => [],
            'rrsets' => $rrsets,
        ];
        $pdns->createZone($payload);
        return;
    }

    if (!empty($rrsets)) {
        $patchRrsets = buildPatchRrsets($rrsets);
        if (!empty($patchRrsets)) {
            $pdns->patchRrsets($name, $patchRrsets);
        }
    }
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
        try {
            restoreSingleZone($pdns, $z, $existing);
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
 * Validate upload file parameters, HTTP status, and maximum file size.
 *
 * @param array<string, mixed> $file
 */
function validateUploadFileParams(array $file, string $label): ?string
{
    if (!isset($file['error']) || is_array($file['error'])) {
        return sprintf('Parameter berkas %s tidak valid.', $label);
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
        return $msgs[(int) $file['error']] ?? sprintf('Unggah berkas %s gagal.', $label);
    }

    $maxBytes = 2 * 1024 * 1024;
    if ((int) ($file['size'] ?? 0) > $maxBytes) {
        return sprintf('Ukuran berkas %s maksimal 2MB.', $label);
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if (!is_uploaded_file($tmp)) {
        return 'Berkas unggahan tidak sah.';
    }

    return null;
}

/**
 * Inspect image MIME type, structure, and sanitize SVG scripts.
 *
 * @return array{ok: bool, ext?: string, error?: string}
 */
function validateImageMimeAndContent(string $tmp, string $label, bool $allowGif): array
{
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
    if ($allowGif) {
        $allowedMimes['image/gif'] = 'gif';
    }

    if (!isset($allowedMimes[$mime])) {
        $allowedFormats = $allowGif ? 'PNG, JPG, WEBP, GIF, atau SVG' : 'PNG, JPG, WEBP, atau SVG';
        return ['ok' => false, 'error' => sprintf('Format %s harus %s.', $label, $allowedFormats)];
    }

    $ext = $allowedMimes[$mime];
    if ($ext !== 'svg') {
        $imgInfo = @getimagesize($tmp);
        if ($imgInfo === false) {
            return ['ok' => false, 'error' => sprintf('Berkas %s tidak valid atau korup.', $label)];
        }
    } else {
        $svgContent = (string) file_get_contents($tmp);
        $svgPattern = '/<script|javascript:|on\w+\s*=|data:\s*text\/html|'
            . 'xlink:href\s*=\s*[\'"\s]*javascript:|<\?php|<\?=/i';
        if (preg_match($svgPattern, $svgContent)) {
            return ['ok' => false, 'error' => sprintf('Berkas SVG %s mengandung skrip yang tidak diizinkan.', $label)];
        }
    }

    return ['ok' => true, 'ext' => $ext];
}

/**
 * Internal helper to validate and store an uploaded image safely.
 *
 * @param array<string, mixed> $file
 * @param string $subDir Directory relative to /public/uploads/ (e.g. 'avatars' or 'branding')
 * @param string $fileBaseName Target base filename without extension
 * @param string $label Indonesian label for error messages (e.g. 'foto profil' or 'logo')
 * @param bool $allowGif Whether GIF is permitted
 * @return array{ok: bool, path?: string, error?: string}
 */
function processUploadedImage(
    array $file,
    string $subDir,
    string $fileBaseName,
    string $label = 'gambar',
    bool $allowGif = true
): array {
    $paramErr = validateUploadFileParams($file, $label);
    if ($paramErr !== null) {
        return ['ok' => false, 'error' => $paramErr];
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    $val = validateImageMimeAndContent($tmp, $label, $allowGif);
    if (!$val['ok']) {
        return ['ok' => false, 'error' => $val['error'] ?? 'Validasi gambar gagal.'];
    }

    $uploadDir = appRoot() . '/public/uploads/' . trim($subDir, '/');
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = sprintf('%s.%s', $fileBaseName, (string) $val['ext']);
    $targetPath = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($tmp, $targetPath)) {
        return ['ok' => false, 'error' => sprintf('Gagal memindahkan berkas %s.', $label)];
    }
    chmod($targetPath, 0644);

    return ['ok' => true, 'path' => '/uploads/' . trim($subDir, '/') . '/' . $filename];
}

/**
 * Validate and save user avatar upload.
 *
 * @param array<string, mixed> $file $_FILES['avatar']
 * @return array{ok: bool, path?: string, error?: string}
 */
function saveUserAvatar(array $file, int $userId): array
{
    $fileBaseName = sprintf('avatar_%d_%s', $userId, bin2hex(random_bytes(8)));
    return processUploadedImage($file, 'avatars', $fileBaseName, 'foto profil', true);
}

/**
 * Validate and save application branding logo upload.
 *
 * @param array<string, mixed> $file $_FILES['logo_file']
 * @return array{ok: bool, path?: string, error?: string}
 */
function saveBrandLogo(array $file): array
{
    $fileBaseName = sprintf('logo_%s', bin2hex(random_bytes(8)));
    return processUploadedImage($file, 'branding', $fileBaseName, 'logo', false);
}
