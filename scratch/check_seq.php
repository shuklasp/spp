<?php
define('SPP_PATH', __DIR__ . '/spp');
require_once 'spp/sppinit.php';
new \SPP\App('lekhak', false, 1);
\SPP\Scheduler::setContext('lekhak');
$db = new \SPPMod\SPPDB\SPPDB();
$res = $db->query("SELECT * FROM sequences WHERE name='lekhaknode_seq'");
while($row = $res->fetch()) {
    print_r($row);
}
