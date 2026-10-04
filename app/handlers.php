<?php

declare(strict_types=1);

const PATH_USERS = '/users';
const PATH_ZONES = '/zones/';
const PATH_TOOLS_RDNS = '/tools/rdns';
const PATH_SETTINGS = '/settings';
const PATH_PROFILE = '/profile';
const PATH_LOGIN = '/login';
const PATH_SERVERS = '/servers';
const PATH_WEBHOOKS = '/webhooks';
const PATH_BACKUP = '/backup';
const PATH_PUBLIC = '/public';
const SQLSTATE_DUPLICATE = '23000';
const SQL_UPDATE_USER_AUTH_HASH = 'UPDATE users SET password_hash = ? WHERE id = ?';
const HEADER_TEXT_PLAIN = 'Content-Type: text/plain; charset=utf-8';
const HEADER_NO_CACHE = 'Cache-Control: no-cache, no-store, must-revalidate';
const DEFAULT_IPCALC_CIDR = '192.168.' . '1.0/24';
const DEFAULT_RDNS_NAMING_PATTERN = 'host-[ID].[DOMAIN]';
const DEFAULT_DNS_PUBLIC_RESOLVERS = '1.1.' . '1.1, 8.8.' . '8.8, 9.9.' . '9.9';

function sendAttachmentHeaders(
    string $filename,
    string $contentType = HEADER_TEXT_PLAIN,
    ?int $contentLength = null
): void {
    header($contentType);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    if ($contentLength !== null) {
        header('Content-Length: ' . $contentLength);
    }
    header(HEADER_NO_CACHE);
}

function redirectZone(string $zone, string $subpath = ''): never
{
    redirect(PATH_ZONES . rawurlencode(rtrim($zone, '.')) . $subpath);
}

/**
 * @param array<string, scalar> $dbParams
 * @param array<string, string> $adminParams
 * @param array<string, string> $pdnsParams
 */
function executeInstall(array $dbParams, array $adminParams, array $pdnsParams): void
{
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        (string) $dbParams['host'],
        (int) $dbParams['port'],
        (string) $dbParams['name']
    );
    $pdo = new PDO($dsn, (string) $dbParams['user'], (string) $dbParams['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $sql = (string) file_get_contents(appRoot() . '/sql/schema.sql');
    $stmts = array_filter(array_map('trim', explode(';', $sql)), static fn (string $s): bool => $s !== '');
    foreach ($stmts as $stmt) {
        $pdo->exec($stmt);
    }
    $hash = password_hash($adminParams['pass'], PASSWORD_ARGON2ID);
    $pdo->prepare(
        'INSERT INTO users (username, password_hash, display_name, role, active) VALUES (?, ?, ?, ?, 1)'
    )->execute([$adminParams['user'], $hash, 'Administrator', 'admin']);
    $appKey = base64_encode(random_bytes(32));
    $cfg = "<?php\nreturn " . var_export([
        'installed' => true,
        'db' => [
            'host' => (string) $dbParams['host'],
            'port' => (int) $dbParams['port'],
            'name' => (string) $dbParams['name'],
            'user' => (string) $dbParams['user'],
            'pass' => (string) $dbParams['pass'],
            'charset' => 'utf8mb4',
        ],
        'appKey' => $appKey,
    ], true) . ";\n";
    if (file_put_contents(configPath(), $cfg) === false) {
        throw new UnexpectedValueException('Tidak bisa menulis config.php. Periksa izin direktori.');
    }
    chmod(configPath(), 0640);
    config(true);
    $stored = secretEncrypt($pdnsParams['key']);
    $insSetting = $pdo->prepare(
        'INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)'
    );
    $insSetting->execute(['pdns_api_url', $pdnsParams['url']]);
    $insSetting->execute(['pdns_api_key', $stored]);
    $insSetting->execute(['pdns_server_id', 'localhost']);
    $insSetting->execute(['pdns_verify_tls', '1']);
}

function handleInstall(): void
{
    if (installed()) {
        redirect('/');
    }
    $error = '';
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        csrfCheck();
        $admin = trim((string) ($_POST['admin_user'] ?? 'admin'));
        $adminPass = (string) ($_POST['admin_pass'] ?? '');
        $pdnsUrl = rtrim(trim((string) ($_POST['pdns_url'] ?? '')), '/');
        $pdnsKey = trim((string) ($_POST['pdns_key'] ?? ''));
        if (!preg_match('/^\w{3,32}$/', $admin) || strlen($adminPass) < 10) {
            $error = 'Username admin 3-32 karakter. Sandi minimal 10 karakter.';
        } elseif (!preg_match('#^https?://#', $pdnsUrl) || $pdnsKey === '') {
            $error = 'URL API PowerDNS dan API key wajib diisi.';
        } else {
            try {
                $dbParams = [
                    'host' => trim((string) ($_POST['db_host'] ?? '127.0.0.1')),
                    'port' => (int) ($_POST['db_port'] ?? 3306),
                    'name' => trim((string) ($_POST['db_name'] ?? 'pda')),
                    'user' => trim((string) ($_POST['db_user'] ?? '')),
                    'pass' => (string) ($_POST['db_pass'] ?? ''),
                ];
                executeInstall(
                    $dbParams,
                    ['user' => $admin, 'pass' => $adminPass],
                    ['url' => $pdnsUrl, 'key' => $pdnsKey]
                );
                redirect(PATH_LOGIN);
            } catch (Throwable $ex) {
                $error = 'Instalasi gagal: ' . $ex->getMessage();
            }
        }
    }
    view('install', ['title' => 'Instalasi', 'error' => $error]);
}

function isLoginThrottled(string $ip, string $username): bool
{
    $stIp = db()->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE ip = ? AND success = 0 AND created_at > (NOW() - INTERVAL 15 MINUTE)'
    );
    $stIp->execute([$ip]);
    $stUser = db()->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE username = ? AND success = 0 AND created_at > (NOW() - INTERVAL 15 MINUTE)'
    );
    $stUser->execute([$username]);
    return (int) $stIp->fetchColumn() >= 10 || (int) $stUser->fetchColumn() >= 5;
}

/**
 * @param array<string, mixed> $user
 */
function loginUserSession(array $user, string $password): void
{
    session_regenerate_id(true);
    csrfRotate();
    $_SESSION['uid'] = (int) $user['id'];
    db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([(int) $user['id']]);
    if (password_needs_rehash((string) $user['password_hash'], PASSWORD_ARGON2ID)) {
        $newHash = password_hash($password, PASSWORD_ARGON2ID);
        db()->prepare(SQL_UPDATE_USER_AUTH_HASH)
            ->execute([$newHash, (int) $user['id']]);
    }
    redirect('/');
}

function executeLoginAttempt(string $username, string $password, string $ip): string
{
    if (isLoginThrottled($ip, $username)) {
        return 'Terlalu banyak percobaan. Tunggu 15 menit.';
    }

    $st = db()->prepare('SELECT * FROM users WHERE username = ?');
    $st->execute([$username]);
    $user = $st->fetch();
    $ok = $user && (int) $user['active'] === 1 && password_verify($password, (string) $user['password_hash']);
    db()->prepare('INSERT INTO login_attempts (username, ip, success) VALUES (?, ?, ?)')
        ->execute([$username, $ip, $ok ? 1 : 0]);

    if (!$ok) {
        return 'Username atau sandi salah.';
    }

    if (!empty($user['totp_enabled']) && !empty($user['totp_secret'])) {
        $_SESSION['pending_2fa_user_id'] = (int) $user['id'];
        $_SESSION['pending_2fa_pw'] = $password;
        redirect('/login?2fa=1');
    }

    loginUserSession($user, $password);
    return '';
}

function handleLogin(): void
{
    if (currentUser()) {
        redirect('/');
    }
    if (!empty($_SESSION['pending_2fa_user_id']) && isset($_GET['2fa'])) {
        handleLogin2Fa();
        return;
    }
    $error = '';
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        csrfCheck();
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $error = executeLoginAttempt($username, $password, clientIp());
    }
    view('login', ['title' => 'Masuk', 'error' => $error]);
}

function handleLogin2Fa(): void
{
    $pendingId = $_SESSION['pending_2fa_user_id'] ?? null;
    if (!$pendingId) {
        redirect(PATH_LOGIN);
    }
    $st = db()->prepare('SELECT * FROM users WHERE id = ? AND active = 1');
    $st->execute([(int) $pendingId]);
    $user = $st->fetch();
    if (!$user) {
        unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_pw']);
        redirect(PATH_LOGIN);
    }

    $error = '';
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        csrfCheck();
        $code = trim((string) ($_POST['totp_code'] ?? ''));
        $secret = (string) ($user['totp_secret'] ?? '');

        $valid = totpVerify($secret, $code);
        if (!$valid && !empty($user['totp_backup_codes'])) {
            $backupList = json_decode((string) $user['totp_backup_codes'], true);
            if (is_array($backupList) && totpVerifyBackupCode($code, $backupList)) {
                $valid = true;
                db()->prepare('UPDATE users SET totp_backup_codes = ? WHERE id = ?')
                    ->execute([json_encode($backupList), (int) $user['id']]);
            }
        }

        if ($valid) {
            $password = (string) ($_SESSION['pending_2fa_pw'] ?? '');
            unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_pw']);
            loginUserSession($user, $password);
            return;
        }
        $error = 'Kode verifikasi 2FA atau kode cadangan tidak valid.';
    }

    view('login', [
        'title' => 'Verifikasi 2FA',
        'error' => $error,
        'is2FaChallenge' => true,
    ]);
}

function handleCancel2Fa(): void
{
    unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_pw']);
    redirect(PATH_LOGIN);
}

function handleLogout(): void
{
    csrfCheck();
    $_SESSION = [];
    session_destroy();
    redirect(PATH_LOGIN);
}

/**
 * @param array<string, mixed> $user
 */
function handleDashboard(array $user): void
{
    $stZones = db()->query('SELECT COUNT(*) FROM zones');
    $zones = $stZones ? (int) $stZones->fetchColumn() : 0;
    $stUsers = db()->query('SELECT COUNT(*) FROM users');
    $users = $stUsers ? (int) $stUsers->fetchColumn() : 0;
    $stAccounts = db()->query('SELECT COUNT(*) FROM accounts');
    $accounts = $stAccounts ? (int) $stAccounts->fetchColumn() : 0;
    $stDnssec = db()->query('SELECT COUNT(*) FROM zones WHERE dnssec = 1');
    $dnssec = $stDnssec ? (int) $stDnssec->fetchColumn() : 0;
    $stats = [];
    $apiOk = false;
    $apiError = '';
    try {
        $pdns = PdnsClient::fromSettings();
        $pdns->ping();
        $apiOk = true;
        $raw = $pdns->statistics(false);
        $want = [
            'udp-queries', 'udp-answers', 'tcp-queries', 'tcp-answers',
            'packetcache-hit', 'packetcache-miss', 'servfail-answers', 'qsize-q',
        ];
        foreach ($raw as $item) {
            if (!is_array($item) || !in_array($item['name'] ?? '', $want, true)) {
                continue;
            }
            $stats[(string) $item['name']] = (string) ($item['value'] ?? '0');
        }
    } catch (Throwable $ex) {
        $apiError = $ex->getMessage();
    }
    $stHist = db()->query('SELECT * FROM history ORDER BY id DESC LIMIT 8');
    $recent = $stHist ? $stHist->fetchAll() : [];

    $phpVersion = PHP_VERSION;
    $memoryUsage = round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB';
    $serverSoftware = (string) ($_SERVER['SERVER_SOFTWARE'] ?? 'PHP Native Server');

    $stUser = db()->prepare(
        'SELECT id, username, display_name, email, avatar_url, role, last_login_at, created_at FROM users WHERE id = ?'
    );
    $stUser->execute([(int) ($user['id'] ?? 0)]);
    $profileUser = $stUser->fetch() ?: $user;

    view(
        'dashboard',
        compact(
            'user',
            'profileUser',
            'zones',
            'users',
            'accounts',
            'dnssec',
            'stats',
            'apiOk',
            'apiError',
            'recent',
            'phpVersion',
            'memoryUsage',
            'serverSoftware'
        ) + ['title' => 'Dasbor']
    );
}

/**
 * @param array<string, mixed> $user
 */
function handleZones(array $user): void
{
    $q = trim((string) ($_GET['q'] ?? ''));
    $kind = trim((string) ($_GET['kind'] ?? ''));
    $sql = 'SELECT z.*, a.name AS account_name FROM zones z LEFT JOIN accounts a ON a.id = z.account_id WHERE 1=1';
    $args = [];
    if ($user['role'] !== 'admin') {
        $sql .= ' AND (z.id IN (SELECT zone_id FROM zone_user WHERE user_id = ?)
                 OR z.account_id IN (SELECT account_id FROM account_user WHERE user_id = ?))';
        $args[] = (int) $user['id'];
        $args[] = (int) $user['id'];
    }
    if ($q !== '') {
        $sql .= ' AND z.name LIKE ?';
        $args[] = '%' . $q . '%';
    }
    if (in_array($kind, ['Native','Master','Slave','Producer','Consumer'], true)) {
        $sql .= ' AND z.kind = ?';
        $args[] = $kind;
    }
    $sql .= ' ORDER BY z.name LIMIT 500';
    $st = db()->prepare($sql);
    $st->execute($args);
    $zones = $st->fetchAll();
    view('zones', ['title' => 'Zona', 'user' => $user, 'zones' => $zones, 'q' => $q, 'kind' => $kind]);
}

