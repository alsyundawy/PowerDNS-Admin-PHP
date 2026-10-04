<?php

declare(strict_types=1);

const RECORD_TYPES = [
    'A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'SRV', 'PTR', 'CAA',
    'TLSA', 'SSHFP', 'NAPTR', 'SPF', 'SOA', 'HTTPS', 'SVCB', 'DS'
];

/**
 * @param array<string, mixed> $user
 */
function userCanZone(array $user, string $zone, bool $edit = false): bool
{
    if (($user['role'] ?? '') === 'admin') {
        return true;
    }
    $name = dnsCanonical($zone);
    $st = db()->prepare(
        'SELECT z.id, zu.can_edit FROM zones z
         LEFT JOIN zone_user zu ON zu.zone_id = z.id AND zu.user_id = ? WHERE z.name = ?'
    );
    $st->execute([(int) ($user['id'] ?? 0), $name]);
    $row = $st->fetch();
    if ($row && !empty($row['id']) && $row['can_edit'] !== null) {
        return $edit ? (int) $row['can_edit'] === 1 : true;
    }
    $st = db()->prepare(
        'SELECT z.id, 1 AS can_edit FROM zones z
         JOIN account_user au ON au.account_id = z.account_id
         WHERE z.name = ? AND au.user_id = ?'
    );
    $st->execute([$name, (int) ($user['id'] ?? 0)]);
    return (bool) $st->fetch();
}

/**
 * @param array<string, mixed> $user
 */
function requireZoneAccess(array $user, string $zone, bool $edit = false): void
{
    if (!userCanZone($user, $zone, $edit)) {
        http_response_code(403);
        view('error', [
            'title' => 'Zona terkunci',
            'message' => 'Anda tidak punya akses ke zona ini.',
            'user' => $user,
        ]);
        exit;
    }
}

function syncZonesFromPdns(PdnsClient $pdns): int
{
    $remote = $pdns->zones();
    $seen = [];
    $n = 0;
    $up = db()->prepare(
        'INSERT INTO zones (name, kind, dnssec, serial, catalog, synced_at) VALUES (?, ?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE kind = VALUES(kind), dnssec = VALUES(dnssec), serial = VALUES(serial),
         catalog = VALUES(catalog), synced_at = NOW()'
    );
    foreach ($remote as $z) {
        if (!is_array($z) || empty($z['name'])) {
            continue;
        }
        $name = dnsCanonical((string) $z['name']);
        $seen[] = $name;
        $up->execute([
            $name,
            (string) ($z['kind'] ?? 'Native'),
            !empty($z['dnssec']) ? 1 : 0,
            isset($z['serial']) ? (int) $z['serial'] : null,
            (string) ($z['catalog'] ?? ''),
        ]);
        $n++;
    }
    if ($seen) {
        $marks = implode(',', array_fill(0, count($seen), '?'));
        $del = db()->prepare("DELETE FROM zones WHERE name NOT IN ($marks)");
        $del->execute($seen);
    }
    return $n;
}

