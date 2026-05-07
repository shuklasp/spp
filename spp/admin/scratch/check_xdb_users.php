<?php
require_once __DIR__ . '/../../../vendor/autoload.php';
define('SPP_BASE_DIR', realpath(__DIR__ . '/../..'));
require_once __DIR__ . '/../../sppinit.php';
require_once __DIR__ . '/../../modules/spp/sppdb/class.sppdb.php';
require_once __DIR__ . '/../../modules/spp/sppxdb/class.sppxdb.php';

try {
    $db = new \SPPMod\SPPDB\SPPDB("xdb:dbname=default");
    $res = $db->execute_query("SELECT * FROM users");
    echo "Users in XDB:\n";
    print_r($res);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