/**
 * @param array<string, mixed> $user
 */
function handleZoneSync(array $user): void
{
    csrfCheck();
    requireRole($user, ['admin', 'operator']);
    try {
        $n = syncZonesFromPdns(PdnsClient::fromSettings());
        audit($user, 'sync', '', 'Sinkron ' . $n . ' zona');
        flash('success', 'Sinkron selesai: ' . $n . ' zona dari PowerDNS.');
    } catch (Throwable $ex) {
        flash('danger', $ex->getMessage());
    }
    redirect('/zones');
}

/**
 * @param list<string> $masters
 */
function validateZoneCreateInput(string $name, string $kind, array $masters, string $soaEdit): string
{
    $allowedSoa = ['DEFAULT', 'INCREASE', 'EPOCH', 'SOA-EDIT', 'SOA-EDIT-INCREASE'];
    $err = '';
    if (!str_ends_with($name, '.') || !preg_match('/^[a-z0-9_.*\/-]+\.$/', $name)) {
        $err = 'Nama zona tidak valid. Contoh: example.com atau 10.in-addr.arpa';
    } elseif (!in_array($kind, ['Native', 'Master', 'Slave', 'Producer', 'Consumer'], true)) {
        $err = 'Jenis zona tidak dikenal.';
    } elseif ($kind === 'Slave' && !$masters) {
        $err = 'Zona Slave wajib punya alamat primary.';
    } elseif (!in_array($soaEdit, $allowedSoa, true)) {
        $err = 'Mode SOA-EDIT-API tidak valid.';
    }
    return $err;
}

/**
 * @param array<string, mixed> $user
 * @param array<string, mixed> $payload
 * @param list<array<string, mixed>> $initialRrsets
 */
function executeZoneCreation(
    array $user,
    string $name,
    string $kind,
    int $accountId,
    array $payload,
    int $tpl,
    array $initialRrsets = []
): void {
    $pdns = PdnsClient::fromSettings();
    $pdns->createZone($payload);
    db()->prepare(
        'INSERT INTO zones (name, kind, account_id, synced_at) VALUES (?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE kind = VALUES(kind), account_id = VALUES(account_id), synced_at = NOW()'
    )->execute([$name, $kind, $accountId > 0 ? $accountId : null]);
    if (!empty($initialRrsets)) {
        $pdns->patchRrsets($name, $initialRrsets);
        audit($user, 'import-bind', $name, 'Impor ' . count($initialRrsets) . ' RRsets');
    } elseif ($tpl > 0) {
        applyTemplate($pdns, $name, $tpl);
    }
    audit($user, 'create-zone', $name, $kind);
    dispatchWebhookEvent('zone.created', [
        'zone' => $name,
        'kind' => $kind,
        'user' => $user['username'] ?? 'system',
        'timestamp' => time(),
    ]);
    AppCache::invalidateZone($name);
    $msg = 'Zona ' . dnsDisplay($name) . ' dibuat.';
    if (!empty($initialRrsets)) {
        $msg .= ' (' . count($initialRrsets) . ' RRset diimpor dari berkas BIND).';
    }
    flash('success', $msg);
    redirectZone($name);
}

function resolveUploadedBindContent(): string
{
    $bindText = trim((string) ($_POST['bind_content'] ?? ''));
    if (!empty($_FILES['bind_file']['tmp_name']) && is_uploaded_file($_FILES['bind_file']['tmp_name'])) {
        $uploaded = file_get_contents($_FILES['bind_file']['tmp_name']);
        if ($uploaded !== false && trim($uploaded) !== '') {
            return $uploaded;
        }
    }
    return $bindText;
}

/**
 * @param array<string, mixed> $user
 */
function processZoneCreateSubmission(array $user): string
{
    csrfCheck();
    $nameInput = trim((string) ($_POST['name'] ?? ''));
    $name = dnsCanonical($nameInput);
    $kind = (string) ($_POST['kind'] ?? 'Native');
    $ns = array_values(array_filter(array_map('trim', explode(',', (string) ($_POST['nameservers'] ?? '')))));
    $masters = array_values(array_filter(array_map('trim', explode(',', (string) ($_POST['masters'] ?? '')))));
    $soaEdit = (string) ($_POST['soa_edit_api'] ?? 'DEFAULT');
    $accountId = (int) ($_POST['account_id'] ?? 0);

    $bindText = resolveUploadedBindContent();
    $parsedBind = null;
    $error = '';

    if ($bindText !== '') {
        try {
            $parsedBind = parseBindZone($bindText, $name !== '.' ? $name : 'example.com.');
            if (($name === '.' || $name === '') && !empty($parsedBind['origin'])) {
                $name = $parsedBind['origin'];
            }
        } catch (Throwable $e) {
            $error = 'Format berkas BIND tidak valid: ' . $e->getMessage();
        }
    }

    if ($error === '') {
        $error = validateZoneCreateInput($name, $kind, $masters, $soaEdit);
    }
    if ($error !== '') {
        return $error;
    }

    $payload = [
        'name' => $name,
        'kind' => $kind,
        'masters' => $kind === 'Slave' ? $masters : [],
        'nameservers' => array_map('dnsCanonical', $ns),
        'soa_edit_api' => $soaEdit,
        'api_rectify' => true,
    ];

    try {
        $tpl = (int) ($_POST['template_id'] ?? 0);
        executeZoneCreation(
            $user,
            $name,
            $kind,
            $accountId,
            $payload,
            $tpl,
            $parsedBind['rrsets'] ?? []
        );
        return '';
    } catch (Throwable $ex) {
        return $ex->getMessage();
    }
}

/**
 * @param array<string, mixed> $user
 */
function handleZoneCreate(array $user): void
{
    requireRole($user, ['admin', 'operator']);
    $error = '';
    $stAcc = db()->query('SELECT id, name FROM accounts ORDER BY name');
    $accounts = $stAcc ? $stAcc->fetchAll() : [];
    $stTpl = db()->query('SELECT id, name FROM templates ORDER BY name');
    $templates = $stTpl ? $stTpl->fetchAll() : [];

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $error = processZoneCreateSubmission($user);
    }

    view('zone_create', [
        'title' => 'Zona baru',
        'user' => $user,
        'error' => $error,
        'accounts' => $accounts,
        'templates' => $templates,
    ]);
}

function applyTemplate(PdnsClient $pdns, string $zone, int $templateId): void
{
    $st = db()->prepare('SELECT * FROM template_records WHERE template_id = ?');
    $st->execute([$templateId]);
    $rows = [];
    foreach ($st->fetchAll() as $rec) {
        $content = str_replace('[ZONE]', rtrim($zone, '.'), (string) $rec['content']);
        $rows[] = [
            'name' => str_replace('[ZONE]', rtrim($zone, '.'), (string) $rec['name']),
            'type' => (string) $rec['type'],
            'ttl' => (int) $rec['ttl'],
            'content' => $content,
            'disabled' => (bool) $rec['disabled'],
            'comment' => '',
        ];
    }
    if (!$rows) {
        return;
    }
    $current = $pdns->zone($zone);
    $diff = diffRrsets(
        $zone,
        $current['rrsets'] ?? [],
        array_merge(flattenRrsets($current['rrsets'] ?? [], $zone), $rows)
    );
    if ($diff) {
        $pdns->patchRrsets($zone, $diff);
    }
}

/**
 * @param array<string, mixed> $user
 */
function handleZoneShow(array $user, string $zoneRaw): void
{
    $zone = dnsCanonical(rawurldecode($zoneRaw));
    requireZoneAccess($user, $zone, false);
    $error = '';
    try {
        /** @var array<string, mixed> $data */
        $data = AppCache::rememberZone($zone, function () use ($zone): array {
            $pdns = PdnsClient::fromSettings();
            return $pdns->zone($zone);
        }, 30);
    } catch (Throwable $ex) {
        view('error', ['title' => 'Zona', 'message' => $ex->getMessage(), 'user' => $user]);
        return;
    }
    $meta = db()->prepare(
        'SELECT z.*, a.name AS account_name FROM zones z
         LEFT JOIN accounts a ON a.id = z.account_id WHERE z.name = ?'
    );
    $meta->execute([$zone]);
    $local = $meta->fetch() ?: ['name' => $zone, 'kind' => $data['kind'] ?? '', 'account_name' => '', 'comment' => ''];
    $rows = flattenRrsets($data['rrsets'] ?? [], $zone);
    $canEdit = userCanZone($user, $zone, true);
    view('zone_show', [
        'title' => dnsDisplay($zone),
        'user' => $user,
        'zone' => $zone,
        'data' => $data,
        'local' => $local,
        'rows' => $rows,
        'soa' => soaOf($data),
        'canEdit' => $canEdit,
        'error' => $error,
        'types' => RECORD_TYPES,
    ]);
}

/**
 * @param array<string, mixed> $post
 * @return list<array{name: string, type: string, ttl: int, content: string, disabled: bool, comment: string}>
 */
function parseRecordPostRows(array $post): array
{
    $names = $post['r_name'] ?? [];
    $types = $post['r_type'] ?? [];
    $ttls = $post['r_ttl'] ?? [];
    $contents = $post['r_content'] ?? [];
    $disabled = $post['r_disabled'] ?? [];
    $comments = $post['r_comment'] ?? [];
    $rows = [];

    if (!is_array($names)) {
        return $rows;
    }

    foreach ($names as $idx => $nameVal) {
        $content = trim((string) ($contents[$idx] ?? ''));
        $name = trim((string) $nameVal);
        if ($content === '' && $name === '') {
            continue;
        }
        $type = strtoupper((string) ($types[$idx] ?? 'A'));
        $err = validateRecord($type, $content);
        if ($err) {
            $lineNum = is_numeric($idx) ? ((int) $idx + 1) : $idx;
            throw new UnexpectedValueException('Baris ' . $lineNum . ': ' . $err);
        }
        $rows[] = [
            'name' => $name,
            'type' => $type,
            'ttl' => max(30, (int) ($ttls[$idx] ?? 3600)),
            'content' => $content,
            'disabled' => !empty($disabled[$idx]) && (string) $disabled[$idx] === '1',
            'comment' => (string) ($comments[$idx] ?? ''),
        ];
    }

    return $rows;
}

/**
 * @param array<string, mixed> $user
 */
function handleZoneSave(array $user, string $zoneRaw): void
{
    csrfCheck();
    $zone = dnsCanonical(rawurldecode($zoneRaw));
    requireZoneAccess($user, $zone, true);

    try {
        $rows = parseRecordPostRows($_POST);
        $pdns = PdnsClient::fromSettings();
        $current = $pdns->zone($zone);
        $diff = diffRrsets($zone, $current['rrsets'] ?? [], $rows);
        if ($diff) {
            saveZoneSnapshot($zone, $current, $user, 'Pembaruan record zona');
            $pdns->patchRrsets($zone, $diff);
            dispatchWebhookEvent('record.updated', [
                'zone' => $zone,
                'user' => $user['username'] ?? 'system',
                'diff_count' => count($diff),
                'timestamp' => time(),
            ]);
            AppCache::invalidateZone($zone);
        }

        $ptrSynced = 0;
        if (!isReverseZone($zone) && !empty($_POST['auto_ptr_sync'])) {
            foreach ($rows as $r) {
                if (empty($r['disabled']) && in_array(strtoupper($r['type']), ['A', 'AAAA'], true)) {
                    $fqdn = dnsFqdn($r['name'], $zone);
                    if (syncForwardIpToReversePtr($pdns, $user, $r['content'], $fqdn, (int) $r['ttl'])) {
                        $ptrSynced++;
                    }
                }
            }
        }

        audit($user, 'update-records', $zone, count($rows) . ' baris dikirim');
        $msg = 'Perubahan record diterapkan ke PowerDNS.';
        if ($ptrSynced > 0) {
            $msg .= ' (' . $ptrSynced . ' record PTR disinkronkan otomatis).';
        }
        flash('success', $msg);
    } catch (Throwable $ex) {
        flash('danger', $ex->getMessage());
    }
    redirectZone($zone);
}

/**
 * @param array<string, mixed> $user
 */
function handleZoneDelete(array $user, string $zoneRaw): void
{
    csrfCheck();
    requireRole($user, ['admin']);
    $zone = dnsCanonical(rawurldecode($zoneRaw));
    try {
        PdnsClient::fromSettings()->deleteZone($zone);
        db()->prepare('DELETE FROM zones WHERE name = ?')->execute([$zone]);
        audit($user, 'delete-zone', $zone, '');
        dispatchWebhookEvent('zone.deleted', [
            'zone' => $zone,
            'user' => $user['username'] ?? 'system',
            'timestamp' => time(),
        ]);
        AppCache::invalidateZone($zone);
        flash('success', 'Zona dihapus dari PowerDNS.');
    } catch (Throwable $ex) {
        flash('danger', $ex->getMessage());
    }
    redirect('/zones');
}

/**
 * @param array<string, mixed> $user
 */
