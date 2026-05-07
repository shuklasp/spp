<?php
/**
 * Diagnostic script to verify SPPSession multi-context isolation.
 */
require_once __DIR__ . '/../spp/sppinit.php';

use SPP\SPPSession;
use SPP\Scheduler;
use SPP\App;

session_start();

echo "Testing SPPSession Isolation...\n";

// 1. Initialize Context A (sppadmin)
echo "Initializing Context: sppadmin\n";
new App('sppadmin', false, 3);
Scheduler::setContext('sppadmin');
SPPSession::setSessionVar('test_var', 'Value for SPPAdmin');

// 2. Initialize Context B (default)
echo "Initializing Context: default\n";
new App('default', false, 3);
Scheduler::setContext('default');
SPPSession::setSessionVar('test_var', 'Value for Default');

// 3. Verify Isolation
echo "\nVerifying Values:\n";

Scheduler::setContext('sppadmin');
$valAdmin = SPPSession::getSessionVar('test_var');
echo "sppadmin context test_var: " . $valAdmin . "\n";

Scheduler::setContext('default');
$valDefault = SPPSession::getSessionVar('test_var');
echo "default context test_var: " . $valDefault . "\n";

if ($valAdmin === 'Value for SPPAdmin' && $valDefault === 'Value for Default') {
    echo "\nSUCCESS: Sessions are isolated and correctly cached!\n";
} else {
    echo "\nFAILURE: Session values are leaking or not cached correctly!\n";
}

echo "\nRaw \$_SESSION structure:\n";
print_r($_SESSION);
