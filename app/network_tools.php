<?php

/**
 * PowerDNS-Admin-PHP - Advanced Network Tools Engine
 * IP Calculator (IPv4/IPv6), IPv6 Subnet Splitter, WHOIS/RDAP, and DNS Record Lookup.
 * Logic adapted and enhanced from orion-lookingglass & standard RFCs (RFC 1035, 3596, 7480, 9082).
 */

declare(strict_types=1);

const RDAP_BASE_URL = 'https://rdap.org/';
const RDAP_TIMEOUT_SECS = 10;
const WHOIS_PORT = 43;
const WHOIS_TIMEOUT_SECS = 6;

/**
 * Determine IPv4 address class.
 */
function ipcalcGetIpv4Class(int $firstOctet): string
{
    if ($firstOctet < 128) {
        return 'Kelas A';
    }
    if ($firstOctet < 192) {
        return 'Kelas B';
    }
    if ($firstOctet < 224) {
        return 'Kelas C';
    }
    if ($firstOctet < 240) {
        return 'Kelas D (Multicast)';
    }
    return 'Kelas E (Eksperimental)';
}

/**
 * Determine IPv4 address scope/type.
 */
function ipcalcGetIpv4Scope(string $ip): string
{
    $long = ip2long($ip);
    if ($long === false) {
        return 'Invalid';
    }
    // 10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16
    if (
        ($long >= (int) ip2long('10.0.0.0') && $long <= (int) ip2long('10.255.255.255')) ||
        ($long >= (int) ip2long('172.16.0.0') && $long <= (int) ip2long('172.31.255.255')) ||
        ($long >= (int) ip2long('192.168.0.0') && $long <= (int) ip2long('192.168.255.255'))
    ) {
        return 'Private (RFC 1918)';
    }
    // 127.0.0.0/8
    if ($long >= (int) ip2long('127.0.0.0') && $long <= (int) ip2long('127.255.255.255')) {
        return 'Loopback (RFC 1122)';
    }
    // 100.64.0.0/10 (CGNAT)
    if ($long >= (int) ip2long('100.64.0.0') && $long <= (int) ip2long('100.127.255.255')) {
        return 'Shared / CGNAT (RFC 6598)';
    }
    return 'Public Internet';
}

/**
 * Calculate IPv4 Subnet parameters.
 * @return array<string, mixed>|null
 */
function ipcalcProcessIpv4(string $cidrInput): ?array
{
    $cidrInput = trim($cidrInput);
    if (!preg_match('#^(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})(?:/(\d{1,2}))?$#', $cidrInput, $m)) {
        return null;
    }

    $ip = $m[1];
    $cidr = isset($m[2]) ? (int) $m[2] : 32;

    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || $cidr < 0 || $cidr > 32) {
        return null;
    }

    $ipLong = ip2long($ip);
    if ($ipLong === false) {
        return null;
    }

    $maskLong = $cidr === 0 ? 0 : (-1 << (32 - $cidr));
    $networkLong = $ipLong & $maskLong;
    $broadcastLong = $networkLong | (~$maskLong & 0xFFFFFFFF);
    $octets = explode('.', $ip);
    $firstOctet = (int) $octets[0];

    $totalHosts = (float) (2 ** (32 - $cidr));
    $usableHosts = $cidr <= 30 ? max(0, (int) ($totalHosts - 2)) : ($cidr === 31 ? 2 : 1);

    if ($cidr <= 30) {
        $firstUsable = long2ip($networkLong + 1);
        $lastUsable = long2ip($broadcastLong - 1);
        $usableRange = $firstUsable . ' - ' . $lastUsable;
    } elseif ($cidr === 31) {
        $firstUsable = long2ip($networkLong);
        $lastUsable = long2ip($broadcastLong);
        $usableRange = $firstUsable . ' - ' . $lastUsable . ' (RFC 3021 Point-to-Point)';
    } else {
        $firstUsable = long2ip($networkLong);
        $lastUsable = long2ip($networkLong);
        $usableRange = $firstUsable . ' (Single Host)';
    }

    $revDns = implode('.', array_reverse($octets)) . '.in-addr.arpa.';

    return [
        'version' => 4,
        'ip' => $ip,
        'cidr' => $ip . '/' . $cidr,
        'mask' => $cidr,
        'network' => long2ip($networkLong),
        'netmask' => long2ip($maskLong),
        'wildcard' => long2ip(~$maskLong & 0xFFFFFFFF),
        'broadcast' => long2ip($broadcastLong),
        'usable_range' => $usableRange,
        'first_usable' => $firstUsable,
        'last_usable' => $lastUsable,
        'total_hosts' => (int) $totalHosts,
        'usable_hosts' => $usableHosts,
        'class' => ipcalcGetIpv4Class($firstOctet),
        'scope' => ipcalcGetIpv4Scope($ip),
        'reverse_dns' => $revDns,
        'binary_ip' => sprintf('%032b', $ipLong),
        'binary_mask' => sprintf('%032b', $maskLong),
    ];
}