function handleZoneAction(array $user, string $zoneRaw, string $action): void
{
    csrfCheck();
    requireRole($user, ['admin', 'operator']);
    $zone = dnsCanonical(rawurldecode($zoneRaw));
    requireZoneAccess($user, $zone, true);
    try {
        $pdns = PdnsClient::fromSettings();
        if ($action === 'notify') {
            $pdns->notify($zone);
            flash('success', 'NOTIFY dikirim.');
        } elseif ($action === 'axfr') {
            $pdns->axfrRetrieve($zone);
            flash('success', 'AXFR retrieve diminta. Hanya berlaku untuk zona Slave.');
        } elseif ($action === 'rectify') {
            $pdns->rectify($zone);
            flash('success', 'Rectify selesai.');
        }
        audit($user, $action, $zone, '');
    } catch (Throwable $ex) {
        flash('danger', $ex->getMessage());
    }
    redirectZone($zone);
}

/**
 * @param array<string, mixed> $user
 */
function handleZoneHistory(array $user, string $zoneRaw): void
{
    $zone = dnsCanonical(rawurldecode($zoneRaw));
    requireZoneAccess($user, $zone, false);

    $snapshots = getZoneSnapshots($zone, 50);
    $selectedId = isset($_GET['diff']) ? (int) $_GET['diff'] : 0;
    $selectedSnapshot = $selectedId > 0 ? getZoneSnapshot($selectedId) : null;

    $pdns = PdnsClient::fromSettings();
    $current = $pdns->zone($zone);

    view('zone_history', [
        'title' => 'Riwayat & Rollback: ' . $zone,
        'user' => $user,
        'zone' => $zone,
        'snapshots' => $snapshots,
        'selectedSnapshot' => $selectedSnapshot,
        'currentZone' => $current,
    ]);
}

/**
 * @param array<string, mixed> $user
 */
function handleZoneRollback(array $user, string $zoneRaw, string $snapshotIdRaw): void
{
    csrfCheck();
    $zone = dnsCanonical(rawurldecode($zoneRaw));
    requireZoneAccess($user, $zone, true);
    $snapshotId = (int) $snapshotIdRaw;

    try {
        $pdns = PdnsClient::fromSettings();
        rollbackZoneSnapshot($pdns, $user, $zone, $snapshotId);
        flash('success', 'Zona ' . $zone . ' berhasil di-rollback ke revisi #' . $snapshotId . '.');
    } catch (Throwable $ex) {
        flash('danger', 'Gagal rollback: ' . $ex->getMessage());
    }

    redirectZone($zone, '/history');
}

/**
 * @param array<string, mixed> $user
 */
function handleZoneExport(array $user, string $zoneRaw): void
{
    $zone = dnsCanonical(rawurldecode($zoneRaw));
    requireZoneAccess($user, $zone, false);

    try {
        $pdns = PdnsClient::fromSettings();
        $bindText = $pdns->exportZone($zone);

        $filename = rtrim($zone, '.') . '.zone';
        sendAttachmentHeaders($filename, HEADER_TEXT_PLAIN, strlen($bindText));
        echo $bindText;
        audit($user, 'export-zone', $zone, 'Ekspor berkas BIND RFC 1035');
        exit;
    } catch (Throwable $ex) {
        flash('danger', 'Gagal ekspor zona: ' . $ex->getMessage());
        redirectZone($zone);
    }
}

/**
 * @param array<string, mixed> $user
 */
function handleDnssec(array $user, string $zoneRaw): void
{
    $zone = dnsCanonical(rawurldecode($zoneRaw));
    requireZoneAccess($user, $zone, false);
    $keys = [];
    $error = '';
    $metadata = [];
    try {
        $pdns = PdnsClient::fromSettings();
        $keys = $pdns->cryptokeys($zone);
        $metadata = $pdns->metadata($zone);
    } catch (Throwable $ex) {
        $error = $ex->getMessage();
    }
    foreach ($keys as &$key) {
        unset($key['privatekey']);
    }
    unset($key);

    $cdsPublished = false;
    $cdnskeyPublished = false;
    foreach ($metadata as $m) {
        $kind = strtoupper((string) ($m['kind'] ?? ''));
        if ($kind === 'PUBLISH-CDS') {
            $cdsPublished = !empty($m['metadata']);
        } elseif ($kind === 'PUBLISH-CDNSKEY') {
            $cdnskeyPublished = !empty($m['metadata']);
        }
    }

    view('dnssec', [
        'title' => 'DNSSEC',
        'user' => $user,
        'zone' => $zone,
        'keys' => $keys,
        'error' => $error,
        'cdsPublished' => $cdsPublished,
        'cdnskeyPublished' => $cdnskeyPublished,
    ]);
}

/**
 * @param array<string, mixed> $user
 */
function handleDnssecEnable(array $user, string $zoneRaw): void
{
    csrfCheck();
    requireRole($user, ['admin', 'operator']);
    $zone = dnsCanonical(rawurldecode($zoneRaw));
    requireZoneAccess($user, $zone, true);
    $mode = (string) ($_POST['mode'] ?? 'csk');
    $algo = (string) ($_POST['algorithm'] ?? 'ed25519');

    $validAlgos = [
        'ed25519' => 256,
        'ecdsa256' => 256,
        'ecdsa384' => 384,
        'rsasha256' => 2048,
    ];
    if (!isset($validAlgos[$algo])) {
        $algo = 'ed25519';
    }
    $bits = $validAlgos[$algo];

    try {
        $pdns = PdnsClient::fromSettings();
        $pdns->updateZone($zone, ['dnssec' => true, 'api_rectify' => true]);
        $cryptoKeyOpts = [
            'active' => true,
            'published' => true,
            'algorithm' => $algo,
            'bits' => $bits,
        ];
        if ($mode === 'split') {
            $pdns->createCryptokey($zone, ['keytype' => 'ksk'] + $cryptoKeyOpts);
            $pdns->createCryptokey($zone, ['keytype' => 'zsk'] + $cryptoKeyOpts);
        } else {
            $pdns->createCryptokey($zone, ['keytype' => 'csk'] + $cryptoKeyOpts);
        }
        $pdns->rectify($zone);
        db()->prepare('UPDATE zones SET dnssec = 1 WHERE name = ?')->execute([$zone]);
        audit($user, 'dnssec-enable', $zone, $mode . ' (' . $algo . ')');
        flash(
            'success',
            'DNSSEC diaktifkan dengan algoritma ' . strtoupper($algo) . ' (' . strtoupper($mode) . ').'
        );
    } catch (Throwable $ex) {
        flash('danger', $ex->getMessage());
    }
    redirectZone($zone, '/dnssec');
}

/**
 * @param array<string, mixed> $user
 */
function handleDnssecToggleCds(array $user, string $zoneRaw): void
{
    csrfCheck();
    requireRole($user, ['admin', 'operator']);
    $zone = dnsCanonical(rawurldecode($zoneRaw));
    requireZoneAccess($user, $zone, true);

    $enable = !empty($_POST['enable_cds']);
    try {
        $pdns = PdnsClient::fromSettings();
        if ($enable) {
            $pdns->setMetadata($zone, 'PUBLISH-CDS', ['2']);
            $pdns->setMetadata($zone, 'PUBLISH-CDNSKEY', ['1']);
            flash('success', 'Publikasi otomatis CDS & CDNSKEY (RFC 7344) diaktifkan.');
            audit($user, 'dnssec-cds-enable', $zone, 'PUBLISH-CDS & PUBLISH-CDNSKEY');
        } else {
            $pdns->deleteMetadata($zone, 'PUBLISH-CDS');
            $pdns->deleteMetadata($zone, 'PUBLISH-CDNSKEY');
            flash('success', 'Publikasi CDS & CDNSKEY dinonaktifkan.');
            audit($user, 'dnssec-cds-disable', $zone, '');
        }
        $pdns->rectify($zone);
    } catch (Throwable $ex) {
        flash('danger', 'Gagal mengubah pengaturan CDS: ' . $ex->getMessage());
    }
    redirectZone($zone, '/dnssec');
}

/**
 * @param array<string, mixed> $user
 */
function handleZoneGrant(array $user, string $zoneRaw): void
{
    csrfCheck();
    requireRole($user, ['admin']);
    $zone = dnsCanonical(rawurldecode($zoneRaw));
    $username = trim((string) ($_POST['username'] ?? ''));
    $canEdit = isset($_POST['can_edit']) ? 1 : 0;
    $st = db()->prepare('SELECT id FROM users WHERE username = ?');
    $st->execute([$username]);
    $target = $st->fetch();
    $zst = db()->prepare('SELECT id FROM zones WHERE name = ?');
    $zst->execute([$zone]);
    $z = $zst->fetch();
    if (!$target || !$z) {
        flash('danger', 'User atau zona tidak ditemukan di cache panel. Sinkronkan zona dulu.');
        redirectZone($zone);
    }
    db()->prepare(
        'INSERT INTO zone_user (zone_id, user_id, can_edit) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE can_edit = VALUES(can_edit)'
    )->execute([(int) $z['id'], (int) $target['id'], $canEdit]);
    audit($user, 'grant-zone', $zone, $username);
    flash('success', 'Akses zona diperbarui.');
    redirectZone($zone);
}

/**
 * @param array<string, mixed> $user
 */
function handleUsers(array $user): void
{
    requireRole($user, ['admin']);
    $st = db()->query(
        'SELECT id, username, display_name, email, avatar_url, role, active, last_login_at FROM users ORDER BY username'
    );
    $users = $st ? $st->fetchAll() : [];
    view('users', ['title' => 'Pengguna', 'user' => $user, 'users' => $users]);
}

/**
 * @param array<string, mixed> $user
 * @param array<string, mixed> $data
 */
