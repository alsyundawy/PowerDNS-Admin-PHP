<?php

declare(strict_types=1);

$isProtoHttps = isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
    && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https';
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || $isProtoHttps
    || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);

session_set_cookie_params([ // NOSONAR php:S2092
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Strict',
    'secure' => $isHttps,
]);
session_start();

if (is_file(dirname(__DIR__) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
} else {
    require_once dirname(__DIR__) . '/app/bootstrap.php';
    require_once dirname(__DIR__) . '/app/csrf.php';
    require_once dirname(__DIR__) . '/app/dns_name.php';
    require_once dirname(__DIR__) . '/app/network_tools.php';
    require_once dirname(__DIR__) . '/app/PdnsDnssecTrait.php';
    require_once dirname(__DIR__) . '/app/PdnsMetadataTrait.php';
    require_once dirname(__DIR__) . '/app/PdnsClient.php';
    require_once dirname(__DIR__) . '/app/services.php';
    require_once dirname(__DIR__) . '/app/handlers.php';
}

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cross-Origin-Opener-Policy: same-origin');
header('Cross-Origin-Resource-Policy: same-origin');
header(
    'Permissions-Policy: accelerometer=(), camera=(), geolocation=(), ' .
    'gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()'
);
header(
    "Content-Security-Policy: default-src 'self'; " .
    "style-src 'self' 'unsafe-inline'; " .
    "script-src 'self' 'unsafe-inline'; " .
    "img-src 'self' data:; font-src 'self'; " .
    "frame-ancestors 'none'; base-uri 'self'; form-action 'self'"
);
if ($isHttps) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rtrim($path, '/') ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (str_starts_with($path, '/api/')) {
    if (!installed()) {
        jsonOut(503, ['error' => 'Belum terpasang']);
    }
    $apiUser = apiUser();
    if (!$apiUser) {
        jsonOut(401, ['error' => 'API key tidak valid']);
    }
    handleApi($apiUser);
}

if (!installed()) {
    handleInstall();
    exit;
}

if ($path === '/nic/update') {
    handleDynDns();
    exit;
}

if ($path === '/login') {
    handleLogin();
    exit;
}
if ($path === '/logout' && $method === 'POST') {
    handleLogout();
}

$user = requireLogin();

if ($path === '/') {
    handleDashboard($user);
} elseif ($path === '/zones' && $method === 'GET') {
    handleZones($user);
} elseif ($path === '/zones/sync' && $method === 'POST') {
    handleZoneSync($user);
} elseif ($path === '/zones/new') {
    handleZoneCreate($user);
} elseif ($path === '/search') {
    handleSearch($user);
} elseif ($path === '/users' && $method === 'GET') {
    handleUsers($user);
} elseif ($path === '/users' && $method === 'POST') {
    handleUserSave($user);
} elseif ($path === '/accounts' && $method === 'GET') {
    handleAccounts($user);
} elseif ($path === '/accounts' && $method === 'POST') {
    handleAccountSave($user);
} elseif ($path === '/templates' && $method === 'GET') {
    handleTemplates($user);
} elseif ($path === '/templates' && $method === 'POST') {
    handleTemplateSave($user);
} elseif ($path === '/apikeys' && $method === 'GET') {
    handleApikeys($user);
} elseif ($path === '/apikeys' && $method === 'POST') {
    handleApikeyCreate($user);
} elseif ($path === '/audit') {
    handleAudit($user);
} elseif ($path === '/settings') {
    handleSettings($user);
} elseif (str_starts_with($path, '/tools/rdns')) {
    handleRdnsTool($user, $path, $method);
} elseif ($path === '/tools/ipcalc') {
    handleIpcalcTool($user, $path, $method);
} elseif ($path === '/tools/ipv6-splitter') {
    handleIpv6SplitterTool($user, $path, $method);
} elseif ($path === '/tools/whois') {
    handleWhoisTool($user, $path, $method);
} elseif ($path === '/tools/dns-lookup') {
    handleDnsLookupTool($user, $path, $method);
} elseif (preg_match('#^/zones/([^/]+)$#', $path, $m) && $method === 'GET') {
    handleZoneShow($user, $m[1]);
} elseif (preg_match('#^/zones/([^/]+)/save$#', $path, $m) && $method === 'POST') {
    handleZoneSave($user, $m[1]);
} elseif (preg_match('#^/zones/([^/]+)/delete$#', $path, $m) && $method === 'POST') {
    handleZoneDelete($user, $m[1]);
} elseif (preg_match('#^/zones/([^/]+)/(notify|axfr|rectify)$#', $path, $m) && $method === 'POST') {
    handleZoneAction($user, $m[1], $m[2]);
} elseif (preg_match('#^/zones/([^/]+)/grant$#', $path, $m) && $method === 'POST') {
    handleZoneGrant($user, $m[1]);
} elseif (preg_match('#^/zones/([^/]+)/dnssec$#', $path, $m) && $method === 'GET') {
    handleDnssec($user, $m[1]);
} elseif (preg_match('#^/zones/([^/]+)/dnssec$#', $path, $m) && $method === 'POST') {
    handleDnssecEnable($user, $m[1]);
} elseif (preg_match('#^/zones/([^/]+)/dnssec/cds$#', $path, $m) && $method === 'POST') {
    handleDnssecToggleCds($user, $m[1]);
} elseif (preg_match('#^/zones/([^/]+)/history$#', $path, $m) && $method === 'GET') {
    handleZoneHistory($user, $m[1]);
} elseif (preg_match('#^/zones/([^/]+)/rollback/(\d+)$#', $path, $m) && $method === 'POST') {
    handleZoneRollback($user, $m[1], $m[2]);
} elseif (preg_match('#^/zones/([^/]+)/export$#', $path, $m) && $method === 'GET') {
    handleZoneExport($user, $m[1]);
} else {
    http_response_code(404);
    view('error', ['title' => 'Tidak ditemukan', 'message' => 'Halaman tidak ada.', 'user' => $user]);
}
