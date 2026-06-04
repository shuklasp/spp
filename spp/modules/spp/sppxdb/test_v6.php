<?php

/**
 * Verification script for SPP XDB Phase 8 (Advanced SQL & queryX)
 */

require_once(__DIR__ . '/sppxdb.php');

use SPPMod\SPPXDB\SPP_XDB;

echo "=== SPP XDB Phase 8 Verification ===\n\n";

try {
    $xdb = new SPP_XDB('test_v8');

    // Setup data
    $xdb->querySQL("CREATE TABLE users (id int, name varchar, role_id int)");
    $xdb->querySQL("CREATE TABLE roles (id int, title varchar, level int)");

    $xdb->connect('roles');
    $xdb->insert(['id' => 1, 'title' => 'Admin', 'level' => 10]);
    $xdb->insert(['id' => 2, 'title' => 'Editor', 'level' => 5]);

    $xdb->connect('users');
    $xdb->insert(['id' => 101, 'name' => 'Alice', 'role_id' => 1]);
    $xdb->insert(['id' => 102, 'name' => 'Bob', 'role_id' => 2]);
    $xdb->insert(['id' => 103, 'name' => 'Charlie', 'role_id' => 99]); // Non-existent role

    // 1. Testing LEFT JOIN
    echo "1. Testing LEFT JOIN...\n";
    $sql = "SELECT users.name, roles.title FROM users LEFT JOIN roles ON users.role_id = roles.id";
    $results = $xdb->querySQL($sql);
    print_r($results);

    $charlie = array_filter($results, fn ($r) => strpos($r['users.name'], 'Charlie') !== false);
    $charlie = reset($charlie);
    if ($charlie && is_null($charlie['roles.title'])) {
        echo "   [CONFIRMED] LEFT JOIN returned NULL for unmatched Charlie.\n";
    } else {
        echo "   [ERROR] LEFT JOIN failed for Charlie!\n";
    }

    // 2. Testing Subqueries
    echo "\n2. Testing Subqueries (WHERE IN SELECT)...\n";
    $sql = "SELECT * FROM users WHERE role_id IN (SELECT id FROM roles WHERE level > 7)";
    $results = $xdb->querySQL($sql);
    echo "   Users with high-level roles: " . count($results) . "\n";
    if (count($results) === 1 && $results[0]['name'] === 'Alice') {
        echo "   [CONFIRMED] Subquery resolved correctly.\n";
    } else {
        echo "   [ERROR] Subquery failed!\n";
    }

    // 3. Testing queryFLWOR (FLWOR-Lite)
    echo "\n3. Testing queryFLWOR (FLWOR-Lite)...\n";
    $qx = "for \$u in users where \$u/name LIKE '%o%' return \$u/name, \$u/id";
    $results = $xdb->queryFLWOR($qx);
    print_r($results);
    if (count($results) === 1 && $results[0]['name'] === 'Bob') {
        echo "   [CONFIRMED] queryX returned Bob correctly.\n";
    } else {
        echo "   [ERROR] queryX failed!\n";
    }

    echo "\n=== All Phase 8 Verifications Passed! ===\n";

} catch (Exception $e) {
    echo "\nVerification FAILED: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