/**
 * Expand and uncompress IPv6 address to full 8-group 32-hex string.
 */
function ipcalcUncompressIpv6(string $ip): string
{
    $packed = inet_pton($ip);
    if ($packed === false) {
        return '';
    }
    $hex = bin2hex($packed);
    return implode(':', str_split($hex, 4));
}

/**
 * Determine IPv6 address scope.
 */
function ipcalcGetIpv6Scope(string $ip): string
{
    $lower = strtolower($ip);
    if ($lower === '::1' || $lower === '0000:0000:0000:0000:0000:0000:0000:0001') {
        return 'Loopback (RFC 4291)';
    }
    if (str_starts_with($lower, 'fe80:')) {
        return 'Link-Local Unicast (RFC 4291)';
    }
    if (
        str_starts_with($lower, 'fc00:')
        || str_starts_with($lower, 'fd00:')
        || str_starts_with($lower, 'fc')
        || str_starts_with($lower, 'fd')
    ) {
        return 'Unique Local Address / ULA (RFC 4193)';
    }
    if (str_starts_with($lower, 'ff')) {
        return 'Multicast (RFC 4291)';
    }
    if (str_starts_with($lower, '2001:db8:') || str_starts_with($lower, '2001:0db8:')) {
        return 'Documentation (RFC 3849)';
    }
    return 'Global Unicast (2000::/3)';
}

/**
 * Calculate IPv6 Subnet parameters.
 * @return array<string, mixed>|null
 */
function ipcalcProcessIpv6(string $cidrInput): ?array
{
    $cidrInput = trim($cidrInput);
    if (!preg_match('#^([0-9a-fA-F:.]+)(?:/(\d{1,3}))?$#', $cidrInput, $m)) {
        return null;
    }

    $ip = $m[1];
    $cidr = isset($m[2]) ? (int) $m[2] : 128;

    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) || $cidr < 0 || $cidr > 128) {
        return null;
    }

    $uncompressed = ipcalcUncompressIpv6($ip);
    if ($uncompressed === '') {
        return null;
    }

    $hex = str_replace(':', '', $uncompressed);
    $revDns = implode('.', array_reverse(str_split($hex))) . '.ip6.arpa.';

    // Calculate network address based on CIDR
    $fullBin = '';
    for ($i = 0; $i < 32; $i++) {
        $fullBin .= sprintf('%04b', (int) hexdec($hex[$i]));
    }
    $netBin = substr($fullBin, 0, $cidr) . str_repeat('0', 128 - $cidr);
    $netHex = '';
    for ($i = 0; $i < 128; $i += 4) {
        $netHex .= dechex((int) bindec(substr($netBin, $i, 4)));
    }
    $netUncompressed = implode(':', str_split($netHex, 4));
    $packedNet = hex2bin($netHex);
    $compressedNet = $packedNet !== false ? (inet_ntop($packedNet) ?: $netUncompressed) : $netUncompressed;

    $totalSubnets64 = $cidr <= 64 ? number_format((float) (2 ** (64 - $cidr))) : '0';

    return [
        'version' => 6,
        'ip' => $ip,
        'cidr' => $ip . '/' . $cidr,
        'mask' => $cidr,
        'uncompressed' => $uncompressed,
        'compressed' => inet_ntop(inet_pton($ip) ?: '') ?: $ip,
        'network' => $compressedNet,
        'network_uncompressed' => $netUncompressed,
        'subnets_slash_64' => $totalSubnets64,
        'scope' => ipcalcGetIpv6Scope($uncompressed),
        'reverse_dns' => $revDns,
    ];
}

/**
 * Validate and sanitize IPv6 subnet for splitting.
 * @return array{ip: string, mask: int}|null
 */
function ipv6splitValidate(string $subnet): ?array
{
    $subnet = trim($subnet);
    if (!preg_match('#^([0-9a-fA-F:.]+)/(\d{1,3})$#', $subnet, $m)) {
        return null;
    }
    $ip = $m[1];
    $mask = (int) $m[2];
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        return null;
    }
    if ($mask < 1 || $mask > 128) {
        return null;
    }
    return ['ip' => $ip, 'mask' => $mask];
}

