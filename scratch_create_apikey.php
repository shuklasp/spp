<?php
require_once 'spp/sppinit.php';

$db = new \SPPMod\SPPDB\SPPDB();
if (!$db->tableExists('api_keys')) {
    $db->exec_squery('create table api_keys (id varchar(20))', 'api_keys');
}
$db->add_columns('api_keys', [
    'name' => 'varchar(255)',
    'token' => 'varchar(255)',
    'user_id' => 'bigint',
    'status' => 'int',
    'created_at' => 'datetime',
    'expires_at' => 'datetime'
]);
echo "Table api_keys created.\n";
