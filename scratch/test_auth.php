<?php
require_once 'spp/sppinit.php';
// Force Admin Context for Session consistency
try {
    \SPP\Scheduler::getProcObj('sppadmin');
} catch (\Exception $e) {
    new \SPP\App('sppadmin', false, 3);
}
\SPP\Scheduler::setContext('sppadmin');

require_once SPP_MODULES_DIR . '/spp/sppauth/class.sppauth.php';
try {
    $success = \SPPMod\SPPAuth\SPPAuth::login('admin', 'admin123');
    echo "Login success: " . ($success ? "TRUE" : "FALSE") . "\n";
} catch (\Exception $e) {
    echo "Login threw exception: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
