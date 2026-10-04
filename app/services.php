<?php

declare(strict_types=1);

const RECORD_TYPES = [
    'A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'SRV', 'PTR', 'CAA',
    'TLSA', 'SSHFP', 'NAPTR', 'SPF', 'SOA', 'HTTPS', 'SVCB', 'DS',
    'ALIAS', 'DNAME', 'DNSKEY', 'CDS', 'CDNSKEY', 'CSYNC', 'URI',
    'OPENPGPKEY', 'SMIMEA', 'CERT', 'LOC', 'HINFO', 'RP', 'DHCID'
];

const REGEX_NUM_NUM_NUM_STR = '/^\d+\s+\d+\s+\d+\s+\S+$/';

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

function validateSecurityRecord(string $type, string $content): ?string
{
    return match ($type) {
        'DS', 'CDS' => preg_match('/^\d+\s+\d+\s+\d+\s+[A-Fa-f0-9]+$/', $content)
            ? null : 'Format harus: "keytag algo digesttype digest".',
        'DNSKEY', 'CDNSKEY' => preg_match(REGEX_NUM_NUM_NUM_STR, $content)
            ? null : 'Format harus: "flags protocol algorithm publickey".',
        'TLSA', 'SMIMEA' => preg_match('/^\d+\s+\d+\s+\d+\s+[A-Fa-f0-9]+$/', $content)
            ? null : 'Format harus: "usage selector matching cert_data".',
        'SSHFP' => preg_match('/^\d+\s+\d+\s+[A-Fa-f0-9]+$/', $content)
            ? null : 'SSHFP harus format: "algorithm fptype fingerprint".',
        'CERT' => preg_match(REGEX_NUM_NUM_NUM_STR, $content)
            ? null : 'CERT harus format: "type keytag algorithm certificate".',
        'CSYNC' => preg_match('/^\d+\s+\d+\s+.+$/', $content)
            ? null : 'CSYNC harus format: "serial flags type1 type2 ...".',
        default => null,
    };
}

function validateExtendedRecord(string $type, string $content): ?string
{
    return match ($type) {
        'HTTPS', 'SVCB' => preg_match('/^\d+\s+\S+/', $content)
            ? null : 'Format harus: "prioritas target [params]" (contoh: 1 . alpn="h3,h2").',
        'URI' => preg_match('/^\d+\s+\d+\s+\S+$/', $content)
            ? null : 'URI harus format: "priority weight target".',
        'HINFO' => (preg_match('/^".*"\s+".*"$/', $content) || preg_match('/^\S+\s+\S+$/', $content))
            ? null : 'HINFO harus format: "hardware" "os".',
        'RP' => preg_match('/^\S+\s+\S+$/', $content)
            ? null : 'RP harus format: "mailbox-fqdn txt-fqdn".',
        default => null,
    };
}

/**
 * Validate IP address records (A, AAAA).
 */
