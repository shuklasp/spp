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

// Failsafe fatal error shutdown handler to ensure SPA always receives valid JSON
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (ob_get_length()) {
            ob_clean();
        }
        if (!headers_sent()) {
            http_response_code(500);
            if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
        }
        echo json_encode([
            'success' => false,
            'message' => 'Fatal Error: ' . $error['message'] . ' in ' . basename($error['file']) . ':' . $error['line'],
            'data' => [],
            'instructions' => [
                ['action' => 'notify', 'message' => 'Fatal Error: ' . $error['message'] . ' in ' . basename($error['file']) . ':' . $error['line'], 'type' => 'error']
            ]
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
});

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
$contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false || (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST')) {
    $input = file_get_contents('php://input');
    if (!empty($input)) {
        $data = json_decode($input, true);
        if (is_array($data)) {
            $queryAction = $_GET['action'] ?? null;
            $_POST = array_merge($_POST, $data);
            $_REQUEST = array_merge($_REQUEST, $data);
            if ($queryAction !== null && $queryAction !== '') {
                $_REQUEST['action'] = $queryAction;
            }
        }
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

if (!defined('SPP_NO_AUTO_BOOT')) {
    define('SPP_NO_AUTO_BOOT', true);
}
require_once SPP_BASE_DIR . '/sppinit.php';

// Force Admin Context for Session consistency with spp/admin/index.php
try {
    \SPP\Scheduler::getProcObj('sppadmin');
} catch (\Exception $e) {
    new \SPP\App('sppadmin');
}
\SPP\Scheduler::setContext('sppadmin');

// Ensure session is started with secure cookie parameters
if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
    @session_start();
}

// Load global handlers if available
$globalPath = dirname(SPP_BASE_DIR) . '/global.php';
if (file_exists($globalPath)) {
    // global.php is deprecated, do nothing
}

/**
 * sendResponse function
 * Helper to transmit JSON results back to the SPA.
 */
function sendResponse($success, $data = [], $message = '', bool $autoNotify = true)
{
    // If output has already been sent (e.g. by SPPAjax::respond terminating), avoid double JSON output
    if (headers_sent() && !ob_get_level()) {
        exit;
    }
    $phpOutput = ob_get_clean();
    $instructions = [];

    // Error extraction via LiveAction (SPPError deprecated)

    // Handle LiveAction object if passed as data
    if ($data instanceof \SPPMod\SPPAPI\LiveAction) {
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

    // Passive read/query actions or generic success messages should NEVER generate toast clutter
    $passiveActions = [
        'check_auth', 'get_profile', 'list_apps', 'list_modules', 'storage_list_files', 
        'storage_tree', 'storage_disk_usage', 'storage_read_file', 'get_admin_permissions',
        'load_view', 'diagnostics_health', 'get_system_info', 'get_global_settings',
        'switch_context', 'set_context', 'manifest_graph', 'manifest_catalog', 'manifest_bundle',
        'storage_get_file_props', 'storage_get_directory_props', 'storage_get_manifest_raw',
        'storage_validate_manifest_yaml', 'storage_diff_manifest', 'storage_manifest_health',
        'storage_verify_integrity', 'storage_check_capabilities', 'props_search',
        'storage_get_favorites', 'files_get_favorites',
        'domains_zone_history', 'dns_zone_history', 'domains_preview_rollback', 'dns_preview_rollback'
    ];
    $reqAction = $_REQUEST['action'] ?? '';
    if (in_array($reqAction, $passiveActions, true) || in_array(strtolower(trim($message)), ['ok', 'success', 'authenticated', 'authenticated.'], true)) {
        $autoNotify = false;
    }

    if ($autoNotify && $message && empty(array_filter($instructions, fn($i) => $i['action'] === 'notify'))) {
        $instructions[] = ['action' => 'notify', 'message' => $message, 'type' => $success ? 'success' : 'error'];
    }

    $curDomain = class_exists('\\SPP\\Core\\Domain\\DomainContextResolver') && \SPP\Core\Domain\DomainContextResolver::has()
        ? \SPP\Core\Domain\DomainContextResolver::current()
        : null;

    $response = [
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'instructions' => $instructions,
        'meta' => [
            'domain' => $curDomain ? $curDomain->getDomainId() : 'default',
            'scope' => ($curDomain && $curDomain->getDomainId() !== 'default') ? 'domain' : 'platform',
        ],
        'error' => $success ? null : ($message ?: 'An error occurred during request execution.')
    ];

    if (!empty($phpOutput)) {
        $response['_debug_output'] = $phpOutput;
    }

    if (defined('SPP_LOG_DIR')) {
        $logFile = SPP_LOG_DIR . '/api_debug.log';
        if (!is_dir(dirname($logFile)))
            mkdir(dirname($logFile), 0777, true);
        error_log("[" . date('Y-m-d H:i:s') . "] API Response: success=" . ($success ? 'true' : 'false') . ", action=" . ($_REQUEST['action'] ?? 'none') . ", message=" . $message . "\n", 3, $logFile);
    }

    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    $encodeFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $encodeFlags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    $json = json_encode($response, $encodeFlags);
    if ($json === false) {
        $cleanFn = function ($val) use (&$cleanFn) {
            if (is_string($val)) {
                if (function_exists('mb_check_encoding') && !mb_check_encoding($val, 'UTF-8')) {
                    $conv = @mb_convert_encoding($val, 'UTF-8', 'Windows-1252');
                    return ($conv !== false && mb_check_encoding($conv, 'UTF-8')) ? $conv : (string)@mb_convert_encoding($val, 'UTF-8', 'UTF-8');
                }
                return $val;
            }
            if (is_array($val)) {
                foreach ($val as $k => $v) {
                    $val[$k] = $cleanFn($v);
                }
                return $val;
            }
            return $val;
        };
        $response = $cleanFn($response);
        $json = json_encode($response, $encodeFlags);
    }
    if ($json === false) {
        $response['data'] = [];
        $response['_debug_output'] = "JSON Encode Failed: " . json_last_error_msg();
        echo json_encode($response, $encodeFlags);
    } else {
        echo $json;
    }
    session_write_close();
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
 * Real system and component health evaluator (no simulated 100% scores).
 */
function runAllHealthChecks($appname)
{
    $checks = [];
    $score = 100;

    // 1. PHP Version
    $checks[] = ['name' => 'PHP Version', 'status' => 'OK', 'detail' => PHP_VERSION];

    // 2. Database Connectivity
    try {
        if (class_exists('\\SPPMod\\SPPDB\\SPPDB')) {
            $db = new \SPPMod\SPPDB\SPPDB();
            if ($db->getDriver() !== 'xdb') {
                $db->execute_query("SELECT 1");
            }
            $checks[] = ['name' => 'Database Connectivity', 'status' => 'OK', 'detail' => 'Connected (' . $appname . ')'];
        } else {
            $checks[] = ['name' => 'Database Connectivity', 'status' => 'WARNING', 'detail' => 'SPPDB driver class not loaded'];
            $score -= 20;
        }
    } catch (\Throwable $e) {
        $checks[] = ['name' => 'Database Connectivity', 'status' => 'CRITICAL', 'detail' => $e->getMessage()];
        $score -= 40;
    }

    // 3. Storage Health
    $free = @disk_free_space(defined('SPP_BASE_DIR') ? SPP_BASE_DIR : '.');
    $total = @disk_total_space(defined('SPP_BASE_DIR') ? SPP_BASE_DIR : '.');
    if ($total > 0) {
        $pct = round((($total - $free) / $total) * 100, 1);
        $diskStatus = ($pct > 95) ? 'CRITICAL' : (($pct > 85) ? 'WARNING' : 'OK');
        if ($diskStatus === 'CRITICAL') $score -= 30;
        elseif ($diskStatus === 'WARNING') $score -= 15;
        $checks[] = ['name' => 'Disk Space', 'status' => $diskStatus, 'detail' => "{$pct}% utilized"];
    }

    // 4. Server Agent Status
    $agentClientPath = dirname(__DIR__) . '/agent/src/Client.php';
    if (file_exists($agentClientPath)) {
        require_once $agentClientPath;
        try {
            $agentClient = new \SPP\Agent\Client();
            $agentOk = $agentClient->isAvailable();
            $checks[] = ['name' => 'Server Agent IPC', 'status' => $agentOk ? 'OK' : 'WARNING', 'detail' => $agentOk ? 'Agent connected' : 'Agent daemon offline (fallback mode)'];
            if (!$agentOk) $score -= 10;
        } catch (\Throwable $e) {
            $checks[] = ['name' => 'Server Agent IPC', 'status' => 'WARNING', 'detail' => $e->getMessage()];
            $score -= 10;
        }
    }

    return ['appname' => $appname, 'score' => max(0, min(100, $score)), 'checks' => $checks];
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
    $profile = $settings['profile'] ?? $settings["\xef\xbb\xbfprofile"] ?? '';
    return strtolower((string)$profile) === 'dev';
}

function isPublicAdminAction(string $action): bool
{
    // Strict allowlist: only authentication initiation and session checks are public
    return in_array($action, [
        'login',
        'Auth_Login',
        'Auth_VerifyMFA',
        'VerifyMFA',
        'verify_mfa',
        'logout',
        'Auth_Logout',
        'check_auth',
        'Auth_SendMagicLink',
        'SendMagicLink',
        'send_magic_link',
        'Auth_ConsumeMagicLink',
        'ConsumeMagicLink',
        'consume_magic_link',
    ], true);
}

function isAdminAuthenticated(): bool
{
    try {
        if (!empty($_SESSION['spp_admin_user'])) {
            return true;
        }
        if (!empty($_SESSION['__sppadmin_sppsession']['vars']['__sppauth_user__']) || !empty($_SESSION['__sppadmin_sppsession']['vars']['spp_admin_user'])) {
            return true;
        }

        // Cross-bucket session inspection: check if any app context or session bucket has authenticated user
        if (isset($_SESSION) && is_array($_SESSION)) {
            foreach ($_SESSION as $k => $v) {
                if (is_array($v) && isset($v['vars']) && is_array($v['vars'])) {
                    if (!empty($v['vars']['__sppauth_user__']) || !empty($v['vars']['spp_admin_user']) || !empty($v['vars']['__user_id__'])) {
                        return true;
                    }
                }
            }
        }

        if (\SPP\SPPSession::sessionExists() && \SPP\SPPSession::sessionVarExists('__sppauth_user__')) {
            return true;
        }
        if (class_exists('\\SPPMod\\SPPAuth\\SPPAuth')) {
            if (\SPPMod\SPPAuth\SPPAuth::check()) {
                return true;
            }
            if (method_exists('\\SPPMod\\SPPAuth\\SPPAuth', 'guard') && \SPPMod\SPPAuth\SPPAuth::guard()->check()) {
                return true;
            }
        }
        if (!empty($_SESSION['__sppauth_user__'])) {
            return true;
        }
    } catch (\Throwable $e) {
        return false;
    }
    return false;
}

if (php_sapi_name() === 'cli' && empty($_GET['action']) && empty($_POST['action']) && empty($_REQUEST['action'])) {
    return;
}

try {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    $action = (!empty($_GET['action'])) ? $_GET['action'] : ($_POST['action'] ?? ($_REQUEST['action'] ?? ''));
    if (!$action) {
        $jsonInput = file_get_contents('php://input');
        if (!empty($jsonInput)) {
            $decoded = json_decode($jsonInput, true);
            if ($decoded && !empty($decoded['action'])) {
                $action = $decoded['action'];
                $_REQUEST = array_merge($_REQUEST, $decoded);
            }
        }
    }

    // Ultra-Fast Path: check_auth requires 0 app boots and produces 0 toast notifications
    if ($action === 'check_auth') {
        $username = null;
        $userId = '1';
        if (!empty($_SESSION['spp_admin_user'])) {
            $username = $_SESSION['spp_admin_user'];
        } elseif (!empty($_SESSION['__sppadmin_sppsession']['vars']['__sppauth_user__'])) {
            $username = $_SESSION['__sppadmin_sppsession']['vars']['__sppauth_user__'];
        } elseif (\SPP\SPPSession::sessionExists() && \SPP\SPPSession::sessionVarExists('__sppauth_user__')) {
            $username = \SPP\SPPSession::getSessionVar('__sppauth_user__');
        } elseif (class_exists('\\SPPMod\\SPPAuth\\SPPAuth') && \SPPMod\SPPAuth\SPPAuth::check()) {
            $user = \SPPMod\SPPAuth\SPPAuth::user();
            $userId = (string) \SPPMod\SPPAuth\SPPAuth::guard()->id();
            $username = $user ? ($user->username ?? (is_object($user) && method_exists($user, 'get') ? $user->get('UserName') : null) ?? $userId) : $userId;
        } elseif (!empty($_SESSION['__sppauth_user__'])) {
            $username = $_SESSION['__sppauth_user__'];
        }

        if ($username) {
            sendResponse(true, [
                'username' => $username,
                'user_id' => $userId,
                'role' => 'Administrator'
            ], 'Authenticated.', false);
        } else {
            sendResponse(false, ['authenticated' => false], '', false);
        }
        exit;
    }

    // Ultra-Fast Path: Reject unauthenticated requests immediately without booting app
    if (!isPublicAdminAction($action) && !isAdminAuthenticated()) {
        http_response_code(401);
        sendResponse(false, ['authenticated' => false], 'Authentication required.', false);
        exit;
    }

    $authContext = 'sppadmin';
    $appContext = $_REQUEST['appname'] ?? $_REQUEST['context'] ?? ($_SESSION['spp_admin_selected_app'] ?? 'default');
    if ($appContext === 'sppadmin' || $appContext === 'sppdev') {
        $appContext = 'default';
    }
    $_SESSION['spp_admin_selected_app'] = $appContext;

    // 3. API Action Routing
    if (isset($_REQUEST['__api']) && $_REQUEST['__api'] == '1') {
        \SPPMod\SPPAPI\SPPAPI::handle();
        exit;
    }

    if (isset($_REQUEST['__svc'])) {
        \SPPMod\SPPAPI\SPPAjax::handle();
        exit;
    }

    $action = (!empty($_GET['action'])) ? $_GET['action'] : ($_POST['action'] ?? ($_REQUEST['action'] ?? ''));

    if ($action === 'info') {
        echo json_encode([
            'session_save_path' => session_save_path(),
            'session_handler' => ini_get('session.save_handler'),
            'redis_class' => class_exists('\Redis'),
            'session' => $_SESSION ?? []
        ]);
        exit;
    }

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

    require_once __DIR__ . '/services/CommandBridge.php';
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

    // Release PHP session file lock early for non-mutating actions to enable full parallel request concurrency
    $sessionMutatingActions = ['login', 'logout', 'switch_context', 'save_profile', 'admin_login'];
    if (!in_array($action, $sessionMutatingActions, true) && session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    $targetDomain = $_REQUEST['domain'] ?? $_REQUEST['domain_id'] ?? null;
    if ($targetDomain !== null && class_exists('\\SPP\\Core\\Domain\\DomainContextResolver')) {
        $domCtx = \SPP\Core\Domain\DomainContextResolver::resolve($targetDomain);
        \SPP\Core\Domain\DomainContextResolver::push($domCtx);
    }

    // RBAC: Gate admin actions by scope and target domain
    if (!isPublicAdminAction($action) && function_exists('gateAdminAction') && !empty($action) && !gateAdminAction($action, $targetDomain)) {
        sendResponse(false, [], "Access Denied: You do not have permission to perform '{$action}' on requested scope. Contact your administrator.");
    }

    $requestData = array_merge($_GET, $_POST, $_REQUEST);
    if (!empty($_FILES)) {
        $requestData['uploaded_files'] = $_FILES;
    }

    // 1. Unified CommandBridge: Execute via CLI or synchronized action
    $bridgeRes = \SPP\Admin\Services\CommandBridge::handleAction($action, $requestData);
    $bridgeSuccess = !empty($bridgeRes['success']) || (!isset($bridgeRes['error']) && is_array($bridgeRes));
    if ($bridgeSuccess || (isset($bridgeRes['error']) && !str_contains((string)$bridgeRes['error'], 'not recognized by CommandBridge'))) {
        $resData = $bridgeRes;
        if (array_key_exists('data', $bridgeRes)) {
            $resData = $bridgeRes['data'];
        } else {
            unset($resData['success'], $resData['message'], $resData['error']);
        }
        $msg = $bridgeRes['message'] ?? (!empty($bridgeRes['success']) ? '' : ($bridgeRes['error'] ?? ''));
        sendResponse($bridgeRes['success'] ?? true, $resData, $msg);
        exit;
    }

    // 2. Fallback to specialized service dispatcher
    \SPPMod\SPPAPI\Dispatchers\ServiceDispatcher::resolveAndExecute($action, $requestData);
} catch (\Throwable $e) {
    $errorMsg = "[" . date('Y-m-d H:i:s') . "] API FATAL ERROR: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "\n" . $e->getTraceAsString() . "\n";
    file_put_contents(SPP_BASE_DIR . "/api_debug.log", $errorMsg, FILE_APPEND);
    sendResponse(false, [], "Server Error: " . $e->getMessage() . " in " . basename($e->getFile()) . ":" . $e->getLine());
}
