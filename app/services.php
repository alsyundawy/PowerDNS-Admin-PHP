<?php
declare(strict_types=1);

const RECORD_TYPES = ['A','AAAA','CNAME','MX','TXT','NS','SRV','PTR','CAA','TLSA','SSHFP','NAPTR','SPF','SOA'];

function user_can_zone(array $user, string $zone, bool $edit = false): bool
{
    if ($user['role'] === 'admin') {
        return true;
    }
    $name = dns_canonical($zone);
    $st = db()->prepare('SELECT z.id, zu.can_edit FROM zones z LEFT JOIN zone_user zu ON zu.zone_id = z.id AND zu.user_id = ? WHERE z.name = ?');
    $st->execute([(int) $user['id'], $name]);
    $row = $st->fetch();
    if ($row && ($edit ? (int) $row['can_edit'] === 1 : true) && $row['id']) {
        if ($row['can_edit'] !== null) {
            return true;
        }
    }
    $st = db()->prepare(
        'SELECT z.id, 1 AS can_edit FROM zones z
         JOIN account_user au ON au.account_id = z.account_id
         WHERE z.name = ? AND au.user_id = ?'
    );
    $st->execute([$name, (int) $user['id']]);
    return (bool) $st->fetch();
}

function require_zone_access(array $user, string $zone, bool $edit = false): void
{
    if (!user_can_zone($user, $zone, $edit)) {
        http_response_code(403);
        view('error', ['title' => 'Zona terkunci', 'message' => 'Anda tidak punya akses ke zona ini.', 'user' => $user]);
        exit;
    }
}

function sync_zones_from_pdns(PdnsClient $pdns): int
{
    $remote = $pdns->zones();
    $seen = [];
    $n = 0;
    $up = db()->prepare('INSERT INTO zones (name, kind, dnssec, serial, catalog, synced_at) VALUES (?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE kind = VALUES(kind), dnssec = VALUES(dnssec), serial = VALUES(serial), catalog = VALUES(catalog), synced_at = NOW()');
    foreach ($remote as $z) {
        if (!is_array($z) || empty($z['name'])) {
            continue;
        }
        $name = dns_canonical((string) $z['name']);
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

function validate_record(string $type, string $content): ?string
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
        'A' => filter_var($content, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? null : 'A harus IPv4.',
        'AAAA' => filter_var($content, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? null : 'AAAA harus IPv6.',
        'CNAME', 'NS', 'PTR' => preg_match('/^[A-Za-z0-9_.*-]+$/', rtrim($content, '.')) ? null : 'Nama host tidak valid.',
        'MX' => preg_match('/^\d{1,5}\s+\S+$/', $content) ? null : 'MX harus "prioritas hostname".',
        'SRV' => preg_match('/^\d+\s+\d+\s+\d+\s+\S+$/', $content) ? null : 'SRV harus "prio weight port target".',
        'CAA' => preg_match('/^\d+\s+\S+\s+/', $content) ? null : 'CAA harus "flags tag value".',
        'TXT', 'SPF' => strlen($content) > 4096 ? 'Teks terlalu panjang.' : null,
        default => null,
    };
}

function normalize_content(string $type, string $content): string
{
    $type = strtoupper($type);
    $content = trim($content);
    if (in_array($type, ['CNAME', 'NS', 'PTR', 'MX', 'SRV'], true)) {
        if ($type === 'MX' && preg_match('/^(\d+)\s+(\S+)$/', $content, $m)) {
            return $m[1] . ' ' . dns_canonical($m[2]);
        }
        if ($type === 'SRV' && preg_match('/^(\d+)\s+(\d+)\s+(\d+)\s+(\S+)$/', $content, $m)) {
            return $m[1] . ' ' . $m[2] . ' ' . $m[3] . ' ' . dns_canonical($m[4]);
        }
        if (in_array($type, ['CNAME', 'NS', 'PTR'], true)) {
            return dns_canonical($content);
        }
    }
    if ($type === 'TXT' && !str_starts_with($content, '"')) {
        return '"' . str_replace('"', '\\"', $content) . '"';
    }
    return $content;
}

/**
 * Build REPLACE/DELETE rrsets. DELETE must not include ttl.
 * Docs: https://doc.powerdns.com/authoritative/http-api/zone.html
 *
 * @param array<int,array{name:string,type:string,ttl:int,content:string,disabled:bool,comment:string}> $submitted
 */
function diff_rrsets(string $zone, array $currentRrsets, array $submitted): array
{
    $zone = dns_canonical($zone);
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
        $fqdn = dns_fqdn($row['name'], $zone);
        $key = strtolower($fqdn) . '|' . $type;
        $next[$key]['name'] = $fqdn;
        $next[$key]['type'] = $type;
        $next[$key]['ttl'] = max(30, (int) $row['ttl']);
        $next[$key]['records'][] = [
            'content' => normalize_content($type, $row['content']),
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
            'records' => [],
        ];
    }
    return $out;
}

function flatten_rrsets(array $rrsets, string $zone): array
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
                'name' => dns_relative((string) $set['name'], $zone),
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

function soa_of(array $zone): string
{
    foreach ($zone['rrsets'] ?? [] as $set) {
        if (($set['type'] ?? '') === 'SOA') {
            return (string) ($set['records'][0]['content'] ?? '');
        }
    }
    return '';
}
