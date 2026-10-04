<?php

declare(strict_types=1);

if (!defined('IDNA_DEFAULT')) {
    define('IDNA_DEFAULT', 0);
}
if (!defined('INTL_IDNA_VARIANT_UTS46')) {
    define('INTL_IDNA_VARIANT_UTS46', 1);
}

/**
 * DNS name helpers.
 * PowerDNS requires canonical names with a trailing dot.
 * Source: https://doc.powerdns.com/authoritative/http-api/zone.html
 */
function dnsCanonical(string $name): string
{
    $name = trim(strtolower($name));
    $name = rtrim($name, '.');
    if ($name === '') {
        return '';
    }
    if (function_exists('idn_to_ascii')) {
        $ascii = idn_to_ascii($name, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
        if ($ascii !== false) {
            $name = $ascii;
        }
    }
    return $name . '.';
}

function dnsDisplay(string $name): string
{
    $name = rtrim($name, '.');
    if ($name !== '' && function_exists('idn_to_utf8')) {
        $utf = idn_to_utf8($name, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
        if ($utf !== false) {
            return $utf;
        }
    }
    return $name;
}

function dnsFqdn(string $owner, string $zone): string
{
    $owner = trim($owner);
    $zone = dnsCanonical($zone);
    if ($owner === '' || $owner === '@') {
        return $zone;
    }
    if (str_ends_with(strtolower($owner), '.')) {
        return dnsCanonical($owner);
    }
    return dnsCanonical($owner . '.' . rtrim($zone, '.'));
}

function dnsRelative(string $fqdn, string $zone): string
{
    $fqdn = dnsCanonical($fqdn);
    $zone = dnsCanonical($zone);
    if ($fqdn === $zone) {
        return '@';
    }
    $suffix = '.' . $zone;
    if (str_ends_with($fqdn, $suffix)) {
        return dnsDisplay(substr($fqdn, 0, -strlen($suffix)));
    }
    return dnsDisplay($fqdn);
}

function isReverseZone(string $zone): bool
{
    $zone = strtolower(rtrim($zone, '.'));
    return str_ends_with($zone, '.in-addr.arpa') || str_ends_with($zone, '.ip6.arpa');
}

/**
 * Generate reverse zone name for a /24 subnet (e.g., "192.0.2.0/24" -> "2.0.192.in-addr.arpa.").
 */
function ipv4ToReverseZone24(string $ipOrCidr): ?string
{
    $ip = explode('/', trim($ipOrCidr))[0];
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return null;
    }
    $parts = explode('.', $ip);
    return sprintf('%d.%d.%d.in-addr.arpa.', (int) $parts[2], (int) $parts[1], (int) $parts[0]);
}

/**
 * Generate relative PTR record name within a /24 zone (4th octet).
 */
function ipv4ToRelativePtr24(string $ipv4): ?string
{
    if (!filter_var($ipv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return null;
    }
    $parts = explode('.', $ipv4);
    return (string) (int) $parts[3];
}

/**
 * Generate full canonical IPv4 PTR record FQDN (e.g., "15.2.0.192.in-addr.arpa.").
 */
function ipv4ToPtrFqdn(string $ipv4): ?string
{
    $rel = ipv4ToRelativePtr24($ipv4);
    $zone = ipv4ToReverseZone24($ipv4);
    return ($rel !== null && $zone !== null) ? $rel . '.' . $zone : null;
}

/**
 * Generate reverse zone name for an IPv6 /64 prefix (RFC 3596 Nibble Format).
 * Example: "2001:db8:1234:5678::/64" -> "8.7.6.5.4.3.2.1.8.b.d.0.1.0.0.2.ip6.arpa."
 */
function ipv6ToReverseZone64(string $ipv6OrPrefix): ?string
{
    $ip = explode('/', trim($ipv6OrPrefix))[0];
    $bin = @inet_pton($ip);
    if ($bin === false || strlen($bin) !== 16) {
        return null;
    }
    $hex = strtolower(bin2hex($bin));
    $first16Nibbles = substr($hex, 0, 16);
    $rev = array_reverse(str_split($first16Nibbles));
    return implode('.', $rev) . '.ip6.arpa.';
}

/**
 * Generate relative PTR record name within a /64 zone (reversed last 16 host nibbles).
 * Example: "2001:db8:1234:5678::1" -> "1.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0"
 */
function ipv6ToRelativePtr64(string $ipv6): ?string
{
    $bin = @inet_pton($ipv6);
    if ($bin === false || strlen($bin) !== 16) {
        return null;
    }
    $hex = strtolower(bin2hex($bin));
    $last16Nibbles = substr($hex, 16, 16);
    $rev = array_reverse(str_split($last16Nibbles));
    return implode('.', $rev);
}

/**
 * Generate full canonical IPv6 PTR record FQDN (32 reversed nibbles).
 */
function ipv6ToPtrFqdn(string $ipv6): ?string
{
    $bin = @inet_pton($ipv6);
    if ($bin === false || strlen($bin) !== 16) {
        return null;
    }
    $hex = strtolower(bin2hex($bin));
    $rev = array_reverse(str_split($hex));
    return implode('.', $rev) . '.ip6.arpa.';
}
