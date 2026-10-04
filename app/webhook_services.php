<?php

/**
 * Cryptographic Webhook Dispatcher (HMAC-SHA256).
 * Delivers signed JSON event notifications (zone.created, zone.deleted, record.updated)
 * with X-PDNS-Signature verification headers and non-blocking timeout containment.
 */

declare(strict_types=1);

/**
 * List all configured webhooks.
 *
 * @return array<int, array<string, mixed>>
 */
function listWebhooks(): array
{
    try {
        ensureEnterpriseSchema();
        $sql = 'SELECT id, name, url, secret, events, is_active, last_status_code, '
            . 'last_error, last_triggered_at, created_at FROM webhooks ORDER BY id DESC';
        $st = db()->query($sql);
        return $st ? $st->fetchAll() : [];
    } catch (Throwable) {
        return [];
    }
}

/**
 * Get webhook by ID.
 *
 * @return array<string, mixed>|null
 */
function getWebhook(int $id): ?array
{
    try {
        ensureEnterpriseSchema();
        $sql = 'SELECT id, name, url, secret, events, is_active, last_status_code, '
            . 'last_error, last_triggered_at, created_at FROM webhooks WHERE id = ?';
        $st = db()->prepare($sql);
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    } catch (Throwable) {
        return null;
    }
}

/**
 * Register a new webhook endpoint.
 */
function createWebhook(string $name, string $url, string $secret, string $events, bool $isActive = true): int
{
    ensureEnterpriseSchema();
    $name = trim($name);
    $url = trim($url);
    $secret = trim($secret) !== '' ? trim($secret) : bin2hex(random_bytes(24));
    $events = trim($events);

    $st = db()->prepare('INSERT INTO webhooks (name, url, secret, events, is_active) VALUES (?, ?, ?, ?, ?)');
    $st->execute([$name, $url, $secret, $events, $isActive ? 1 : 0]);
    return (int) db()->lastInsertId();
}

/**
 * Update an existing webhook endpoint.
 */
function updateWebhook(int $id, string $name, string $url, string $secret, string $events, bool $isActive = true): bool
{
    ensureEnterpriseSchema();
    $name = trim($name);
    $url = trim($url);
    $secret = trim($secret);
    $events = trim($events);

    $st = db()->prepare('UPDATE webhooks SET name = ?, url = ?, secret = ?, events = ?, is_active = ? WHERE id = ?');
    return $st->execute([$name, $url, $secret, $events, $isActive ? 1 : 0, $id]);
}

/**
 * Delete a webhook endpoint.
 */
function deleteWebhook(int $id): bool
{
    ensureEnterpriseSchema();
    $st = db()->prepare('DELETE FROM webhooks WHERE id = ?');
    return $st->execute([$id]);
}

const WEBHOOK_HTTP_ERR_PREFIX = 'HTTP error ';

/**
 * Resolve webhook error message from cURL error and HTTP status code.
 */
function resolveWebhookError(bool $success, string $curlError, int $code): ?string
{
    if ($success) {
        return null;
    }
    if ($curlError !== '') {
        return $curlError;
    }
    return WEBHOOK_HTTP_ERR_PREFIX . $code;
}

/**
 * Execute HTTP POST dispatch for a single webhook target.
 *
 * @param array<string, mixed> $hook
 * @param array<string, mixed> $envelope
 * @return array{id: int, url: string, status: int, success: bool}
 */