function validateRecord(string $type, string $content): ?string
{
    $type = strtoupper($type);
    $content = trim($content);
    if ($content === '') {
        return 'Isi record kosong.';
    }
    if (!in_array($type, RECORD_TYPES, true)) {
        return 'Tipe record tidak diizinkan.';
    }
    return match ($type) {
        'A' => filter_var($content, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? null : 'A harus alamat IPv4 valid.',
        'AAAA' => filter_var($content, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? null : 'AAAA harus alamat IPv6 valid.',
        'CNAME', 'NS', 'PTR' => preg_match('/^[A-Za-z0-9_.*-]+$/', rtrim($content, '.'))
            ? null : 'Nama host tidak valid.',
        'MX' => preg_match('/^\d{1,5}\s+\S+$/', $content)
            ? null : 'MX harus format: "prioritas hostname" (contoh: 10 mail.example.com).',
        'SRV' => preg_match('/^\d+\s+\d+\s+\d+\s+\S+$/', $content)
            ? null : 'SRV harus format: "prio weight port target".',
        'CAA' => preg_match('/^\d+\s+\S+\s+/', $content) ? null : 'CAA harus format: "flags tag value".',
        'TXT', 'SPF' => strlen($content) > 4096 ? 'Teks terlalu panjang (maksimal 4096 karakter).' : null,
        'HTTPS', 'SVCB' => preg_match('/^\d+\s+\S+/', $content)
            ? null : 'Format harus: "prioritas target [params]" (contoh: 1 . alpn="h3,h2").',
        'DS' => preg_match('/^\d+\s+\d+\s+\d+\s+[A-Fa-f0-9]+$/', $content)
            ? null : 'DS harus format: "keytag algo digesttype digest".',
        default => null,
    };
}

function normalizeContent(string $type, string $content): string
{
    $type = strtoupper($type);
    $content = trim($content);
    $result = $content;

    if (in_array($type, ['CNAME', 'NS', 'PTR', 'MX', 'SRV'], true)) {
        if ($type === 'MX' && preg_match('/^(\d+)\s+(\S+)$/', $content, $m)) {
            $result = $m[1] . ' ' . dnsCanonical($m[2]);
        } elseif ($type === 'SRV' && preg_match('/^(\d+)\s+(\d+)\s+(\d+)\s+(\S+)$/', $content, $m)) {
            $result = $m[1] . ' ' . $m[2] . ' ' . $m[3] . ' ' . dnsCanonical($m[4]);
        } elseif (in_array($type, ['CNAME', 'NS', 'PTR'], true)) {
            $result = dnsCanonical($content);
        }
    } elseif ($type === 'TXT') {
        if (str_starts_with($content, '"') && str_ends_with($content, '"') && strlen($content) >= 2) {
            $unquoted = substr($content, 1, -1);
            $clean = str_replace('\\"', '"', $unquoted);
            $result = '"' . str_replace('"', '\\"', $clean) . '"';
        } else {
            $result = '"' . str_replace('"', '\\"', $content) . '"';
        }
    }

    return $result;
}

/**
 * Build REPLACE/DELETE rrsets. DELETE must not include records or ttl per PowerDNS spec.
 * Docs: https://doc.powerdns.com/authoritative/http-api/zone.html
 *
 * @param array<int, mixed> $currentRrsets
 * @param array<int, array{name:string,type:string,ttl:int,content:string,disabled:bool,comment:string}> $submitted
 * @return array<int, array<string, mixed>>
 */
function diffRrsets(string $zone, array $currentRrsets, array $submitted): array
{
    $zone = dnsCanonical($zone);
    $current = [];
    foreach ($currentRrsets as $set) {
        if (!is_array($set)) {
            continue;
        }
        $type = strtoupper((string) ($set['type'] ?? ''));
        if ($type === 'SOA') {
            continue;
        }
        $key = strtolower((string) $set['name']) . '|' . $type;
        $current[$key] = $set;
    }
    $next = [];
    foreach ($submitted as $row) {
        $type = strtoupper($row['type']);
        if ($type === 'SOA') {
            continue;
        }
        $fqdn = dnsFqdn($row['name'], $zone);
        $key = strtolower($fqdn) . '|' . $type;
        $next[$key]['name'] = $fqdn;
        $next[$key]['type'] = $type;
        $next[$key]['ttl'] = max(30, (int) $row['ttl']);
        $next[$key]['records'][] = [
            'content' => normalizeContent($type, $row['content']),
            'disabled' => (bool) $row['disabled'],
        ];
        $comment = trim($row['comment']);
        if ($comment !== '') {
            $next[$key]['comments'][] = ['content' => $comment, 'account' => ''];
        }
    }
    $out = [];
    foreach ($next as $key => $set) {
        $payload = [
            'name' => $set['name'],
            'type' => $set['type'],
            'ttl' => $set['ttl'],
            'changetype' => 'REPLACE',
            'records' => $set['records'],
            'comments' => $set['comments'] ?? [],
        ];
        $out[] = $payload;
        unset($current[$key]);
    }
    foreach ($current as $set) {
        $out[] = [
            'name' => (string) $set['name'],
            'type' => (string) $set['type'],
            'changetype' => 'DELETE',
        ];
    }
    return $out;
}

/**
 * @param array<int, mixed> $rrsets
 * @return array<int, array{name:string,type:string,ttl:int,content:string,disabled:bool,comment:string}>
 */
function flattenRrsets(array $rrsets, string $zone): array
{
    $rows = [];
    foreach ($rrsets as $set) {
        if (!is_array($set)) {
            continue;
        }
        $type = strtoupper((string) ($set['type'] ?? ''));
        if ($type === 'SOA') {
            continue;
        }
        $comment = '';
        if (!empty($set['comments'][0]['content'])) {
            $comment = (string) $set['comments'][0]['content'];
        }
        foreach ($set['records'] ?? [] as $rec) {
            $rows[] = [
                'name' => dnsRelative((string) $set['name'], $zone),
                'type' => $type,
                'ttl' => (int) ($set['ttl'] ?? 3600),
                'content' => (string) ($rec['content'] ?? ''),
                'disabled' => !empty($rec['disabled']),
                'comment' => $comment,
            ];
        }
    }
    usort($rows, static fn ($a, $b) => [$a['name'], $a['type']] <=> [$b['name'], $b['type']]);
    return $rows;
}

/**
 * @param array<string, mixed> $zone
 */
function soaOf(array $zone): string
{
    foreach ($zone['rrsets'] ?? [] as $set) {
        if (($set['type'] ?? '') === 'SOA') {
            return (string) ($set['records'][0]['content'] ?? '');
        }
    }
    return '';
}

/**
 * Auto-generate daftar record PTR untuk subnet /24 (misal: host 1 s/d 254).
 *
 * @return array<int, array{name: string, type: string, ttl: int, content: string, disabled: bool, comment: string}>
 */
function generateIpv4SubnetPtrBatch(
    string $cidr24,
    string $targetDomain,
    string $namingPattern = 'host-[ID].[DOMAIN]',
    int $ttl = 3600,
    int $start = 1,
    int $end = 254
): array {
    $zone = ipv4ToReverseZone24($cidr24);
    if (!$zone) {
        return [];
    }
    $baseIp = explode('/', $cidr24)[0];
    $parts = explode('.', $baseIp);
    $prefix = $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.';
    $targetDomain = rtrim($targetDomain, '.');

    $records = [];
    $start = max(1, min(254, $start));
    $end = max($start, min(254, $end));

    for ($i = $start; $i <= $end; $i++) {
        $ip = $prefix . $i;
        $ipDash = str_replace('.', '-', $ip);

        $hostname = str_replace(
            ['[ID]', '[IP_DASH]', '[DOMAIN]'],
            [(string) $i, $ipDash, $targetDomain],
            $namingPattern
        );
        $hostname = dnsCanonical($hostname);

        $records[] = [
            'name' => (string) $i,
            'type' => 'PTR',
            'ttl' => $ttl,
            'content' => $hostname,
            'disabled' => false,
            'comment' => 'Auto-generated /24 PTR',
        ];
    }
    return $records;
}

/**
 * Auto-generate sekuensial record PTR untuk subnet IPv6 /64.
 *
 * @return array<int, array{name: string, type: string, ttl: int, content: string, disabled: bool, comment: string}>
 */
function generateIpv6SubnetPtrBatch(
    string $prefix64,
    string $targetDomain,
    string $namingPattern = 'ipv6-[HEX].[DOMAIN]',
    int $ttl = 3600,
    int $start = 1,
    int $end = 50
): array {
    $zone = ipv6ToReverseZone64($prefix64);
    if (!$zone) {
        return [];
    }
    $bin = @inet_pton(explode('/', $prefix64)[0]);
    if ($bin === false || strlen($bin) !== 16) {
        return [];
    }
    $targetDomain = rtrim($targetDomain, '.');

    $records = [];
    $start = max(1, $start);
    $end = max($start, min(500, $end));

    for ($i = $start; $i <= $end; $i++) {
        $hostHex = str_pad(dechex($i), 16, '0', STR_PAD_LEFT);
        $relativeName = implode('.', array_reverse(str_split($hostHex)));
        $hostname = str_replace(
            ['[ID]', '[HEX]', '[DOMAIN]'],
            [(string) $i, dechex($i), $targetDomain],
            $namingPattern
        );
        $hostname = dnsCanonical($hostname);

        $records[] = [
            'name' => $relativeName,
            'type' => 'PTR',
            'ttl' => $ttl,
            'content' => $hostname,
            'disabled' => false,
            'comment' => 'Auto-generated /64 PTR',
        ];
    }
    return $records;
}

/**
 * Mencari apakah zona reverse untuk IP ini terdaftar di database panel.
 */
function findMatchingReverseZone(string $ip): ?string
{
    $revZone = null;
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $revZone = ipv4ToReverseZone24($ip);
    } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $revZone = ipv6ToReverseZone64($ip);
    }

    if ($revZone === null) {
        return null;
    }

    $st = db()->prepare('SELECT name FROM zones WHERE name = ?');
    $st->execute([$revZone]);
    $row = $st->fetch();
    return $row ? (string) $row['name'] : null;
}

