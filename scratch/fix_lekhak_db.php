<?php
define('SPP_PATH', __DIR__ . '/spp');
require_once 'spp/sppinit.php';

$app = 'lekhak';
new \SPP\App($app, false, 1);
\SPP\Scheduler::setContext($app);

try {
    $db = new \SPPMod\SPPDB\SPPDB();

    $tableName = 'lek_nodes';

    echo "Creating table $tableName...\n";

    $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (
        `id` BIGINT NOT NULL AUTO_INCREMENT,
        `title` VARCHAR(255),
        `alias` VARCHAR(255),
        `body` LONGTEXT,
        `author_id` BIGINT,
        `status` VARCHAR(20),
        `langcode` VARCHAR(10),
        `translation_id` BIGINT,
        `created` DATETIME,
        `changed` DATETIME,
        PRIMARY KEY (`id`),
        UNIQUE KEY `alias_idx` (`alias`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $db->exec($sql);
    echo "Table created successfully.\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
