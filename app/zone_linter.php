<?php

/**
 * Zone RFC Compliance & Sanity Diagnostics Engine.
 * Verifies DNS zone conformance against RFC 1035, RFC 1912, and RFC 2181.
 * Prevents apex CNAME conflicts, missing glue records, and invalid MX targets.
 */

declare(strict_types=1);

/**
 * Index zone RRsets for rapid cross-referencing.
 *
 * @param array<int, array<string, mixed>> $rrsets
 * @return array{0: array<string, bool>, 1: array<string, array<string, bool>>,
 *               2: array<string, string>, 3: list<string>, 4: array<string, list<string>>}
 */
function indexZoneRrsets(array $rrsets): array
{
    $names = [];
    $typesByName = [];
    $cnames = [];
    $nsTargets = [];
    $mxTargets = [];

    foreach ($rrsets as $rr) {
        $name = dnsCanonical((string) ($rr['name'] ?? ''));
        $type = strtoupper((string) ($rr['type'] ?? ''));
        $names[$name] = true;
        $typesByName[$name][$type] = true;

        foreach ($rr['records'] ?? [] as $rec) {
            $content = trim((string) ($rec['content'] ?? ''));
            if ($type === 'CNAME') {
                $cnames[$name] = dnsCanonical($content);
            } elseif ($type === 'NS') {
                $nsTargets[] = dnsCanonical($content);
            } elseif ($type === 'MX') {
                $parts = preg_split('/\s+/', $content, 2);
                $host = $parts[1] ?? $parts[0];
                $mxTargets[$name][] = dnsCanonical($host);
            }
        }
    }

    return [$names, $typesByName, $cnames, $nsTargets, $mxTargets];
}

/**
 * Check 1: Apex CNAME Conflict (RFC 1912 §2.4, RFC 2181 §10.1).
 *
 * @param array<string, array<string, bool>> $typesByName
 * @return list<array{severity: string, code: string, message: string, record_name: string, suggestion: string}>
 */
function lintCheckApexCname(string $zoneFqdn, array $typesByName): array
{
    if (!isset($typesByName[$zoneFqdn]['CNAME'])) {
        return [];
    }

    return [[
        'severity' => 'error',
        'code' => 'RFC-1912-APEX-CNAME',
        'message' => "Record CNAME pada apex zona ('{$zoneFqdn}') bertentangan dengan SOA dan NS.",
        'record_name' => $zoneFqdn,
        'suggestion' => 'Ganti CNAME apex dengan record ALIAS (PowerDNS native) atau record A/AAAA langsung.',
    ]];
}

/**
 * Check 2: CNAME Co-existence with Other Types at Same Name (RFC 2181 §10.1).
 *
 * @param array<string, array<string, bool>> $typesByName
 * @return list<array{severity: string, code: string, message: string, record_name: string, suggestion: string}>
 */
function lintCheckCnameCoexistence(string $zoneFqdn, array $typesByName): array
{
    $issues = [];
    foreach ($typesByName as $name => $types) {
        if ($name !== $zoneFqdn && isset($types['CNAME']) && count($types) > 1) {
            $otherTypes = array_diff(array_keys($types), ['CNAME']);
            $issues[] = [
                'severity' => 'error',
                'code' => 'RFC-2181-CNAME-COEXISTENCE',
                'message' => "Record CNAME pada '{$name}' tidak boleh berdampingan dengan "
                    . 'tipe record lain (' . implode(', ', $otherTypes) . ').',
                'record_name' => $name,
                'suggestion' => 'Hapus CNAME atau hapus record tipe lain pada nama host yang sama.',
            ];
        }
    }
    return $issues;
}

/**
 * Check 3: Missing In-Bailiwick Glue Records for NS (RFC 1035 §3.3.11).
 *
 * @param list<string> $nsTargets
 * @param array<string, array<string, bool>> $typesByName
 * @return list<array{severity: string, code: string, message: string, record_name: string, suggestion: string}>
 */
