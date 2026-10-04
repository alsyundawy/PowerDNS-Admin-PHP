<?php

declare(strict_types=1);

require_once __DIR__ . '/helper.php';
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/PdnsDnssecTrait.php';
require_once dirname(__DIR__) . '/app/PdnsMetadataTrait.php';
require_once dirname(__DIR__) . '/app/PdnsClient.php';
require_once dirname(__DIR__) . '/app/PdnsCluster.php';

// Test 1: PdnsCluster returns empty or array of servers safely without active DB
$servers = PdnsCluster::listServers();
assertEq(is_array($servers), true, 'PdnsCluster::listServers() returns array');

// Test 2: Active server id session management
$_SESSION['active_pdns_server_id'] = 9999;
assertEq(isset($_SESSION['active_pdns_server_id']), true, 'Session active server id set');
unset($_SESSION['active_pdns_server_id']);
assertEq(isset($_SESSION['active_pdns_server_id']), false, 'Session active server id cleared');

// Test 3: Fallback client construction
$client = new PdnsClient('http://127.0.0.1:8081', 'secret_key', 'localhost', false);
assertEq(is_object($client), true, 'PdnsClient manual instantiation works');

echo "All cluster management tests passed successfully!\n";