/**
 * Sinkronisasi otomatis record A/AAAA forward ke record PTR di zona reverse yang sesuai.
 *
 * @param array<string, mixed> $user
 */
function syncForwardIpToReversePtr(
    PdnsClient $pdns,
    array $user,
    string $ip,
    string $targetFqdn,
    int $ttl = 3600,
    bool $delete = false
): bool {
    $targetFqdn = dnsCanonical($targetFqdn);
    $revZone = findMatchingReverseZone($ip);
    if ($revZone === null || !userCanZone($user, $revZone, true)) {
        return false;
    }

    $relName = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
        ? ipv4ToRelativePtr24($ip)
        : ipv6ToRelativePtr64($ip);

    if ($relName === null) {
        return false;
    }

    $fullPtrFqdn = $relName . '.' . $revZone;
    $rrset = [
        'name' => $fullPtrFqdn,
        'type' => 'PTR',
        'changetype' => $delete ? 'DELETE' : 'REPLACE',
    ];
    if (!$delete) {
        $rrset['ttl'] = max(30, $ttl);
        $rrset['records'] = [
            ['content' => $targetFqdn, 'disabled' => false],
        ];
        $rrset['comments'] = [
            ['content' => 'Auto-PTR synced from ' . $targetFqdn, 'account' => ''],
        ];
    }

    $pdns->patchRrsets($revZone, [$rrset]);
    audit(
        $user,
        'auto-ptr-sync',
        $revZone,
        ($delete ? 'Delete ' : 'Set ') . $fullPtrFqdn . ' -> ' . $targetFqdn
    );
    return true;
}

