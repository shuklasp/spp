<?php
define('SPP_PATH', __DIR__ . '/spp');
require_once 'spp/sppinit.php';
new \SPP\App('lekhak', false, 1);
\SPP\Scheduler::setContext('lekhak');
$db = new \SPPMod\SPPDB\SPPDB();
$db->exec('ALTER TABLE audit_logs ADD COLUMN ip_address VARCHAR(45) AFTER user_id');
echo "Audit logs table updated.\n";
