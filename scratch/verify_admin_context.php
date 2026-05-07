<?php
/**
 * Diagnostic script to verify context resolution for Admin SPA.
 */
require_once __DIR__ . '/../spp/sppinit.php';

use SPP\Scheduler;

function testUri($uri) {
    $_SERVER['REQUEST_URI'] = $uri;
    // We need to bypass the static cache in detectAndEnforceContext if we want to run multiple tests
    // But since it's a one-off script, we'll just run it once.
    Scheduler::detectAndEnforceContext();
    return Scheduler::getContext();
}

echo "Testing Context Resolution...\n";

$uri1 = '/spp/admin/index.php';
$ctx1 = testUri($uri1);
echo "URI: $uri1 -> Context: $ctx1\n";

if ($ctx1 === 'sppadmin') {
    echo "SUCCESS: /spp/admin correctly resolves to sppadmin context.\n";
} else {
    echo "FAILURE: /spp/admin resolved to $ctx1 instead of sppadmin.\n";
}

$uri2 = '/spp/docs/readme.txt';
// Note: We need to clear Scheduler's state or run in a sub-process
// For now, let's just see if sppadmin works first.
