<?php

/**
 * Multi-Server PowerDNS Node Clustering Engine.
 * Manages independent Authoritative nodes, active server session routing,
 * health latency telemetry, and transparent backward-compatible fallback.
 */

declare(strict_types=1);

// phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace
final class PdnsCluster
{
    /**
     * List all registered PowerDNS server nodes.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function listServers(): array
    {
        try {
            ensureEnterpriseSchema();
            $sql = 'SELECT id, name, api_url, server_id, is_default, is_active, '
                . 'latency_ms, last_check_at, created_at FROM pdns_servers ORDER BY is_default DESC, id ASC';
            $st = db()->query($sql);
            return $st ? $st->fetchAll() : [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Alias for listServers().
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getAllServers(): array
    {
        return self::listServers();
    }

    /**
     * Get single server record by ID.
     *
     * @return array<string, mixed>|null
     */
    public static function getServer(int $id): ?array
    {
        try {
            ensureEnterpriseSchema();
            $sql = 'SELECT id, name, api_url, api_key_encrypted, server_id, is_default, '
                . 'is_active, latency_ms, last_check_at, created_at FROM pdns_servers WHERE id = ?';
            $st = db()->prepare($sql);
            $st->execute([$id]);
            $row = $st->fetch();
            return $row ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Add a new PowerDNS server node.
     */
    public static function addServer(
        string $name,
        string $apiUrl,
        string $apiKey,
        string $serverId = 'localhost',
        bool $isDefault = false,
        bool $isActive = true
    ): int {
        ensureEnterpriseSchema();
        $apiUrl = rtrim(trim($apiUrl), '/');
        $name = trim($name);
        $encryptedKey = secretEncrypt($apiKey);

        if ($isDefault) {
            db()->exec('UPDATE pdns_servers SET is_default = 0');
        }

        $insertSql = 'INSERT INTO pdns_servers '
            . '(name, api_url, api_key_encrypted, server_id, is_default, is_active) '
            . 'VALUES (?, ?, ?, ?, ?, ?)';
        $st = db()->prepare($insertSql);
        $st->execute([
            $name,
            $apiUrl,
            $encryptedKey,
            $serverId !== '' ? $serverId : 'localhost',
            $isDefault ? 1 : 0,
            $isActive ? 1 : 0,
        ]);
        return (int) db()->lastInsertId();
    }

    /**
     * Update an existing PowerDNS server node.
     */
    public static function updateServer(
        int $id,
        string $name,
        string $apiUrl,
        ?string $apiKey = null,
        string $serverId = 'localhost',
        bool $isDefault = false,
        bool $isActive = true
    ): bool {
        ensureEnterpriseSchema();
        $apiUrl = rtrim(trim($apiUrl), '/');
        $name = trim($name);

        if ($isDefault) {
            $stDef = db()->prepare('UPDATE pdns_servers SET is_default = 0 WHERE id != ?');
            $stDef->execute([$id]);
        }

        if ($apiKey !== null && trim($apiKey) !== '') {
            $encryptedKey = secretEncrypt(trim($apiKey));
            $upSql = 'UPDATE pdns_servers SET name = ?, api_url = ?, api_key_encrypted = ?, '
                . 'server_id = ?, is_default = ?, is_active = ? WHERE id = ?';
            $st = db()->prepare($upSql);
            return $st->execute([
                $name,
                $apiUrl,
                $encryptedKey,
                $serverId !== '' ? $serverId : 'localhost',
                $isDefault ? 1 : 0,
                $isActive ? 1 : 0,
                $id,
            ]);
        }

        $upSql = 'UPDATE pdns_servers SET name = ?, api_url = ?, server_id = ?, '
            . 'is_default = ?, is_active = ? WHERE id = ?';
        $st = db()->prepare($upSql);
        return $st->execute([
            $name,
            $apiUrl,
            $serverId !== '' ? $serverId : 'localhost',
            $isDefault ? 1 : 0,
            $isActive ? 1 : 0,
            $id,
        ]);
    }

    /**
     * Delete a server node.
     */
    public static function deleteServer(int $id): bool
    {
        ensureEnterpriseSchema();
        $st = db()->prepare('DELETE FROM pdns_servers WHERE id = ?');
        $res = $st->execute([$id]);
        if (isset($_SESSION['active_pdns_server_id']) && (int) $_SESSION['active_pdns_server_id'] === $id) {
            unset($_SESSION['active_pdns_server_id']);
        }
        return $res;
    }

    /**
     * Get active server ID from session or default database row.
     */
    public static function getActiveServerId(): ?int
    {
        ensureEnterpriseSchema();
        if (isset($_SESSION['active_pdns_server_id'])) {
            $sessId = (int) $_SESSION['active_pdns_server_id'];
            $st = db()->prepare('SELECT id FROM pdns_servers WHERE id = ? AND is_active = 1');
            $st->execute([$sessId]);
            if ($st->fetch()) {
                return $sessId;
            }
        }

        $activeId = null;
        try {
            $sql = 'SELECT id FROM pdns_servers WHERE is_active = 1 '
                . 'ORDER BY is_default DESC, id ASC LIMIT 1';
            $st = db()->query($sql);
            $row = $st ? $st->fetch() : null;
            if ($row) {
                $activeId = (int) $row['id'];
                $_SESSION['active_pdns_server_id'] = $activeId;
            }
        } catch (Throwable) {
            $activeId = null;
        }

        return $activeId;
    }

    /**
     * Switch the active server in session.
     */
    public static function setActiveServerId(int $id): bool
    {
        $server = self::getServer($id);
        if ($server && (int) $server['is_active'] === 1) {
            $_SESSION['active_pdns_server_id'] = $id;
            return true;
        }
        return false;
    }

    /**
     * Get active server details.
     *
     * @return array<string, mixed>|null
     */
    public static function getActiveServer(): ?array
    {
        $id = self::getActiveServerId();
        if ($id === null) {
            return null;
        }
        return self::getServer($id);
    }

    /**
     * Resolve active PdnsClient instance, with seamless fallback to legacy single-server settings.
     */
    public static function getActiveClient(): PdnsClient
    {
        $active = self::getActiveServer();
        if ($active !== null && !empty($active['api_url']) && !empty($active['api_key_encrypted'])) {
            $decryptedKey = secretDecrypt((string) $active['api_key_encrypted']);
            $serverId = !empty($active['server_id']) ? (string) $active['server_id'] : 'localhost';
            $verify = setting('pdns_verify_tls', '1') !== '0';
            return new PdnsClient((string) $active['api_url'], $decryptedKey, $serverId, $verify);
        }

        // Transparent fallback to legacy single-server installation in settings table
        return PdnsClient::fromSettings();
    }

    /**
     * Ping a server node to measure latency and verify connectivity.
     *
     * @return array{success: bool, latency_ms: int, message: string}
     */
    public static function pingServer(int $id): array
    {
        $server = self::getServer($id);
        if (!$server) {
            return ['success' => false, 'latency_ms' => 0, 'message' => 'Server tidak ditemukan'];
        }

        $apiUrl = rtrim((string) $server['api_url'], '/');
        $decryptedKey = secretDecrypt((string) $server['api_key_encrypted']);
        $serverId = !empty($server['server_id']) ? (string) $server['server_id'] : 'localhost';
        $verify = setting('pdns_verify_tls', '1') !== '0';

        $endpoint = $apiUrl . '/api/v1/servers/' . rawurlencode($serverId);
        $ch = curl_init($endpoint);
        if ($ch === false) {
            return ['success' => false, 'latency_ms' => 0, 'message' => 'Gagal inisialisasi cURL'];
        }

        $start = microtime(true);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'X-API-Key: ' . $decryptedKey,
                'Accept: application/json',
            ],
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => $verify,
            CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0,
        ]);

        $response = curl_exec($ch);
        $latencyMs = (int) round((microtime(true) - $start) * 1000);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $success = ($httpCode >= 200 && $httpCode < 300 && $response !== false);
        if ($success) {
            $msg = 'Terkoneksi (HTTP ' . $httpCode . ')';
        } elseif ($curlError !== '') {
            $msg = $curlError;
        } else {
            $msg = 'HTTP error ' . $httpCode;
        }

        try {
            $st = db()->prepare('UPDATE pdns_servers SET latency_ms = ?, last_check_at = NOW() WHERE id = ?');
            $st->execute([$success ? $latencyMs : null, $id]);
        } catch (Throwable) {
            // safely ignore db update failure
        }

        return [
            'success' => $success,
            'latency_ms' => $latencyMs,
            'message' => $msg,
        ];
    }
}
