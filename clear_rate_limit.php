<?php
define('SPP_BASE_DIR', __DIR__ . '/spp');
require_once SPP_BASE_DIR . '/sppinit.php';
require_once SPP_BASE_DIR . '/modules/spp/sppauth/class.ratelimiter.php';

use SPPMod\SPPAuth\RateLimiter;
use SPPMod\SPPDB\SPPDB;

try {
    echo "Clearing rate limit for all users...\n";
    $db = new SPPDB();
    $sql = "DELETE FROM " . SPPDB::sppTable('login_attempts');
    $db->execute_query($sql);
    echo "Rate limit cleared!\n";
} catch (\Exception $e) {
    echo "Failed: " . $e->getMessage() . "\n";
}
