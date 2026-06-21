<?php
define('SPP_BASE_DIR', __DIR__ . '/spp');
require_once SPP_BASE_DIR . '/sppinit.php';

use SPPMod\SPPDB\SPPDB;

try {
    $db = new SPPDB();
    $table = SPPDB::sppTable('loginrec');
    echo "Testing loginrec table: $table\n";
    $res = $db->execute_query("SELECT count(*) as cnt FROM $table");
    echo "Count: " . $res[0]['cnt'] . "\n";
    
    // Test insertion
    $sessid = 'test_session_id';
    $now = date('Y-m-d H:i:s');
    $ip = '127.0.0.1';
    $id = 1; // Assuming admin id is 1
    
    echo "Inserting into loginrec...\n";
    $db->execute_query("INSERT INTO $table (sessid, uid, logintime, ipaddr, lastaccess) VALUES (?, ?, ?, ?, ?)", [$sessid, $id, $now, $ip, $now]);
    echo "Insert successful!\n";
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
