<?php
declare(strict_types=1);

function handle_install(): void
{
    if (installed()) {
        redirect('/');
    }
    $error = '';
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        csrf_check();
        $host = trim((string) ($_POST['db_host'] ?? '127.0.0.1'));
        $port = (int) ($_POST['db_port'] ?? 3306);
        $name = trim((string) ($_POST['db_name'] ?? 'pda'));
        $user = trim((string) ($_POST['db_user'] ?? ''));
        $pass = (string) ($_POST['db_pass'] ?? '');
        $admin = trim((string) ($_POST['admin_user'] ?? 'admin'));
        $adminPass = (string) ($_POST['admin_pass'] ?? '');
        $pdnsUrl = rtrim(trim((string) ($_POST['pdns_url'] ?? '')), '/');
        $pdnsKey = trim((string) ($_POST['pdns_key'] ?? ''));
        if (!preg_match('/^[a-zA-Z0-9_]{3,32}$/', $admin) || strlen($adminPass) < 10) {
            $error = 'Username admin 3-32 karakter. Sandi minimal 10 karakter.';
        } elseif (!preg_match('#^https?://#', $pdnsUrl) || $pdnsKey === '') {
            $error = 'URL API PowerDNS dan API key wajib diisi.';
        } else {
            try {
                $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name);
                $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $sql = file_get_contents(app_root() . '/sql/schema.sql');
                foreach (array_filter(array_map('trim', explode(';', (string) $sql))) as $stmt) {
                    if ($stmt !== '') {
                        $pdo->exec($stmt);
                    }
                }
                $hash = password_hash($adminPass, PASSWORD_ARGON2ID);
                $pdo->prepare('INSERT INTO users (username, password_hash, display_name, role, active) VALUES (?, ?, ?, ?, 1)')
                    ->execute([$admin, $hash, 'Administrator', 'admin']);
                $appKey = base64_encode(random_bytes(32));
                $cfg = "<?php\nreturn " . var_export([
                    'installed' => true,
                    'db' => ['host' => $host, 'port' => $port, 'name' => $name, 'user' => $user, 'pass' => $pass, 'charset' => 'utf8mb4'],
                    'app_key' => $appKey,
                ], true) . ";\n";
                if (file_put_contents(config_path(), $cfg) === false) {
                    throw new RuntimeException('Tidak bisa menulis config.php. Periksa izin direktori.');
                }
                chmod(config_path(), 0640);
                $enc = openssl_encrypt($pdnsKey, 'aes-256-gcm', substr(base64_decode($appKey), 0, 32), OPENSSL_RAW_DATA, $iv = random_bytes(12), $tag);
                $stored = base64_encode($iv . $tag . $enc);
                $pdo->prepare('INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)')->execute(['pdns_api_url', $pdnsUrl]);
                $pdo->prepare('INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)')->execute(['pdns_api_key', $stored]);
                $pdo->prepare('INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)')->execute(['pdns_server_id', 'localhost']);
                $pdo->prepare('INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)')->execute(['pdns_verify_tls', '1']);
                redirect('/login');
            } catch (Throwable $ex) {
                $error = $ex->getMessage();
            }
        }
    }
    view('install', ['title' => 'Instalasi', 'error' => $error]);
}

function handle_login(): void
{
    if (current_user()) {
        redirect('/');
    }
    $error = '';
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        csrf_check();
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $ip = client_ip();
        $st = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND success = 0 AND created_at > (NOW() - INTERVAL 15 MINUTE)');
        $st->execute([$ip]);
        if ((int) $st->fetchColumn() >= 8) {
            $error = 'Terlalu banyak percobaan. Tunggu 15 menit.';
        } else {
            $st = db()->prepare('SELECT * FROM users WHERE username = ?');
            $st->execute([$username]);
            $user = $st->fetch();
            $ok = $user && (int) $user['active'] === 1 && password_verify($password, (string) $user['password_hash']);
            db()->prepare('INSERT INTO login_attempts (username, ip, success) VALUES (?, ?, ?)')->execute([$username, $ip, $ok ? 1 : 0]);
            if ($ok) {
                session_regenerate_id(true);
                $_SESSION['uid'] = (int) $user['id'];
                db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([(int) $user['id']]);
                if (password_needs_rehash((string) $user['password_hash'], PASSWORD_ARGON2ID)) {
                    db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_ARGON2ID), (int) $user['id']]);
                }
                redirect('/');
            }
            $error = 'Username atau sandi salah.';
        }
    }
    view('login', ['title' => 'Masuk', 'error' => $error]);
}

