<?php
define('SPP_BASE_DIR', __DIR__ . '/spp');
require_once SPP_BASE_DIR . '/sppinit.php';
require_once SPP_BASE_DIR . '/modules/spp/sppauth/class.sppauth.php';

use SPPMod\SPPAuth\SPPAuth;
use SPPMod\SPPAuth\SPPUser;

try {
    echo "Testing user load...\n";
    $user = new SPPUser('admin');
    echo "User loaded. Hash: " . $user->password . "\n";
    echo "Verify test: " . ($user->verifyPassword('admin123') ? 'Pass' : 'Fail') . "\n";

    echo "Testing login...\n";
    $res = SPPAuth::login('admin', 'admin123');
    echo "Login successful: " . ($res ? 'Yes' : 'No') . "\n";
} catch (\Exception $e) {
    echo "Login exception: " . $e->getMessage() . "\n";
}