/**
 * Generate subnets using PHP Generator to prevent memory exhaustion.
 * Supports arbitrary bit boundaries (up to 16-bit split / 65,536 subnets).
 *
 * @return \Generator<int, string>
 */
function ipv6splitGenerate(string $baseIp, int $sourceMask, int $targetMask): \Generator
{
    if ($targetMask < $sourceMask || ($targetMask - $sourceMask) > 16 || $targetMask > 128) {
        return;
    }

    $diffBits = $targetMask - $sourceMask;
    $maxCount = 1 << $diffBits;

    $uncompressed = ipcalcUncompressIpv6($baseIp);
    $hex = str_replace(':', '', $uncompressed);

    // Convert full 32-hex to 128-bit binary string
    $fullBin = '';
    for ($i = 0; $i < 32; $i++) {
        $fullBin .= sprintf('%04b', (int) hexdec($hex[$i]));
    }

    // Zero out host bits beyond source mask
    $baseNetBin = substr($fullBin, 0, $sourceMask) . str_repeat('0', 128 - $sourceMask);

    for ($i = 0; $i < $maxCount; $i++) {
        $iBin = $diffBits > 0 ? sprintf('%0' . $diffBits . 'b', $i) : '';
        $subnetBin = substr($baseNetBin, 0, $sourceMask) . $iBin . substr($baseNetBin, $targetMask);

        $subnetHex = '';
        for ($j = 0; $j < 128; $j += 4) {
            $subnetHex .= dechex((int) bindec(substr($subnetBin, $j, 4)));
        }

        $packed = hex2bin($subnetHex);
        $formatted = $packed !== false ? inet_ntop($packed) : implode(':', str_split($subnetHex, 4));
        yield (string) ($formatted !== false ? $formatted : implode(':', str_split($subnetHex, 4))) . '/' . $targetMask;
    }
}

/**
 * Execute RDAP query over cURL with fallback.
 * @return array<string, mixed>
 */
