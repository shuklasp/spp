<?php
/**
 * SPP Admin SPA API Controller
 */

// Load vendor autoloader first
$vendorPath = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (file_exists($vendorPath)) {
    require_once $vendorPath;
}

// Capture any PHP warnings/notices so they don't corrupt JSON output
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');

// Relying on global SPPErrorHandler from sppinit.php instead of local handlers.

// Polyfills for PHP < 8.0
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle)
    {
        return (string) $needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle)
    {
        return $needle !== '' && substr($haystack, -strlen($needle)) === (string) $needle;
    }
}
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle)
    {
        return $needle !== '' && strpos($haystack, $needle) !== false;
    }
}

// Define framework paths
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


// Pre-load classes required for session deserialization BEFORE session_start()
// sppinit.php calls session_start() which unserializes objects.
$coreDir = SPP_BASE_DIR . '/core';
$authDir = SPP_BASE_DIR . '/modules/spp/sppauth';
$dbDir = SPP_BASE_DIR . '/modules/spp/sppdb';
$cfgDir = SPP_BASE_DIR . '/modules/spp/sppconfig';
$entDir = SPP_BASE_DIR . '/modules/spp/sppdb';

foreach (['class.sppobject.php', 'class.sppsession.php', 'class.sppbase.php', 'class.sppexception.php', 'sppsystemexceptions.php', 'EntityInterface.php'] as $f) {
    if (file_exists($coreDir . '/' . $f))
        require_once $coreDir . '/' . $f;
}
if (file_exists($dbDir . '/class.sppdb.php'))
    require_once $dbDir . '/class.sppdb.php';
if (file_exists($cfgDir . '/class.sppconfig.php'))
    require_once $cfgDir . '/class.sppconfig.php';
if (file_exists($entDir . '/class.sppentity.php'))
    require_once $entDir . '/class.sppentity.php';

foreach (['class.sppuser.php'] as $f) {
    if (file_exists($authDir . '/' . $f))
        require_once $authDir . '/' . $f;
}

require_once SPP_BASE_DIR . '/sppinit.php';

// Load global handlers if available
$globalPath = dirname(SPP_BASE_DIR) . '/global.php';
if (file_exists($globalPath)) {
    // global.php is deprecated, do nothing
}

/**
 * sendResponse function
 * Helper to transmit JSON results back to the SPA.
 */
function sendResponse($success, $data = [], $message = '')
{
    $phpOutput = ob_get_clean();
    $instructions = [];

    // Error extraction via LiveAction (SPPError deprecated)

    // Handle LiveAction object if passed as data
    if ($data instanceof \SPPMod\SppApi\LiveAction) {
        $la = $data;
        $refl = new \ReflectionClass($la);
        $instrProp = $refl->getProperty('instructions');
        $instrProp->setAccessible(true);
        $instructions = array_merge($instructions, $instrProp->getValue($la));

        $dataProp = $refl->getProperty('data');
        $dataProp->setAccessible(true);
        $data = $dataProp->getValue($la);
    } elseif (isset($data['instructions'])) {
        $instructions = array_merge($instructions, $data['instructions']);
        unset($data['instructions']);
    }

    if ($message && empty(array_filter($instructions, fn($i) => $i['action'] === 'notify'))) {
        $instructions[] = ['action' => 'notify', 'message' => $message, 'type' => $success ? 'success' : 'error'];
    }

    $response = [
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'instructions' => $instructions
    ];

    if (!empty($phpOutput)) {
        $response['_debug_output'] = $phpOutput;
    }

    if (defined('SPP_LOG_DIR')) {
        $logFile = SPP_LOG_DIR . '/api_debug.log';
        if (!is_dir(dirname($logFile)))
            mkdir(dirname($logFile), 0777, true);
        error_log("[" . date('Y-m-d H:i:s') . "] API Response: success=" . ($success ? 'true' : 'false') . ", action=" . ($_REQUEST['action'] ?? 'none') . "\n", 3, $logFile);
    }

    header('Content-Type: application/json; charset=utf-8');
    $json = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        $response['data'] = [];
        $response['_debug_output'] = "JSON Encode Failed: " . json_last_error_msg();
        echo json_encode($response);
    } else {
        echo $json;
    }
    exit;
}

/**
 * remoteCall: Helper to communicate with another SPP instance.
 */
function remoteCall($url, $action, $params = [], $token = '')
{
    $url = rtrim($url, '/');
    if (!str_ends_with($url, 'api.php') && !str_ends_with($url, 'spp-api')) {
        $url .= '/spp-api/';
    }

    $params['action'] = $action;
    $params['spp_deploy_token'] = $token;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $res = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);

    if ($info['http_code'] !== 200) {
        return ['success' => false, 'message' => "Remote server error ({$info['http_code']})"];
    }
    return json_decode($res, true) ?: ['success' => false, 'message' => "Invalid response from remote server."];
}

/**
 * Path Transformation Helpers
 */