function lintCheckMissingGlue(string $zoneFqdn, array $nsTargets, array $typesByName): array
{
    $issues = [];
    foreach ($nsTargets as $nsTarget) {
        if (str_ends_with($nsTarget, '.' . $zoneFqdn) || $nsTarget === $zoneFqdn) {
            $hasA = !empty($typesByName[$nsTarget]['A']);
            $hasAaaa = !empty($typesByName[$nsTarget]['AAAA']);
            if (!$hasA && !$hasAaaa) {
                $issues[] = [
                    'severity' => 'warning',
                    'code' => 'RFC-1035-MISSING-GLUE',
                    'message' => "Nameserver internal '{$nsTarget}' belum memiliki glue record A atau AAAA dalam zona.",
                    'record_name' => $nsTarget,
                    'suggestion' => "Tambahkan record A atau AAAA untuk nameserver '{$nsTarget}'.",
                ];
            }
        }
    }
    return $issues;
}

/**
 * Check 4: MX Exchange Points to CNAME (RFC 2181 §10.3).
 *
 * @param array<string, list<string>> $mxTargets
 * @param array<string, string> $cnames
 * @return list<array{severity: string, code: string, message: string, record_name: string, suggestion: string}>
 */
function lintCheckMxCname(array $mxTargets, array $cnames): array
{
    $issues = [];
    foreach ($mxTargets as $mxOwner => $targets) {
        foreach ($targets as $target) {
            if (isset($cnames[$target])) {
                $issues[] = [
                    'severity' => 'warning',
                    'code' => 'RFC-2181-MX-CNAME',
                    'message' => "Exchange MX pada '{$mxOwner}' menunjuk ke host CNAME '{$target}'.",
                    'record_name' => $mxOwner,
                    'suggestion' => "Arahkan target MX langsung ke hostname A/AAAA ('{$cnames[$target]}') "
                        . 'bukan ke CNAME.',
                ];
            }
        }
    }
    return $issues;
}

/**
 * Check 5: Dangling Internal CNAME.
 *
 * @param array<string, string> $cnames
 * @param array<string, bool> $names
 * @return list<array{severity: string, code: string, message: string, record_name: string, suggestion: string}>
 */
function lintCheckDanglingCnames(string $zoneFqdn, array $cnames, array $names): array
{
    $issues = [];
    foreach ($cnames as $cnameOwner => $target) {
        if ((str_ends_with($target, '.' . $zoneFqdn) || $target === $zoneFqdn) && !isset($names[$target])) {
            $issues[] = [
                'severity' => 'warning',
                'code' => 'DNS-DANGLING-CNAME',
                'message' => "CNAME '{$cnameOwner}' menunjuk ke host internal '{$target}' "
                    . 'yang tidak terdaftar dalam zona ini.',
                'record_name' => $cnameOwner,
                'suggestion' => "Pastikan host tujuan '{$target}' dibuat di zona atau perbaiki target CNAME.",
            ];
        }
    }
    return $issues;
}

/**
 * Lint zone RRsets for RFC compliance and operational best practices.
 *
 * @param array<int, array<string, mixed>> $rrsets
 * @return array<int, array{severity: string, code: string, message: string, record_name: string, suggestion: string}>
 */
function lintZoneRrsets(string $zoneName, array $rrsets): array
{
    $zoneFqdn = dnsCanonical($zoneName);
    [$names, $typesByName, $cnames, $nsTargets, $mxTargets] = indexZoneRrsets($rrsets);

    return array_merge(
        lintCheckApexCname($zoneFqdn, $typesByName),
        lintCheckCnameCoexistence($zoneFqdn, $typesByName),
        lintCheckMissingGlue($zoneFqdn, $nsTargets, $typesByName),
        lintCheckMxCname($mxTargets, $cnames),
        lintCheckDanglingCnames($zoneFqdn, $cnames, $names)
    );
}
