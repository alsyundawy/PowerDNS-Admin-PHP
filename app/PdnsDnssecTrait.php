<?php

declare(strict_types=1);

/**
 * DNSSEC Cryptokey operations for PowerDNS API.
 *
 * @method string zonePath(string $name, string $subpath = '')
 * @method array request(string $method, string $path, ?array $body = null)
 */
// phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace
trait PdnsDnssecTrait
{
    public function cryptokeys(string $name): array
    {
        return $this->request('GET', $this->zonePath($name, '/cryptokeys'));
    }

    public function createCryptokey(string $name, array $payload): array
    {
        return $this->request('POST', $this->zonePath($name, '/cryptokeys'), $payload);
    }

    public function updateCryptokey(string $name, int $keyId, array $payload): array
    {
        return $this->request('PUT', $this->zonePath($name, '/cryptokeys/' . $keyId), $payload);
    }

    public function deleteCryptokey(string $name, int $keyId): array
    {
        return $this->request('DELETE', $this->zonePath($name, '/cryptokeys/' . $keyId));
    }
}