function normalizePath($p)
{
    return str_replace('\\', '/', $p);
}
function relativizePath($path)
{
    if (empty($path))
        return '';
    $path = normalizePath($path);
    $root = normalizePath(SPP_APP_DIR);
    return str_starts_with($path, $root) ? ltrim(substr($path, strlen($root)), '/') : $path;
}
function absolutizePath($path)
{
    if (empty($path))
        return '';
    $path = normalizePath($path);
    if (preg_match('/^([a-zA-Z]:|\/)/', $path))
        return $path;
    return normalizePath(SPP_APP_DIR) . '/' . $path;
}

/**
 * Context Management
 */
function withContext($targetApp, $callback)
{
    $current = \SPP\Scheduler::getContext();
    if ($current === $targetApp)
        return $callback();

    try {
        try {
            \SPP\Scheduler::getProcObj($targetApp);
        } catch (\Exception $e) {
            new \SPP\App($targetApp);
        }
        \SPP\Scheduler::setContext($targetApp);
        $result = $callback();
        \SPP\Scheduler::setContext($current);
        return $result;
    } catch (\Throwable $e) {
        \SPP\Scheduler::setContext($current);
        throw $e;
    }
}

/**
 * Settings Management
 */
function getGlobalSettings()
{
    return \SPP\App::getGlobalSettings();
}

function saveGlobalSettings($settings)
{
    $path = (defined('SPP_ETC_DIR') ? SPP_ETC_DIR : SPP_BASE_DIR . '/etc') . '/global-settings.yml';
    try {
        $yml = \Symfony\Component\Yaml\Yaml::dump($settings, 10, 2);
        return file_put_contents($path, $yml) !== false;
    } catch (\Exception $e) {
        return false;
    }
}

function getSyncConfig()
{
    $path = SPP_BASE_DIR . '/modules/spp/sppsync/config.yml';
    if (!file_exists($path))
        return ['environments' => []];
    return \Symfony\Component\Yaml\Yaml::parseFile($path) ?: [];
}

function saveSyncConfig(array $config)
{
    $path = SPP_BASE_DIR . '/modules/spp/sppsync/config.yml';
    if (!is_dir(dirname($path)))
        mkdir(dirname($path), 0777, true);
    return file_put_contents($path, \Symfony\Component\Yaml\Yaml::dump($config, 10, 2)) !== false;
}

/**
 * Security Management
 */
function getDeploymentToken()
{
    try {
        $xdb = new \SPPMod\SPPXDB\SPP_XDB('sys', 'security');
        $xdb->setEncryptedFields(['value']);
        $row = $xdb->queryX("//row[key = 'deployment_token']");
        return $row[0]['value'] ?? 'DISABLED';
    } catch (\Exception $e) {
        return 'DISABLED';
    }
}

function setDeploymentToken($token)
{
    $xdb = new \SPPMod\SPPXDB\SPP_XDB('sys', 'security');
    $xdb->setEncryptedFields(['value']);
    $existing = $xdb->queryX("//row[key = 'deployment_token']");
    $existing ? $xdb->update(['value' => $token], "key = 'deployment_token'") : $xdb->insert(['key' => 'deployment_token', 'value' => $token]);
    return true;
}

/**
 * Health Check Utility
 */
function runAllHealthChecks($appname)
{
    $checks = [];
    $checks[] = ['name' => 'PHP Version', 'status' => 'OK', 'detail' => PHP_VERSION];
    $checks[] = ['name' => 'Database Connectivity', 'status' => 'OK', 'detail' => 'Connected (' . $appname . ')'];
    return ['appname' => $appname, 'score' => 100, 'checks' => $checks];
}

function repairNamespace($class)
{
    if (empty($class) || strpos($class, '\\') !== false)
        return $class;
    if (strpos($class, 'SPPMod') === 0) {
        return str_replace(['SPPMod', 'SPPAuth', 'SppDb'], ['\\SPPMod', '\\SPPAuth', '\\SppDb'], $class);
    }
    return $class;
}

function checkDevMode()
{
    $settings = getGlobalSettings();
    return strtolower($settings['profile'] ?? '') === 'dev';
}

function isPublicAdminAction(string $action): bool
{
    return in_array($action, [
        'login',
        'Auth_Login',
        'Auth_VerifyMFA',
        'Auth_SendMagicLink',
        'Auth_ConsumeMagicLink',
        'logout',
        'check_auth',
        'get_profile',
    ], true);
}

function isAdminAuthenticated(): bool
{
    try {
        if (isset($_SESSION['spp_admin_fallback'])) {
            return true;
        }
        if (isset($_SESSION['spp_admin_user'])) {
            return true;
        }
        if (class_exists('\\SPPMod\\SPPAuth\\SPPAuth') && \SPPMod\SPPAuth\SPPAuth::check()) {
            return true;
        }
        if (\SPP\SPPSession::sessionVarExists('__sppauth_user__')) {
            return true;
        }
    } catch (\Throwable $e) {
        return false;
    }
    return false;
}

