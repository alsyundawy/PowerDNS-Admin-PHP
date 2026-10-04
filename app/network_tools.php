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
    $class = 'Class E (Experimental)';
    if ($firstOctet < 128) {
        $class = 'Class A';
    } elseif ($firstOctet < 192) {
        $class = 'Class B';
    } elseif ($firstOctet < 224) {
        $class = 'Class C';
    } elseif ($firstOctet < 240) {
        $class = 'Class D (Multicast)';
    }
    return $class;
}

/**
 * Determine IPv4 address scope/type using standard RFC bitwise hex bounds.
 */
function ipcalcGetIpv4Scope(string $ip): string
{
    $long = ip2long($ip);
    if ($long === false) {
        return 'Invalid';
    }
    $uLong = (int) sprintf('%u', $long);
    $scope = 'Public Internet';

    // 10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16 (RFC 1918)
    if (
        ($uLong >= 0x0A000000 && $uLong <= 0x0AFFFFFF) ||
        ($uLong >= 0xAC100000 && $uLong <= 0xAC1FFFFF) ||
        ($uLong >= 0xC0A80000 && $uLong <= 0xC0A8FFFF)
    ) {
        $scope = 'Private (RFC 1918)';
    } elseif ($uLong >= 0x7F000000 && $uLong <= 0x7FFFFFFF) {
        // 127.0.0.0/8 (RFC 1122)
        $scope = 'Loopback (RFC 1122)';
    } elseif ($uLong >= 0x64400000 && $uLong <= 0x647FFFFF) {
        // 100.64.0.0/10 (CGNAT RFC 6598)
        $scope = 'Shared / CGNAT (RFC 6598)';
    }

    return $scope;
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
    $ipLong = ip2long($ip);

    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || $cidr < 0 || $cidr > 32 || $ipLong === false) {
        return null;
    }

    $maskLong = $cidr === 0 ? 0 : (-1 << (32 - $cidr));
    $networkLong = $ipLong & $maskLong;
    $broadcastLong = $networkLong | (~$maskLong & 0xFFFFFFFF);
    $octets = explode('.', $ip);
    $firstOctet = (int) $octets[0];

    $totalHosts = (float) (2 ** (32 - $cidr));
    $usableHosts = 1;
    if ($cidr <= 30) {
        $usableHosts = max(0, (int) ($totalHosts - 2));
    } elseif ($cidr === 31) {
        $usableHosts = 2;
    }

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
    $scope = 'Global Unicast (2000::/3)';
    if ($lower === '::1' || $lower === '0000:0000:0000:0000:0000:0000:0000:0001') {
        $scope = 'Loopback (RFC 4291)';
    } elseif (str_starts_with($lower, 'fe80:')) {
        $scope = 'Link-Local Unicast (RFC 4291)';
    } elseif (
        str_starts_with($lower, 'fc00:')
        || str_starts_with($lower, 'fd00:')
        || str_starts_with($lower, 'fc')
        || str_starts_with($lower, 'fd')
    ) {
        $scope = 'Unique Local Address / ULA (RFC 4193)';
    } elseif (str_starts_with($lower, 'ff')) {
        $scope = 'Multicast (RFC 4291)';
    } elseif (str_starts_with($lower, '2001:db8:') || str_starts_with($lower, '2001:0db8:')) {
        $scope = 'Documentation (RFC 3849)';
    }
    return $scope;
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
    $uncompressed = ipcalcUncompressIpv6($ip);

    if (
        !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ||
        $cidr < 0 ||
        $cidr > 128 ||
        $uncompressed === ''
    ) {
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
    $compressedNet = $netUncompressed;
    if ($packedNet !== false) {
        $ntop = inet_ntop($packedNet);
        if ($ntop !== false && $ntop !== '') {
            $compressedNet = $ntop;
        }
    }

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
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) || $mask < 1 || $mask > 128) {
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

    $prefixBin = substr($fullBin, 0, $sourceMask);
    $suffixZeros = str_repeat('0', 128 - $targetMask);

    for ($i = 0; $i < $maxCount; $i++) {
        $subnetBits = sprintf('%0' . $diffBits . 'b', $i);
        $fullSubnetBin = $prefixBin . $subnetBits . $suffixZeros;

        // Convert 128-bit binary back to hex
        $subHex = '';
        for ($b = 0; $b < 128; $b += 4) {
            $subHex .= dechex((int) bindec(substr($fullSubnetBin, $b, 4)));
        }

        $packed = hex2bin($subHex);
        $compressed = $packed !== false ? inet_ntop($packed) : '';
        if ($compressed !== false && $compressed !== '') {
            yield $compressed . '/' . $targetMask;
        } else {
            yield implode(':', str_split($subHex, 4)) . '/' . $targetMask;
        }
    }
}

