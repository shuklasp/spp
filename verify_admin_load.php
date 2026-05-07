<?php
require_once 'spp/sppinit.php';
try {
    $user = new \SPPMod\SPPAuth\SPPUser('admin');
    echo "Successfully loaded user: " . $user->username . "\n";
    echo "ID: " . $user->getId() . "\n";
    echo "Email: " . $user->email . "\n";
} catch (\Exception $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
}
