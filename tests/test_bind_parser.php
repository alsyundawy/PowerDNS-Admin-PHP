<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/dns_name.php';
require_once __DIR__ . '/../app/services.php';
require_once __DIR__ . '/helper.php';

$sampleZone = <<<'ZONE'
$ORIGIN example.com.
$TTL 1h

; Zone Authority
@   IN  SOA ns1.example.com. admin.example.com. (
            2026100401 ; Serial
            3h         ; Refresh
            1h         ; Retry
            1w         ; Expire
            1d )       ; Negative Cache TTL

; Name Servers
    IN  NS  ns1.example.com.
    IN  NS  ns2.example.com.

; Mail Exchanges
@   IN  MX  10 mail1.example.com.
    IN  MX  20 mail2.example.com.

; Addresses
ns1     IN  A       192.0.2.1
ns2     IN  A       192.0.2.2
www 300 IN  A       192.0.2.10
www 300 IN  AAAA    2001:db8::10
mail1   IN  A       192.0.2.20
mail2   IN  A       192.0.2.21

; Text records
@       IN  TXT     "v=spf1 mx ~all"
_dmarc  IN  TXT     "v=DMARC1; p=none;"
ZONE;

$parsed = parseBindZone($sampleZone, 'example.com.');

assertEq($parsed['origin'], 'example.com.', 'Origin correctly identified as example.com.');
assertEq(count($parsed['rrsets']), 11, 'Parsed into 11 unique RRsets');

// Find SOA
$soaRr = null;
foreach ($parsed['rrsets'] as $rr) {
    if ($rr['type'] === 'SOA') {
        $soaRr = $rr;
        break;
    }
}
if (!$soaRr) {
    fwrite(STDERR, "FAIL: SOA RRset not found\n");
    exit(1);
}
assertEq($soaRr['name'], 'example.com.', 'SOA record name is example.com.');
assertEq(count($soaRr['records']), 1, 'SOA has 1 record');
// Serial 2026100401 should be preserved in content
if (!str_contains($soaRr['records'][0]['content'], '2026100401')) {
    fwrite(STDERR, "FAIL: SOA serial not found in content: " . $soaRr['records'][0]['content'] . "\n");
    exit(1);
}
echo "PASS: SOA content matches\n";

// Find NS
$nsRr = null;
foreach ($parsed['rrsets'] as $rr) {
    if ($rr['type'] === 'NS') {
        $nsRr = $rr;
        break;
    }
}
if (!$nsRr) {
    fwrite(STDERR, "FAIL: NS RRset not found\n");
    exit(1);
}
assertEq(count($nsRr['records']), 2, 'NS RRset has 2 records (ns1 and ns2)');

// Find www A
$wwwA = null;
foreach ($parsed['rrsets'] as $rr) {
    if ($rr['name'] === 'www.example.com.' && $rr['type'] === 'A') {
        $wwwA = $rr;
        break;
    }
}
if (!$wwwA) {
    fwrite(STDERR, "FAIL: www.example.com. A not found\n");
    exit(1);
}
assertEq($wwwA['ttl'], 300, 'Explicit TTL 300 parsed correctly');
assertEq($wwwA['records'][0]['content'], '192.0.2.10', 'www A content matches');

echo "All BIND zone parser tests passed successfully!\n";
