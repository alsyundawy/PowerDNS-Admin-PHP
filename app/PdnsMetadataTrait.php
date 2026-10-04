<?php

declare(strict_types=1);

/**
 * Zone metadata operations for PowerDNS API.
 *
 * @method string zonePath(string $name, string $subpath = '')
 * @method array<string, mixed>|array<int, mixed> request(string $method, string $path, ?array $body = null)
 */
// phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace
trait PdnsMetadataTrait
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function metadata(string $name): array
    {
        return $this->request('GET', $this->zonePath($name, '/metadata'));
    }

    /**
     * @param list<string>|array<int, string> $metadata
     * @return array<string, mixed>
     */
    public function setMetadata(string $name, string $kind, array $metadata): array
    {
        return $this->request('POST', $this->zonePath($name, '/metadata'), [
            'kind' => $kind,
            'metadata' => array_values($metadata),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteMetadata(string $name, string $kind): array
    {
        return $this->request('DELETE', $this->zonePath($name, '/metadata/' . rawurlencode($kind)));
    }
}
