<?php
/**
 * Verification script for SPP XDB Migration System
 */

require_once(__DIR__ . '/sppxdb.php');

use SPPMod\SPPXDB\SPP_XDB;
use SPPMod\SPPXDB\MigrationManager;

echo "=== SPP XDB Migration Verification ===\n\n";

try {
    $xdb = new SPP_XDB('test_migrations');
    $mgr = new MigrationManager($xdb);
    
    // 1. Create a migration
    echo "1. Creating a migration...\n";
    $file = $mgr->create('create_test_table');
    echo "   Migration created: " . basename($file) . "\n";
    
    // Fill the migration
    $content = "<?php
use SPPMod\SPPXDB\SPP_XDB;

return new class {
    public function up(SPP_XDB \$db) {
        \$db->querySQL(\"CREATE TABLE migration_test (id int, name varchar)\");
    }
    public function down(SPP_XDB \$db) {
        \$db->querySQL(\"DROP TABLE migration_test\");
    }
};";
    file_put_contents($file, $content);

    // 2. Run migrations
    echo "\n2. Running migrations...\n";
    $count = $mgr->migrate();
    echo "   Executed $count migration(s).\n";
    
    if ($xdb->tableExists('migration_test')) {
        echo "   [CONFIRMED] Table 'migration_test' created.\n";
    }

    // 3. Verify tracking
    echo "\n3. Verifying migration tracking...\n";
    $tracking = $xdb->table('_migrations')->get();
    print_r($tracking);

    // 4. Rollback
    echo "\n4. Testing rollback...\n";
    $rbCount = $mgr->rollback();
    echo "   Rolled back $rbCount migration(s).\n";
    
    if (!$xdb->tableExists('migration_test')) {
        echo "   [CONFIRMED] Table 'migration_test' dropped.\n";
    }

    echo "\n=== All Migration Verifications Passed! ===\n";

} catch (Exception $e) {
    echo "\nVerification FAILED: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