/**
 * Execute HTTP cURL request to RDAP server and decode JSON response.
 *
 * @return array{success: bool, data?: array<string, mixed>, error?: string}
 */
function fetchRdapJson(string $url): array
{
    $ch = curl_init($url);
    if ($ch === false) {
        return ['success' => false, 'error' => 'cURL initialization failed.'];
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => RDAP_TIMEOUT_SECS,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_USERAGENT => 'PowerDNS-Admin-PHP/0.3.0 RDAP-Client',
        CURLOPT_HTTPHEADER => ['Accept: application/rdap+json, application/json'],
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $res = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $errorMsg = null;
    if ($res === false || $err !== '') {
        $errorMsg = 'RDAP query failed: ' . $err;
    } elseif ($httpCode !== 200) {
        $errorMsg = 'RDAP server returned HTTP status ' . $httpCode;
    } else {
        $json = json_decode((string) $res, true);
        if (!is_array($json)) {
            $errorMsg = 'RDAP response is not a valid JSON format.';
        }
    }

    if ($errorMsg !== null) {
        return ['success' => false, 'error' => $errorMsg];
    }

    /** @var array<string, mixed> $json */
    return ['success' => true, 'data' => $json];
}

/**
 * Execute RDAP query over cURL with fallback.
 * @return array<string, mixed>
 */
function whoisQueryRdap(string $query): array
{
    $isIp = filter_var($query, FILTER_VALIDATE_IP) !== false;
    $url = RDAP_BASE_URL . ($isIp ? 'ip/' : 'domain/') . urlencode($query);

    $res = fetchRdapJson($url);
    if (!$res['success']) {
        return ['success' => false, 'error' => $res['error'] ?? 'RDAP query failed.', 'data' => []];
    }

    return [
        'success' => true,
        'type' => $isIp ? 'IP Network' : 'Domain',
        'data' => (array) ($res['data'] ?? []),
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
        return ['success' => false, 'server' => $targetServer, 'error' => 'Invalid query input.', 'raw' => ''];
    }

    $fp = @fsockopen($targetServer, WHOIS_PORT, $errno, $errstr, WHOIS_TIMEOUT_SECS);
    if (!$fp) {
        return [
            'success' => false,
            'server' => $targetServer,
            'error' => "Failed to connect to $targetServer: $errstr ($errno)",
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
            $out .= "\n[Output truncated due to size limit]";
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
        return ['status' => 'error', 'message' => 'Domain name cannot be empty.'];
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
 * Format string representation for specific DNS record type.
 *
 * @param array<string, mixed> $rec
 */
function formatDnsRecordValue(string $type, array $rec): string
{
    return match ($type) {
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
}

/**
 * Resolve IPv4 and IPv6 glue records for nameserver hostname.
 *
 * @return array{glue_ipv4: list<string>, glue_ipv6: list<string>, glue_ips: string}
 */
function resolveNsGlue(string $target): array
{
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

    $totalGlue = count($glueIpv4) + count($glueIpv6);
    return [
        'glue_ipv4' => $glueIpv4,
        'glue_ipv6' => $glueIpv6,
        'glue_ips' => $totalGlue > 0 ? implode(', ', array_merge($glueIpv4, $glueIpv6)) : 'N/A',
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
        $rec['value'] = trim(formatDnsRecordValue($type, $rec));

        if ($type === 'NS' && !empty($rec['target'])) {
            $glue = resolveNsGlue((string) $rec['target']);
            $rec['glue_ipv4'] = $glue['glue_ipv4'];
            $rec['glue_ipv6'] = $glue['glue_ipv6'];
            $rec['glue_ips'] = $glue['glue_ips'];
        }
        $out[] = $rec;
    }
    return $out;
}
