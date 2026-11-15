<?php

declare(strict_types=1);

require_once __DIR__ . '/helper.php';
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/dns_name.php';
require_once dirname(__DIR__) . '/app/PdnsDnssecTrait.php';
require_once dirname(__DIR__) . '/app/PdnsMetadataTrait.php';
require_once dirname(__DIR__) . '/app/PdnsClient.php';

// Mock client testing catalog zones logic
class MockPdnsCatalogClient
{
    /** @var array<int, array<string, mixed>> */
    private array $zones = [];
    private string $version = '4.8.4';

    /**
     * @param array<int, array<string, mixed>> $zones
     */
    public function __construct(array $zones, string $version = '4.8.4')
    {
        $this->zones = $zones;
        $this->version = $version;
    }

    public function ping(): array
    {
        return ['version' => $this->version];
    }

    public function isCatalogSupported(): bool
    {
        if (preg_match('/^(\d+)\.(\d+)/', $this->version, $m)) {
            $major = (int) $m[1];
            $minor = (int) $m[2];
            return ($major > 4) || ($major === 4 && $minor >= 7);
        }
        return true;
    }

    public function zones(): array
    {
        return $this->zones;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function catalogZones(): array
    {
        $catalogs = [];
        foreach ($this->zones as $z) {
            $kind = strtoupper((string) ($z['kind'] ?? ''));
            if ($kind === 'PRODUCER' || $kind === 'CONSUMER') {
                $catalogs[] = $z;
            }
        }
        return $catalogs;
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function zonesGroupedByCatalog(): array
    {
        $grouped = [];
        foreach ($this->zones as $z) {
            $cat = trim((string) ($z['catalog'] ?? ''));
            if ($cat !== '') {
                $catCanonical = dnsCanonical($cat);
                $grouped[$catCanonical][] = $z;
            }
        }
        return $grouped;
    }
}

// Test dataset
$sampleZones = [
    [
        'name' => 'catalog.example.org.',
        'kind' => 'PRODUCER',
        'serial' => 2026111501,
        'catalog' => '',
    ],
    [
        'name' => 'consumer.example.org.',
        'kind' => 'CONSUMER',
        'serial' => 2026111501,
        'catalog' => '',
    ],
    [
        'name' => 'zone1.example.com.',
        'kind' => 'Native',
        'serial' => 2026111502,
        'catalog' => 'catalog.example.org.',
    ],
    [
        'name' => 'zone2.example.com.',
        'kind' => 'Master',
        'serial' => 2026111503,
        'catalog' => 'catalog.example.org.',
    ],
    [
        'name' => 'standalone.example.net.',
        'kind' => 'Native',
        'serial' => 2026111504,
        'catalog' => '',
    ],
];

// Test 1: Version check
$client48 = new MockPdnsCatalogClient($sampleZones, '4.8.4');
assertEq($client48->isCatalogSupported(), true, 'PowerDNS 4.8.4 supports RFC 9432 Catalog Zones');

$client46 = new MockPdnsCatalogClient($sampleZones, '4.6.3');
assertEq($client46->isCatalogSupported(), false, 'PowerDNS 4.6.3 does not support Catalog Zones');

// Test 2: Filter catalog zones (Producer & Consumer)
$catalogs = $client48->catalogZones();
assertEq(count($catalogs), 2, 'Exactly 2 catalog zones detected (Producer & Consumer)');
assertEq($catalogs[0]['name'], 'catalog.example.org.', 'First catalog zone is Producer');
assertEq($catalogs[1]['name'], 'consumer.example.org.', 'Second catalog zone is Consumer');

// Test 3: Grouped member zones
$grouped = $client48->zonesGroupedByCatalog();
assertEq(isset($grouped['catalog.example.org.']), true, 'Member zones grouped under catalog.example.org.');
assertEq(count($grouped['catalog.example.org.']), 2, '2 member zones belong to catalog.example.org.');
assertEq($grouped['catalog.example.org.'][0]['name'], 'zone1.example.com.', 'First member zone matches');
assertEq($grouped['catalog.example.org.'][1]['name'], 'zone2.example.com.', 'Second member zone matches');
assertEq(isset($grouped['consumer.example.org.']), false, 'Consumer catalog has 0 members initially');

// Test 4: Real PdnsClient method availability
$realClient = new PdnsClient('http://127.0.0.1:8081', 'secret', 'localhost', false);
assertEq(method_exists($realClient, 'isCatalogSupported'), true, 'PdnsClient::isCatalogSupported exists');
assertEq(method_exists($realClient, 'catalogZones'), true, 'PdnsClient::catalogZones exists');
assertEq(method_exists($realClient, 'zonesGroupedByCatalog'), true, 'PdnsClient::zonesGroupedByCatalog exists');
assertEq(method_exists($realClient, 'assignCatalog'), true, 'PdnsClient::assignCatalog exists');

echo "All Catalog Zones (RFC 9432) logic tests passed successfully!\n";
