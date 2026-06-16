<?php
require_once __DIR__ . '/spp.php';

$db = new \SPPMod\SPPDB\SPPDB();
$table = \SPPMod\SPPDB\SPPDB::sppTable('abac_policies');

$sql = "CREATE TABLE IF NOT EXISTS `$table` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `permission` varchar(100) NOT NULL,
  `condition_logic` text NOT NULL,
  `status` varchar(20) DEFAULT 'active',
  PRIMARY KEY (`id`),
  KEY `idx_permission` (`permission`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

try {
    $db->execute_query($sql);
    echo "ABAC Policies table created successfully.\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
