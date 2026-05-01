<?php
define('SPP_BASE_DIR', dirname(__DIR__));
require_once SPP_BASE_DIR . '/sppinit.php';

$db = new \SPPMod\SPPDB\SPPDB();
$tables = $db->execute_query("SHOW TABLES LIKE 'lek_%'");
echo "Lekhak Tables:\n";
print_r($tables);

if (empty($tables)) {
    echo "\nCreating lek_nodes table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS `lek_nodes` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `title` varchar(255) NOT NULL,
        `body` longtext,
        `status` varchar(50) DEFAULT 'draft',
        `alias` varchar(255) DEFAULT NULL,
        `created` datetime DEFAULT NULL,
        `changed` datetime DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $db->execute_query($sql);
    echo "Table created.\n";
}