function executeWebhookPost(array $hook, array $envelope, string $json, string $event): array
{
    $url = (string) $hook['url'];
    $secret = (string) $hook['secret'];
    $signature = 'sha256=' . hash_hmac('sha256', $json, $secret);

    $ch = curl_init($url);
    if ($ch === false) {
        return [
            'id' => (int) $hook['id'],
            'url' => $url,
            'status' => 0,
            'success' => false,
        ];
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json; charset=utf-8',
            'X-PDNS-Event: ' . $event,
            'X-PDNS-Signature: ' . $signature,
            'X-PDNS-Delivery: ' . (string) $envelope['delivery_id'],
            'User-Agent: PowerDNS-Admin-PHP/0.3.0 Webhook Dispatcher',
        ],
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 4,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $success = ($code >= 200 && $code < 300 && $resp !== false);
    $errorMsg = resolveWebhookError($success, $curlError, $code);

    try {
        $updateSql = 'UPDATE webhooks SET last_status_code = ?, last_error = ?, '
            . 'last_triggered_at = NOW() WHERE id = ?';
        $st = db()->prepare($updateSql);
        $st->execute([
            $code > 0 ? $code : null,
            $errorMsg,
            (int) $hook['id'],
        ]);
    } catch (Throwable) {
        // ignore db update fail
    }

    return [
        'id' => (int) $hook['id'],
        'url' => $url,
        'status' => $code,
        'success' => $success,
    ];
}

/**
 * Dispatch an event to all matching active webhooks with cryptographic HMAC-SHA256 signature.
 *
 * @param array<string, mixed> $payload
 * @return array<int, array{id: int, url: string, status: int, success: bool}>
 */
function dispatchWebhookEvent(string $event, array $payload): array
{
    $results = [];
    $all = listWebhooks();
    if (empty($all)) {
        return $results;
    }

    $envelope = [
        'event' => $event,
        'timestamp' => time(),
        'delivery_id' => bin2hex(random_bytes(16)),
        'data' => $payload,
    ];
    $json = (string) json_encode($envelope, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    foreach ($all as $hook) {
        if (empty($hook['is_active'])) {
            continue;
        }

        $eventsList = array_map('trim', explode(',', (string) $hook['events']));
        if (!in_array('*', $eventsList, true) && !in_array($event, $eventsList, true)) {
            continue;
        }

        $results[] = executeWebhookPost($hook, $envelope, $json, $event);
    }

    return $results;
}

/**
 * Test delivery for a single webhook.
 *
 * @return array{success: bool, status_code: int, message: string}
 */
function testWebhookDelivery(int $id): array
{
    $hook = getWebhook($id);
    if (!$hook) {
        return ['success' => false, 'status_code' => 0, 'message' => 'Webhook tidak ditemukan'];
    }

    $envelope = [
        'event' => 'ping',
        'timestamp' => time(),
        'delivery_id' => bin2hex(random_bytes(16)),
        'data' => [
            'message' => 'Uji konektivitas webhook PowerDNS-Admin-PHP v0.3.0',
            'webhook_id' => $id,
            'webhook_name' => $hook['name'],
        ],
    ];
    $json = (string) json_encode($envelope, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $signature = 'sha256=' . hash_hmac('sha256', $json, (string) $hook['secret']);

    $ch = curl_init((string) $hook['url']);
    if ($ch === false) {
        return ['success' => false, 'status_code' => 0, 'message' => 'Gagal inisialisasi cURL'];
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json; charset=utf-8',
            'X-PDNS-Event: ping',
            'X-PDNS-Signature: ' . $signature,
            'X-PDNS-Delivery: ' . $envelope['delivery_id'],
            'User-Agent: PowerDNS-Admin-PHP/0.3.0 Test Ping',
        ],
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 5,
    ]);

    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    $success = ($code >= 200 && $code < 300 && $resp !== false);
    $errorMsg = resolveWebhookError($success, $err, $code);

    try {
        $updateSql = 'UPDATE webhooks SET last_status_code = ?, last_error = ?, '
            . 'last_triggered_at = NOW() WHERE id = ?';
        $st = db()->prepare($updateSql);
        $st->execute([$code > 0 ? $code : null, $errorMsg, $id]);
    } catch (Throwable) {
        // ignore
    }

    $statusMessage = 'Terkirim (HTTP ' . $code . ')';
    if (!$success) {
        $statusMessage = $errorMsg ?? (WEBHOOK_HTTP_ERR_PREFIX . $code);
    }

    return [
        'success' => $success,
        'status_code' => $code,
        'message' => $statusMessage,
    ];
}
