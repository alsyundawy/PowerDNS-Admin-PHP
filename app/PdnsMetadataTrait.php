<?php

declare(strict_types=1);

/**
 * Zone metadata operations for PowerDNS API.
 *
 * @method string zonePath(string $name, string $subpath = '')
 * @method array request(string $method, string $path, ?array $body = null)
 */
// phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace
trait PdnsMetadataTrait
{
    public function metadata(string $name): array
    {
        return $this->request('GET', $this->zonePath($name, '/metadata'));
    }

    public function setMetadata(string $name, string $kind, array $metadata): array
    {
        return $this->request('POST', $this->zonePath($name, '/metadata'), [
            'kind' => $kind,
            'metadata' => array_values($metadata),
        ]);
    }

    public function deleteMetadata(string $name, string $kind): array
    {
        return $this->request('DELETE', $this->zonePath($name, '/metadata/' . rawurlencode($kind)));
    }
}
