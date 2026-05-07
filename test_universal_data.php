<?php
require_once __DIR__ . '/spp/sppinit.php';

use SPPMod\SPPDB\SPPDB;
use SPPMod\SPPInterDB\SPPInterDB;

echo "=== SPP UNIVERSAL DATA LAYER TEST (INTERDB) ===\n\n";

// 1. Test Bridge (SQL -> XDB Integration)
echo "Step 1: Testing Universal Bridge...\n";
try {
    $db = new SPPDB();
    echo "Active Adapter: " . get_class($db->getAdapter()) . "\n";
    
    $tables = $db->execute_query("SHOW TABLES");
    echo "Found " . count($tables) . " tables.\n";
} catch (Exception $e) {
    echo "Bridge Error: " . $e->getMessage() . "\n";
}

echo "\n";

// 2. Test InterDB (Federated Aggregation)
echo "Step 2: Testing SPPInterDB Aggregation...\n";
$interDB = new SPPInterDB();

// Registry is loaded from config.yml, but we can override:
$interDB->map('user', 'default', 'users');
$interDB->map('preferences', 'xdb', 'user_preferences');

$query = '
query {
  user(id: 1) {
    name
    email
    preferences {
      theme
      notifications
    }
  }
}';

echo "Executing GraphQL through SPPInterDB (Mode: " . \SPP\Module::getConfig('mode', 'sppinterdb') . ")...\n";
$response = $interDB->graphql($query);

echo "Response JSON:\n";
echo json_encode($response, JSON_PRETTY_PRINT) . "\n";

echo "\nTest Completed.\n";