function createNewUser(array $user, array $data): void
{
    $username = (string) ($data['username'] ?? '');
    $display = (string) ($data['display_name'] ?? '');
    $email = (string) ($data['email'] ?? '');
    $role = (string) ($data['role'] ?? 'user');
    $active = (int) ($data['active'] ?? 0);
    $password = (string) ($data['password'] ?? '');

    if (strlen($password) < 10) {
        flash('danger', 'Sandi awal minimal 10 karakter.');
        redirect(PATH_USERS);
    }
    db()->prepare(
        'INSERT INTO users (username, password_hash, display_name, email, role, active)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([$username, password_hash($password, PASSWORD_ARGON2ID), $display, $email, $role, $active]);
    audit($user, 'create-user', '', $username);
}

/**
 * @param array<string, mixed> $user
 * @param array<string, mixed> $data
 */
function updateExistingUser(array $user, int $id, array $data): void
{
    $username = (string) ($data['username'] ?? '');
    $display = (string) ($data['display_name'] ?? '');
    $email = (string) ($data['email'] ?? '');
    $role = (string) ($data['role'] ?? 'user');
    $active = (int) ($data['active'] ?? 0);
    $password = (string) ($data['password'] ?? '');

    db()->prepare(
        'UPDATE users SET username = ?, display_name = ?, email = ?, role = ?, active = ? WHERE id = ?'
    )->execute([$username, $display, $email, $role, $active, $id]);
    if ($password !== '') {
        if (strlen($password) < 10) {
            flash('danger', 'Sandi baru minimal 10 karakter.');
            redirect(PATH_USERS);
        }
        db()->prepare(SQL_UPDATE_USER_AUTH_HASH)
            ->execute([password_hash($password, PASSWORD_ARGON2ID), $id]);
    }
    audit($user, 'update-user', '', $username);
}

/**
 * @param array<string, mixed> $user
 */
function handleUserSave(array $user): void
{
    csrfCheck();
    requireRole($user, ['admin']);
    $id = (int) ($_POST['id'] ?? 0);
    $username = trim((string) ($_POST['username'] ?? ''));
    $display = trim((string) ($_POST['display_name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $role = (string) ($_POST['role'] ?? 'user');
    $password = (string) ($_POST['password'] ?? '');
    $active = isset($_POST['active']) ? 1 : 0;
    if (!preg_match('/^[a-zA-Z0-9_.-]{3,64}$/', $username) || !in_array($role, ['admin', 'operator', 'user'], true)) {
        flash('danger', 'Data pengguna tidak valid. Username 3-64 karakter alfanumerik.');
        redirect(PATH_USERS);
    }
    $userData = [
        'username' => $username,
        'display_name' => $display,
        'email' => $email,
        'role' => $role,
        'active' => $active,
        'password' => $password,
    ];
    try {
        if ($id === 0) {
            createNewUser($user, $userData);
        } else {
            updateExistingUser($user, $id, $userData);
        }
        flash('success', 'Pengguna disimpan.');
    } catch (PDOException $ex) {
        $msg = $ex->getCode() === SQLSTATE_DUPLICATE
            ? 'Username sudah terdaftar. Gunakan username lain.'
            : 'Gagal menyimpan pengguna: ' . $ex->getMessage();
        flash('danger', $msg);
    }
    redirect(PATH_USERS);
}

/**
 * @param array<string, mixed> $user
 */
function handleAccounts(array $user): void
{
    requireRole($user, ['admin', 'operator']);
    $st = db()->query(
        'SELECT a.*, (SELECT COUNT(*) FROM zones z WHERE z.account_id = a.id) AS zone_count
         FROM accounts a ORDER BY a.name'
    );
    $accounts = $st ? $st->fetchAll() : [];
    view('accounts', ['title' => 'Akun', 'user' => $user, 'accounts' => $accounts]);
}

/**
 * @param array<string, mixed> $user
 */
function handleAccountSave(array $user): void
{
    csrfCheck();
    requireRole($user, ['admin']);
    $name = trim((string) ($_POST['name'] ?? ''));
    $contact = trim((string) ($_POST['contact'] ?? ''));
    $notes = trim((string) ($_POST['notes'] ?? ''));
    if ($name === '') {
        flash('danger', 'Nama akun wajib.');
        redirect('/accounts');
    }
    try {
        db()->prepare('INSERT INTO accounts (name, contact, notes) VALUES (?, ?, ?)')
            ->execute([$name, $contact, $notes]);
        audit($user, 'create-account', '', $name);
        flash('success', 'Akun dibuat.');
    } catch (PDOException $ex) {
        if ($ex->getCode() === SQLSTATE_DUPLICATE) {
            flash('danger', 'Nama akun sudah terdaftar.');
        } else {
            flash('danger', 'Gagal membuat akun: ' . $ex->getMessage());
        }
    }
    redirect('/accounts');
}

/**
 * @param array<string, mixed> $user
 */
function handleTemplates(array $user): void
{
    requireRole($user, ['admin', 'operator']);
    $st = db()->query(
        'SELECT t.*, (SELECT COUNT(*) FROM template_records r WHERE r.template_id = t.id) AS rec_count
         FROM templates t ORDER BY t.name'
    );
    $templates = $st ? $st->fetchAll() : [];
    view('templates', ['title' => 'Template', 'user' => $user, 'templates' => $templates, 'types' => RECORD_TYPES]);
}

/**
 * @param array<string, mixed> $user
 */
function handleTemplateSave(array $user): void
{
    csrfCheck();
    requireRole($user, ['admin', 'operator']);
    $name = trim((string) ($_POST['name'] ?? ''));
    $desc = trim((string) ($_POST['description'] ?? ''));
    if ($name === '') {
        flash('danger', 'Nama template wajib.');
        redirect('/templates');
    }
    try {
        db()->prepare('INSERT INTO templates (name, description, created_by) VALUES (?, ?, ?)')
            ->execute([$name, $desc, (int) ($user['id'] ?? 0)]);
        $id = (int) db()->lastInsertId();
        $names = $_POST['r_name'] ?? [];
        $types = $_POST['r_type'] ?? [];
        $ttls = $_POST['r_ttl'] ?? [];
        $contents = $_POST['r_content'] ?? [];
        $ins = db()->prepare(
            'INSERT INTO template_records (template_id, name, type, content, ttl) VALUES (?, ?, ?, ?, ?)'
        );
        if (is_array($names)) {
            foreach ($names as $i => $n) {
                $c = trim((string) ($contents[$i] ?? ''));
                if (trim((string) $n) === '' || $c === '') {
                    continue;
                }
                $ins->execute([
                    $id,
                    trim((string) $n),
                    strtoupper((string) ($types[$i] ?? 'A')),
                    $c,
                    (int) ($ttls[$i] ?? 3600),
                ]);
            }
        }
        flash('success', 'Template disimpan. Gunakan [ZONE] sebagai pengganti nama zona.');
    } catch (PDOException $ex) {
        if ($ex->getCode() === SQLSTATE_DUPLICATE) {
            flash('danger', 'Nama template sudah terdaftar.');
        } else {
            flash('danger', 'Gagal menyimpan template: ' . $ex->getMessage());
        }
    }
    redirect('/templates');
}

/**
 * @param array<string, mixed> $user
 */
function handleApikeys(array $user): void
{
    requireRole($user, ['admin', 'operator']);
    $st = db()->query(
        'SELECT k.id, k.name, k.key_prefix, k.role, k.revoked, k.last_used_at, k.created_at, u.username
         FROM api_keys k JOIN users u ON u.id = k.user_id ORDER BY k.id DESC'
    );
    $keys = $st ? $st->fetchAll() : [];
    $plain = $_SESSION['new_api_key'] ?? '';
    unset($_SESSION['new_api_key']);
    view('apikeys', ['title' => 'API key', 'user' => $user, 'keys' => $keys, 'plain' => $plain]);
}

/**
 * @param array<string, mixed> $user
 */
function handleApikeyCreate(array $user): void
{
    csrfCheck();
    requireRole($user, ['admin', 'operator']);
    $name = trim((string) ($_POST['name'] ?? 'key'));
    $role = (string) ($_POST['role'] ?? 'user');
    if (($user['role'] ?? '') !== 'admin') {
        $role = 'user';
    }
    if (!in_array($role, ['admin','operator','user'], true)) {
        $role = 'user';
    }
    $raw = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $prefix = substr($raw, 0, 8);
    db()->prepare('INSERT INTO api_keys (name, key_prefix, key_hash, role, user_id) VALUES (?, ?, ?, ?, ?)')
        ->execute([$name, $prefix, hash('sha256', $raw), $role, (int) ($user['id'] ?? 0)]);
    $_SESSION['new_api_key'] = $raw;
    audit($user, 'create-apikey', '', $name);
    flash('success', 'API key dibuat. Salin sekarang. Nilai ini tidak ditampilkan lagi.');
    redirect('/apikeys');
}

/**
 * @param array<string, mixed> $user
 */
function handleAudit(array $user): void
{
    requireRole($user, ['admin']);
    $st = db()->query('SELECT * FROM history ORDER BY id DESC LIMIT 200');
    $rows = $st ? $st->fetchAll() : [];
    view('audit', ['title' => 'Audit', 'user' => $user, 'rows' => $rows]);
}

function processSettingsBrandingLogo(): void
{
    $appLogoUrl = trim((string) ($_POST['app_logo_url'] ?? ''));
    if (!empty($_POST['remove_logo'])) {
        $oldLogo = (string) setting('app_logo_url', '');
        if ($oldLogo !== '' && str_starts_with($oldLogo, '/uploads/branding/')) {
            $oldFile = appRoot() . PATH_PUBLIC . $oldLogo;
            if (is_file($oldFile)) {
                @unlink($oldFile);
            }
        }
        settingSet('app_logo_url', '');
        return;
    }

    if (
        !empty($_FILES['app_logo_file']) &&
        is_array($_FILES['app_logo_file']) &&
        ($_FILES['app_logo_file']['error'] ?? 1) === UPLOAD_ERR_OK
    ) {
        $logoRes = saveBrandLogo($_FILES['app_logo_file']);
        if ($logoRes['ok'] && !empty($logoRes['path'])) {
            settingSet('app_logo_url', $logoRes['path']);
        } else {
            flash('danger', 'Logo gagal diunggah: ' . ($logoRes['error'] ?? 'Berkas tidak valid.'));
            redirect(PATH_SETTINGS);
        }
    } elseif ($appLogoUrl !== '') {
        settingSet('app_logo_url', $appLogoUrl);
    }
}

function saveDnsPolicySettings(): void
{
    $defaultTtl = max(30, min(604800, (int) ($_POST['dns_default_ttl'] ?? 3600)));
    $defaultNs = trim((string) ($_POST['dns_default_ns'] ?? ''));
    $defaultSoaEmail = trim((string) ($_POST['dns_default_soa_email'] ?? 'hostmaster.example.com'));
    $defaultSoaRefresh = max(60, min(1209600, (int) ($_POST['dns_default_soa_refresh'] ?? 10800)));
    $defaultSoaRetry = max(60, min(1209600, (int) ($_POST['dns_default_soa_retry'] ?? 3600)));
    $defaultSoaExpire = max(300, min(2419200, (int) ($_POST['dns_default_soa_expire'] ?? 604800)));
    $defaultSoaMin = max(30, min(604800, (int) ($_POST['dns_default_soa_minimum'] ?? 3600)));
    $autoPtrDefault = isset($_POST['dns_auto_ptr_default']) ? '1' : '0';

    settingSet('dns_default_ttl', (string) $defaultTtl);
    settingSet('dns_default_ns', $defaultNs);
    settingSet('dns_default_soa_email', $defaultSoaEmail);
    settingSet('dns_default_soa_refresh', (string) $defaultSoaRefresh);
    settingSet('dns_default_soa_retry', (string) $defaultSoaRetry);
    settingSet('dns_default_soa_expire', (string) $defaultSoaExpire);
    settingSet('dns_default_soa_minimum', (string) $defaultSoaMin);
    settingSet('dns_auto_ptr_default', $autoPtrDefault);
}

function saveSecurityAndOperationalSettings(): void
{
    $sessionLifetime = max(5, min(10080, (int) ($_POST['session_lifetime_minutes'] ?? 120)));
    $maxLoginAttempts = max(1, min(50, (int) ($_POST['login_max_attempts'] ?? 5)));
    $lockoutSeconds = max(30, min(86400, (int) ($_POST['login_lockout_seconds'] ?? 900)));
    $forceHsts = isset($_POST['security_force_hsts']) ? '1' : '0';

    settingSet('session_lifetime_minutes', (string) $sessionLifetime);
    settingSet('login_max_attempts', (string) $maxLoginAttempts);
    settingSet('login_lockout_seconds', (string) $lockoutSeconds);
    settingSet('security_force_hsts', $forceHsts);

    $maxSnapshots = max(1, min(500, (int) ($_POST['history_max_snapshots'] ?? 25)));
    $auditDays = max(1, min(3650, (int) ($_POST['audit_retention_days'] ?? 90)));
    settingSet('history_max_snapshots', (string) $maxSnapshots);
    settingSet('audit_retention_days', (string) $auditDays);

    $rdnsPattern = trim((string) ($_POST['rdns_default_naming_pattern'] ?? DEFAULT_RDNS_NAMING_PATTERN));
    $publicResolvers = trim((string) ($_POST['dns_public_resolvers'] ?? DEFAULT_DNS_PUBLIC_RESOLVERS));
    settingSet('rdns_default_naming_pattern', $rdnsPattern !== '' ? $rdnsPattern : DEFAULT_RDNS_NAMING_PATTERN);
    settingSet('dns_public_resolvers', $publicResolvers);
}

function updateApplicationSettings(array $user): void
{
    csrfCheck();
    $url = rtrim(trim((string) ($_POST['pdns_api_url'] ?? '')), '/');
    $server = trim((string) ($_POST['pdns_server_id'] ?? 'localhost'));
    $verify = isset($_POST['pdns_verify_tls']) ? '1' : '0';
    $key = trim((string) ($_POST['pdns_api_key'] ?? ''));

    $appName = trim((string) ($_POST['app_name'] ?? 'PowerDNS Admin'));
    $appFooter = trim((string) ($_POST['app_footer_text'] ?? ''));
    $theme = (isset($_POST['app_default_theme']) && $_POST['app_default_theme'] === 'light') ? 'light' : 'dark';

    if (!preg_match('#^https?://#', $url)) {
        flash('danger', 'URL API harus http atau https.');
        redirect(PATH_SETTINGS);
    }
    settingSet('pdns_api_url', $url);
    settingSet('pdns_server_id', $server !== '' ? $server : 'localhost');
    settingSet('pdns_verify_tls', $verify);
    if ($key !== '') {
        settingSet('pdns_api_key', secretEncrypt($key));
    }

    settingSet('app_name', $appName !== '' ? $appName : 'PowerDNS Admin');
    settingSet('app_footer_text', $appFooter);
    settingSet('app_default_theme', $theme);

    processSettingsBrandingLogo();
    saveDnsPolicySettings();
    saveSecurityAndOperationalSettings();

    audit($user, 'settings', '', 'Pengaturan global aplikasi diperbarui');
    flash('success', 'Seluruh konfigurasi sistem berhasil disimpan.');
    redirect(PATH_SETTINGS);
}

/**
 * @param array<string, mixed> $user
 */
function handleSettings(array $user): void
{
    requireRole($user, ['admin']);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        updateApplicationSettings($user);
    }
    view('settings', [
        'title' => 'Pengaturan',
        'user' => $user,
        'url' => (string) setting('pdns_api_url', ''),
        'server' => (string) setting('pdns_server_id', 'localhost'),
        'verify' => setting('pdns_verify_tls', '1') !== '0',
        'appName' => appName(),
        'appLogoUrl' => appLogoUrl(),
        'appFooterText' => appFooterText(),
        'defaultTheme' => (string) setting('app_default_theme', 'dark'),
        'defaultTtl' => (int) setting('dns_default_ttl', '3600'),
        'defaultNs' => (string) setting('dns_default_ns', ''),
        'defaultSoaEmail' => (string) setting('dns_default_soa_email', 'hostmaster.example.com'),
        'defaultSoaRefresh' => (int) setting('dns_default_soa_refresh', '10800'),
        'defaultSoaRetry' => (int) setting('dns_default_soa_retry', '3600'),
        'defaultSoaExpire' => (int) setting('dns_default_soa_expire', '604800'),
        'defaultSoaMinimum' => (int) setting('dns_default_soa_minimum', '3600'),
        'autoPtrDefault' => setting('dns_auto_ptr_default', '0') === '1',
        'sessionLifetime' => (int) setting('session_lifetime_minutes', '120'),
        'maxLoginAttempts' => (int) setting('login_max_attempts', '5'),
        'lockoutSeconds' => (int) setting('login_lockout_seconds', '900'),
        'forceHsts' => setting('security_force_hsts', '1') === '1',
        'maxSnapshots' => (int) setting('history_max_snapshots', '25'),
        'auditRetentionDays' => (int) setting('audit_retention_days', '90'),
        'rdnsPattern' => (string) setting('rdns_default_naming_pattern', DEFAULT_RDNS_NAMING_PATTERN),
        'publicResolvers' => (string) setting('dns_public_resolvers', DEFAULT_DNS_PUBLIC_RESOLVERS),
    ]);
}

/**
 * @param array<string, mixed> $user
 */
function handleSearch(array $user): void
{
    $q = trim((string) ($_GET['q'] ?? ''));
    $results = [];
    $error = '';
    if ($q !== '') {
        try {
            $results = PdnsClient::fromSettings()->search($q, 50);
        } catch (Throwable $ex) {
            $error = $ex->getMessage();
        }
    }
    view('search', ['title' => 'Cari', 'user' => $user, 'q' => $q, 'results' => $results, 'error' => $error]);
}

/**
 * @param array<string, mixed> $user
 */
function handleRdnsCreateZone(array $user): never
{
    $subnet = trim((string) ($_POST['subnet'] ?? ''));
    $family = (string) ($_POST['family'] ?? 'ipv4');
    $kind = (string) ($_POST['kind'] ?? 'Native');
    $accountId = (int) ($_POST['account_id'] ?? 0);
    $ns = array_values(array_filter(array_map('trim', explode(',', (string) ($_POST['nameservers'] ?? '')))));

    $zone = ($family === 'ipv6') ? ipv6ToReverseZone64($subnet) : ipv4ToReverseZone24($subnet);
    if ($zone === null) {
        flash('danger', 'Format subnet tidak valid.');
        redirect(PATH_TOOLS_RDNS);
    }

    try {
        $pdns = PdnsClient::fromSettings();
        $payload = [
            'name' => $zone,
            'kind' => $kind,
            'masters' => [],
            'nameservers' => array_map('dnsCanonical', $ns),
            'soa_edit_api' => 'DEFAULT',
            'api_rectify' => true,
        ];
        $pdns->createZone($payload);

        $st = db()->prepare(
            'INSERT INTO zones (name, kind, account_id, dnssec, synced_at) VALUES (?, ?, ?, 0, NOW())
             ON DUPLICATE KEY UPDATE kind = VALUES(kind), account_id = VALUES(account_id), synced_at = NOW()'
        );
        $st->execute([$zone, $kind, $accountId > 0 ? $accountId : null]);
        audit($user, 'create-reverse-zone', $zone, "Subnet: $subnet");
        flash('success', "Zona reverse $zone berhasil dibuat di PowerDNS.");
        redirectZone($zone);
    } catch (Throwable $ex) {
        flash('danger', 'Gagal membuat zona reverse: ' . $ex->getMessage());
        redirect(PATH_TOOLS_RDNS);
    }
}

/**
 * @param array<string, mixed> $user
 */
function handleRdnsGeneratePtr(array $user): never
{
    $zone = dnsCanonical((string) ($_POST['zone'] ?? ''));
    requireZoneAccess($user, $zone, true);

    $family = (string) ($_POST['family'] ?? 'ipv4');
    $subnet = trim((string) ($_POST['subnet'] ?? ''));
    $domain = trim((string) ($_POST['domain'] ?? ''));
    $pattern = trim((string) ($_POST['pattern'] ?? DEFAULT_RDNS_NAMING_PATTERN));
    $start = (int) ($_POST['start'] ?? 1);
    $end = (int) ($_POST['end'] ?? 254);
    $ttl = max(30, (int) ($_POST['ttl'] ?? 3600));

    $batch = ($family === 'ipv6')
        ? generateIpv6SubnetPtrBatch($subnet, $domain, $pattern, $ttl, $start, $end)
        : generateIpv4SubnetPtrBatch($subnet, $domain, $pattern, $ttl, $start, $end);

    if (!$batch) {
        flash('danger', 'Gagal membangkitkan baris PTR. Periksa subnet dan domain tujuan.');
        redirect(PATH_TOOLS_RDNS);
    }

    try {
        $pdns = PdnsClient::fromSettings();
        $rrsets = [];
        foreach ($batch as $row) {
            $fqdn = $row['name'] . '.' . $zone;
            $rrsets[] = [
                'name' => $fqdn,
                'type' => 'PTR',
                'ttl' => $row['ttl'],
                'changetype' => 'REPLACE',
                'records' => [
                    ['content' => $row['content'], 'disabled' => false],
                ],
                'comments' => [
                    ['content' => $row['comment'], 'account' => ''],
                ],
            ];
        }
        $pdns->patchRrsets($zone, $rrsets);
        audit($user, 'batch-ptr-generate', $zone, count($rrsets) . " record PTR dibangkitkan untuk $subnet");
        flash('success', count($rrsets) . " record PTR berhasil diterapkan ke zona $zone.");
        redirectZone($zone);
    } catch (Throwable $ex) {
        flash('danger', 'Gagal menerapkan record PTR: ' . $ex->getMessage());
        redirect(PATH_TOOLS_RDNS);
    }
}

/**
 * @param list<array<string, mixed>> $rrsets
 * @param array<string, string> $matched
 */
function extractMatchingIpsFromRrsets(array $rrsets, bool $isV4, string $v4Prefix, array &$matched): void
{
    $targetType = $isV4 ? 'A' : 'AAAA';
    foreach ($rrsets as $rr) {
        $type = strtoupper((string) ($rr['type'] ?? ''));
        if ($type !== $targetType) {
            continue;
        }
        foreach ($rr['records'] ?? [] as $rec) {
            $ip = trim((string) ($rec['content'] ?? ''));
            if ($isV4 && !str_starts_with($ip, $v4Prefix)) {
                continue;
            }
            $matched[$ip] = (string) $rr['name'];
        }
    }
}

/**
 * @return array<string, string>
 */
function collectMatchingForwardIps(PdnsClient $pdns, bool $isV4, string $v4Prefix): array
{
    $matched = [];
    foreach ($pdns->zones() as $zInfo) {
        $zName = (string) ($zInfo['name'] ?? '');
        if ($zName === '' || isReverseZone($zName)) {
            continue;
        }
        $zData = $pdns->zone($zName);
        extractMatchingIpsFromRrsets($zData['rrsets'] ?? [], $isV4, $v4Prefix, $matched);
    }
    return $matched;
}

/**
 * @param array<string, string> $matched
 * @return list<array<string, mixed>>
 */
function buildBatchImportPtrRrsets(array $matched, string $zone, bool $isV4): array
{
    $rrsets = [];
    foreach ($matched as $ip => $fqdn) {
        $rel = $isV4 ? ipv4ToRelativePtr24($ip) : ipv6ToRelativePtr64($ip);
        if ($rel === null) {
            continue;
        }
        $rrsets[] = [
            'name' => $rel . '.' . $zone,
            'type' => 'PTR',
            'ttl' => 3600,
            'changetype' => 'REPLACE',
            'records' => [
                ['content' => dnsCanonical($fqdn), 'disabled' => false],
            ],
            'comments' => [
                ['content' => 'Auto-populated from forward ' . $fqdn, 'account' => ''],
            ],
        ];
    }
    return $rrsets;
}

/**
 * @param array<string, mixed> $user
 */
function handleRdnsScanForward(array $user): never
{
    $subnet = trim((string) ($_POST['subnet'] ?? ''));
    $zone = dnsCanonical((string) ($_POST['zone'] ?? ''));
    requireZoneAccess($user, $zone, true);

    try {
        $pdns = PdnsClient::fromSettings();
        $isV4 = filter_var(explode('/', $subnet)[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
        $prefixParts = $isV4 ? explode('.', explode('/', $subnet)[0]) : [];
        $v4Prefix = $isV4 ? ($prefixParts[0] . '.' . $prefixParts[1] . '.' . $prefixParts[2] . '.') : '';

        $matched = collectMatchingForwardIps($pdns, $isV4, $v4Prefix);
        if (!$matched) {
            flash('warning', 'Tidak ditemukan record A/AAAA yang cocok dengan subnet ' . $subnet);
            redirect(PATH_TOOLS_RDNS);
        }

        $rrsets = buildBatchImportPtrRrsets($matched, $zone, $isV4);
        if ($rrsets) {
            $pdns->patchRrsets($zone, $rrsets);
            audit($user, 'scan-forward-ptr', $zone, count($rrsets) . " record PTR diimpor dari zona forward");
            flash('success', count($rrsets) . " record PTR berhasil diimpor otomatis ke zona $zone.");
        }
        redirectZone($zone);
    } catch (Throwable $ex) {
        flash('danger', 'Gagal memindai forward zone: ' . $ex->getMessage());
        redirect(PATH_TOOLS_RDNS);
    }
}

/**
 * Handler untuk Modul Generator Reverse DNS (rDNS) & Subnet PTR.
 *
 * @param array<string, mixed> $user
 */
function handleRdnsTool(array $user, string $path, string $method): void
{
    requireRole($user, ['admin', 'operator']);

    if ($method === 'POST') {
        csrfCheck();
        match ($path) {
            '/tools/rdns/create-zone' => handleRdnsCreateZone($user),
            '/tools/rdns/generate-ptr' => handleRdnsGeneratePtr($user),
            '/tools/rdns/scan-forward' => handleRdnsScanForward($user),
            default => redirect(PATH_TOOLS_RDNS),
        };
    }

    $stRev = db()->query(
        "SELECT id, name, kind FROM zones WHERE name LIKE '%.in-addr.arpa.' OR name LIKE '%.ip6.arpa.' ORDER BY name"
    );
    $reverseZones = $stRev ? $stRev->fetchAll() : [];

    $stAcc = db()->query('SELECT id, name FROM accounts ORDER BY name');
    $accounts = $stAcc ? $stAcc->fetchAll() : [];

    view('tools_rdns', [
        'title' => 'Generator Subnet rDNS & PTR',
        'user' => $user,
        'reverseZones' => $reverseZones,
        'accounts' => $accounts,
    ]);
}

/**
 * @param array<string, mixed> $userFromKey
 */
function handleApi(array $userFromKey): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if ($path === '/api/v1/zones' && $method === 'GET') {
        if (($userFromKey['role'] ?? '') === 'admin') {
            $st = db()->query('SELECT name, kind, dnssec, serial, catalog FROM zones ORDER BY name');
            jsonOut(200, ['zones' => $st ? $st->fetchAll() : []]);
        }
        $st = db()->prepare(
            'SELECT z.name, z.kind, z.dnssec, z.serial, z.catalog FROM zones z
             WHERE z.id IN (SELECT zone_id FROM zone_user WHERE user_id = ?)
                OR z.account_id IN (SELECT account_id FROM account_user WHERE user_id = ?)
             ORDER BY z.name'
        );
        $st->execute([(int) ($userFromKey['id'] ?? 0), (int) ($userFromKey['id'] ?? 0)]);
        jsonOut(200, ['zones' => $st->fetchAll()]);
    }
    if (preg_match('#^/api/v1/zones/(.+)$#', $path, $m) && $method === 'GET') {
        $zone = dnsCanonical(rawurldecode($m[1]));
        if (!userCanZone($userFromKey, $zone, false)) {
            jsonOut(403, ['error' => 'Akses zona ditolak']);
        }
        try {
            jsonOut(200, PdnsClient::fromSettings()->zone($zone));
        } catch (Throwable $ex) {
            jsonOut(502, ['error' => $ex->getMessage()]);
        }
    }
    jsonOut(404, ['error' => 'Endpoint tidak dikenal']);
}

/**
 * @return array<string, mixed>|null
 */
function apiUser(): ?array
{
    $header = (string) ($_SERVER['HTTP_X_API_KEY'] ?? '');
    if ($header === '') {
        return null;
    }
    $st = db()->prepare(
        'SELECT k.role, k.user_id, u.username, u.active
         FROM api_keys k JOIN users u ON u.id = k.user_id WHERE k.key_hash = ? AND k.revoked = 0'
    );
    $st->execute([hash('sha256', $header)]);
    $row = $st->fetch();
    if (!$row || !(int) $row['active']) {
        return null;
    }
    db()->prepare('UPDATE api_keys SET last_used_at = NOW() WHERE key_hash = ?')
        ->execute([hash('sha256', $header)]);
    return [
        'id' => (int) $row['user_id'],
        'username' => $row['username'],
        'role' => $row['role'],
        'active' => 1,
    ];
}

/**
 * Dynamic DNS (DynDNS 2 Protocol) Endpoint: /nic/update
 *
 * Implements the standard DynDNS v2 specification supporting:
 * - Query params: hostname, myip
 * - Auth: HTTP Basic Auth or API Key (X-API-Key or Bearer)
 * - Responses: good <ip>, nochg <ip>, nohost, badauth, notfqdn, badagent, 911
 */
/**
 * Authenticate DynDNS request via HTTP Basic Auth or API Key.
 *
 * @return array<string, mixed>|null
 */
function authenticateDynDnsUser(): ?array
{
    $authUser = $_SERVER['PHP_AUTH_USER'] ?? null;
    $authPw = $_SERVER['PHP_AUTH_PW'] ?? null;

    if ($authUser !== null && $authPw !== null) {
        $st = db()->prepare('SELECT id, username, password_hash, role, active FROM users WHERE username = ?');
        $st->execute([(string) $authUser]);
        $row = $st->fetch();
        if ($row && !empty($row['active']) && password_verify((string) $authPw, (string) $row['password_hash'])) {
            return $row;
        }
    }

    $rawKey = (string) ($_SERVER['HTTP_X_API_KEY'] ?? ($_GET['key'] ?? ''));
    $authHeader = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if ($rawKey === '' && $authHeader !== '' && preg_match('/^Bearer\s+(.+)$/i', $authHeader, $bm)) {
        $rawKey = trim($bm[1]);
    }

    if ($rawKey !== '') {
        $hash = hash('sha256', $rawKey);
        $st = db()->prepare('SELECT id, name, role, user_id FROM api_keys WHERE key_hash = ? AND revoked = 0');
        $st->execute([$hash]);
        $kRow = $st->fetch();
        if ($kRow) {
            return [
                'id' => (int) $kRow['user_id'],
                'username' => 'apikey:' . $kRow['name'],
                'role' => $kRow['role'] ?: 'operator',
                'active' => 1,
                'api_key_id' => (int) $kRow['id'],
            ];
        }
    }

    return null;
}

/**
 * @param array<string, mixed> $user
 */
function canDynDnsUserAccessZone(array $user, string $matchingZone): bool
{
    if (!empty($user['api_key_id'])) {
        $sql = 'SELECT 1 FROM api_key_zone '
            . 'WHERE api_key_id = ? AND zone_id = (SELECT id FROM zones WHERE name = ?)';
        $st = db()->prepare($sql);
        $st->execute([(int) $user['api_key_id'], $matchingZone]);
        return (bool) $st->fetch() || ($user['role'] ?? '') === 'admin';
    }

    return userCanZone($user, $matchingZone, true);
}

/**
 * @param array<string, mixed> $user
 */
function resolveDynDnsZoneTarget(array $user, string $hostFqdn): string
{
    $matchingZone = findMatchingZoneForHostname($hostFqdn);
    if ($matchingZone === null) {
        return 'nohost';
    }
    return canDynDnsUserAccessZone($user, $matchingZone) ? $matchingZone : 'badauth';
}

/**
 * @param array<string, mixed> $user
 */
function updateSingleDynDnsHost(
    PdnsClient $pdns,
    array $user,
    string $host,
    string $rawIp,
    string $recordType
): string {
    $hostFqdn = dnsCanonical($host);
    $targetZone = resolveDynDnsZoneTarget($user, $hostFqdn);
    if ($targetZone === 'nohost' || $targetZone === 'badauth') {
        return $targetZone;
    }

    $matchingZone = $targetZone;
    $zoneData = $pdns->zone($matchingZone);
    $currentIp = null;
    foreach (($zoneData['rrsets'] ?? []) as $rr) {
        $rname = dnsCanonical((string) ($rr['name'] ?? ''));
        $rtype = strtoupper((string) ($rr['type'] ?? ''));
        if ($rname === $hostFqdn && $rtype === $recordType) {
            $records = $rr['records'] ?? [];
            if (!empty($records[0]['content'])) {
                $currentIp = trim((string) $records[0]['content']);
            }
            break;
        }
    }

    if ($currentIp === $rawIp) {
        return 'nochg ' . $rawIp;
    }

    $rrset = [
        'name' => $hostFqdn,
        'type' => $recordType,
        'ttl' => 60,
        'changetype' => 'REPLACE',
        'records' => [
            ['content' => $rawIp, 'disabled' => false],
        ],
        'comments' => [
            ['content' => 'DynDNS update at ' . gmdate('c'), 'account' => ''],
        ],
    ];
    $pdns->patchRrsets($matchingZone, [$rrset]);
    audit($user, 'dyndns-update', $matchingZone, $hostFqdn . ' (' . $recordType . ') -> ' . $rawIp);
    return 'good ' . $rawIp;
}

/**
 * Dynamic DNS (DynDNS 2 Protocol) Endpoint Handler.
 * Supports /nic/update?hostname=...&myip=...
 */
function handleDynDns(): void
{
    header('Content-Type: text/plain; charset=utf-8');

    $user = authenticateDynDnsUser();
    if (!$user) {
        header('WWW-Authenticate: Basic realm="PowerDNS-Admin DynDNS"');
        http_response_code(401);
        echo "badauth\n";
        exit;
    }

    $hostname = trim((string) ($_GET['hostname'] ?? ($_POST['hostname'] ?? '')));
    if ($hostname === '') {
        echo "notfqdn\n";
        exit;
    }

    $rawIp = trim((string) ($_GET['myip'] ?? ($_POST['myip'] ?? '')));
    if ($rawIp === '') {
        $rawIp = clientIp();
    }

    $recordType = null;
    if (filter_var($rawIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
        $recordType = 'A';
    } elseif (filter_var($rawIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
        $recordType = 'AAAA';
    }
    if ($recordType === null) {
        echo "badagent\n";
        exit;
    }

    $hostnames = array_values(array_filter(array_map('trim', explode(',', $hostname))));
    $responses = [];

    try {
        $pdns = PdnsClient::fromSettings();
        foreach ($hostnames as $host) {
            $responses[] = updateSingleDynDnsHost($pdns, $user, $host, $rawIp, $recordType);
        }
        echo implode("\n", $responses) . "\n";
    } catch (Throwable) {
        echo "911\n";
    }
    exit;
}

/**
 * Handler for IPCalc (IPv4 & IPv6 Subnet Calculator).
 *
 * @param array<string, mixed> $user
 */
function handleIpcalcTool(array $user): void
{
    $cidr = trim((string) ($_GET['cidr'] ?? ($_POST['cidr'] ?? DEFAULT_IPCALC_CIDR)));
    if ($cidr === '') {
        $cidr = DEFAULT_IPCALC_CIDR;
    }

    $error = null;
    $result = null;

    if (str_contains($cidr, ':')) {
        $result = ipcalcProcessIpv6($cidr);
    } else {
        $result = ipcalcProcessIpv4($cidr);
    }

    if ($result === null) {
        $error = 'Format CIDR tidak valid. Contoh: 192.168.1.0/24 atau 2001:db8::/32';
    }

    view('tools_ipcalc', [
        'title' => 'IPCalc & Subnetting IPv4 / IPv6',
        'user' => $user,
        'activeTab' => 'ipcalc',
        'cidr' => $cidr,
        'result' => $result,
        'error' => $error,
    ]);
}

/**
 * Handler for IPv6 Subnet Splitter.
 *
 * @param array<string, mixed> $user
 */
function handleIpv6SplitterTool(array $user): void
{
    $subnet = trim((string) ($_GET['subnet'] ?? ($_POST['subnet'] ?? '2001:db8::/32')));
    $targetMask = (int) ($_GET['target_mask'] ?? ($_POST['target_mask'] ?? 48));
    $isDownload = (string) ($_GET['download'] ?? '') === '1';

    $parsed = ipv6splitValidate($subnet);
    $error = null;
    $previewSubnets = [];
    $totalCount = 0;

    if ($parsed === null) {
        $error = 'Format prefix IPv6 tidak valid. Contoh: 2001:db8::/32';
    } elseif ($targetMask < $parsed['mask']) {
        $error = 'Target prefix (/' . $targetMask . ') harus lebih spesifik atau sama dengan prefix sumber (/'
            . $parsed['mask'] . ')';
    } elseif ($targetMask > 128) {
        $error = 'Target prefix tidak boleh lebih dari /128';
    } elseif (($targetMask - $parsed['mask']) > 16) {
        $error = 'Perbedaan prefix maksimal 16 bit (maksimum 65.536 subnet per operasi).';
    } else {
        $totalCount = (int) (2 ** ($targetMask - $parsed['mask']));

        if ($isDownload) {
            $filename = sprintf(
                'ipv6_subnets_%s_slash_%d_to_%d.txt',
                str_replace(':', '_', $parsed['ip']),
                $parsed['mask'],
                $targetMask
            );
            sendAttachmentHeaders($filename, HEADER_TEXT_PLAIN);
            header('X-Content-Type-Options: nosniff');
            foreach (ipv6splitGenerate($parsed['ip'], $parsed['mask'], $targetMask) as $item) {
                echo $item . "\n";
            }
            exit;
        }

        $count = 0;
        foreach (ipv6splitGenerate($parsed['ip'], $parsed['mask'], $targetMask) as $item) {
            $previewSubnets[] = $item;
            $count++;
            if ($count >= 256) {
                break;
            }
        }
    }

    view('tools_ipcalc', [
        'title' => 'IPv6 Subnet Splitter',
        'user' => $user,
        'activeTab' => 'splitter',
        'subnet' => $subnet,
        'targetMask' => $targetMask,
        'previewSubnets' => $previewSubnets,
        'totalCount' => $totalCount,
        'error' => $error,
    ]);
}

/**
 * Perform RDAP with WHOIS socket fallback query.
 *
 * @return array{rdap: ?array<string, mixed>, socket: ?array<string, mixed>, error: ?string}
 */
function executeWhoisLookup(string $query, string $customServer, string $mode): array
{
    if ($mode === 'socket' || $customServer !== '') {
        $socketResult = whoisQuerySocket($query, $customServer !== '' ? $customServer : null);
        $error = !$socketResult['success'] ? ($socketResult['error'] ?? 'Gagal query WHOIS socket.') : null;
        return ['rdap' => null, 'socket' => $socketResult, 'error' => $error];
    }

    $rdapResult = whoisQueryRdap($query);
    if ($rdapResult['success']) {
        return ['rdap' => $rdapResult, 'socket' => null, 'error' => null];
    }

    $socketResult = whoisQuerySocket($query);
    $error = !$socketResult['success'] ? 'RDAP & WHOIS Socket tidak menemukan data untuk query tersebut.' : null;
    return ['rdap' => $rdapResult, 'socket' => $socketResult, 'error' => $error];
}

/**
 * Handler for WHOIS & RDAP Lookup Tool.
 *
 * @param array<string, mixed> $user
 */
function handleWhoisTool(array $user): void
{
    $query = trim((string) ($_GET['query'] ?? ($_POST['query'] ?? '')));
    $customServer = trim((string) ($_GET['server'] ?? ($_POST['server'] ?? '')));
    $mode = trim((string) ($_GET['mode'] ?? 'rdap'));

    $rdapResult = null;
    $socketResult = null;
    $error = null;

    if ($query !== '') {
        $lookup = executeWhoisLookup($query, $customServer, $mode);
        $rdapResult = $lookup['rdap'];
        $socketResult = $lookup['socket'];
        $error = $lookup['error'];
    }

    view('tools_whois', [
        'title' => 'WHOIS & RDAP Lookup',
        'user' => $user,
        'query' => $query,
        'server' => $customServer,
        'mode' => $mode,
        'rdapResult' => $rdapResult,
        'socketResult' => $socketResult,
        'error' => $error,
    ]);
}

/**
 * Handler for Native DNS Record Lookup Tool.
 *
 * @param array<string, mixed> $user
 */
function handleDnsLookupTool(array $user): void
{
    $domain = trim((string) ($_GET['domain'] ?? ($_POST['domain'] ?? '')));
    $type = strtoupper(trim((string) ($_GET['type'] ?? ($_POST['type'] ?? 'ANY'))));

    $validTypes = ['ANY', 'A', 'AAAA', 'NS', 'MX', 'TXT', 'SOA', 'CNAME', 'PTR', 'SRV', 'CAA'];
    if (!in_array($type, $validTypes, true)) {
        $type = 'ANY';
    }

    $records = [];
    $error = null;

    if ($domain !== '') {
        $result = dnsLookupAll($domain);
        if ($result['status'] === 'success') {
            $records = $result['records'];
            if ($type !== 'ANY') {
                $records = array_values(
                    array_filter($records, static fn (array $r): bool => ($r['type'] ?? '') === $type)
                );
            }
        } else {
            $error = $result['message'] ?? 'Tidak dapat melakukan query DNS.';
        }
    }

    view('tools_dns_lookup', [
        'title' => 'DNS Record Lookup',
        'user' => $user,
        'domain' => $domain,
        'type' => $type,
        'records' => $records,
        'error' => $error,
    ]);
}

/**
 * Handler for User Profile management (display name, email, password, and avatar).
 *
 * @param array<string, mixed> $user
 */
function updateProfileInfo(int $userId, array $user): void
{
    csrfCheck();
    $displayName = trim((string) ($_POST['display_name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('danger', 'Format email tidak valid.');
        redirect(PATH_PROFILE);
    }
    $up = db()->prepare('UPDATE users SET display_name = ?, email = ? WHERE id = ?');
    $up->execute([$displayName, $email, $userId]);
    audit($user, 'profile', '', 'Memperbarui profil (nama/email)');
    flash('success', 'Profil pengguna berhasil diperbarui.');
    redirect(PATH_PROFILE);
}

function updateProfilePassword(int $userId, array $user): void
{
    csrfCheck();
    $currPass = (string) ($_POST['current_password'] ?? '');
    $newPass = (string) ($_POST['new_password'] ?? '');
    $confirmPass = (string) ($_POST['confirm_password'] ?? '');

    $stHash = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stHash->execute([$userId]);
    $hashRow = $stHash->fetch();
    $currentHash = (string) ($hashRow['password_hash'] ?? '');

    if (!password_verify($currPass, $currentHash)) {
        flash('danger', 'Kata sandi saat ini tidak cocok.');
        redirect(PATH_PROFILE);
    }
    if (strlen($newPass) < 8) {
        flash('danger', 'Kata sandi baru minimal 8 karakter.');
        redirect(PATH_PROFILE);
    }
    if ($newPass !== $confirmPass) {
        flash('danger', 'Konfirmasi kata sandi baru tidak cocok.');
        redirect(PATH_PROFILE);
    }

    $newHash = password_hash($newPass, PASSWORD_ARGON2ID);
    $up = db()->prepare(SQL_UPDATE_USER_AUTH_HASH);
    $up->execute([$newHash, $userId]);
    audit($user, 'profile', '', 'Mengubah kata sandi');
    flash('success', 'Kata sandi berhasil diubah.');
    redirect(PATH_PROFILE);
}

function updateProfileAvatar(int $userId, array $user, array $freshUser): void
{
    csrfCheck();
    if (empty($_FILES['avatar']) || !is_array($_FILES['avatar'])) {
        flash('danger', 'Silakan pilih berkas gambar foto profil.');
        redirect(PATH_PROFILE);
    }
    $res = saveUserAvatar($_FILES['avatar'], $userId);
    if (!$res['ok']) {
        flash('danger', $res['error'] ?? 'Gagal mengunggah foto profil.');
        redirect(PATH_PROFILE);
    }

    $oldAvatar = (string) ($freshUser['avatar_url'] ?? '');
    if ($oldAvatar !== '' && str_starts_with($oldAvatar, '/uploads/avatars/')) {
        $oldFile = appRoot() . PATH_PUBLIC . $oldAvatar;
        if (is_file($oldFile)) {
            @unlink($oldFile);
        }
    }

    $newAvatarUrl = (string) ($res['path'] ?? '');
    $up = db()->prepare('UPDATE users SET avatar_url = ? WHERE id = ?');
    $up->execute([$newAvatarUrl, $userId]);
    audit($user, 'profile', '', 'Mengunggah foto profil baru');
    flash('success', 'Foto profil berhasil diperbarui.');
    redirect(PATH_PROFILE);
}

function deleteProfileAvatar(int $userId, array $user, array $freshUser): void
{
    csrfCheck();
    $oldAvatar = (string) ($freshUser['avatar_url'] ?? '');
    if ($oldAvatar !== '' && str_starts_with($oldAvatar, '/uploads/avatars/')) {
        $oldFile = appRoot() . PATH_PUBLIC . $oldAvatar;
        if (is_file($oldFile)) {
            @unlink($oldFile);
        }
    }
    $up = db()->prepare("UPDATE users SET avatar_url = '' WHERE id = ?");
    $up->execute([$userId]);
    audit($user, 'profile', '', 'Menghapus foto profil');
    flash('success', 'Foto profil telah dihapus.');
    redirect(PATH_PROFILE);
}

/**
 * Verify submitted TOTP token during profile 2FA enrollment.
 *
 * @param array<string, mixed> $user
 */
function verifyProfile2fa(int $userId, array $user): void
{
    $setup = $_SESSION['pending_totp_setup'] ?? null;
    if (!$setup || empty($setup['secret'])) {
        flash('danger', 'Sesi setup 2FA telah berakhir.');
        return;
    }
    $code = trim((string) ($_POST['code'] ?? ''));
    if (totpVerify((string) $setup['secret'], $code)) {
        $stUp = db()->prepare(
            'UPDATE users SET totp_secret = ?, totp_enabled = 1, totp_backup_codes = ? WHERE id = ?'
        );
        $stUp->execute([
            $setup['secret'],
            json_encode($setup['backup']['hashed']),
            $userId,
        ]);
        unset($_SESSION['pending_totp_setup']);
        audit($user, 'profile', '', 'Mengaktifkan Autentikasi Dua Faktor (2FA TOTP)');
        flash('success', 'Autentikasi Dua Faktor (2FA) berhasil diaktifkan!');
        return;
    }
    flash('danger', 'Kode 6-digit tidak valid. Pastikan waktu jam perangkat Anda akurat.');
}

/**
 * Disable TOTP 2FA for user profile after password verification.
 *
 * @param array<string, mixed> $user
 */
function disableProfile2fa(int $userId, array $user, string $passwordHash): void
{
    $pw = (string) ($_POST['current_password'] ?? '');
    if (password_verify($pw, $passwordHash)) {
        $stUp = db()->prepare(
            'UPDATE users SET totp_secret = NULL, totp_enabled = 0, totp_backup_codes = NULL WHERE id = ?'
        );
        $stUp->execute([$userId]);
        audit($user, 'profile', '', 'Menonaktifkan Autentikasi Dua Faktor (2FA)');
        flash('success', 'Autentikasi Dua Faktor (2FA) telah dinonaktifkan.');
        return;
    }
    flash('danger', 'Kata sandi saat ini salah.');
}

/**
 * Handle 2FA enrollment and disabling actions.
 *
 * @param array<string, mixed> $user
 * @param array<string, mixed> $freshUser
 */
function handleProfile2faActions(int $userId, array $user, array $freshUser, string $path): void
{
    csrfCheck();
    if ($path === '/profile/2fa/setup') {
        $secret = totpGenerateSecret(20);
        $backup = totpGenerateBackupCodes(10, 8);
        $_SESSION['pending_totp_setup'] = [
            'secret' => $secret,
            'backup' => $backup,
        ];
    } elseif ($path === '/profile/2fa/verify') {
        verifyProfile2fa($userId, $user);
    } elseif ($path === '/profile/2fa/disable') {
        disableProfile2fa($userId, $user, (string) $freshUser['password_hash']);
    }
    redirect(PATH_PROFILE);
}

/**
 * Dispatch POST actions for user profile management.
 *
 * @param array<string, mixed> $user
 * @param array<string, mixed> $freshUser
 */
function handleProfilePostActions(int $userId, array $user, array $freshUser, string $path): void
{
    if ($path === '/profile/update') {
        updateProfileInfo($userId, $user);
    } elseif ($path === '/profile/password') {
        updateProfilePassword($userId, $user);
    } elseif ($path === '/profile/avatar') {
        updateProfileAvatar($userId, $user, $freshUser);
    } elseif ($path === '/profile/avatar/delete') {
        deleteProfileAvatar($userId, $user, $freshUser);
    } elseif (str_starts_with($path, '/profile/2fa/')) {
        handleProfile2faActions($userId, $user, $freshUser, $path);
    }
}

/**
 * Handler for User Profile management (display name, email, password, and avatar).
 *
 * @param array<string, mixed> $user
 */
function handleProfile(array $user, string $path, string $method): void
{
    $userId = (int) ($user['id'] ?? 0);
    ensureEnterpriseSchema();
    $st = db()->prepare(
        'SELECT id, username, display_name, email, avatar_url, role, active, ' .
        'password_hash, last_login_at, created_at, ' .
        'totp_secret, totp_enabled, totp_backup_codes ' .
        'FROM users WHERE id = ?'
    );
    $st->execute([$userId]);
    /** @var array<string, mixed>|false $freshUser */
    $freshUser = $st->fetch();
    if (!$freshUser) {
        flash('danger', 'Pengguna tidak ditemukan.');
        redirect('/');
    }

    if ($method === 'POST') {
        handleProfilePostActions($userId, $user, $freshUser, $path);
    }

    $totpSetup = null;
    if (!empty($_SESSION['pending_totp_setup'])) {
        $setupData = $_SESSION['pending_totp_setup'];
        $uri = totpGetProvisioningUri((string) $setupData['secret'], (string) $freshUser['username'], appName());
        $totpSetup = [
            'secret' => $setupData['secret'],
            'uri' => $uri,
            'qrSvg' => totpGenerateQrSvg($uri, 180),
            'backup' => $setupData['backup'],
        ];
    }

    view('profile', [
        'title' => 'Profil Pengguna',
        'user' => $user,
        'profile' => $freshUser,
        'totpSetup' => $totpSetup,
    ]);
}

function downloadDatabaseBackup(array $user): never
{
    $sql = backupDatabaseMetadata();
    $filename = 'pdns_admin_db_backup_' . gmdate('Ymd_His') . '.sql';
    sendAttachmentHeaders($filename, 'application/sql; charset=utf-8', strlen($sql));
    echo $sql;
    audit($user, 'backup', '', 'Mengunduh cadangan SQL database metadata');
    exit;
}

function downloadConfigBackup(array $user): never
{
    $config = backupConfigSettings();
    $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        flash('danger', 'Gagal mengenkode JSON konfigurasi.');
        redirect(PATH_BACKUP);
    }
    $filename = 'pdns_admin_config_' . gmdate('Ymd_His') . '.json';
    sendAttachmentHeaders($filename, 'application/json; charset=utf-8', strlen($json));
    echo $json;
    audit($user, 'backup', '', 'Mengunduh cadangan konfigurasi JSON');
    exit;
}

function downloadZonesBackup(array $user): never
{
    try {
        $pdns = PdnsClient::fromSettings();
        $zonesData = backupAllZones($pdns);
        $json = json_encode($zonesData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new UnexpectedValueException('Gagal mengenkode JSON zona.');
        }
        $filename = 'pdns_admin_zones_' . gmdate('Ymd_His') . '.json';
        sendAttachmentHeaders($filename, 'application/json; charset=utf-8', strlen($json));
        echo $json;
        audit($user, 'backup', '', 'Mengunduh cadangan zona PowerDNS JSON (' . $zonesData['count'] . ' zona)');
        exit;
    } catch (Throwable $e) {
        flash('danger', 'Gagal mencadangkan zona PowerDNS: ' . $e->getMessage());
        redirect(PATH_BACKUP);
    }
}

function handleBackupDownloads(array $user, string $path): void
{
    if ($path === '/backup/download/db') {
        downloadDatabaseBackup($user);
    } elseif ($path === '/backup/download/config') {
        downloadConfigBackup($user);
    } elseif ($path === '/backup/download/zones') {
        downloadZonesBackup($user);
    }
}

function restoreDatabaseBackup(array $user): void
{
    csrfCheck();
    if (
        empty($_FILES['db_file']) ||
        !is_array($_FILES['db_file']) ||
        ($_FILES['db_file']['error'] ?? 1) !== UPLOAD_ERR_OK
    ) {
        flash('danger', 'Pilih berkas cadangan SQL yang valid.');
        redirect(PATH_BACKUP);
    }
    $tmp = (string) ($_FILES['db_file']['tmp_name'] ?? '');
    $sql = (string) file_get_contents($tmp);
    $res = restoreDatabaseMetadata($sql);
    if ($res['success']) {
        audit($user, 'restore', '', 'Memulihkan database metadata (' . $res['count'] . ' kueri)');
        flash('success', 'Database metadata berhasil dipulihkan (' . $res['count'] . ' perintah SQL dieksekusi).');
    } else {
        flash('danger', 'Pemulihan database gagal: ' . ($res['error'] ?? 'Kesalahan tidak diketahui.'));
    }
    redirect(PATH_BACKUP);
}

function restoreConfigBackup(array $user): void
{
    csrfCheck();
    if (
        empty($_FILES['config_file']) ||
        !is_array($_FILES['config_file']) ||
        ($_FILES['config_file']['error'] ?? 1) !== UPLOAD_ERR_OK
    ) {
        flash('danger', 'Pilih berkas konfigurasi JSON yang valid.');
        redirect(PATH_BACKUP);
    }
    $tmp = (string) ($_FILES['config_file']['tmp_name'] ?? '');
    $raw = (string) file_get_contents($tmp);
    try {
        /** @var array<string, mixed> $data */
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $res = restoreConfigSettings($data);
        if ($res['success']) {
            audit($user, 'restore', '', 'Memulihkan konfigurasi settings (' . $res['count'] . ' opsi)');
            flash('success', 'Konfigurasi berhasil dipulihkan (' . $res['count'] . ' pengaturan diperbarui).');
        } else {
            flash('danger', 'Pemulihan konfigurasi gagal: ' . ($res['error'] ?? 'Format tidak valid.'));
        }
    } catch (Throwable $e) {
        flash('danger', 'Berkas JSON rusak atau tidak valid: ' . $e->getMessage());
    }
    redirect(PATH_BACKUP);
}

function restoreZonesBackup(array $user): void
{
    csrfCheck();
    if (
        empty($_FILES['zones_file']) ||
        !is_array($_FILES['zones_file']) ||
        ($_FILES['zones_file']['error'] ?? 1) !== UPLOAD_ERR_OK
    ) {
        flash('danger', 'Pilih berkas cadangan zona JSON yang valid.');
        redirect(PATH_BACKUP);
    }
    $tmp = (string) ($_FILES['zones_file']['tmp_name'] ?? '');
    $raw = (string) file_get_contents($tmp);
    try {
        /** @var array<string, mixed> $data */
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $pdns = PdnsClient::fromSettings();
        $res = restoreZones($pdns, $data);
        if ($res['success']) {
            audit($user, 'restore', '', 'Memulihkan zona (' . $res['restored'] . ' zona)');
            flash(
                'success',
                'Pemulihan zona selesai: ' . $res['restored'] . ' dari ' . $res['total'] .
                ' zona berhasil dipulihkan.'
            );
        } else {
            flash('danger', 'Pemulihan zona mengalami masalah: ' . implode('; ', $res['errors']));
        }
    } catch (Throwable $e) {
        flash('danger', 'Gagal memproses berkas zona: ' . $e->getMessage());
    }
    redirect(PATH_BACKUP);
}

function handleBackupRestores(array $user, string $path): void
{
    if ($path === '/backup/restore/db') {
        restoreDatabaseBackup($user);
    } elseif ($path === '/backup/restore/config') {
        restoreConfigBackup($user);
    } elseif ($path === '/backup/restore/zones') {
        restoreZonesBackup($user);
    }
}

/**
 * Gather database table count metrics and PowerDNS status for backup overview.
 *
 * @return array{tableCounts: array<string, int>, zoneCount: int, apiOk: bool}
 */
function getBackupOverview(): array
{
    $tableCounts = [];
    foreach (APP_METADATA_TABLES as $tbl) {
        try {
            $st = db()->query("SELECT COUNT(*) FROM `{$tbl}`");
            $tableCounts[$tbl] = $st ? (int) $st->fetchColumn() : 0;
        } catch (Throwable) {
            $tableCounts[$tbl] = 0;
        }
    }

    $zoneCount = 0;
    $apiOk = false;
    try {
        $pdns = PdnsClient::fromSettings();
        $remote = $pdns->zones();
        $zoneCount = count($remote);
        $apiOk = true;
    } catch (Throwable) {
        $apiOk = false;
    }

    return [
        'tableCounts' => $tableCounts,
        'zoneCount' => $zoneCount,
        'apiOk' => $apiOk,
    ];
}

/**
 * Handler for Database, Settings, and Zones Backup & Restore.
 *
 * @param array<string, mixed> $user
 */
function handleBackup(array $user, string $path, string $method): void
{
    requireRole($user, ['admin']);

    if ($method === 'GET' && str_starts_with($path, '/backup/download/')) {
        handleBackupDownloads($user, $path);
    }

    if ($method === 'POST' && str_starts_with($path, '/backup/restore/')) {
        handleBackupRestores($user, $path);
    }

    $overview = getBackupOverview();
    view('backup', array_merge([
        'title' => 'Cadangan & Pemulihan',
        'user' => $user,
    ], $overview));
}

/**
 * Dispatch POST actions for PowerDNS cluster node management.
 */
function handleServersPost(string $path): void
{
    if ($path === '/servers/add') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $apiUrl = trim((string) ($_POST['api_url'] ?? ''));
        $apiKey = (string) ($_POST['api_key'] ?? '');
        $serverId = trim((string) ($_POST['server_id'] ?? 'localhost'));
        $isDefault = !empty($_POST['is_default']);
        $isActive = !empty($_POST['is_active']);

        if ($name === '' || $apiUrl === '' || $apiKey === '') {
            flash('danger', 'Nama, API URL, dan API Key wajib diisi.');
        } else {
            PdnsCluster::addServer(
                $name,
                $apiUrl,
                $apiKey,
                $serverId !== '' ? $serverId : 'localhost',
                $isDefault,
                $isActive
            );
            flash('success', 'Node server ' . $name . ' berhasil ditambahkan ke cluster.');
        }
    } elseif ($path === '/servers/update') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $apiUrl = trim((string) ($_POST['api_url'] ?? ''));
        $serverId = trim((string) ($_POST['server_id'] ?? 'localhost'));
        $isDefault = !empty($_POST['is_default']);
        $isActive = !empty($_POST['is_active']);
        $apiKey = trim((string) ($_POST['api_key'] ?? ''));

        PdnsCluster::updateServer(
            $id,
            $name,
            $apiUrl,
            $apiKey !== '' ? $apiKey : null,
            $serverId !== '' ? $serverId : 'localhost',
            $isDefault,
            $isActive
        );
        flash('success', 'Pengaturan node server berhasil diperbarui.');
    } elseif ($path === '/servers/delete') {
        $id = (int) ($_POST['id'] ?? 0);
        PdnsCluster::deleteServer($id);
        flash('success', 'Node server berhasil dihapus.');
    }
}

/**
 * Dispatch GET actions for PowerDNS cluster node management.
 */
function handleServersGet(string $path): void
{
    if ($path === '/servers/switch') {
        $id = (int) ($_GET['id'] ?? 0);
        PdnsCluster::setActiveServerId($id);
        flash('success', 'Target node cluster aktif dialihkan.');
    } elseif ($path === '/servers/ping') {
        $id = (int) ($_GET['id'] ?? 0);
        $res = PdnsCluster::pingServer($id);
        if ($res['success']) {
            flash('success', "Koneksi node server berhasil diuji. Latensi: {$res['latency_ms']} ms.");
        } else {
            flash('danger', 'Gagal menghubungi node server: ' . $res['message']);
        }
    }
}

/**
 * Multi-Server PowerDNS Node Clustering Management Handler.
 *
 * @param array<string, mixed> $user
 */
function handleServers(array $user, string $path, string $method): void
{
    requireRole($user, ['admin']);

    if ($method === 'POST') {
        csrfCheck();
        handleServersPost($path);
        redirect(PATH_SERVERS);
    }

    if ($method === 'GET' && ($path === '/servers/switch' || $path === '/servers/ping')) {
        handleServersGet($path);
        redirect(PATH_SERVERS);
    }

    view('servers', [
        'title' => 'Node PowerDNS Cluster',
        'user' => $user,
        'servers' => PdnsCluster::listServers(),
        'activeServer' => PdnsCluster::getActiveServer(),
    ]);
}

/**
 * Handle creation of new webhook endpoint.
 */
function handleWebhookAdd(): void
{
    $name = trim((string) ($_POST['name'] ?? ''));
    $url = trim((string) ($_POST['url'] ?? ''));
    $secret = trim((string) ($_POST['secret'] ?? ''));
    $rawEvents = $_POST['events'] ?? [];
    $events = is_array($rawEvents)
        ? implode(',', array_map('trim', $rawEvents))
        : 'zone.created,zone.deleted,record.updated';
    $isActive = !empty($_POST['is_active']);

    if ($name === '' || $url === '') {
        flash('danger', 'Nama dan URL webhook wajib diisi.');
        return;
    }

    createWebhook(
        $name,
        $url,
        $secret !== '' ? $secret : bin2hex(random_bytes(16)),
        $events,
        $isActive
    );
    flash('success', 'Webhook baru berhasil didaftarkan.');
}

/**
 * Handle updating an existing webhook endpoint.
 */
function handleWebhookUpdate(): void
{
    $id = (int) ($_POST['id'] ?? 0);
    $name = trim((string) ($_POST['name'] ?? ''));
    $url = trim((string) ($_POST['url'] ?? ''));
    $secret = trim((string) ($_POST['secret'] ?? ''));
    $rawEvents = $_POST['events'] ?? [];
    $events = is_array($rawEvents) ? implode(',', array_map('trim', $rawEvents)) : '';
    $isActive = !empty($_POST['is_active']);

    updateWebhook(
        $id,
        $name,
        $url,
        $secret !== '' ? $secret : null,
        $events,
        $isActive
    );
    flash('success', 'Pengaturan webhook diperbarui.');
}

/**
 * Handle deletion of a webhook endpoint.
 */
function handleWebhookDelete(): void
{
    $id = (int) ($_POST['id'] ?? 0);
    deleteWebhook($id);
    flash('success', 'Webhook berhasil dihapus.');
}

/**
 * Dispatch POST actions for webhooks management.
 */
function handleWebhooksPost(string $path): void
{
    if ($path === '/webhooks/add') {
        handleWebhookAdd();
    } elseif ($path === '/webhooks/update') {
        handleWebhookUpdate();
    } elseif ($path === '/webhooks/delete') {
        handleWebhookDelete();
    }
}

/**
 * Webhooks Management Handler.
 *
 * @param array<string, mixed> $user
 */
function handleWebhooks(array $user, string $path, string $method): void
{
    requireRole($user, ['admin']);

    if ($method === 'POST') {
        csrfCheck();
        handleWebhooksPost($path);
        redirect(PATH_WEBHOOKS);
    }

    if ($method === 'GET' && $path === '/webhooks/test') {
        $id = (int) ($_GET['id'] ?? 0);
        $res = testWebhookDelivery($id);
        if ($res['success']) {
            flash('success', 'Uji coba payload webhook berhasil dikirim (' . $res['message'] . ').');
        } else {
            flash('danger', 'Gagal mengirim uji coba webhook: ' . $res['message']);
        }
        redirect(PATH_WEBHOOKS);
    }

    view('webhooks', [
        'title' => 'Webhooks CI/CD & Integrasi',
        'user' => $user,
        'webhooks' => listWebhooks(),
    ]);
}

/**
 * Cross-Zone Bulk Record Operations Handler.
 *
 * @param array<string, mixed> $user
 */
function handleBulkRecords(array $user, string $path, string $method): void
{
    requireRole($user, ['admin', 'operator']);

    $replaceResult = null;
    $query = trim((string) ($_REQUEST['q'] ?? ''));
    $typeFilter = trim((string) ($_REQUEST['type'] ?? ''));
    $results = [];

    if ($method === 'POST' && $path === '/bulk-records/replace') {
        csrfCheck();
        $target = trim((string) ($_POST['target'] ?? ''));
        $replacement = trim((string) ($_POST['replacement'] ?? ''));
        $type = trim((string) ($_POST['type'] ?? ''));

        if ($target === '') {
            flash('danger', 'Konten target penggantian tidak boleh kosong.');
            redirect('/bulk-records');
        }

        try {
            $pdns = PdnsClient::fromSettings();
            $replaceResult = bulkReplaceRecords(
                $pdns,
                $user,
                $target,
                $replacement,
                $type !== '' ? $type : null
            );
            $query = $replacement;
            $results = bulkSearchRecords($pdns, $replacement, $type !== '' ? $type : null);
        } catch (Throwable $e) {
            flash('danger', 'Gagal menjalankan penggantian massal: ' . $e->getMessage());
        }
    } elseif ($query !== '') {
        try {
            $pdns = PdnsClient::fromSettings();
            $results = bulkSearchRecords($pdns, $query, $typeFilter !== '' ? $typeFilter : null);
        } catch (Throwable $e) {
            flash('danger', 'Gagal mencari record DNS: ' . $e->getMessage());
        }
    }

    view('bulk_records', [
        'title' => 'Operasi Rekam Massal (Bulk Records)',
        'user' => $user,
        'query' => $query,
        'typeFilter' => $typeFilter,
        'results' => $results,
        'replaceResult' => $replaceResult,
    ]);
}

/**
 * Advanced DNS Telemetry & Visual Analytics Handler.
 *
 * @param array<string, mixed> $user
 */
function handleAnalytics(array $user, string $path): void
{
    requireRole($user, ['admin', 'operator']);

    if ($path === '/analytics/export') {
        try {
            $pdns = PdnsClient::fromSettings();
            $raw = $pdns->statistics(true);
            $active = PdnsCluster::getActiveServer();
            $exportData = [
                'exported_at' => date('c'),
                'server' => $active['name'] ?? 'Local PowerDNS Daemon',
                'statistics' => $raw,
            ];
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="pdns_telemetry_' . date('Ymd_His') . '.json"');
            echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            exit;
        } catch (Throwable $e) {
            flash('danger', 'Gagal mengekspor data telemetri: ' . $e->getMessage());
            redirect('/analytics');
        }
    }

    $refresh = (int) ($_GET['refresh'] ?? 0);
    $mask = !empty($_GET['mask']);
    $metrics = [];
    $topQueries = [];
    $topRemotes = [];

    $active = PdnsCluster::getActiveServer();
    $serverName = $active['name'] ?? 'Local PowerDNS Daemon';

    try {
        $pdns = PdnsClient::fromSettings();
        $raw = $pdns->statistics(true);
        foreach ($raw as $item) {
            if (is_array($item) && isset($item['name'], $item['value'])) {
                $metrics[(string) $item['name']] = $item['value'];
            }
        }

        if (isset($metrics['queries']) && is_array($metrics['queries'])) {
            $topQueries = parseRingBuffer($metrics['queries'], 10);
        }
        if (isset($metrics['remotes']) && is_array($metrics['remotes'])) {
            $topRemotes = parseRingBuffer($metrics['remotes'], 10, $mask);
        }
    } catch (Throwable $e) {
        flash('warning', 'Gagal memuat telemetri PowerDNS: ' . $e->getMessage());
    }

    view('analytics', [
        'title' => 'Telemetri & Analitik DNS',
        'user' => $user,
        'metrics' => $metrics,
        'topQueries' => $topQueries,
        'topRemotes' => $topRemotes,
        'serverName' => $serverName,
        'refreshSeconds' => $refresh,
        'anonymizeIp' => $mask,
    ]);
}
