<?php
require_once __DIR__ . '/spp/sppinit.php';
\SPP\Scheduler::withContext('sppadmin', function() {
    $db = \SPP\Core\ModuleInstaller::getDb();
    $table = 'spp_modules';
    try {
        $db->execute_query("UPDATE $table SET version = ?, last_updated_at = ? WHERE name = ?", [
            '1.2',
            date('Y-m-d H:i:s'),
            'sppdb'
        ]);
        echo "UPDATE SUCCEEDED\n";
    } catch (\Exception $e) {
        echo "UPDATE FAILED: " . $e->getMessage() . "\n";
    }
});
