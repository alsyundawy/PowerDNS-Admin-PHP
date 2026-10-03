<?php
declare(strict_types=1);

/**
 * Client for the PowerDNS Authoritative HTTP API.
 * Header: X-API-Key. Zone names must be canonical.
 * Docs: https://doc.powerdns.com/authoritative/http-api/zone.html
 */
final class PdnsClient
{
    public function __construct(
        private string $baseUrl,
        private string $apiKey,
        private string $serverId = 'localhost',
        private bool $verifyTls = true,
        private int $timeout = 20
    ) {
        $this->baseUrl = rtrim($this->baseUrl, '/');
    }

    public static function fromSettings(): self
    {
        $url = (string) setting('pdns_api_url', '');
        $enc = (string) setting('pdns_api_key', '');
        $key = $enc !== '' ? secret_decrypt($enc) : '';
        $server = (string) setting('pdns_server_id', 'localhost');
        $verify = setting('pdns_verify_tls', '1') !== '0';
        if ($url === '' || $key === '') {
            throw new RuntimeException('PowerDNS API belum dikonfigurasi.');
        }
        return new self($url, $key, $server !== '' ? $server : 'localhost', $verify);
    }

    public function ping(): array
    {
        return $this->request('GET', '/api/v1/servers/' . rawurlencode($this->serverId));
    }

    public function statistics(bool $rings = false): array
    {
        return $this->request('GET', '/api/v1/servers/' . rawurlencode($this->serverId) . '/statistics?includerings=' . ($rings ? 'true' : 'false'));
    }

    public function zones(): array
    {
        return $this->request('GET', '/api/v1/servers/' . rawurlencode($this->serverId) . '/zones');
    }

    public function zone(string $name): array
    {
        $id = rawurlencode(dns_canonical($name));
        return $this->request('GET', '/api/v1/servers/' . rawurlencode($this->serverId) . '/zones/' . $id);
    }

    public function createZone(array $payload): array
    {
        return $this->request('POST', '/api/v1/servers/' . rawurlencode($this->serverId) . '/zones', $payload);
    }

    public function updateZone(string $name, array $payload): array
    {
        $id = rawurlencode(dns_canonical($name));
        return $this->request('PUT', '/api/v1/servers/' . rawurlencode($this->serverId) . '/zones/' . $id, $payload);
    }

    public function deleteZone(string $name): array
    {
        $id = rawurlencode(dns_canonical($name));
        return $this->request('DELETE', '/api/v1/servers/' . rawurlencode($this->serverId) . '/zones/' . $id);
    }

    public function patchRrsets(string $name, array $rrsets): array
    {
        $id = rawurlencode(dns_canonical($name));
        return $this->request('PATCH', '/api/v1/servers/' . rawurlencode($this->serverId) . '/zones/' . $id, ['rrsets' => $rrsets]);
    }

    public function axfrRetrieve(string $name): array
    {
        $id = rawurlencode(dns_canonical($name));
        return $this->request('PUT', '/api/v1/servers/' . rawurlencode($this->serverId) . '/zones/' . $id . '/axfr-retrieve');
    }

    public function notify(string $name): array
    {
        $id = rawurlencode(dns_canonical($name));
        return $this->request('PUT', '/api/v1/servers/' . rawurlencode($this->serverId) . '/zones/' . $id . '/notify');
    }

    public function rectify(string $name): array
    {
        $id = rawurlencode(dns_canonical($name));
        return $this->request('PUT', '/api/v1/servers/' . rawurlencode($this->serverId) . '/zones/' . $id . '/rectify');
    }

    public function cryptokeys(string $name): array
    {
        $id = rawurlencode(dns_canonical($name));
        return $this->request('GET', '/api/v1/servers/' . rawurlencode($this->serverId) . '/zones/' . $id . '/cryptokeys');
    }

    public function createCryptokey(string $name, array $payload): array
    {
        $id = rawurlencode(dns_canonical($name));
        return $this->request('POST', '/api/v1/servers/' . rawurlencode($this->serverId) . '/zones/' . $id . '/cryptokeys', $payload);
    }

    public function updateCryptokey(string $name, int $keyId, array $payload): array
    {
        $id = rawurlencode(dns_canonical($name));
        return $this->request('PUT', '/api/v1/servers/' . rawurlencode($this->serverId) . '/zones/' . $id . '/cryptokeys/' . $keyId, $payload);
    }

    public function deleteCryptokey(string $name, int $keyId): array
    {
        $id = rawurlencode(dns_canonical($name));
        return $this->request('DELETE', '/api/v1/servers/' . rawurlencode($this->serverId) . '/zones/' . $id . '/cryptokeys/' . $keyId);
    }

    public function search(string $q, int $max = 50): array
    {
        $q = rawurlencode($q);
        return $this->request('GET', '/api/v1/servers/' . rawurlencode($this->serverId) . '/search-data?q=' . $q . '&max=' . $max);
    }

    private function request(string $method, string $path, ?array $body = null): array
    {
        $url = $this->baseUrl . $path;
        $ch = curl_init($url);
        $headers = [
            'X-API-Key: ' . $this->apiKey,
            'Accept: application/json',
        ];
        $opts = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => $this->verifyTls,
            CURLOPT_SSL_VERIFYHOST => $this->verifyTls ? 2 : 0,
        ];
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_HTTPHEADER] = $headers;
            $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('PowerDNS API tidak terjangkau: ' . $err);
        }
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $decoded = $raw === '' ? [] : json_decode($raw, true);
        if ($code >= 400) {
            $msg = is_array($decoded) ? (string) ($decoded['error'] ?? $raw) : $raw;
            throw new RuntimeException('PowerDNS API ' . $code . ': ' . $msg);
        }
        return is_array($decoded) ? $decoded : [];
    }
}
