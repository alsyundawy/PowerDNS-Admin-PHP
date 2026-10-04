<?php

/**
 * Client for the PowerDNS Authoritative HTTP API.
 * Header: X-API-Key. Zone names must be canonical.
 * Docs: https://doc.powerdns.com/authoritative/http-api/zone.html
 */

declare(strict_types=1);

// phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace
final class PdnsClient
{
    use PdnsDnssecTrait;
    use PdnsMetadataTrait;

    private const API_SERVERS = '/api/v1/servers/';

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
        $key = $enc !== '' ? secretDecrypt($enc) : '';
        $server = (string) setting('pdns_server_id', 'localhost');
        $verify = setting('pdns_verify_tls', '1') !== '0';
        if ($url === '' || $key === '') {
            throw new UnexpectedValueException('PowerDNS API belum dikonfigurasi.');
        }
        return new self($url, $key, $server !== '' ? $server : 'localhost', $verify);
    }

    private function zonePath(string $name, string $subpath = ''): string
    {
        return self::API_SERVERS . rawurlencode($this->serverId)
            . '/zones/' . rawurlencode(dnsCanonical($name)) . $subpath;
    }

    public function ping(): array
    {
        return $this->request('GET', self::API_SERVERS . rawurlencode($this->serverId));
    }

    public function statistics(bool $rings = false): array
    {
        $query = '?includerings=' . ($rings ? 'true' : 'false');
        return $this->request('GET', self::API_SERVERS . rawurlencode($this->serverId) . '/statistics' . $query);
    }

    public function zones(): array
    {
        return $this->request('GET', self::API_SERVERS . rawurlencode($this->serverId) . '/zones');
    }

    public function zone(string $name): array
    {
        return $this->request('GET', $this->zonePath($name));
    }

    public function createZone(array $payload): array
    {
        return $this->request('POST', self::API_SERVERS . rawurlencode($this->serverId) . '/zones', $payload);
    }

    public function updateZone(string $name, array $payload): array
    {
        return $this->request('PUT', $this->zonePath($name), $payload);
    }

    public function deleteZone(string $name): array
    {
        return $this->request('DELETE', $this->zonePath($name));
    }

    public function patchRrsets(string $name, array $rrsets): array
    {
        return $this->request('PATCH', $this->zonePath($name), ['rrsets' => $rrsets]);
    }

    public function axfrRetrieve(string $name): array
    {
        return $this->request('PUT', $this->zonePath($name, '/axfr-retrieve'));
    }

    public function notify(string $name): array
    {
        return $this->request('PUT', $this->zonePath($name, '/notify'));
    }

    public function rectify(string $name): array
    {
        return $this->request('PUT', $this->zonePath($name, '/rectify'));
    }

    public function exportZone(string $name): string
    {
        try {
            return $this->requestRaw('GET', $this->zonePath($name, '/export'), null, 'text/plain');
        } catch (Throwable) {
            // Fallback: format zone RRsets into standard BIND zone format
            $z = $this->zone($name);
            $out = "; PowerDNS-Admin-PHP zone export for " . $name . "\n";
            $out .= "; Exported at: " . gmdate('Y-m-d H:i:s') . " UTC\n\n";
            $out .= "\$ORIGIN " . dnsCanonical($name) . "\n\n";
            foreach (($z['rrsets'] ?? []) as $rr) {
                $rname = (string) ($rr['name'] ?? '');
                $type = (string) ($rr['type'] ?? '');
                $ttl = (int) ($rr['ttl'] ?? 3600);
                foreach (($rr['records'] ?? []) as $rec) {
                    if (!empty($rec['disabled'])) {
                        continue;
                    }
                    $content = (string) ($rec['content'] ?? '');
                    $out .= sprintf("%-30s %-8d IN  %-8s %s\n", $rname, $ttl, $type, $content);
                }
            }
            return $out;
        }
    }

    public function search(string $q, int $max = 50): array
    {
        $q = rawurlencode($q);
        return $this->request(
            'GET',
            self::API_SERVERS . rawurlencode($this->serverId) . '/search-data?q=' . $q . '&max=' . $max
        );
    }

    public function requestRaw(string $method, string $path, ?array $body = null, string $accept = '*/*'): string
    {
        $url = $this->baseUrl . $path;
        $ch = curl_init($url);
        if ($ch === false) {
            throw new UnexpectedValueException('Gagal menginisialisasi cURL untuk PowerDNS API.');
        }

        $headers = [
            'X-API-Key: ' . $this->apiKey,
            'Accept: ' . $accept,
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
            throw new UnexpectedValueException('PowerDNS API tidak terjangkau: ' . $err);
        }
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($code >= 400) {
            $decoded = json_decode((string) $raw, true);
            $msg = is_array($decoded) ? (string) ($decoded['error'] ?? $raw) : (string) $raw;
            throw new UnexpectedValueException('PowerDNS API ' . $code . ': ' . $msg);
        }
        return (string) $raw;
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>|array<int, mixed>
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        $raw = $this->requestRaw($method, $path, $body, 'application/json');
        $decoded = ($raw === '') ? [] : json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
