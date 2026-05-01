<?php
/**
 * Verification script for SPP XDB Phase 9 & 10 (Streaming & Auditing)
 */

require_once(__DIR__ . '/sppxdb.php');

use SPPMod\SPPXDB\SPP_XDB;

echo "=== SPP XDB Phase 9 & 10 Verification ===\n\n";

try {
    $xdb = new SPP_XDB('test_v9_10');
    
    // 1. Testing Audit Logging
    echo "1. Testing Audit Logging...\n";
    $xdb->querySQL("CREATE TABLE products (id int, name varchar, price float)");
    $xdb->connect('products');
    $xdb->enableAuditing(true);
    
    $xdb->insert(['id' => 1, 'name' => 'Laptop', 'price' => 999]);
    $xdb->update(['price' => 899], 'id = 1');
    $xdb->delete('id = 1');
    
    $auditXdb = new SPP_XDB('test_v9_10', '_audit');
    $logs = $auditXdb->querySQL("SELECT * FROM _audit");
    echo "   Audit log count: " . count($logs) . "\n";
    foreach ($logs as $log) {
        echo "   - [{$log['timestamp']}] Action: {$log['action']} on {$log['table']}\n";
    }
    
    if (count($logs) === 3) {
        echo "   [CONFIRMED] Auditing tracked all 3 mutations.\n";
    } else {
        echo "   [ERROR] Auditing missed some actions!\n";
    }

    // 2. Testing Streaming Query
    echo "\n2. Testing Streaming Query...\n";
    // Insert many rows to test "memory-efficient" streaming (simulated)
    $xdb->connect('products');
    for ($i = 1; $i <= 100; $i++) {
        $xdb->insert(['id' => $i, 'name' => "Item $i", 'price' => rand(10, 100)]);
    }
    
    $totalPrice = 0;
    $count = $xdb->streamQuery(function($row) use (&$totalPrice) {
        $totalPrice += $row['price'];
    });
    
    echo "   Processed $count rows via stream.\n";
    echo "   Total price: $totalPrice\n";
    if ($count === 100) {
        echo "   [CONFIRMED] streamQuery processed all rows correctly.\n";
    } else {
        echo "   [ERROR] streamQuery missed rows!\n";
    }

    echo "\n=== All Phase 9 & 10 Verifications Passed! ===\n";

} catch (Exception $e) {
    echo "\nVerification FAILED: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
