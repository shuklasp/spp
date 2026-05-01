<?php

require_once 'spp/sppinit.php';

use SPP\Core\Middleware\CSRFMiddleware;
use SPP\SPPSession;

echo "SPP CSRF Middleware Modernization Test\n";
echo "======================================\n\n";

// Mock request
$request = ['action' => 'save_settings'];
$_SERVER['SCRIPT_NAME'] = '/spp/admin/api.php';

// Setup Session Token for CLI
$ssname = \SPP\App::getSessionName();
$_SESSION[$ssname] = serialize(new SPPSession());
$token = SPPSession::getCsrfToken();
echo "Generated Session Token: $token\n\n";

$middleware = new CSRFMiddleware();

// 1. Test Fail (No Token)
echo "Test 1: Missing Token (Expect Fail)\n";
try {
    $_REQUEST = [];
    $_SERVER['HTTP_X_CSRF_TOKEN'] = '';
    $middleware->handle($request, function($req) { return "PASS"; });
    echo "Result: FAIL (Middleware allowed missing token!)\n";
} catch (\Exception $e) {
    echo "Result: PASS (Caught expected exception: " . $e->getMessage() . ")\n";
}

// 2. Test Pass (Body Token)
echo "\nTest 2: Body Token (Expect Pass)\n";
try {
    $_REQUEST['csrf_token'] = $token;
    $_SERVER['HTTP_X_CSRF_TOKEN'] = '';
    $res = $middleware->handle($request, function($req) { return "SUCCESS"; });
    echo "Result: $res (Expected SUCCESS)\n";
} catch (\Exception $e) {
    echo "Result: FAIL (Unexpected exception: " . $e->getMessage() . ")\n";
}

// 3. Test Pass (Header Token)
echo "\nTest 3: Header Token (Expect Pass)\n";
try {
    $_REQUEST = [];
    $_SERVER['HTTP_X_CSRF_TOKEN'] = $token;
    $res = $middleware->handle($request, function($req) { return "SUCCESS"; });
    echo "Result: $res (Expected SUCCESS)\n";
} catch (\Exception $e) {
    echo "Result: FAIL (Unexpected exception: " . $e->getMessage() . ")\n";
}

// 4. Test Fail (Mismatch Header Token)
echo "\nTest 4: Mismatch Header Token (Expect Fail)\n";
try {
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'invalid_token';
    $middleware->handle($request, function($req) { return "PASS"; });
    echo "Result: FAIL (Middleware allowed mismatched header token!)\n";
} catch (\Exception $e) {
    echo "Result: PASS (Caught expected exception: " . $e->getMessage() . ")\n";
}

echo "\nTests Completed.\n";