function handle_logout(): void
{
    csrf_check();
    $_SESSION = [];
    session_destroy();
    redirect('/login');
}

function handle_dashboard(array $user): void
{
    $zones = (int) db()->query('SELECT COUNT(*) FROM zones')->fetchColumn();
    $users = (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $accounts = (int) db()->query('SELECT COUNT(*) FROM accounts')->fetchColumn();
    $dnssec = (int) db()->query('SELECT COUNT(*) FROM zones WHERE dnssec = 1')->fetchColumn();
    $stats = [];
    $apiOk = false;
    $apiError = '';
    try {
        $pdns = PdnsClient::fromSettings();
        $pdns->ping();
        $apiOk = true;
        $raw = $pdns->statistics(false);
        $want = ['udp-queries','udp-answers','tcp-queries','tcp-answers','packetcache-hit','packetcache-miss','servfail-answers','qsize-q'];
        foreach ($raw as $item) {
            if (!is_array($item) || !in_array($item['name'] ?? '', $want, true)) {
                continue;
            }
            $stats[(string) $item['name']] = (string) ($item['value'] ?? '0');
        }
    } catch (Throwable $ex) {
        $apiError = $ex->getMessage();
    }
    $recent = db()->query('SELECT * FROM history ORDER BY id DESC LIMIT 8')->fetchAll();
    view('dashboard', compact('user', 'zones', 'users', 'accounts', 'dnssec', 'stats', 'apiOk', 'apiError', 'recent') + ['title' => 'Dasbor']);
}

function handle_zones(array $user): void
{
    $q = trim((string) ($_GET['q'] ?? ''));
    $kind = trim((string) ($_GET['kind'] ?? ''));
    $sql = 'SELECT z.*, a.name AS account_name FROM zones z LEFT JOIN accounts a ON a.id = z.account_id WHERE 1=1';
    $args = [];
    if ($user['role'] !== 'admin') {
        $sql .= ' AND (z.id IN (SELECT zone_id FROM zone_user WHERE user_id = ?) OR z.account_id IN (SELECT account_id FROM account_user WHERE user_id = ?))';
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

function handle_zone_sync(array $user): void
{
    csrf_check();
    require_role($user, ['admin', 'operator']);
    try {
        $n = sync_zones_from_pdns(PdnsClient::fromSettings());
        audit($user, 'sync', '', 'Sinkron ' . $n . ' zona');
        flash('success', 'Sinkron selesai: ' . $n . ' zona dari PowerDNS.');
    } catch (Throwable $ex) {
        flash('danger', $ex->getMessage());
    }
    redirect('/zones');
}

function handle_zone_create(array $user): void
{
    require_role($user, ['admin', 'operator']);
    $error = '';
    $accounts = db()->query('SELECT id, name FROM accounts ORDER BY name')->fetchAll();
    $templates = db()->query('SELECT id, name FROM templates ORDER BY name')->fetchAll();
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        csrf_check();
        $name = dns_canonical((string) ($_POST['name'] ?? ''));
        $kind = (string) ($_POST['kind'] ?? 'Native');
        $ns = array_values(array_filter(array_map('trim', explode(',', (string) ($_POST['nameservers'] ?? '')))));
        $masters = array_values(array_filter(array_map('trim', explode(',', (string) ($_POST['masters'] ?? '')))));
        $soaEdit = (string) ($_POST['soa_edit_api'] ?? 'DEFAULT');
        $accountId = (int) ($_POST['account_id'] ?? 0);
        $allowedSoa = ['DEFAULT','INCREASE','EPOCH','SOA-EDIT','SOA-EDIT-INCREASE'];
        if (!str_ends_with($name, '.') || !preg_match('/^[a-z0-9_.*\/-]+\.$/', $name)) {
            $error = 'Nama zona tidak valid. Contoh: example.com atau 10.in-addr.arpa';
        } elseif (!in_array($kind, ['Native','Master','Slave','Producer','Consumer'], true)) {
            $error = 'Jenis zona tidak dikenal.';
        } elseif ($kind === 'Slave' && !$masters) {
            $error = 'Zona Slave wajib punya alamat primary.';
        } elseif (!in_array($soaEdit, $allowedSoa, true)) {
            $error = 'Mode SOA-EDIT-API tidak valid.';
        } else {
            $nsCanon = array_map('dns_canonical', $ns);
            $payload = [
                'name' => $name,
                'kind' => $kind,
                'masters' => $kind === 'Slave' ? $masters : [],
                'nameservers' => $nsCanon,
                'soa_edit_api' => $soaEdit,
                'api_rectify' => true,
            ];
            try {
                $pdns = PdnsClient::fromSettings();
                $pdns->createZone($payload);
                db()->prepare('INSERT INTO zones (name, kind, account_id, synced_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE kind = VALUES(kind), account_id = VALUES(account_id), synced_at = NOW()')
                    ->execute([$name, $kind, $accountId > 0 ? $accountId : null]);
                $tpl = (int) ($_POST['template_id'] ?? 0);
                if ($tpl > 0) {
                    apply_template($pdns, $name, $tpl);
                }
                audit($user, 'create-zone', $name, $kind);
                flash('success', 'Zona ' . dns_display($name) . ' dibuat.');
                redirect('/zones/' . rawurlencode(rtrim($name, '.')));
            } catch (Throwable $ex) {
                $error = $ex->getMessage();
            }
        }
    }
    view('zone_create', ['title' => 'Zona baru', 'user' => $user, 'error' => $error, 'accounts' => $accounts, 'templates' => $templates]);
}

function apply_template(PdnsClient $pdns, string $zone, int $templateId): void
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
    $diff = diff_rrsets($zone, $current['rrsets'] ?? [], array_merge(flatten_rrsets($current['rrsets'] ?? [], $zone), $rows));
    if ($diff) {
        $pdns->patchRrsets($zone, $diff);
    }
}

function handle_zone_show(array $user, string $zoneRaw): void
{
    $zone = dns_canonical(rawurldecode($zoneRaw));
    require_zone_access($user, $zone, false);
    $error = '';
    try {
        $pdns = PdnsClient::fromSettings();
        $data = $pdns->zone($zone);
    } catch (Throwable $ex) {
        view('error', ['title' => 'Zona', 'message' => $ex->getMessage(), 'user' => $user]);
        return;
    }
    $meta = db()->prepare('SELECT z.*, a.name AS account_name FROM zones z LEFT JOIN accounts a ON a.id = z.account_id WHERE z.name = ?');
    $meta->execute([$zone]);
    $local = $meta->fetch() ?: ['name' => $zone, 'kind' => $data['kind'] ?? '', 'account_name' => '', 'comment' => ''];
    $rows = flatten_rrsets($data['rrsets'] ?? [], $zone);
    $canEdit = user_can_zone($user, $zone, true);
    view('zone_show', [
        'title' => dns_display($zone),
        'user' => $user,
        'zone' => $zone,
        'data' => $data,
        'local' => $local,
        'rows' => $rows,
        'soa' => soa_of($data),
        'canEdit' => $canEdit,
        'error' => $error,
        'types' => RECORD_TYPES,
    ]);
}

function handle_zone_save(array $user, string $zoneRaw): void
{
    csrf_check();
    $zone = dns_canonical(rawurldecode($zoneRaw));
    require_zone_access($user, $zone, true);
    if ($user['role'] === 'user' && !user_can_zone($user, $zone, true)) {
        require_zone_access($user, $zone, true);
    }
    $names = $_POST['r_name'] ?? [];
    $types = $_POST['r_type'] ?? [];
    $ttls = $_POST['r_ttl'] ?? [];
    $contents = $_POST['r_content'] ?? [];
    $disabled = $_POST['r_disabled'] ?? [];
    $comments = $_POST['r_comment'] ?? [];
    $rows = [];
    $count = is_array($names) ? count($names) : 0;
    for ($i = 0; $i < $count; $i++) {
        $content = trim((string) ($contents[$i] ?? ''));
        $name = trim((string) ($names[$i] ?? ''));
        if ($content === '' && $name === '') {
            continue;
        }
        $type = strtoupper((string) ($types[$i] ?? ''));
        $err = validate_record($type, $content);
        if ($err) {
            flash('danger', 'Baris ' . ($i + 1) . ': ' . $err);
            redirect('/zones/' . rawurlencode(rtrim($zone, '.')));
        }
        $rows[] = [
            'name' => $name,
            'type' => $type,
            'ttl' => (int) ($ttls[$i] ?? 3600),
            'content' => $content,
            'disabled' => isset($disabled[$i]) && (string) $disabled[$i] === '1',
            'comment' => (string) ($comments[$i] ?? ''),
        ];
    }
    try {
        $pdns = PdnsClient::fromSettings();
        $current = $pdns->zone($zone);
        $diff = diff_rrsets($zone, $current['rrsets'] ?? [], $rows);
        if ($diff) {
            $pdns->patchRrsets($zone, $diff);
        }
        audit($user, 'update-records', $zone, count($rows) . ' baris dikirim');
        flash('success', 'Perubahan record diterapkan ke PowerDNS.');
    } catch (Throwable $ex) {
        flash('danger', $ex->getMessage());
    }
    redirect('/zones/' . rawurlencode(rtrim($zone, '.')));
}

function handle_zone_delete(array $user, string $zoneRaw): void
{
    csrf_check();
    require_role($user, ['admin']);
    $zone = dns_canonical(rawurldecode($zoneRaw));
    try {
        PdnsClient::fromSettings()->deleteZone($zone);
        db()->prepare('DELETE FROM zones WHERE name = ?')->execute([$zone]);
        audit($user, 'delete-zone', $zone, '');
        flash('success', 'Zona dihapus dari PowerDNS.');
    } catch (Throwable $ex) {
        flash('danger', $ex->getMessage());
    }
    redirect('/zones');
}

function handle_zone_action(array $user, string $zoneRaw, string $action): void
{
    csrf_check();
    require_role($user, ['admin', 'operator']);
    $zone = dns_canonical(rawurldecode($zoneRaw));
    require_zone_access($user, $zone, true);
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
    redirect('/zones/' . rawurlencode(rtrim($zone, '.')));
}

function handle_dnssec(array $user, string $zoneRaw): void
{
    $zone = dns_canonical(rawurldecode($zoneRaw));
    require_zone_access($user, $zone, false);
    $keys = [];
    $error = '';
    try {
        $keys = PdnsClient::fromSettings()->cryptokeys($zone);
    } catch (Throwable $ex) {
        $error = $ex->getMessage();
    }
    foreach ($keys as &$key) {
        unset($key['privatekey']);
    }
    unset($key);
    view('dnssec', ['title' => 'DNSSEC', 'user' => $user, 'zone' => $zone, 'keys' => $keys, 'error' => $error]);
}

function handle_dnssec_enable(array $user, string $zoneRaw): void
{
    csrf_check();
    require_role($user, ['admin', 'operator']);
    $zone = dns_canonical(rawurldecode($zoneRaw));
    require_zone_access($user, $zone, true);
    $mode = (string) ($_POST['mode'] ?? 'csk');
    try {
        $pdns = PdnsClient::fromSettings();
        $pdns->updateZone($zone, ['dnssec' => true, 'api_rectify' => true]);
        if ($mode === 'split') {
            $pdns->createCryptokey($zone, ['keytype' => 'ksk', 'active' => true, 'published' => true, 'algorithm' => 'ecdsa256', 'bits' => 256]);
            $pdns->createCryptokey($zone, ['keytype' => 'zsk', 'active' => true, 'published' => true, 'algorithm' => 'ecdsa256', 'bits' => 256]);
        } else {
            $pdns->createCryptokey($zone, ['keytype' => 'csk', 'active' => true, 'published' => true, 'algorithm' => 'ecdsa256', 'bits' => 256]);
        }
        $pdns->rectify($zone);
        db()->prepare('UPDATE zones SET dnssec = 1 WHERE name = ?')->execute([$zone]);
        audit($user, 'dnssec-enable', $zone, $mode);
        flash('success', 'DNSSEC diaktifkan. Mode ' . $mode . ' memakai ECDSA P-256. CSK adalah perilaku modern PowerDNS; pilih split jika butuh KSK dan ZSK terpisah.');
    } catch (Throwable $ex) {
        flash('danger', $ex->getMessage());
    }
    redirect('/zones/' . rawurlencode(rtrim($zone, '.')) . '/dnssec');
}

function handle_zone_grant(array $user, string $zoneRaw): void
{
    csrf_check();
    require_role($user, ['admin']);
    $zone = dns_canonical(rawurldecode($zoneRaw));
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
        redirect('/zones/' . rawurlencode(rtrim($zone, '.')));
    }
    db()->prepare('INSERT INTO zone_user (zone_id, user_id, can_edit) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE can_edit = VALUES(can_edit)')
        ->execute([(int) $z['id'], (int) $target['id'], $canEdit]);
    audit($user, 'grant-zone', $zone, $username);
    flash('success', 'Akses zona diperbarui.');
    redirect('/zones/' . rawurlencode(rtrim($zone, '.')));
}

