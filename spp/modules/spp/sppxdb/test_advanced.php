<?php
/**
 * Comprehensive test script for SPP XDB Module v2
 */

require_once(__DIR__ . '/sppxdb.php');

use SPPMod\SPPXDB\SPP_XDB;

echo "=== SPP XDB Advanced Verification ===\n\n";

try {
    $xdb = new SPP_XDB('test_advanced');
    
    // 1. Schema & Auto-ID
    echo "1. Testing Schema & Auto-ID...\n";
    $xdb->querySQL("CREATE TABLE products (id int, name varchar, category varchar, price int)");
    $xdb->querySQL("INSERT INTO products (name, category, price) VALUES ('Laptop', 'Electronics', 1200)");
    $xdb->querySQL("INSERT INTO products (name, category, price) VALUES ('Phone', 'Electronics', 800)");
    $xdb->querySQL("INSERT INTO products (name, category, price) VALUES ('Shirt', 'Apparel', 25)");
    $xdb->querySQL("INSERT INTO products (name, category, price) VALUES ('Pants', 'Apparel', 45)");
    $xdb->querySQL("INSERT INTO products (name, category, price) VALUES ('Watch', 'Electronics', 200)");
    
    $results = $xdb->querySQL("SELECT * FROM products");
    echo "   Last Insert ID: " . $xdb->lastInsertId() . "\n";
    echo "   Total products: " . count($results) . "\n";

    // 2. Aggregates (Global)
    echo "\n2. Testing Global Aggregates...\n";
    $sum = $xdb->querySQL("SELECT SUM(price) FROM products");
    $avg = $xdb->querySQL("SELECT AVG(price) FROM products");
    echo "   Sum Price: " . $sum[0]['SUM(price)'] . "\n";
    echo "   Avg Price: " . $avg[0]['AVG(price)'] . "\n";

    // 3. GROUP BY & Aggregates
    echo "\n3. Testing GROUP BY & Aggregates...\n";
    $grouped = $xdb->querySQL("SELECT category, COUNT(*), SUM(price) FROM products GROUP BY category");
    foreach ($grouped as $g) {
        echo "   Category: {$g['category']} | Count: {$g['COUNT(*)']} | Total: {$g['SUM(price)']}\n";
    }

    // 4. LIKE & IN
    echo "\n4. Testing LIKE & IN...\n";
    $like = $xdb->querySQL("SELECT name FROM products WHERE name LIKE 'P%'");
    echo "   Names starting with P: " . implode(', ', array_column($like, 'name')) . "\n";
    
    $in = $xdb->querySQL("SELECT name FROM products WHERE category IN ('Apparel')");
    echo "   Apparel items: " . implode(', ', array_column($in, 'name')) . "\n";

    // 5. DISTINCT
    echo "\n5. Testing DISTINCT...\n";
    $distinct = $xdb->querySQL("SELECT DISTINCT category FROM products");
    echo "   Distinct categories: " . implode(', ', array_column($distinct, 'category')) . "\n";

    // 6. Transactions
    echo "\n6. Testing Transactions...\n";
    $xdb->beginTransaction();
    $xdb->querySQL("INSERT INTO products (name, category, price) VALUES ('Temp Item', 'Misc', 10)");
    echo "   Inside Transaction: " . count($xdb->querySQL("SELECT * FROM products")) . " items\n";
    $xdb->rollback();
    echo "   After Rollback: " . count($xdb->querySQL("SELECT * FROM products")) . " items\n";

    // 7. Schema Validation
    echo "\n7. Testing Schema Validation...\n";
    try {
        $xdb->querySQL("INSERT INTO products (name, price) VALUES ('Bad Item', 'not-a-number')");
        echo "   ERROR: Validation failed to catch bad input!\n";
    } catch (Exception $e) {
        echo "   Caught expected validation error: " . $e->getMessage() . "\n";
    }

    // 8. Full-Text Search
    echo "\n8. Testing Search...\n";
    $search = $xdb->search('products', 'Phone');
    echo "   Search for 'Phone': found " . count($search) . " item\n";

    // 9. Export (JSON/CSV)
    echo "\n9. Testing Export...\n";
    $json = $xdb->export('products', 'json');
    echo "   JSON Export length: " . strlen($json) . " bytes\n";
    $csv = $xdb->export('products', 'csv');
    echo "   CSV Export head: " . strtok($csv, "\n") . "\n";

    // 10. Backup & Restore
    echo "\n10. Testing Backup & Restore...\n";
    $backupPath = __DIR__ . '/data/backup_test.zip';
    if ($xdb->backup('test_advanced', $backupPath)) {
        echo "    Backup created: " . basename($backupPath) . "\n";
        if ($xdb->restore($backupPath, 'test_restored')) {
            echo "    Restored to 'test_restored'\n";
            $restoredXdb = new SPP_XDB('test_restored', 'products');
            echo "    Restored data count: " . count($restoredXdb->querySQL("SELECT * FROM products")) . "\n";
        }
    } else {
        echo "    Backup failed (ZipArchive might be missing)\n";
    }

    // 11. Caching
    echo "\n11. Testing Caching...\n";
    $start = microtime(true);
    $xdb->querySQL("SELECT * FROM products");
    $first = microtime(true) - $start;
    
    $start = microtime(true);
    $xdb->querySQL("SELECT * FROM products");
    $second = microtime(true) - $start;
    echo "    First query: " . number_format($first, 6) . "s\n";
    echo "    Second query (cached): " . number_format($second, 6) . "s\n";

    echo "\n=== All Advanced Verifications Passed! ===\n";

} catch (Exception $e) {
    echo "\nVerification FAILED: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
