<?php
define('SPP_PATH', __DIR__ . '/spp');
require_once 'spp/sppinit.php';
new \SPP\App('lekhak', false, 1);
\SPP\Scheduler::setContext('lekhak');
$db = new \SPPMod\SPPDB\SPPDB();

try {
    echo "Creating audit_logs table in lekhak database...\n";
    $sql = "CREATE TABLE IF NOT EXISTS `audit_logs` (
        `id` BIGINT NOT NULL AUTO_INCREMENT,
        `entity_type` VARCHAR(255),
        `entity_id` BIGINT,
        `action` VARCHAR(50),
        `old_values` LONGTEXT,
        `new_values` LONGTEXT,
        `user_id` BIGINT,
        `created_at` DATETIME,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $db->exec($sql);
    echo "Audit logs table created.\n";
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