function handle_users(array $user): void
{
    require_role($user, ['admin']);
    $users = db()->query('SELECT id, username, display_name, email, role, active, last_login_at FROM users ORDER BY username')->fetchAll();
    view('users', ['title' => 'Pengguna', 'user' => $user, 'users' => $users]);
}

function handle_user_save(array $user): void
{
    csrf_check();
    require_role($user, ['admin']);
    $id = (int) ($_POST['id'] ?? 0);
    $username = trim((string) ($_POST['username'] ?? ''));
    $display = trim((string) ($_POST['display_name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $role = (string) ($_POST['role'] ?? 'user');
    $password = (string) ($_POST['password'] ?? '');
    $active = isset($_POST['active']) ? 1 : 0;
    if (!preg_match('/^[a-zA-Z0-9_.-]{3,64}$/', $username) || !in_array($role, ['admin','operator','user'], true)) {
        flash('danger', 'Data pengguna tidak valid.');
        redirect('/users');
    }
    if ($id === 0) {
        if (strlen($password) < 10) {
            flash('danger', 'Sandi awal minimal 10 karakter.');
            redirect('/users');
        }
        db()->prepare('INSERT INTO users (username, password_hash, display_name, email, role, active) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$username, password_hash($password, PASSWORD_ARGON2ID), $display, $email, $role, $active]);
        audit($user, 'create-user', '', $username);
    } else {
        db()->prepare('UPDATE users SET username = ?, display_name = ?, email = ?, role = ?, active = ? WHERE id = ?')
            ->execute([$username, $display, $email, $role, $active, $id]);
        if ($password !== '') {
            if (strlen($password) < 10) {
                flash('danger', 'Sandi baru minimal 10 karakter.');
                redirect('/users');
            }
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_ARGON2ID), $id]);
        }
        audit($user, 'update-user', '', $username);
    }
    flash('success', 'Pengguna disimpan.');
    redirect('/users');
}

function handle_accounts(array $user): void
{
    require_role($user, ['admin', 'operator']);
    $accounts = db()->query('SELECT a.*, (SELECT COUNT(*) FROM zones z WHERE z.account_id = a.id) AS zone_count FROM accounts a ORDER BY a.name')->fetchAll();
    view('accounts', ['title' => 'Akun', 'user' => $user, 'accounts' => $accounts]);
}

function handle_account_save(array $user): void
{
    csrf_check();
    require_role($user, ['admin']);
    $name = trim((string) ($_POST['name'] ?? ''));
    $contact = trim((string) ($_POST['contact'] ?? ''));
    $notes = trim((string) ($_POST['notes'] ?? ''));
    if ($name === '') {
        flash('danger', 'Nama akun wajib.');
        redirect('/accounts');
    }
    db()->prepare('INSERT INTO accounts (name, contact, notes) VALUES (?, ?, ?)')->execute([$name, $contact, $notes]);
    audit($user, 'create-account', '', $name);
    flash('success', 'Akun dibuat.');
    redirect('/accounts');
}

function handle_templates(array $user): void
{
    require_role($user, ['admin', 'operator']);
    $templates = db()->query('SELECT t.*, (SELECT COUNT(*) FROM template_records r WHERE r.template_id = t.id) AS rec_count FROM templates t ORDER BY t.name')->fetchAll();
    view('templates', ['title' => 'Template', 'user' => $user, 'templates' => $templates, 'types' => RECORD_TYPES]);
}

function handle_template_save(array $user): void
{
    csrf_check();
    require_role($user, ['admin', 'operator']);
    $name = trim((string) ($_POST['name'] ?? ''));
    $desc = trim((string) ($_POST['description'] ?? ''));
    if ($name === '') {
        flash('danger', 'Nama template wajib.');
        redirect('/templates');
    }
    db()->prepare('INSERT INTO templates (name, description, created_by) VALUES (?, ?, ?)')->execute([$name, $desc, (int) $user['id']]);
    $id = (int) db()->lastInsertId();
    $names = $_POST['r_name'] ?? [];
    $types = $_POST['r_type'] ?? [];
    $ttls = $_POST['r_ttl'] ?? [];
    $contents = $_POST['r_content'] ?? [];
    $ins = db()->prepare('INSERT INTO template_records (template_id, name, type, content, ttl) VALUES (?, ?, ?, ?, ?)');
    if (is_array($names)) {
        foreach ($names as $i => $n) {
            $c = trim((string) ($contents[$i] ?? ''));
            if (trim((string) $n) === '' || $c === '') {
                continue;
            }
            $ins->execute([$id, trim((string) $n), strtoupper((string) ($types[$i] ?? 'A')), $c, (int) ($ttls[$i] ?? 3600)]);
        }
    }
    flash('success', 'Template disimpan. Gunakan [ZONE] sebagai pengganti nama zona.');
    redirect('/templates');
}

function handle_apikeys(array $user): void
{
    require_role($user, ['admin', 'operator']);
    $keys = db()->query('SELECT k.id, k.name, k.key_prefix, k.role, k.revoked, k.last_used_at, k.created_at, u.username FROM api_keys k JOIN users u ON u.id = k.user_id ORDER BY k.id DESC')->fetchAll();
    $plain = $_SESSION['new_api_key'] ?? '';
    unset($_SESSION['new_api_key']);
    view('apikeys', ['title' => 'API key', 'user' => $user, 'keys' => $keys, 'plain' => $plain]);
}

function handle_apikey_create(array $user): void
{
    csrf_check();
    require_role($user, ['admin', 'operator']);
    $name = trim((string) ($_POST['name'] ?? 'key'));
    $role = (string) ($_POST['role'] ?? 'user');
    if ($user['role'] !== 'admin') {
        $role = 'user';
    }
    if (!in_array($role, ['admin','operator','user'], true)) {
        $role = 'user';
    }
    $raw = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $prefix = substr($raw, 0, 8);
    db()->prepare('INSERT INTO api_keys (name, key_prefix, key_hash, role, user_id) VALUES (?, ?, ?, ?, ?)')
        ->execute([$name, $prefix, hash('sha256', $raw), $role, (int) $user['id']]);
    $_SESSION['new_api_key'] = $raw;
    audit($user, 'create-apikey', '', $name);
    flash('success', 'API key dibuat. Salin sekarang. Nilai ini tidak ditampilkan lagi.');
    redirect('/apikeys');
}

function handle_audit(array $user): void
{
    require_role($user, ['admin']);
    $rows = db()->query('SELECT * FROM history ORDER BY id DESC LIMIT 200')->fetchAll();
    view('audit', ['title' => 'Audit', 'user' => $user, 'rows' => $rows]);
}

function handle_settings(array $user): void
{
    require_role($user, ['admin']);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        csrf_check();
        $url = rtrim(trim((string) ($_POST['pdns_api_url'] ?? '')), '/');
        $server = trim((string) ($_POST['pdns_server_id'] ?? 'localhost'));
        $verify = isset($_POST['pdns_verify_tls']) ? '1' : '0';
        $key = trim((string) ($_POST['pdns_api_key'] ?? ''));
        if (!preg_match('#^https?://#', $url)) {
            flash('danger', 'URL API harus http atau https.');
            redirect('/settings');
        }
        setting_set('pdns_api_url', $url);
        setting_set('pdns_server_id', $server !== '' ? $server : 'localhost');
        setting_set('pdns_verify_tls', $verify);
        if ($key !== '') {
            setting_set('pdns_api_key', secret_encrypt($key));
        }
        audit($user, 'settings', '', 'PDNS endpoint diperbarui');
        flash('success', 'Pengaturan disimpan. API key lama tetap dipakai jika kolom dikosongkan.');
        redirect('/settings');
    }
    view('settings', [
        'title' => 'Pengaturan',
        'user' => $user,
        'url' => (string) setting('pdns_api_url', ''),
        'server' => (string) setting('pdns_server_id', 'localhost'),
        'verify' => setting('pdns_verify_tls', '1') !== '0',
    ]);
}

