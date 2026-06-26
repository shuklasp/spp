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
    $user = new \SPPMod\SPPAuth\SPPUser('admin');
    echo "User found! ID: " . $user->id . "\n";
    echo "Password hash: " . $user->password . "\n";
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
