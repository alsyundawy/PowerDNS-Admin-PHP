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
        'message' => "CNAME record at zone apex ('{$zoneFqdn}') conflicts with SOA and NS records.",
        'record_name' => $zoneFqdn,
        'suggestion' => 'Replace apex CNAME with native PowerDNS ALIAS record or direct A/AAAA records.',
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
                'message' => "CNAME record at '{$name}' cannot co-exist with "
                    . 'other record types (' . implode(', ', $otherTypes) . ').',
                'record_name' => $name,
                'suggestion' => 'Remove CNAME or delete other record types at the same hostname.',
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
                    'message' => "Internal nameserver '{$nsTarget}' lacks in-bailiwick glue A or AAAA records.",
                    'record_name' => $nsTarget,
                    'suggestion' => "Add an A or AAAA address record for nameserver '{$nsTarget}'.",
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
                    'message' => "MX exchange for '{$mxOwner}' points to CNAME target '{$target}'.",
                    'record_name' => $mxOwner,
                    'suggestion' => "Point MX target directly to canonical A/AAAA hostname ('{$cnames[$target]}') "
                        . 'rather than a CNAME.',
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
                'message' => "CNAME '{$cnameOwner}' points to internal target host '{$target}' "
                    . 'which does not exist in this zone.',
                'record_name' => $cnameOwner,
                'suggestion' => "Ensure target host '{$target}' is created in zone or correct the CNAME target.",
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