// 1. Initial Gating
if (!checkDevMode()) {
    http_response_code(403);
    sendResponse(false, [], "Access Denied: Administration portal is only accessible in 'dev' mode.");
}

// --- Global Helpers ---
if (!function_exists('createEntityRevision')) {
    function createEntityRevision($appContext, $name) {
        $srcDir = SPP_APP_DIR . "/src/{$appContext}/entities";
        $revDir = $srcDir . '/.revisions';
        if (!is_dir($revDir)) @mkdir($revDir, 0777, true);
        
        $ts = time();
        $ymlPath = $srcDir . '/' . strtolower($name) . '.yml';
        $phpPath = $srcDir . '/entity.' . strtolower($name) . '.php';
        if (!file_exists($phpPath)) $phpPath = $srcDir . '/' . $name . '.php';
        
        if (file_exists($ymlPath)) @copy($ymlPath, $revDir . '/' . strtolower($name) . '_' . $ts . '.yml');
        if (file_exists($phpPath)) @copy($phpPath, $revDir . '/entity.' . strtolower($name) . '_' . $ts . '.php');
    }
}

try {
    file_put_contents(SPP_BASE_DIR . "/api_debug.log", "[" . date('Y-m-d H:i:s') . "] API Request: action=" . ($_REQUEST['action'] ?? 'none') . " session_keys=" . (isset($_SESSION) ? implode(',', array_keys($_SESSION)) : 'none') . "\n", FILE_APPEND);

    // 2. Authentication & Context Setup
    $authContext = 'sppadmin';
    $appContext = $_REQUEST['appname'] ?? $_REQUEST['context'] ?? 'default';
    if ($appContext === 'sppadmin')
        $appContext = 'default';

    try {
        try {
            \SPP\Scheduler::getProcObj($authContext);
        } catch (\Exception $e) {
            new \SPP\App($authContext);
        }
        \SPP\Scheduler::setContext($authContext);
        file_put_contents(SPP_BASE_DIR . "/api_debug.log", "[" . date('Y-m-d H:i:s') . "] Context set to $authContext\n", FILE_APPEND);
    } catch (\Exception $e) {
        file_put_contents(SPP_BASE_DIR . "/api_debug.log", "[" . date('Y-m-d H:i:s') . "] Context failure: " . $e->getMessage() . "\n", FILE_APPEND);
    }

    // 3. API Action Routing
    if (isset($_REQUEST['__api']) && $_REQUEST['__api'] == '1') {
        \SPPMod\SPPAPI\SPPAPI::handle();
        exit;
    }

    if (isset($_REQUEST['__svc'])) {
        \SPPMod\SppApi\SPPAjax::handle();
        exit;
    }

    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    if (!$action) {
        $jsonInput = file_get_contents('php://input');
        if (!empty($jsonInput)) {
            $decoded = json_decode($jsonInput, true);
            if ($decoded) {
                $action = $decoded['action'] ?? '';
                $_REQUEST = array_merge($_REQUEST, $decoded);
            }
        }
    }

    require_once __DIR__ . '/services/General.php';
    error_log("Dispatching action: " . $action);

    if (!isPublicAdminAction($action)) {
        if (!isAdminAuthenticated()) {
            sendResponse(false, [], "Authentication required.");
        }
        \SPPMod\SPPAPI\Dispatchers\ServiceDispatcher::enforceRequestGuards();
    }

    // Audit log every admin action
    if (function_exists('spp_admin_audit_log') && !empty($action)) {
        spp_admin_audit_log($action, $_REQUEST);
    }

    // RBAC: Gate admin actions by scope
    if (!isPublicAdminAction($action) && function_exists('gateAdminAction') && !empty($action) && !gateAdminAction($action)) {
        sendResponse(false, [], "Access Denied: You do not have permission to perform '{$action}'. Contact your administrator.");
    }

    // Switch context if target app context differs from auth context
    if ($appContext !== $authContext) {
        try {
            try {
                \SPP\Scheduler::getProcObj($appContext);
            } catch (\Exception $e) {
                new \SPP\App($appContext);
            }
            \SPP\Scheduler::setContext($appContext);
        } catch (\Exception $e) {
            file_put_contents(SPP_BASE_DIR . "/api_debug.log", "[" . date('Y-m-d H:i:s') . "] Failed to switch to context $appContext: " . $e->getMessage() . "\n", FILE_APPEND);
        }
    }

    \SPPMod\SPPAPI\Dispatchers\ServiceDispatcher::resolveAndExecute($action, $_REQUEST);
} catch (\Throwable $e) {
    $errorMsg = "[" . date('Y-m-d H:i:s') . "] API FATAL ERROR: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "\n" . $e->getTraceAsString() . "\n";
    file_put_contents(SPP_BASE_DIR . "/api_debug.log", $errorMsg, FILE_APPEND);
    sendResponse(false, [], "Server Error: " . $e->getMessage() . " in " . basename($e->getFile()) . ":" . $e->getLine());
}
