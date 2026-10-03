<?php
declare(strict_types=1);

/**
 * PowerDNS-Admin-PHP core.
 * Native PHP 8.1+. No framework, no Python, no Node.
 */

function app_root(): string
{
    return dirname(__DIR__);
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function config_path(): string
{
    return app_root() . '/config.php';
}

function config(): array
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }
    $cfg = is_file(config_path()) ? require config_path() : [];
    return $cfg;
}

function installed(): bool
{
    $cfg = config();
    return !empty($cfg['installed']) && !empty($cfg['db']['name']);
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
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
    return $row['value'];
}

function setting_set(string $name, string $value): void
{
    $st = db()->prepare('INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)');
    $st->execute([$name, $value]);
}

function app_key(): string
{
    $key = (string) (config()['app_key'] ?? '');
    $raw = base64_decode($key, true);
    if ($raw === false || strlen($raw) < 32) {
        throw new RuntimeException('app_key tidak valid.');
    }
    return substr($raw, 0, 32);
}

function secret_encrypt(string $plain): string
{
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', app_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) {
        throw new RuntimeException('Gagal mengenkripsi rahasia.');
    }
    return base64_encode($iv . $tag . $cipher);
}

function secret_decrypt(string $encoded): string
{
    $raw = base64_decode($encoded, true);
    if ($raw === false || strlen($raw) < 28) {
        return '';
    }
    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $cipher = substr($raw, 28);
    $plain = openssl_decrypt($cipher, 'aes-256-gcm', app_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return $plain === false ? '' : $plain;
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64);
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

function take_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : null;
}

function current_user(): ?array
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

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        flash('warning', 'Sesi berakhir. Silakan masuk lagi.');
        redirect('/login');
    }
    return $user;
}

function require_role(array $user, array $roles): void
{
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        view('error', ['title' => 'Akses ditolak', 'message' => 'Peran Anda tidak boleh melakukan aksi ini.', 'user' => $user]);
        exit;
    }
}

function audit(?array $user, string $action, string $zone, string $detail): void
{
    $st = db()->prepare('INSERT INTO history (user_id, username, action, zone_name, detail, ip) VALUES (?, ?, ?, ?, ?, ?)');
    $st->execute([
        $user['id'] ?? null,
        $user['username'] ?? '',
        $action,
        $zone,
        $detail,
        client_ip(),
    ]);
}

function view(string $name, array $data = []): void
{
    $data['flash'] = $data['flash'] ?? take_flash();
    extract($data, EXTR_SKIP);
    $viewFile = app_root() . '/views/' . $name . '.php';
    if (!is_file($viewFile)) {
        http_response_code(500);
        echo 'View tidak ditemukan.';
        return;
    }
    ob_start();
    include $viewFile;
    $content = ob_get_clean();
    $layout = ($name === 'login' || $name === 'install') ? 'layout_bare' : 'layout';
    include app_root() . '/views/' . $layout . '.php';
}

function json_out(int $code, array $payload): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}
