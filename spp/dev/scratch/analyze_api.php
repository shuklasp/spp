<?php
$adminDir = 'c:/projects/apache/school1/spp/admin';
$jsDir = $adminDir . '/js';
$servicesDir = $adminDir . '/services';

// 1. Find all frontend API calls
$frontendCalls = [];
$jsFiles = array_merge([$jsDir . '/admin.js', $jsDir . '/admin-settings.js'], glob($jsDir . '/views/*.js'));

foreach ($jsFiles as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    // match this.api('action' or this.apiPost('action'
    preg_match_all("/(?:this\.api|this\.apiPost|admin\.api|admin\.apiPost)\(\s*['\"]([^'\"]+)['\"]/i", $content, $matches);
    foreach ($matches[1] as $action) {
        $frontendCalls[$action][] = basename($file);
    }
}

// 2. Find all backend API handlers in api.php
$backendHandlers = [];
$apiPhpContent = file_get_contents($adminDir . '/api.php');
// Look for case 'action': or case "action":
preg_match_all("/case\s+['\"]([^'\"]+)['\"]:/i", $apiPhpContent, $matches);
foreach ($matches[1] as $action) {
    $backendHandlers[$action] = 'api.php';
}

echo "--- API Discrepancy Report ---\n";

echo "\n1. Actions called by Frontend but NOT handled in api.php:\n";
$missingInBackend = [];
foreach ($frontendCalls as $action => $files) {
    if (!isset($backendHandlers[$action])) {
        $missingInBackend[] = "- '$action' (called in " . implode(', ', array_unique($files)) . ")";
    }
}
if (empty($missingInBackend)) {
    echo "None! All frontend calls have a corresponding switch case in api.php.\n";
} else {
    echo implode("\n", $missingInBackend) . "\n";
}

echo "\n2. Actions handled in api.php but NOT called by Frontend:\n";
$missingInFrontend = [];
foreach ($backendHandlers as $action => $file) {
    if (!isset($frontendCalls[$action])) {
        $missingInFrontend[] = "- '$action'";
    }
}
if (empty($missingInFrontend)) {
    echo "None! All backend cases are called by the frontend.\n";
} else {
    echo implode("\n", $missingInFrontend) . "\n";
}

// 3. Look for function calls in api.php to undefined live_* functions
$liveCalls = [];
preg_match_all("/(live_[a-zA-Z0-9_]+)\(/i", $apiPhpContent, $matches);
foreach ($matches[1] as $func) {
    $liveCalls[$func] = true;
}

$definedLiveFuncs = [];
$serviceFiles = glob($servicesDir . '/*.php');
foreach ($serviceFiles as $file) {
    $content = file_get_contents($file);
    preg_match_all("/function\s+(live_[a-zA-Z0-9_]+)\s*\(/i", $content, $matches);
    foreach ($matches[1] as $func) {
        $definedLiveFuncs[$func] = basename($file);
    }
}

echo "\n--- Function Definition Report ---\n";
echo "\n1. live_* functions called in api.php but NOT defined in any service:\n";
$missingFuncs = [];
foreach ($liveCalls as $func => $val) {
    if (!isset($definedLiveFuncs[$func])) {
        $missingFuncs[] = "- $func()";
    }
}
if (empty($missingFuncs)) {
    echo "None! All live_* functions called in api.php exist in services.\n";
} else {
    echo implode("\n", $missingFuncs) . "\n";
}

echo "\n2. live_* functions defined in services but NOT called in api.php:\n";
$unusedFuncs = [];
foreach ($definedLiveFuncs as $func => $file) {
    if (!isset($liveCalls[$func])) {
        $unusedFuncs[] = "- $func() (in $file)";
    }
}
if (empty($unusedFuncs)) {
    echo "None! All defined live_* functions are used.\n";
} else {
    echo implode("\n", $unusedFuncs) . "\n";
}

?>