/**
 * Ensure zone_snapshots table exists in database.
 */
function ensureZoneSnapshotsTable(): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }
    db()->exec('CREATE TABLE IF NOT EXISTS zone_snapshots (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      zone_name VARCHAR(255) NOT NULL,
      serial BIGINT UNSIGNED NULL,
      rrsets_json LONGTEXT NOT NULL,
      user_id INT UNSIGNED NULL,
      username VARCHAR(64) NOT NULL DEFAULT \'\',
      comment VARCHAR(255) NOT NULL DEFAULT \'\',
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY idx_snapshots_zone (zone_name, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $ensured = true;
}

/**
 * Save snapshot of zone\'s current rrsets into MySQL.
 *
 * @param array<string, mixed> $zoneData
 * @param array<string, mixed>|null $user
 */
function saveZoneSnapshot(string $zoneName, array $zoneData, ?array $user = null, string $comment = ''): int
{
    ensureZoneSnapshotsTable();
    $name = dnsCanonical($zoneName);
    $serial = isset($zoneData['serial']) ? (int) $zoneData['serial'] : null;
    $rrsets = is_array($zoneData['rrsets'] ?? null) ? $zoneData['rrsets'] : [];

    $json = json_encode($rrsets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

    $st = db()->prepare(
        'INSERT INTO zone_snapshots (zone_name, serial, rrsets_json, user_id, username, comment, created_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())'
    );
    $st->execute([
        $name,
        $serial,
        $json,
        $user['id'] ?? null,
        $user['username'] ?? '',
        mb_substr($comment, 0, 255),
    ]);

    return (int) db()->lastInsertId();
}

/**
 * @return list<array<string, mixed>>
 */
function getZoneSnapshots(string $zoneName, int $limit = 30): array
{
    ensureZoneSnapshotsTable();
    $st = db()->prepare(
        'SELECT id, zone_name, serial, user_id, username, comment, created_at,
                CHAR_LENGTH(rrsets_json) AS json_size
         FROM zone_snapshots
         WHERE zone_name = ?
         ORDER BY id DESC
         LIMIT ?'
    );
    $st->bindValue(1, dnsCanonical($zoneName), PDO::PARAM_STR);
    $st->bindValue(2, $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

/**
 * @return array<string, mixed>|null
 */
function getZoneSnapshot(int $snapshotId): ?array
{
    ensureZoneSnapshotsTable();
    $st = db()->prepare('SELECT * FROM zone_snapshots WHERE id = ?');
    $st->execute([$snapshotId]);
    $row = $st->fetch();
    if (!$row) {
        return null;
    }
    $decoded = json_decode((string) $row['rrsets_json'], true);
    $row['rrsets'] = is_array($decoded) ? $decoded : [];
    return $row;
}

/**
 * Calculate RRsets diff to transform $currentRrsets to $targetRrsets.
 *
 * @param list<array<string, mixed>> $currentRrsets
 * @param list<array<string, mixed>> $targetRrsets
 * @return list<array<string, mixed>>
 */
function diffSnapshotRrsets(array $currentRrsets, array $targetRrsets): array
{
    $targetMap = [];
    foreach ($targetRrsets as $rr) {
        $key = strtoupper((string) ($rr['name'] ?? '')) . '|' . strtoupper((string) ($rr['type'] ?? ''));
        $targetMap[$key] = $rr;
    }

    $patch = [];

    // Delete current RRsets that are not in target (excluding SOA)
    foreach ($currentRrsets as $curr) {
        $name = (string) ($curr['name'] ?? '');
        $type = strtoupper((string) ($curr['type'] ?? ''));
        if ($type === 'SOA') {
            continue;
        }
        $key = strtoupper($name) . '|' . $type;
        if (!isset($targetMap[$key])) {
            $patch[] = [
                'name' => $name,
                'type' => $type,
                'changetype' => 'DELETE',
            ];
        }
    }

    // Replace all RRsets from target
    foreach ($targetRrsets as $tgt) {
        $patch[] = [
            'name' => (string) ($tgt['name'] ?? ''),
            'type' => strtoupper((string) ($tgt['type'] ?? '')),
            'ttl' => (int) ($tgt['ttl'] ?? 3600),
            'changetype' => 'REPLACE',
            'records' => $tgt['records'] ?? [],
            'comments' => $tgt['comments'] ?? [],
        ];
    }

    return $patch;
}

/**
 * Rollback a zone to a specific snapshot.
 *
 * @param array<string, mixed> $user
 */
function rollbackZoneSnapshot(PdnsClient $pdns, array $user, string $zoneName, int $snapshotId): bool
{
    $name = dnsCanonical($zoneName);
    requireZoneAccess($user, $name, true);

    $snapshot = getZoneSnapshot($snapshotId);
    if (!$snapshot || dnsCanonical((string) $snapshot['zone_name']) !== $name) {
        throw new InvalidArgumentException('Snapshot tidak ditemukan untuk zona ini.');
    }

    $currentZone = $pdns->zone($name);
    // Take safety snapshot before rolling back
    saveZoneSnapshot(
        $name,
        $currentZone,
        $user,
        'Auto-snapshot sebelum rollback ke revisi #' . $snapshotId
    );

    $patch = diffSnapshotRrsets($currentZone['rrsets'] ?? [], $snapshot['rrsets']);
    if (!empty($patch)) {
        $pdns->patchRrsets($name, $patch);
    }

    audit(
        $user,
        'zone-rollback',
        $name,
        'Rollback ke revisi #' . $snapshotId . ' (dibuat ' . ($snapshot['created_at'] ?? '') . ')'
    );

    return true;
}

/**
 * Parse DNS duration shorthand like 3h, 1d, 1w into integer seconds.
 */
function parseDnsDuration(string $str): int
{
    $str = trim($str);
    if (is_numeric($str)) {
        return (int) $str;
    }
    if (preg_match('/^(\d+)([smhdw]?)$/i', $str, $m)) {
        $val = (int) $m[1];
        $unit = strtolower($m[2]);
        return match ($unit) {
            'w' => $val * 604800,
            'd' => $val * 86400,
            'h' => $val * 3600,
            'm' => $val * 60,
            default => $val,
        };
    }
    return 3600;
}

/**
 * Strip comments from a BIND zone line while preserving quoted strings.
 */
function stripBindComments(string $rawLine): string
{
    $line = '';
    $inQuote = false;
    $len = strlen($rawLine);
    for ($i = 0; $i < $len; $i++) {
        $ch = $rawLine[$i];
        if ($ch === '"') {
            $inQuote = !$inQuote;
            $line .= $ch;
        } elseif ($ch === ';' && !$inQuote) {
            break;
        } else {
            $line .= $ch;
        }
    }
    return $line;
}

/**
 * @param list<string> $cleanLines
 */
function processParenthesisState(
    string $line,
    bool &$inParen,
    string &$parenAccumulator,
    array &$cleanLines
): void {
    $trimmed = trim($line);
    if ($inParen) {
        if (str_contains($line, ')')) {
            $parts = explode(')', $line, 2);
            $parenAccumulator .= ' ' . trim($parts[0]);
            $cleanLines[] = trim($parenAccumulator);
            $inParen = false;
            $parenAccumulator = '';
            if (trim($parts[1]) !== '') {
                $cleanLines[] = trim($parts[1]);
            }
        } else {
            $parenAccumulator .= ' ' . $trimmed;
        }
        return;
    }

    if (str_contains($line, '(')) {
        $parts = explode('(', $line, 2);
        $parenAccumulator = trim($parts[0]);
        if (str_contains($parts[1], ')')) {
            $subParts = explode(')', $parts[1], 2);
            $parenAccumulator .= ' ' . trim($subParts[0]);
            $cleanLines[] = trim($parenAccumulator);
            $parenAccumulator = '';
        } else {
            $inParen = true;
            $parenAccumulator .= ' ' . trim($parts[1]);
        }
        return;
    }

    $cleanLines[] = rtrim($line);
}

/**
 * Pre-process BIND zone raw content: strip comments and join multiline parentheses.
 *
 * @return list<string>
 */
function normalizeBindZoneLines(string $content): array
{
    $content = str_replace(["\r\n", "\r"], "\n", $content);
    $lines = explode("\n", $content);
    $cleanLines = [];
    $inParen = false;
    $parenAccumulator = '';

    foreach ($lines as $rawLine) {
        $line = stripBindComments($rawLine);
        if (trim($line) === '') {
            continue;
        }
        processParenthesisState($line, $inParen, $parenAccumulator, $cleanLines);
    }

    return $cleanLines;
}

/**
 * @param list<string> $rdataTokens
 */
function formatBindSoaContent(array $rdataTokens, string $currentOrigin): string
{
    $mname = str_ends_with($rdataTokens[0], '.') ? $rdataTokens[0] : $rdataTokens[0] . '.' . $currentOrigin;
    $rname = str_ends_with($rdataTokens[1], '.') ? $rdataTokens[1] : $rdataTokens[1] . '.' . $currentOrigin;
    return sprintf(
        '%s %s %s %d %d %d %d',
        dnsCanonical($mname),
        dnsCanonical($rname),
        $rdataTokens[2],
        parseDnsDuration($rdataTokens[3]),
        parseDnsDuration($rdataTokens[4]),
        parseDnsDuration($rdataTokens[5]),
        parseDnsDuration($rdataTokens[6])
    );
}

/**
 * Format BIND resource record content based on record type.
 *
 * @param list<string> $rdataTokens
 */
function formatBindRecordContent(string $type, array $rdataTokens, string $currentOrigin): string
{
    if ($type === 'SOA' && count($rdataTokens) >= 7) {
        return formatBindSoaContent($rdataTokens, $currentOrigin);
    }

    if (in_array($type, ['CNAME', 'NS', 'PTR'], true)) {
        $target = str_ends_with($rdataTokens[0], '.') ? $rdataTokens[0] : $rdataTokens[0] . '.' . $currentOrigin;
        return dnsCanonical($target);
    }

    if ($type === 'MX' && count($rdataTokens) >= 2) {
        $prio = (int) $rdataTokens[0];
        $target = str_ends_with($rdataTokens[1], '.') ? $rdataTokens[1] : $rdataTokens[1] . '.' . $currentOrigin;
        $rdataTokens = [$prio, dnsCanonical($target)];
    }

    return implode(' ', $rdataTokens);
}

function handleBindDirective(
    string $trimmed,
    string &$currentOrigin,
    string &$lastOwner,
    int &$defaultTtl
): void {
    $tokens = preg_split('/\s+/', $trimmed, 3);
    $dir = strtoupper($tokens[0] ?? '');
    if ($dir === '$ORIGIN' && !empty($tokens[1])) {
        $newOrigin = trim($tokens[1]);
        if (!str_ends_with($newOrigin, '.')) {
            $newOrigin .= '.' . $currentOrigin;
        }
        $currentOrigin = dnsCanonical($newOrigin);
        $lastOwner = $currentOrigin;
    } elseif ($dir === '$TTL' && !empty($tokens[1])) {
        $defaultTtl = max(30, parseDnsDuration($tokens[1]));
    }
}

/**
 * @param list<string> $tokens
 */
function resolveBindRecordOwner(
    array $tokens,
    int &$idx,
    bool $hasLeadingSpace,
    string $currentOrigin,
    string $lastOwner
): string {
    if ($hasLeadingSpace) {
        return $lastOwner;
    }
    $rawOwner = $tokens[$idx++];
    if ($rawOwner === '@') {
        $owner = $currentOrigin;
    } elseif (str_ends_with($rawOwner, '.')) {
        $owner = dnsCanonical($rawOwner);
    } else {
        $owner = dnsCanonical($rawOwner . '.' . $currentOrigin);
    }
    return $owner;
}

/**
 * @param list<string> $tokens
 * @param list<string> $knownTypes
 * @return array{0: ?int, 1: ?string, 2: list<string>}
 */
function parseBindTokensRdata(array $tokens, int $idx, array $knownTypes): array
{
    $ttl = null;
    $type = null;
    $rdataTokens = [];

    while ($idx < count($tokens)) {
        $tok = $tokens[$idx++];
        $tokUpper = strtoupper($tok);

        if ($tokUpper === 'IN' || $tokUpper === 'CH' || $tokUpper === 'CS' || $tokUpper === 'HS') {
            continue;
        }
        if (preg_match('/^\d+[smhdw]?$/i', $tok) && $ttl === null) {
            $ttl = max(30, parseDnsDuration($tok));
        } elseif (in_array($tokUpper, $knownTypes, true)) {
            $type = $tokUpper;
            while ($idx < count($tokens)) {
                $rdataTokens[] = $tokens[$idx++];
            }
            break;
        }
    }

    return [$ttl, $type, $rdataTokens];
}

/**
 * @param array<string, array<string, mixed>> $rrsetsMap
 */
function appendBindRecordToRrsets(
    array &$rrsetsMap,
    string $owner,
    string $type,
    int $ttl,
    string $content
): void {
    $key = strtoupper($owner) . '|' . $type;
    if (!isset($rrsetsMap[$key])) {
        $rrsetsMap[$key] = [
            'name' => $owner,
            'type' => $type,
            'ttl' => $ttl,
            'changetype' => 'REPLACE',
            'records' => [],
            'comments' => [],
        ];
    }
    $rrsetsMap[$key]['records'][] = [
        'content' => $content,
        'disabled' => false,
    ];
}

/**
 * Native RFC 1035 BIND Zone File Parser.
 *
 * @return array{origin: string, rrsets: list<array<string, mixed>>}
 */
function parseBindZone(string $content, string $defaultZone): array
{
    $currentOrigin = dnsCanonical($defaultZone);
    $defaultTtl = 3600;
    $lastOwner = $currentOrigin;
    $cleanLines = normalizeBindZoneLines($content);
    $rrsetsMap = [];

    foreach ($cleanLines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') {
            continue;
        }

        if (str_starts_with($trimmed, '$')) {
            handleBindDirective($trimmed, $currentOrigin, $lastOwner, $defaultTtl);
            continue;
        }

        $hasLeadingSpace = preg_match('/^\s+/', $line) === 1;
        preg_match_all('/"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"|\S+/', $trimmed, $m);
        $tokens = $m[0];
        if (empty($tokens)) {
            continue;
        }

        $idx = 0;
        $owner = resolveBindRecordOwner($tokens, $idx, $hasLeadingSpace, $currentOrigin, $lastOwner);
        $lastOwner = $owner;

        [$ttl, $type, $rdataTokens] = parseBindTokensRdata($tokens, $idx, RECORD_TYPES);
        if ($type === null || empty($rdataTokens)) {
            continue;
        }

        $ttl = $ttl ?? $defaultTtl;
        $content = formatBindRecordContent($type, $rdataTokens, $currentOrigin);
        appendBindRecordToRrsets($rrsetsMap, $owner, $type, $ttl, $content);
    }

    return [
        'origin' => $currentOrigin,
        'rrsets' => array_values($rrsetsMap),
    ];
}

/**
 * Find the most specific (longest) existing zone name for a given hostname.
 */
function findMatchingZoneForHostname(string $hostname): ?string
{
    $hostFqdn = dnsCanonical($hostname);
    $st = db()->query('SELECT name FROM zones');
    if (!$st) {
        return null;
    }
    $bestMatch = null;
    $bestLen = 0;
    while ($row = $st->fetch()) {
        $zoneName = dnsCanonical((string) $row['name']);
        if ($hostFqdn === $zoneName || str_ends_with($hostFqdn, '.' . $zoneName)) {
            $len = strlen($zoneName);
            if ($len > $bestLen) {
                $bestLen = $len;
                $bestMatch = $zoneName;
            }
        }
    }
    return $bestMatch;
}
