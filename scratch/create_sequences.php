<?php
define('SPP_PATH', __DIR__ . '/spp');
require_once 'spp/sppinit.php';
new \SPP\App('lekhak', false, 1);
\SPP\Scheduler::setContext('lekhak');
$db = new \SPPMod\SPPDB\SPPDB();

try {
    echo "Creating sequences table in lekhak database...\n";
    $sql = "CREATE TABLE IF NOT EXISTS `sequences` (
        `seqname` VARCHAR(255) NOT NULL,
        `initval` INT DEFAULT 1,
        `seqval` INT DEFAULT 1,
        `incval` INT DEFAULT 1,
        `lastaccess` INT,
        PRIMARY KEY (`seqname`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $db->exec($sql);
    echo "Sequences table created.\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
