<?php
declare(strict_types=1);

/**
 * DNS name helpers.
 * PowerDNS requires canonical names with a trailing dot.
 * Source: https://doc.powerdns.com/authoritative/http-api/zone.html
 */
function dns_canonical(string $name): string
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

function dns_display(string $name): string
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

function dns_fqdn(string $owner, string $zone): string
{
    $owner = trim($owner);
    $zone = dns_canonical($zone);
    if ($owner === '' || $owner === '@') {
        return $zone;
    }
    if (str_ends_with(strtolower($owner), '.')) {
        return dns_canonical($owner);
    }
    return dns_canonical($owner . '.' . rtrim($zone, '.'));
}

function dns_relative(string $fqdn, string $zone): string
{
    $fqdn = dns_canonical($fqdn);
    $zone = dns_canonical($zone);
    if ($fqdn === $zone) {
        return '@';
    }
    $suffix = '.' . $zone;
    if (str_ends_with($fqdn, $suffix)) {
        return dns_display(substr($fqdn, 0, -strlen($suffix)));
    }
    return dns_display($fqdn);
}

function is_reverse_zone(string $zone): bool
{
    $zone = strtolower(rtrim($zone, '.'));
    return str_ends_with($zone, '.in-addr.arpa') || str_ends_with($zone, '.ip6.arpa');
}