function whoisQueryRdap(string $query): array
{
    $isIp = filter_var($query, FILTER_VALIDATE_IP) !== false;
    $url = RDAP_BASE_URL . ($isIp ? 'ip/' : 'domain/') . urlencode($query);

    $ch = curl_init($url);
    if ($ch === false) {
        return ['success' => false, 'error' => 'Inisialisasi cURL gagal.', 'data' => []];
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => RDAP_TIMEOUT_SECS,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_USERAGENT => 'PowerDNS-Admin-PHP/0.2.1 RDAP-Client',
        CURLOPT_HTTPHEADER => ['Accept: application/rdap+json, application/json'],
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $res = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($res === false || $err !== '') {
        return ['success' => false, 'error' => 'Kueri RDAP gagal: ' . $err, 'data' => []];
    }

    if ($httpCode !== 200) {
        return ['success' => false, 'error' => 'Server RDAP mengembalikan status HTTP ' . $httpCode, 'data' => []];
    }

    $json = json_decode((string) $res, true);
    if (!is_array($json)) {
        return ['success' => false, 'error' => 'Respons RDAP bukan format JSON yang valid.', 'data' => []];
    }

    return [
        'success' => true,
        'type' => $isIp ? 'IP Network' : 'Domain',
        'data' => $json,
    ];
}

/**
 * Query legacy Port 43 WHOIS via raw socket.
 * @return array<string, mixed>
 */
function whoisQuerySocket(string $query, ?string $server = null): array
{
    $targetServer = $server ?: 'whois.iana.org';
    $cleanQuery = trim(preg_replace('/[^a-zA-Z0-9.\-:]/', '', $query) ?? '');
    if ($cleanQuery === '') {
        return ['success' => false, 'server' => $targetServer, 'error' => 'Input kueri tidak valid.', 'raw' => ''];
    }

    $fp = @fsockopen($targetServer, WHOIS_PORT, $errno, $errstr, WHOIS_TIMEOUT_SECS);
    if (!$fp) {
        return [
            'success' => false,
            'server' => $targetServer,
            'error' => "Gagal terhubung ke $targetServer: $errstr ($errno)",
            'raw' => '',
        ];
    }

    stream_set_timeout($fp, WHOIS_TIMEOUT_SECS);
    fwrite($fp, $cleanQuery . "\r\n");

    $out = '';
    while (!feof($fp)) {
        $line = fgets($fp, 512);
        if ($line === false) {
            break;
        }
        $out .= $line;
        if (strlen($out) > 65536) {
            $out .= "\n[Output dipotong karena batas ukuran]";
            break;
        }
    }
    fclose($fp);
    return ['success' => true, 'server' => $targetServer, 'raw' => trim($out)];
}

/**
 * Perform comprehensive DNS Lookup using native PHP dns_get_record().
 * @return array<string, mixed>
 */
function dnsLookupAll(string $domain): array
{
    $domain = trim($domain);
    $domain = rtrim($domain, '.');
    if ($domain === '') {
        return ['status' => 'error', 'message' => 'Nama domain tidak boleh kosong.'];
    }

    $types = [
        'A' => DNS_A,
        'AAAA' => DNS_AAAA,
        'CNAME' => DNS_CNAME,
        'MX' => DNS_MX,
        'TXT' => DNS_TXT,
        'NS' => DNS_NS,
        'SOA' => DNS_SOA,
        'CAA' => DNS_CAA,
        'SRV' => DNS_SRV,
        'PTR' => DNS_PTR,
    ];

    $records = [];
    foreach ($types as $name => $const) {
        $res = @dns_get_record($domain, $const);
        if (is_array($res) && count($res) > 0) {
            foreach (processDnsRecords($name, $res) as $item) {
                $records[] = $item;
            }
        }
    }

    return [
        'status' => 'success',
        'domain' => $domain,
        'records' => $records,
    ];
}

/**
 * Clean and enrich DNS records with glue IPs and formatted fields.
 * @param array<int, array<string, mixed>> $items
 * @return array<int, array<string, mixed>>
 */
function processDnsRecords(string $type, array $items): array
{
    $out = [];
    foreach ($items as $rec) {
        unset($rec['class']);
        $value = match ($type) {
            'A' => (string) ($rec['ip'] ?? ''),
            'AAAA' => (string) ($rec['ipv6'] ?? ''),
            'MX' => (isset($rec['pri']) ? ($rec['pri'] . ' ') : '') . (string) ($rec['target'] ?? ''),
            'TXT' => (string) ($rec['txt'] ?? ''),
            'NS', 'CNAME', 'PTR' => (string) ($rec['target'] ?? ''),
            'SOA' => sprintf(
                '%s %s %s %s %s %s %s',
                (string) ($rec['mname'] ?? ''),
                (string) ($rec['rname'] ?? ''),
                (string) ($rec['serial'] ?? ''),
                (string) ($rec['refresh'] ?? ''),
                (string) ($rec['retry'] ?? ''),
                (string) ($rec['expire'] ?? ''),
                (string) ($rec['minimum-ttl'] ?? '')
            ),
            'CAA' => sprintf(
                '%s %s "%s"',
                (string) ($rec['flags'] ?? 0),
                (string) ($rec['tag'] ?? ''),
                (string) ($rec['value'] ?? '')
            ),
            'SRV' => sprintf(
                '%s %s %s %s',
                (string) ($rec['pri'] ?? 0),
                (string) ($rec['weight'] ?? 0),
                (string) ($rec['port'] ?? 0),
                (string) ($rec['target'] ?? '')
            ),
            default => (string) ($rec['ip'] ?? $rec['ipv6'] ?? $rec['target'] ?? $rec['txt'] ?? ''),
        };
        $rec['value'] = trim($value);

        if ($type === 'NS' && !empty($rec['target'])) {
            $target = (string) $rec['target'];
            $glueA = @dns_get_record($target, DNS_A);
            $glueAaaa = @dns_get_record($target, DNS_AAAA);
            $glueIpv4 = [];
            $glueIpv6 = [];
            if (is_array($glueA)) {
                foreach ($glueA as $g) {
                    if (!empty($g['ip'])) {
                        $glueIpv4[] = (string) $g['ip'];
                    }
                }
            }
            if (is_array($glueAaaa)) {
                foreach ($glueAaaa as $g) {
                    if (!empty($g['ipv6'])) {
                        $glueIpv6[] = (string) $g['ipv6'];
                    }
                }
            }
            $rec['glue_ipv4'] = $glueIpv4;
            $rec['glue_ipv6'] = $glueIpv6;
            $rec['glue_ips'] = count($glueIpv4) + count($glueIpv6) > 0
                ? implode(', ', array_merge($glueIpv4, $glueIpv6))
                : 'N/A';
        }
        $out[] = $rec;
    }
    return $out;
}
