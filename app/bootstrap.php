<?php

/**
 * PowerDNS-Admin-PHP core.
 * Native PHP 8.1+. No framework, no Python, no Node.
 */

declare(strict_types=1);

function appRoot(): string
{
    return dirname(__DIR__);
}

function e(string|int|float|null|Stringable $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function configPath(): string
{
    return appRoot() . '/config.php';
}

/**
 * @return array<string, mixed>
 */
function config(bool $reload = false): array
{
    static $cfg = null;
    if ($reload || $cfg === null) {
        // Use `require` (not `require_once`) so that forced reload re-evaluates
        // the file even if it was already included. `require_once` returns `true`
        // on the second call, making $cfg a boolean instead of the config array.
        $cfg = is_file(configPath()) ? (require configPath()) : [];
    }
    return is_array($cfg) ? $cfg : [];
}

function installed(): bool
{
    $cfg = config();
    return !empty($cfg['installed']) && !empty($cfg['db']['name']);
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = config()['db'] ?? [];
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $c['host'] ?? '127.0.0.1',
            (int) ($c['port'] ?? 3306),
            $c['name'] ?? '',
            $c['charset'] ?? 'utf8mb4'
        );
        $pdo = new PDO($dsn, (string) ($c['user'] ?? ''), (string) ($c['pass'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}

function setting(string $name, ?string $default = null): ?string
{
    $st = db()->prepare('SELECT value FROM settings WHERE name = ?');
    $st->execute([$name]);
    $row = $st->fetch();
    if (!$row) {
        return $default;
    }
    return (string) $row['value'];
}

function settingSet(string $name, string $value): void
{
    $st = db()->prepare(
        'INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)'
    );
    $st->execute([$name, $value]);
}

function appKey(): string
{
    $key = (string) (config()['appKey'] ?? '');
    $raw = base64_decode($key, true);
    if ($raw === false || strlen($raw) < 32) {
        throw new UnexpectedValueException('appKey tidak valid.');
    }
    return substr($raw, 0, 32);
}

function secretEncrypt(string $plain): string
{
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', appKey(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) {
        throw new UnexpectedValueException('Gagal mengenkripsi rahasia.');
    }
    return base64_encode($iv . $tag . $cipher);
}

function secretDecrypt(string $encoded): string
{
    $raw = base64_decode($encoded, true);
    if ($raw === false || strlen($raw) < 28) {
        return '';
    }
    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $cipher = substr($raw, 28);
    $plain = openssl_decrypt($cipher, 'aes-256-gcm', appKey(), OPENSSL_RAW_DATA, $iv, $tag);
    return $plain === false ? '' : $plain;
}

function clientIp(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $candidate = trim((string) $_SERVER['HTTP_CF_CONNECTING_IP']);
        if (filter_var($candidate, FILTER_VALIDATE_IP)) {
            $ip = $candidate;
        }
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
        $candidate = trim($parts[0]);
        if (filter_var($candidate, FILTER_VALIDATE_IP)) {
            $ip = $candidate;
        }
    }
    return substr($ip, 0, 64);
}

function redirect(string $path): never
{
    header('Location: ' . $path, true, 303);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * @return array{type: string, message: string}|null
 */
function takeFlash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    if (is_array($flash) && isset($flash['type'], $flash['message'])) {
        return [
            'type' => (string) $flash['type'],
            'message' => (string) $flash['message'],
        ];
    }
    return null;
}

/**
 * @return array<string, mixed>|null
 */
function currentUser(): ?array
{
    $id = $_SESSION['uid'] ?? null;
    if (!$id) {
        return null;
    }
    $st = db()->prepare('SELECT id, username, display_name, email, role, active FROM users WHERE id = ?');
    $st->execute([(int) $id]);
    $user = $st->fetch();
    if (!$user || !(int) $user['active']) {
        return null;
    }
    return $user;
}

/**
 * @return array<string, mixed>
 */
function requireLogin(): array
{
    $user = currentUser();
    if (!$user) {
        flash('warning', 'Sesi berakhir. Silakan masuk lagi.');
        redirect('/login');
    }
    return $user;
}

/**
 * @param array<string, mixed> $user
 * @param array<int, string> $roles
 */
function requireRole(array $user, array $roles): void
{
    if (!in_array($user['role'] ?? '', $roles, true)) {
        http_response_code(403);
        view('error', [
            'title' => 'Akses ditolak',
            'message' => 'Peran Anda tidak boleh melakukan aksi ini.',
            'user' => $user,
        ]);
        exit;
    }
}

/**
 * @param array<string, mixed>|null $user
 */
function audit(?array $user, string $action, string $zone, string $detail): void
{
    $st = db()->prepare(
        'INSERT INTO history (user_id, username, action, zone_name, detail, ip) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $st->execute([
        $user['id'] ?? null,
        $user['username'] ?? '',
        $action,
        $zone,
        $detail,
        clientIp(),
    ]);
}

/**
 * @param array<string, mixed> $data
 */
function view(string $name, array $data = []): void
{
    $data['flash'] = $data['flash'] ?? takeFlash();
    extract($data, EXTR_SKIP);
    $viewFile = appRoot() . '/views/' . $name . '.php';
    if (!is_file($viewFile)) {
        http_response_code(500);
        echo 'View tidak ditemukan.';
        return;
    }
    ob_start();
    include $viewFile;
    $content = (string) ob_get_clean();
    $layout = ($name === 'login' || $name === 'install') ? 'layout_bare' : 'layout';
    $layoutFile = appRoot() . '/views/' . $layout . '.php';
    (static function (string $file, string $content, array $viewData): void {
        extract($viewData, EXTR_SKIP);
        include $file;
    })($layoutFile, $content, $data);
}

/**
 * @param array<string, mixed> $payload
 */
function jsonOut(int $code, array $payload): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}