function validateIpRecord(string $type, string $content): ?string
{
    if ($type === 'A') {
        return filter_var($content, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? null : 'A harus alamat IPv4 valid.';
    }
    if ($type === 'AAAA') {
        return filter_var($content, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? null : 'AAAA harus alamat IPv6 valid.';
    }
    return null;
}

/**
 * Validate target hostname records (CNAME, NS, PTR, ALIAS, DNAME).
 */
function validateNameRecord(string $type, string $content): ?string
{
    if (in_array($type, ['CNAME', 'NS', 'PTR', 'ALIAS', 'DNAME'], true)) {
        return preg_match('/^[A-Za-z0-9_.*-]+$/', rtrim($content, '.')) ? null : 'Nama host target tidak valid.';
    }
    return null;
}

/**
 * Validate special format records (MX, SRV, CAA, TXT, SPF).
 */
function validateSpecialRecord(string $type, string $content): ?string
{
    $error = null;
    if ($type === 'MX') {
        $error = preg_match('/^\d{1,5}\s+\S+$/', $content)
            ? null : 'MX harus format: "prioritas hostname" (contoh: 10 mail.example.com).';
    } elseif ($type === 'SRV') {
        $error = preg_match(REGEX_NUM_NUM_NUM_STR, $content)
            ? null : 'SRV harus format: "prio weight port target".';
    } elseif ($type === 'CAA') {
        $error = preg_match('/^\d+\s+\S+\s+/', $content) ? null : 'CAA harus format: "flags tag value".';
    } elseif ($type === 'TXT' || $type === 'SPF') {
        $error = strlen($content) > 4096 ? 'Teks terlalu panjang (maksimal 4096 karakter).' : null;
    }
    return $error;
}

/**
 * Validate standard DNS records (A, AAAA, CNAME, MX, SRV, CAA, TXT, etc.).
 */
function validateStandardRecord(string $type, string $content): ?string
{
    if ($type === 'A' || $type === 'AAAA') {
        return validateIpRecord($type, $content);
    }
    if (in_array($type, ['CNAME', 'NS', 'PTR', 'ALIAS', 'DNAME'], true)) {
        return validateNameRecord($type, $content);
    }
    return validateSpecialRecord($type, $content);
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

    if (in_array($type, ['DS', 'CDS', 'DNSKEY', 'CDNSKEY', 'TLSA', 'SMIMEA', 'SSHFP', 'CERT', 'CSYNC'], true)) {
        $result = validateSecurityRecord($type, $content);
    } elseif (in_array($type, ['HTTPS', 'SVCB', 'URI', 'HINFO', 'RP'], true)) {
        $result = validateExtendedRecord($type, $content);
    } else {
        $result = validateStandardRecord($type, $content);
    }

    return $result;
}

function normalizeContent(string $type, string $content): string
{
    $type = strtoupper($type);
    $content = trim($content);
    $result = $content;

    if (in_array($type, ['CNAME', 'NS', 'PTR', 'ALIAS', 'DNAME', 'MX', 'SRV'], true)) {
        if ($type === 'MX' && preg_match('/^(\d+)\s+(\S+)$/', $content, $m)) {
            $result = $m[1] . ' ' . dnsCanonical($m[2]);
        } elseif ($type === 'SRV' && preg_match('/^(\d+)\s+(\d+)\s+(\d+)\s+(\S+)$/', $content, $m)) {
            $result = $m[1] . ' ' . $m[2] . ' ' . $m[3] . ' ' . dnsCanonical($m[4]);
        } elseif (in_array($type, ['CNAME', 'NS', 'PTR', 'ALIAS', 'DNAME'], true)) {
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
            ['[ID]', '[IP]', '[IP_DASH]', '[OCTET4]', '[DOMAIN]'],
            [(string) $i, $ip, $ipDash, (string) $i, $targetDomain],
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
            ['[ID]', '[HEX]', '[HEX16]', '[DOMAIN]'],
            [(string) $i, dechex($i), $hostHex, $targetDomain],
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
 * Mendukung pencocokan hierarkis fleksibel untuk IPv4 (/24, /16, /8) dan IPv6 (/64, /48, /32).
 */
function findMatchingReverseZone(string $ip): ?string
{
    $fullPtrFqdn = null;
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $fullPtrFqdn = ipv4ToPtrFqdn($ip);
    } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $fullPtrFqdn = ipv6ToPtrFqdn($ip);
    }

    if ($fullPtrFqdn === null) {
        return null;
    }

    $st = db()->query(
        "SELECT name FROM zones WHERE name LIKE '%.in-addr.arpa.' OR name LIKE '%.ip6.arpa.'"
    );
    if (!$st) {
        return null;
    }

    $bestMatch = null;
    $bestLen = 0;
    while ($row = $st->fetch()) {
        $zoneName = dnsCanonical((string) $row['name']);
        if ($fullPtrFqdn === $zoneName || str_ends_with($fullPtrFqdn, '.' . $zoneName)) {
            $len = strlen($zoneName);
            if ($len > $bestLen) {
                $bestLen = $len;
                $bestMatch = $zoneName;
            }
        }
    }
    return $bestMatch;
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
    $fullPtrFqdn = null;
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $fullPtrFqdn = ipv4ToPtrFqdn($ip);
    } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $fullPtrFqdn = ipv6ToPtrFqdn($ip);
    }

    if ($fullPtrFqdn === null) {
        return false;
    }

    $revZone = findMatchingReverseZone($ip);
    if ($revZone === null || !userCanZone($user, $revZone, true)) {
        return false;
    }

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
 * @return array<int, array<string, mixed>>
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

    if (in_array($type, ['CNAME', 'NS', 'PTR', 'ALIAS', 'DNAME'], true)) {
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

/**
 * Check and collect matching records within a single RRset.
 *
 * @param array<string, mixed> $rr
 * @return list<array{zone: string, name: string, type: string, ttl: int, content: string, disabled: bool}>
 */
function matchRrsetRecords(string $zoneName, array $rr, string $query): array
{
    $matches = [];
    $recName = (string) ($rr['name'] ?? '');
    $type = (string) ($rr['type'] ?? '');
    $ttl = (int) ($rr['ttl'] ?? 300);

    foreach ($rr['records'] ?? [] as $rec) {
        $content = (string) ($rec['content'] ?? '');
        if (stripos($content, $query) !== false || stripos($recName, $query) !== false) {
            $matches[] = [
                'zone' => $zoneName,
                'name' => $recName,
                'type' => $type,
                'ttl' => $ttl,
                'content' => $content,
                'disabled' => !empty($rec['disabled']),
            ];
        }
    }
    return $matches;
}

/**
 * Search matching records in a single authoritative zone.
 *
 * @return list<array{zone: string, name: string, type: string, ttl: int, content: string, disabled: bool}>
 */
function searchZoneRrsets(PdnsClient $pdns, string $zoneName, string $query, ?string $typeFilter): array
{
    $matches = [];
    try {
        $data = $pdns->zone($zoneName);
        $rrsets = is_array($data['rrsets'] ?? null) ? $data['rrsets'] : [];
        foreach ($rrsets as $rr) {
            if ($typeFilter !== null && strtoupper((string) $rr['type']) !== $typeFilter) {
                continue;
            }
            $matched = matchRrsetRecords($zoneName, $rr, $query);
            if (!empty($matched)) {
                array_push($matches, ...$matched);
            }
        }
    } catch (Throwable) {
        return [];
    }
    return $matches;
}

/**
 * Search records across authoritative zones by content or record name.
 *
 * @param array<int, string> $allowedZones
 * @return array<int, array{zone: string, name: string, type: string, ttl: int, content: string, disabled: bool}>
 */
function bulkSearchRecords(
    PdnsClient $pdns,
    string $query,
    ?string $typeFilter = null,
    array $allowedZones = []
): array {
    $query = trim($query);
    if ($query === '') {
        return [];
    }

    $allZones = $pdns->zones();
    $matches = [];
    $typeFilter = $typeFilter !== null && trim($typeFilter) !== '' ? strtoupper(trim($typeFilter)) : null;

    foreach ($allZones as $z) {
        $zoneName = dnsCanonical((string) $z['name']);
        if (!empty($allowedZones) && !in_array($zoneName, $allowedZones, true)) {
            continue;
        }

        $zoneMatches = searchZoneRrsets($pdns, $zoneName, $query, $typeFilter);
        if (!empty($zoneMatches)) {
            array_push($matches, ...$zoneMatches);
        }
    }

    return $matches;
}

/**
 * Build patched RRset list for a single zone.
 *
 * @param array<int, array<string, mixed>> $rrsets
 * @return array{patch: list<array<string, mixed>>, replaced: int}
 */
/**
 * Patch a single DNS record if its content matches target.
 *
 * @param array<string, mixed> $rec
 * @return array{record: array<string, mixed>, replaced: bool}
 */
function patchSingleRecord(array $rec, string $target, string $replacement): array
{
    $c = (string) ($rec['content'] ?? '');
    if ($c === $target || stripos($c, $target) !== false) {
        $newC = str_ireplace($target, $replacement, $c);
        return [
            'record' => [
                'content' => $newC,
                'disabled' => !empty($rec['disabled']),
            ],
            'replaced' => true,
        ];
    }
    return ['record' => $rec, 'replaced' => false];
}

/**
 * Patch records inside a single RRset.
 *
 * @param array<string, mixed> $rr
 * @return array{patch: ?array<string, mixed>, replaced: int}
 */
function patchSingleRrset(array $rr, string $targetContent, string $replacementContent): array
{
    $rrModified = false;
    $newRecords = [];
    $count = 0;

    foreach ($rr['records'] ?? [] as $rec) {
        $res = patchSingleRecord($rec, $targetContent, $replacementContent);
        $newRecords[] = $res['record'];
        if ($res['replaced']) {
            $rrModified = true;
            $count++;
        }
    }

    if (!$rrModified) {
        return ['patch' => null, 'replaced' => 0];
    }

    return [
        'patch' => [
            'name' => $rr['name'],
            'type' => $rr['type'],
            'ttl' => (int) ($rr['ttl'] ?? 300),
            'changetype' => 'REPLACE',
            'records' => $newRecords,
        ],
        'replaced' => $count,
    ];
}

/**
 * Build patched RRset list for a single zone.
 *
 * @param array<int, array<string, mixed>> $rrsets
 * @return array{patch: list<array<string, mixed>>, replaced: int}
 */
function buildPatchedZoneRrsets(
    array $rrsets,
    string $targetContent,
    string $replacementContent,
    ?string $typeFilter
): array {
    $patchRrsets = [];
    $modifiedInZone = 0;

    foreach ($rrsets as $rr) {
        if ($typeFilter !== null && strtoupper((string) $rr['type']) !== $typeFilter) {
            continue;
        }

        $res = patchSingleRrset($rr, $targetContent, $replacementContent);
        if ($res['patch'] !== null) {
            $patchRrsets[] = $res['patch'];
            $modifiedInZone += $res['replaced'];
        }
    }

    return ['patch' => $patchRrsets, 'replaced' => $modifiedInZone];
}

/**
 * Apply patched RRsets to a single zone with snapshot, cache invalidation, and auditing.
 *
 * @param array<string, mixed> $user
 * @param array<string, mixed> $currentData
 * @param list<array<string, mixed>> $patchRrsets
 * @param array{from: string, to: string} $replacePair
 */
function applyZoneBulkPatch(
    PdnsClient $pdns,
    array $user,
    string $zoneName,
    array $currentData,
    array $patchRrsets,
    int $modifiedInZone,
    array $replacePair
): void {
    $desc = 'Bulk Search & Replace: ' . $replacePair['from'] . ' -> ' . $replacePair['to'];
    saveZoneSnapshot($zoneName, $currentData, $user, $desc);
    $pdns->patchRrsets($zoneName, $patchRrsets);

    if (class_exists('AppCache')) {
        AppCache::invalidateZone($zoneName);
    }
    $auditMsg = "Mengganti {$modifiedInZone} record '{$replacePair['from']}' -> '{$replacePair['to']}'";
    audit($user, 'bulk_replace_records', $zoneName, $auditMsg);
    if (function_exists('dispatchWebhookEvent')) {
        dispatchWebhookEvent('record.updated', [
            'zone' => $zoneName,
            'action' => 'bulk_replace',
            'replaced' => $modifiedInZone,
        ]);
    }
}

/**
 * Process replace for a single zone.
 *
 * @param array<string, mixed> $user
 * @return array{modified: int, replaced: int, error: ?string}
 */
function processZoneBulkReplace(
    PdnsClient $pdns,
    array $user,
    string $zoneName,
    string $targetContent,
    string $replacementContent,
    ?string $typeFilter
): array {
    try {
        $currentData = $pdns->zone($zoneName);
        $rrsets = is_array($currentData['rrsets'] ?? null) ? $currentData['rrsets'] : [];
        $res = buildPatchedZoneRrsets($rrsets, $targetContent, $replacementContent, $typeFilter);
        if (!empty($res['patch'])) {
            applyZoneBulkPatch(
                $pdns,
                $user,
                $zoneName,
                $currentData,
                $res['patch'],
                $res['replaced'],
                ['from' => $targetContent, 'to' => $replacementContent]
            );
            return ['modified' => 1, 'replaced' => $res['replaced'], 'error' => null];
        }
        return ['modified' => 0, 'replaced' => 0, 'error' => null];
    } catch (Throwable $e) {
        return ['modified' => 0, 'replaced' => 0, 'error' => "Zona {$zoneName}: " . $e->getMessage()];
    }
}

/**
 * Execute mass atomic search and replace across specified or all managed zones.
 * Automatically saves zone snapshots prior to mutation for instant rollback.
 *
 * @param array<string, mixed> $user
 * @param array<int, string> $targetZones
 * @return array{zones_modified: int, records_replaced: int, errors: array<int, string>}
 */
function bulkReplaceRecords(
    PdnsClient $pdns,
    array $user,
    string $targetContent,
    string $replacementContent,
    ?string $typeFilter = null,
    array $targetZones = []
): array {
    $targetContent = trim($targetContent);
    $replacementContent = trim($replacementContent);
    if ($targetContent === '') {
        return ['zones_modified' => 0, 'records_replaced' => 0, 'errors' => ['Target konten pencarian kosong']];
    }

    $typeFilter = $typeFilter !== null && trim($typeFilter) !== '' ? strtoupper(trim($typeFilter)) : null;
    $allZones = $pdns->zones();
    $zonesModified = 0;
    $recordsReplaced = 0;
    $errors = [];

    foreach ($allZones as $z) {
        $zoneName = dnsCanonical((string) $z['name']);
        if (!empty($targetZones) && !in_array($zoneName, $targetZones, true)) {
            continue;
        }

        $res = processZoneBulkReplace($pdns, $user, $zoneName, $targetContent, $replacementContent, $typeFilter);
        $zonesModified += $res['modified'];
        $recordsReplaced += $res['replaced'];
        if ($res['error'] !== null) {
            $errors[] = $res['error'];
        }
    }

    return [
        'zones_modified' => $zonesModified,
        'records_replaced' => $recordsReplaced,
        'errors' => $errors,
    ];
}
