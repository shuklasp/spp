<?php
$_SERVER['DOCUMENT_ROOT'] = __DIR__;
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/etc/initrc.php';
\SPP\System::init();

$db = new \SPPMod\SPPDB\SPPDB();
$res = $db->execute_query("DESCRIBE users");
print_r($res);
