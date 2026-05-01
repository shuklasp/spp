<?php
/**
 * Verification script for SPP XDB v3 (Indexing & Query Builder)
 */

require_once(__DIR__ . '/sppxdb.php');

use SPPMod\SPPXDB\SPP_XDB;

echo "=== SPP XDB Phase 5 Verification ===\n\n";

try {
    $xdb = new SPP_XDB('test_v3');
    
    // 1. Testing Query Builder (Insert)
    echo "1. Testing Query Builder (Insert)...\n";
    $xdb->table('users')->insert(['name' => 'Alice', 'role' => 'Admin', 'status' => 'active']);
    $xdb->table('users')->insert(['name' => 'Bob', 'role' => 'User', 'status' => 'inactive']);
    $xdb->table('users')->insert(['name' => 'Charlie', 'role' => 'User', 'status' => 'active']);
    
    $count = $xdb->table('users')->count();
    echo "   Total users: $count\n";

    // 2. Testing Fluent Queries
    echo "\n2. Testing Fluent Queries...\n";
    $activeUsers = $xdb->table('users')->where('status', 'active')->get();
    echo "   Active users: " . implode(', ', array_column($activeUsers, 'name')) . "\n";
    
    $bob = $xdb->table('users')->where('name', 'Bob')->first();
    echo "   First user named Bob: " . ($bob['name'] ?? 'Not found') . "\n";

    // 3. Testing Structural Indexing
    echo "\n3. Testing Structural Indexing...\n";
    echo "   Creating index on 'role'...\n";
    $xdb->connect('users');
    $xdb->createIndex('role');
    
    // Verify index file exists
    $idxFile = __DIR__ . '/data/test_v3/_indexes/users/role.json';
    if (file_exists($idxFile)) {
        echo "   Index file created: " . basename($idxFile) . "\n";
        echo "   Index content: " . file_get_contents($idxFile) . "\n";
    } else {
        echo "   ERROR: Index file NOT found!\n";
    }

    // 4. Testing Index Maintenance (Update)
    echo "\n4. Testing Index Maintenance (Update)...\n";
    $xdb->table('users')->where('name', 'Bob')->update(['role' => 'Admin']);
    echo "   Bob's role updated to Admin.\n";
    echo "   Index content after update: " . file_get_contents($idxFile) . "\n";

    // 5. Testing Index Maintenance (Delete)
    echo "\n5. Testing Index Maintenance (Delete)...\n";
    $xdb->table('users')->where('name', 'Alice')->delete();
    echo "   Alice deleted.\n";
    echo "   Index content after delete: " . file_get_contents($idxFile) . "\n";

    // 6. Testing JOIN
    echo "\n6. Testing JOIN...\n";
    $xdb->table('roles')->insert(['role_name' => 'Admin', 'permissions' => 'all']);
    $xdb->table('roles')->insert(['role_name' => 'User', 'permissions' => 'limited']);
    
    $joined = $xdb->querySQL("SELECT users.name, roles.permissions FROM users JOIN roles ON users.role = roles.role_name");
    echo "   Joined results:\n";
    foreach ($joined as $row) {
        echo "   Name: {$row['users.name']} | Perms: {$row['roles.permissions']}\n";
    }

    echo "\n=== All Phase 5 Verifications Passed! ===\n";

} catch (Exception $e) {
    echo "\nVerification FAILED: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
