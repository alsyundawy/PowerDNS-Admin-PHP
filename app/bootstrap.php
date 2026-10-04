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
        $path = configPath();
        $loaded = is_file($path) ? (require_once $path) : [];
        if (is_array($loaded)) {
            $cfg = $loaded;
        } elseif (!is_array($cfg)) {
            $cfg = [];
        }
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
    try {
        $st = db()->prepare('SELECT value FROM settings WHERE name = ?');
        $st->execute([$name]);
        $row = $st->fetch();
        if (!$row) {
            return $default;
        }
        return (string) $row['value'];
    } catch (Throwable) {
        return $default;
    }
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
        throw new UnexpectedValueException('appKey is invalid.');
    }
    return substr($raw, 0, 32);
}

function secretEncrypt(string $plain): string
{
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', appKey(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) {
        throw new UnexpectedValueException('Failed to encrypt secret.');
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

function ensureUserAvatarColumn(): void
{
    ensureEnterpriseSchema();
}

function ensureEnterpriseSchema(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;
    try {
        $st = db()->query("SHOW COLUMNS FROM users LIKE 'avatar_url'");
        if ($st && $st->rowCount() === 0) {
            db()->exec("ALTER TABLE users ADD COLUMN avatar_url VARCHAR(255) NOT NULL DEFAULT '' AFTER email");
        }
        $stTotp = db()->query("SHOW COLUMNS FROM users LIKE 'totp_secret'");
        if ($stTotp && $stTotp->rowCount() === 0) {
            db()->exec("ALTER TABLE users
                ADD COLUMN totp_secret VARCHAR(64) NULL AFTER password_hash,
                ADD COLUMN totp_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER totp_secret,
                ADD COLUMN totp_backup_codes TEXT NULL AFTER totp_enabled");
        }
        db()->exec("CREATE TABLE IF NOT EXISTS pdns_servers (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
          name VARCHAR(128) NOT NULL,
          api_url VARCHAR(255) NOT NULL,
          api_key_encrypted TEXT NOT NULL,
          server_id VARCHAR(64) NOT NULL DEFAULT 'localhost',
          is_default TINYINT(1) NOT NULL DEFAULT 0,
          is_active TINYINT(1) NOT NULL DEFAULT 1,
          latency_ms INT NULL,
          last_check_at DATETIME NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          UNIQUE KEY uq_pdns_server_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        db()->exec("CREATE TABLE IF NOT EXISTS webhooks (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
          name VARCHAR(128) NOT NULL,
          url VARCHAR(512) NOT NULL,
          secret VARCHAR(128) NOT NULL,
          events VARCHAR(255) NOT NULL DEFAULT 'zone.created,zone.deleted,record.updated',
          is_active TINYINT(1) NOT NULL DEFAULT 1,
          last_status_code INT NULL,
          last_error VARCHAR(255) NULL,
          last_triggered_at DATETIME NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        db()->exec("CREATE TABLE IF NOT EXISTS dyndns_tokens (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
          name VARCHAR(128) NOT NULL,
          token_hash CHAR(64) NOT NULL,
          hostname VARCHAR(255) NOT NULL,
          record_type ENUM('A','AAAA','BOTH') NOT NULL DEFAULT 'BOTH',
          user_id INT UNSIGNED NOT NULL,
          last_ip VARCHAR(64) NULL,
          last_update_at DATETIME NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          UNIQUE KEY uq_dyndns_token (token_hash),
          KEY idx_dyndns_host (hostname),
          KEY idx_dyndns_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable) {
        // Table might not exist yet or running in unit tests, safely ignore
    }
}

/**
 * @param array<string, mixed>|null $user
 */
function userAvatar(?array $user): string
{
    if ($user && !empty($user['avatar_url'])) {
        return (string) $user['avatar_url'];
    }
    return '';
}

function appName(): string
{
    $name = setting('app_name');
    return ($name !== null && trim($name) !== '') ? trim($name) : 'PowerDNS Admin';
}

function appLogoUrl(): ?string
{
    $logo = setting('app_logo_url');
    return ($logo !== null && trim($logo) !== '') ? trim($logo) : null;
}

function appFooterText(): string
{
    $footer = setting('app_footer_text');
    if ($footer !== null && trim($footer) !== '') {
        return trim($footer);
    }
    return 'PowerDNS-Admin-PHP &bull; Native High-Performance DNS Panel';
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
    ensureUserAvatarColumn();
    $st = db()->prepare('SELECT id, username, display_name, email, avatar_url, role, active FROM users WHERE id = ?');
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
        flash('warning', 'Session expired. Please sign in again.');
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
            'title' => 'Access Denied',
            'message' => 'Your role is not authorized to perform this action.',
            'user' => $user,
        ]);
        exit;
    }
}

/**
 * Recursively sanitize and mask sensitive secrets, passwords, and tokens.
 */
function appRedactSensitive(mixed $data): mixed
{
    if (!is_array($data)) {
        return $data;
    }
    $sensitivePattern = '/(pass|password|hash|secret|token|api_key|authorization|bearer|cookie|session|credential)/i';
    $sanitized = [];
    foreach ($data as $k => $v) {
        if (is_string($k) && preg_match($sensitivePattern, $k)) {
            $sanitized[$k] = '[REDACTED]';
        } elseif (is_array($v)) {
            $sanitized[$k] = appRedactSensitive($v);
        } else {
            $sanitized[$k] = $v;
        }
    }
    return $sanitized;
}

/**
 * Global custom logger sink handler (primarily for testing and custom log pipelines).
 *
 * @param (callable(array<string, mixed>): void)|null|false $handler False to read, null to clear, callable to set.
 * @return (callable(array<string, mixed>): void)|null
 */
function customLoggerSink(callable|null|false $handler = false): ?callable
{
    /** @var (callable(array<string, mixed>): void)|null $sink */
    static $sink = null;
    if ($handler !== false) {
        $sink = $handler;
    }
    return $sink;
}

/**
 * @param (callable(array<string, mixed>): void)|null $handler
 */
function setCustomLoggerHandler(?callable $handler): void
{
    customLoggerSink($handler);
}

/**
 * Structured enterprise multi-channel logger.
 * Supports channels: application, api, pdns_api, security, audit, auth,
 * authorization, backup, restore, import, export, database, performance, system, debug, warning, error.
 *
 * @param array<string, mixed> $context
 */
function appLogger(string $channel, string $level, string $message, array $context = []): void
{
    $normalizedChannel = strtolower(trim($channel));
    $validChannels = [
        'application', 'api', 'pdns_api', 'security', 'audit', 'auth',
        'authorization', 'backup', 'restore', 'import', 'export', 'database',
        'performance', 'system', 'debug', 'warning', 'error',
    ];
    if (!in_array($normalizedChannel, $validChannels, true)) {
        $normalizedChannel = 'application';
    }

    $record = [
        'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        'channel' => $normalizedChannel,
        'level' => strtoupper($level),
        'message' => $message,
        'context' => appRedactSensitive($context),
        'client_ip' => clientIp(),
    ];

    $sink = customLoggerSink();
    if (is_callable($sink)) {
        $sink($record);
        return;
    }

    $json = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json !== false) {
        error_log('[PDNS-ADMIN][' . $normalizedChannel . '] ' . $json);
    }
}

/**
 * @param array<string, mixed> $context
 */
function logSecurity(string $message, array $context = [], string $level = 'WARNING'): void
{
    appLogger('security', $level, $message, $context);
}

/**
 * @param array<string, mixed> $context
 */
function logAuth(string $message, array $context = [], string $level = 'INFO'): void
{
    appLogger('auth', $level, $message, $context);
}

/**
 * @param array<string, mixed> $context
 */
function logApi(string $message, array $context = [], string $level = 'INFO'): void
{
    appLogger('api', $level, $message, $context);
}

/**
 * @param array<string, mixed> $context
 */
function logPdns(string $message, array $context = [], string $level = 'INFO'): void
{
    appLogger('pdns_api', $level, $message, $context);
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

    appLogger('audit', 'INFO', $action, [
        'user' => $user['username'] ?? 'anonymous',
        'zone' => $zone,
        'detail' => $detail,
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
        echo 'View not found.';
        return;
    }
    ob_start();
    include_once $viewFile;
    $content = (string) ob_get_clean();
    $layout = ($name === 'login' || $name === 'install') ? 'layout_bare' : 'layout';
    $layoutFile = appRoot() . '/views/' . $layout . '.php';
    (static function (string $file, string $content, array $viewData): void {
        extract($viewData, EXTR_SKIP);
        include_once $file;
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
