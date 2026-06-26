<?php
$_SERVER['DOCUMENT_ROOT'] = __DIR__ . '/spp';
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/spp/etc/initrc.php';
\SPP\Module::initWS('sppreport');

$db = new \SPPMod\SPPDB\SPPDB();
$driver = $db->getDriver();
echo "Driver: " . $driver . "\n";

$tables = $db->execute_query("SHOW TABLES");
foreach ($tables as $t) {
    $tableName = array_values($t)[0];
    if ($tableName !== 'users')
        continue;
    echo "Table: " . $tableName . "\n";
    $columns = $db->execute_query("DESCRIBE " . $tableName);
    print_r($columns);
}
