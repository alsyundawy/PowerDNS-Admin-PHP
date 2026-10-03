<?php
declare(strict_types=1);

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Strict',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
]);
session_start();

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/csrf.php';
require dirname(__DIR__) . '/app/dns_name.php';
require dirname(__DIR__) . '/app/PdnsClient.php';
require dirname(__DIR__) . '/app/services.php';
require dirname(__DIR__) . '/app/handlers.php';

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Content-Security-Policy: default-src \'self\'; style-src \'self\'; script-src \'self\'; img-src \'self\' data:; frame-ancestors \'none\'');

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rtrim($path, '/') ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (str_starts_with($path, '/api/')) {
    if (!installed()) {
        json_out(503, ['error' => 'Belum terpasang']);
    }
    $apiUser = api_user();
    if (!$apiUser) {
        json_out(401, ['error' => 'API key tidak valid']);
    }
    handle_api($apiUser);
}

if (!installed()) {
    handle_install();
    exit;
}

if ($path === '/login') {
    handle_login();
    exit;
}
if ($path === '/logout' && $method === 'POST') {
    handle_logout();
}

$user = require_login();

if ($path === '/') {
    handle_dashboard($user);
} elseif ($path === '/zones' && $method === 'GET') {
    handle_zones($user);
} elseif ($path === '/zones/sync' && $method === 'POST') {
    handle_zone_sync($user);
} elseif ($path === '/zones/new') {
    handle_zone_create($user);
} elseif ($path === '/search') {
    handle_search($user);
} elseif ($path === '/users' && $method === 'GET') {
    handle_users($user);
} elseif ($path === '/users' && $method === 'POST') {
    handle_user_save($user);
} elseif ($path === '/accounts' && $method === 'GET') {
    handle_accounts($user);
} elseif ($path === '/accounts' && $method === 'POST') {
    handle_account_save($user);
} elseif ($path === '/templates' && $method === 'GET') {
    handle_templates($user);
} elseif ($path === '/templates' && $method === 'POST') {
    handle_template_save($user);
} elseif ($path === '/apikeys' && $method === 'GET') {
    handle_apikeys($user);
} elseif ($path === '/apikeys' && $method === 'POST') {
    handle_apikey_create($user);
} elseif ($path === '/audit') {
    handle_audit($user);
} elseif ($path === '/settings') {
    handle_settings($user);
} elseif (preg_match('#^/zones/([^/]+)$#', $path, $m) && $method === 'GET') {
    handle_zone_show($user, $m[1]);
} elseif (preg_match('#^/zones/([^/]+)/save$#', $path, $m) && $method === 'POST') {
    handle_zone_save($user, $m[1]);
} elseif (preg_match('#^/zones/([^/]+)/delete$#', $path, $m) && $method === 'POST') {
    handle_zone_delete($user, $m[1]);
} elseif (preg_match('#^/zones/([^/]+)/(notify|axfr|rectify)$#', $path, $m) && $method === 'POST') {
    handle_zone_action($user, $m[1], $m[2]);
} elseif (preg_match('#^/zones/([^/]+)/grant$#', $path, $m) && $method === 'POST') {
    handle_zone_grant($user, $m[1]);
} elseif (preg_match('#^/zones/([^/]+)/dnssec$#', $path, $m) && $method === 'GET') {
    handle_dnssec($user, $m[1]);
} elseif (preg_match('#^/zones/([^/]+)/dnssec$#', $path, $m) && $method === 'POST') {
    handle_dnssec_enable($user, $m[1]);
} else {
    http_response_code(404);
    view('error', ['title' => 'Tidak ditemukan', 'message' => 'Halaman tidak ada.', 'user' => $user]);
}
