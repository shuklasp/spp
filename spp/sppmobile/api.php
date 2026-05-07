<?php
/**
 * SPP Mobile Studio API Controller
 */

// Load vendor autoloader
$vendorPath = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (file_exists($vendorPath)) {
    require_once $vendorPath;
}

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) return;
    $msg = "[" . date('Y-m-d H:i:s') . "] PHP Error ($errno): $errstr in $errfile on line $errline\n";
    file_put_contents(dirname(__DIR__) . "/api_debug.log", $msg, FILE_APPEND);
});

if (!defined('SPP_BASE_DIR')) {
    define('SPP_BASE_DIR', dirname(__DIR__));
}

// Support for JSON payloads
if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    if (is_array($data)) {
        $_POST = array_merge($_POST, $data);
        $_REQUEST = array_merge($_REQUEST, $data);
    }
}

require_once SPP_BASE_DIR . '/sppinit.php';

// Force Mobile Studio Context
\SPP\Scheduler::setContext('sppmobile');

function sendResponse($success, $data = [], $message = '') {
    $phpOutput = ob_get_clean();
    $response = [
        'success' => $success,
        'data' => $data,
        'message' => $message,
        'timestamp' => time()
    ];
    if (!empty($phpOutput)) $response['_debug_output'] = $phpOutput;
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

$action = $_REQUEST['action'] ?? '';
if (!$action) sendResponse(false, [], "Action required.");

// Discovery: Look in local services directory
$serviceDir = __DIR__ . '/services';
require_once $serviceDir . '/Mobile.php';

$la = new class {
    public $success = true;
    public $data = [];
    public $message = '';
    public function setData($d) { $this->data = array_merge($this->data, $d); return $this; }
    public function setStatus($s) { $this->success = ($s === 'success'); return $this; }
    public function notify($m) { $this->message = $m; return $this; }
};

$method = 'live_Mobile_' . str_replace(' ', '', ucwords(str_replace('_', ' ', $action)));
if (function_exists($method)) {
    $method($la, $_REQUEST);
    sendResponse($la->success, $la->data, $la->message);
} else {
    sendResponse(false, [], "Service action '$action' ($method) not found.");
}