function handle_search(array $user): void
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

function handle_api(array $userFromKey): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if ($path === '/api/v1/zones' && $method === 'GET') {
        $st = db()->query('SELECT name, kind, dnssec, serial, catalog FROM zones ORDER BY name');
        json_out(200, ['zones' => $st->fetchAll()]);
    }
    if (preg_match('#^/api/v1/zones/(.+)$#', $path, $m) && $method === 'GET') {
        $zone = dns_canonical(rawurldecode($m[1]));
        if (!user_can_zone($userFromKey, $zone, false)) {
            json_out(403, ['error' => 'Akses zona ditolak']);
        }
        try {
            json_out(200, PdnsClient::fromSettings()->zone($zone));
        } catch (Throwable $ex) {
            json_out(502, ['error' => $ex->getMessage()]);
        }
    }
    json_out(404, ['error' => 'Endpoint tidak dikenal']);
}

function api_user(): ?array
{
    $header = (string) ($_SERVER['HTTP_X_API_KEY'] ?? '');
    if ($header === '') {
        return null;
    }
    $st = db()->prepare('SELECT k.role, k.user_id, u.username, u.active FROM api_keys k JOIN users u ON u.id = k.user_id WHERE k.key_hash = ? AND k.revoked = 0');
    $st->execute([hash('sha256', $header)]);
    $row = $st->fetch();
    if (!$row || !(int) $row['active']) {
        return null;
    }
    db()->prepare('UPDATE api_keys SET last_used_at = NOW() WHERE key_hash = ?')->execute([hash('sha256', $header)]);
    return ['id' => (int) $row['user_id'], 'username' => $row['username'], 'role' => $row['role'], 'active' => 1];
}
