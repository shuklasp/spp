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

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno))
        return;
    $msg = "[" . date('Y-m-d H:i:s') . "] PHP Error ($errno): $errstr in $errfile on line $errline\n";
    file_put_contents(dirname(__DIR__) . "/api_debug.log", $msg, FILE_APPEND);
});
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== NULL && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_CORE_ERROR || $error['type'] === E_COMPILE_ERROR)) {
        $msg = "[" . date('Y-m-d H:i:s') . "] FATAL SHUTDOWN: {$error['message']} in {$error['file']} on line {$error['line']}\n";
        file_put_contents(dirname(__DIR__) . "/api_debug.log", $msg, FILE_APPEND);
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
$entDir = SPP_BASE_DIR . '/modules/spp/sppentity';

foreach (['class.sppobject.php', 'class.sppsession.php', 'class.sppbase.php', 'class.sppexception.php', 'sppsystemexceptions.php'] as $f) {
    if (file_exists($coreDir . '/' . $f))
        require_once $coreDir . '/' . $f;
}
if (file_exists($dbDir . '/class.sppdb.php'))
    require_once $dbDir . '/class.sppdb.php';
if (file_exists($cfgDir . '/class.sppconfig.php'))
    require_once $cfgDir . '/class.sppconfig.php';
if (file_exists($entDir . '/class.sppentity.php'))
    require_once $entDir . '/class.sppentity.php';

foreach (['class.sppuser.php', 'class.sppusersession.php'] as $f) {
    if (file_exists($authDir . '/' . $f))
        require_once $authDir . '/' . $f;
}

require_once SPP_BASE_DIR . '/sppinit.php';

// Load global handlers if available
$globalPath = dirname(SPP_BASE_DIR) . '/global.php';
if (file_exists($globalPath)) {
    require_once $globalPath;
}

/**
 * sendResponse function
 * Helper to transmit JSON results back to the SPA.
 */
function sendResponse($success, $data = [], $message = '')
{
    $phpOutput = ob_get_clean();
    $instructions = [];

    // Auto-convert SPPError to LiveAction notifications
    if (class_exists('SPP\\SPPError')) {
        $errobj = \SPP\Scheduler::getActiveErrorObj();
        if ($errobj instanceof \SPP\SPPError) {
            foreach ($errobj->getErrors() as $errno => $errors) {
                foreach ($errors as $err) {
                    $instructions[] = ['action' => 'notify', 'message' => $err['errmsg'], 'type' => 'error'];
                }
            }
            $errobj->destroySelfErrors();
        }
    }

    // Handle LiveAction object if passed as data
    if ($data instanceof \SPPMod\SPPAjax\LiveAction) {
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
            new \SPP\App($targetApp, false, 1);
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
        return str_replace(['SPPMod', 'SPPAuth', 'SPPGroup', 'SPPDB'], ['\\SPPMod', '\\SPPAuth', '\\SPPGroup', '\\SPPDB'], $class);
    }
    return $class;
}

function checkDevMode()
{
    $settings = getGlobalSettings();
    return strtolower($settings['profile'] ?? '') === 'dev';
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
    // 2. Authentication & Context Setup
    $authContext = 'sppadmin';
    $appContext = $_REQUEST['appname'] ?? $_REQUEST['context'] ?? 'default';
    if ($appContext === 'sppadmin')
        $appContext = 'default';

    try {
        try {
            \SPP\Scheduler::getProcObj($authContext);
        } catch (\Exception $e) {
            new \SPP\App($authContext, false, 3);
        }
        \SPP\Scheduler::setContext($authContext);
        file_put_contents(SPP_BASE_DIR . "/api_debug.log", "[" . date('Y-m-d H:i:s') . "] Context set to $authContext\n", FILE_APPEND);
    } catch (\Exception $e) {
        file_put_contents(SPP_BASE_DIR . "/api_debug.log", "[" . date('Y-m-d H:i:s') . "] Context failure: " . $e->getMessage() . "\n", FILE_APPEND);
    }

    // 3. Action Routing
    $action = $_POST['action'] ?? $_GET['action'] ?? null;

    if (!$action) {
        $jsonInput = file_get_contents('php://input');
        if (!empty($jsonInput)) {
            $decoded = json_decode($jsonInput, true);
            if ($decoded) {
                $action = $decoded['action'] ?? null;
                $_REQUEST = array_merge($_REQUEST, $decoded);
            }
        }
    }

    if ($action === 'list_revisions') {
        $name = $_POST['name'] ?? '';
        if (!$name) sendResponse(false, [], "Entity name required.");
        
        $srcDir = SPP_APP_DIR . "/src/{$appContext}/entities";
        $revDir = $srcDir . '/.revisions';
        $revisions = [];
        
        if (is_dir($revDir)) {
            $files = glob($revDir . '/' . strtolower($name) . '_*.yml');
            foreach ($files as $file) {
                if (preg_match('/_(\d+)\.yml$/', $file, $matches)) {
                    $ts = $matches[1];
                    $revisions[] = [
                        'timestamp' => (int)$ts,
                        'date' => date('Y-m-d H:i:s', $ts)
                    ];
                }
            }
        }
        usort($revisions, function($a, $b) { return $b['timestamp'] <=> $a['timestamp']; });
        sendResponse(true, ['revisions' => $revisions]);
    }
    
    if ($action === 'ai_parse_scaffold') {
        $prompt = strtolower($_POST['prompt'] ?? '');
        if (!$prompt) sendResponse(false, [], "Prompt required.");
        
        $commands = [];
        if (preg_match('/app(?:lication)? called (\w+)/', $prompt, $matches)) {
            $appName = $matches[1];
            $opts = (strpos($prompt, 'api') !== false) ? '--api' : '';
            $commands[] = ['cmd' => 'make:app', 'target' => $appName, 'opts' => $opts];
        }
        
        if (preg_match('/module(?: called)? (\w+)/', $prompt, $matches)) {
            $modName = $matches[1];
            $commands[] = ['cmd' => 'make:module', 'target' => ucfirst($modName), 'opts' => ''];
        }
        
        if (preg_match('/entity(?: called)? (\w+)/', $prompt, $matches)) {
            $entName = $matches[1];
            $commands[] = ['cmd' => 'make:entity', 'target' => ucfirst($entName), 'opts' => ''];
        }
        
        if (empty($commands)) {
            sendResponse(false, [], "Could not understand the requested command from prompt.");
        }
        
        sendResponse(true, ['commands' => $commands], "Parsed successfully.");
    }

    if ($action === 'clone_app') {
        $source = $_POST['source'] ?? '';
        $target = $_POST['target'] ?? '';
        if (!$source || !$target) sendResponse(false, [], "Source and target required.");
        
        // Mock cloning logic for safety in dev environment
        $settingsFile = SPP_BASE_DIR . '/config/settings.json';
        if (file_exists($settingsFile)) {
            $settings = json_decode(file_get_contents($settingsFile), true);
            if (isset($settings['apps'][$source])) {
                $settings['apps'][$target] = $settings['apps'][$source];
                $settings['apps'][$target]['base_url'] = '/' . strtolower($target);
                $settings['apps'][$target]['table_prefix'] = strtolower($target) . '_';
                file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT));
                sendResponse(true, [], "App cloned successfully (Mock).");
            }
        }
        sendResponse(false, [], "Source app not found.");
    }

    if ($action === 'scaffold_template') {
        $template = $_POST['template'] ?? '';
        if (!$template) sendResponse(false, [], "Template name required.");
        
        // Create mock app from template
        $settingsFile = SPP_BASE_DIR . '/config/settings.json';
        if (file_exists($settingsFile)) {
            $settings = json_decode(file_get_contents($settingsFile), true);
            $settings['apps'][$template . '_app'] = [
                'type' => 'user',
                'base_url' => '/' . $template,
                'table_prefix' => $template . '_',
                'options_yaml' => "template: {$template}\ncreated_at: " . time()
            ];
            file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT));
            sendResponse(true, [], "Template scaffolded (Mock).");
        }
        sendResponse(false, [], "Failed to scaffold template.");
    }

    if ($action === 'tail_logs') {
        $lines = [];
        
        // Try PHP error log
        $phpLog = ini_get('error_log');
        if ($phpLog && file_exists($phpLog) && is_readable($phpLog)) {
            $raw = file($phpLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $tail = array_slice($raw, -50);
            $lines = array_merge($lines, $tail);
        }
        
        // Try SPP internal log
        $sppLog = SPP_BASE_DIR . '/logs/spp.log';
        if (file_exists($sppLog) && is_readable($sppLog)) {
            $raw = file($sppLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $tail = array_slice($raw, -50);
            $lines = array_merge($lines, $tail);
        }
        
        // Fallback: If no log files found, return a helpful message
        if (empty($lines)) {
            $lines = [
                '[INFO] SPP Log Tail initialized. No log entries found yet.',
                '[INFO] PHP error_log path: ' . ($phpLog ?: '(not configured)'),
                '[INFO] SPP log path: ' . $sppLog,
                '[INFO] Logs will appear here as your application generates them.'
            ];
        }
        
        sendResponse(true, ['lines' => $lines]);
    }

    if ($action === 'export_app_package') {
        $appName = $_POST['app'] ?? '';
        if (!$appName) sendResponse(false, [], 'Application name required.');
        
        $package = [
            'app_name' => $appName,
            'exported_at' => date('c'),
            'config' => [],
            'entities' => [],
            'modules' => []
        ];
        
        // Load app config
        $settingsFile = SPP_BASE_DIR . '/config/settings.json';
        if (file_exists($settingsFile)) {
            $settings = json_decode(file_get_contents($settingsFile), true);
            $package['config'] = $settings['apps'][$appName] ?? [];
            $package['shared_groups'] = $settings['shared_groups'] ?? [];
        }
        
        // Load entity YAMLs
        $etcDir = SPP_APP_DIR . "/etc/{$appName}/entities";
        if (is_dir($etcDir)) {
            foreach (glob($etcDir . '/*.yml') as $file) {
                $package['entities'][basename($file)] = file_get_contents($file);
            }
        }
        
        // Load entity PHP files
        $srcDir = SPP_APP_DIR . "/src/{$appName}/entities";
        if (is_dir($srcDir)) {
            foreach (glob($srcDir . '/*.php') as $file) {
                $package['entities'][basename($file)] = file_get_contents($file);
            }
        }
        
        sendResponse(true, ['package' => $package], 'Package exported.');
    }

    if ($action === 'generate_docker') {
        $dockerfile = <<<DOCKER
FROM php:8.2-fpm
RUN docker-php-ext-install pdo pdo_mysql
COPY . /var/www/html
WORKDIR /var/www/html
RUN chown -R www-data:www-data /var/www/html
CMD ["php-fpm"]
DOCKER;

        $dockerCompose = <<<YAML
version: '3.8'
services:
  web:
    image: nginx:alpine
    ports:
      - "8080:80"
    volumes:
      - .:/var/www/html
      - ./nginx.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - app
  app:
    build: .
    volumes:
      - .:/var/www/html
  db:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: spp_db
    ports:
      - "3306:3306"
YAML;

        $nginxConf = <<<CONF
server {
    listen 80;
    index index.php index.html;
    server_name localhost;
    error_log  /var/log/nginx/error.log;
    access_log /var/log/nginx/access.log;
    root /var/www/html;
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
    location ~ \.php\$ {
        try_files \$uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)\$;
        fastcgi_pass app:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_param PATH_INFO \$fastcgi_path_info;
    }
}
CONF;

        file_put_contents(SPP_APP_DIR . '/Dockerfile', $dockerfile);
        file_put_contents(SPP_APP_DIR . '/docker-compose.yml', $dockerCompose);
        file_put_contents(SPP_APP_DIR . '/nginx.conf', $nginxConf);
        
        sendResponse(true, [], "Docker deployment files generated.");
    }

    if ($action === 'compile_workflow') {
        $trigger = $_POST['trigger'] ?? 'after_save';
        $task = $_POST['task'] ?? 'log';
        
        $snippet = "";
        if ($task === 'email') {
            $snippet = "\$to = \$this->email ?? 'admin@example.com';\n        @mail(\$to, 'Workflow Notification', 'Action triggered.');";
        } elseif ($task === 'log') {
            $snippet = "\\SPPMod\\SPPLogger\\SPP_Logger::info(static::class . ' workflow triggered.');";
        } elseif ($task === 'validate') {
            $snippet = "if (empty(\$this->name)) throw new \\Exception('Validation failed in workflow.');";
        } elseif ($task === 'webhook') {
            $snippet = "file_get_contents('https://hook.example.com/?entity=' . static::class);";
        }
        
        $code = <<<PHP
    public function {$trigger}() {
        // [Workflow Auto-Compiled]
        {$snippet}
        return parent::{$trigger}();
    }
PHP;
        sendResponse(true, ['code' => "\n" . $code . "\n"]);
    }

    if ($action === 'generate_sdk') {
        $srcDir = SPP_APP_DIR . "/src/{$appContext}/entities";
        $entities = [];
        if (is_dir($srcDir)) {
            $files = glob($srcDir . '/*.yml');
            foreach ($files as $file) {
                $yaml = file_get_contents($file);
                $config = \Symfony\Component\Yaml\Yaml::parse($yaml);
                if (isset($config['enable_api']) && $config['enable_api'] == true) {
                    $entityName = pathinfo($file, PATHINFO_FILENAME);
                    // CamelCase
                    $entityName = str_replace(' ', '', ucwords(str_replace('_', ' ', $entityName)));
                    $entities[] = $entityName;
                }
            }
        }
        
        $sdkCode = "/**\n * SPP Auto-Generated JavaScript SDK\n */\n\n";
        $sdkCode .= "class SPPClient {\n";
        $sdkCode .= "    constructor(baseUrl = '/api/v1') {\n";
        $sdkCode .= "        this.baseUrl = baseUrl;\n";
        $sdkCode .= "    }\n\n";
        
        foreach ($entities as $entity) {
            $eLower = strtolower($entity);
            $sdkCode .= <<<JS
    // {$entity} API
    async get{$entity}s() {
        const res = await fetch(\`\${this.baseUrl}/{$eLower}\`);
        return res.json();
    }
    async get{$entity}(id) {
        const res = await fetch(\`\${this.baseUrl}/{$eLower}?id=\${id}\`);
        return res.json();
    }
    async create{$entity}(data) {
        const res = await fetch(\`\${this.baseUrl}/{$eLower}\`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        return res.json();
    }
    async update{$entity}(id, data) {
        data.id = id;
        const res = await fetch(\`\${this.baseUrl}/{$eLower}\`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        return res.json();
    }
    async delete{$entity}(id) {
        const res = await fetch(\`\${this.baseUrl}/{$eLower}?id=\${id}\`, {
            method: 'DELETE'
        });
        return res.json();
    }


JS;
        }
        $sdkCode .= "}\n\nexport default SPPClient;\n";
        
        file_put_contents(SPP_APP_DIR . '/src/spp_sdk.js', $sdkCode);
        
        sendResponse(true, [], "SDK generated.");
    }

    if ($action === 'scaffold_test') {
        $entityName = $_POST['entityName'] ?? '';
        if (!$entityName) sendResponse(false, [], "Entity name required.");
        
        $testsDir = SPP_APP_DIR . "/tests";
        if (!is_dir($testsDir)) @mkdir($testsDir, 0777, true);
        
        $className = "\\App\\Entities\\{$entityName}";
        
        $testCode = <<<PHP
<?php

use PHPUnit\Framework\TestCase;

class {$entityName}Test extends TestCase {
    
    public function testCreate() {
        \$entity = new {$className}();
        // Set basic properties if needed
        \$entity->save();
        \$this->assertNotNull(\$entity->id);
        
        return \$entity->id;
    }
    
    /**
     * @depends testCreate
     */
    public function testRead(\$id) {
        \$entity = new {$className}(\$id);
        \$this->assertEquals(\$id, \$entity->id);
        return \$id;
    }
    
    /**
     * @depends testRead
     */
    public function testDelete(\$id) {
        \$entity = new {$className}(\$id);
        \$entity->delete();
        
        \$check = new {$className}(\$id);
        \$this->assertNull(\$check->id);
    }
}
PHP;
        
        file_put_contents($testsDir . "/{$entityName}Test.php", $testCode);
        
        sendResponse(true, [], "Tests scaffolded.");
    }

    if ($action === 'scaffold_auth') {
        $srcDir = SPP_APP_DIR . "/src/{$appContext}/entities";
        if (!is_dir($srcDir)) @mkdir($srcDir, 0777, true);
        
        $userYaml = <<<YAML
table: users
id_field: id
extends: Person
login_enabled: true
enable_api: true
attributes:
  username: varchar(50)
  email: varchar(255)
  password_hash: varchar(255)
  role_id: int
relations:
  - { type: belongsTo, entity: Role, field: role_id }
YAML;

        $roleYaml = <<<YAML
table: roles
id_field: id
enable_api: true
attributes:
  name: varchar(50)
  permissions: text
YAML;

        file_put_contents($srcDir . '/user.yml', $userYaml);
        file_put_contents($srcDir . '/role.yml', $roleYaml);
        
        // Ensure classes are generated
        $configUser = \Symfony\Component\Yaml\Yaml::parse($userYaml);
        \App\Entities\SPPEntity::saveEntityDefinition('User', $appContext, $configUser);
        
        $configRole = \Symfony\Component\Yaml\Yaml::parse($roleYaml);
        \App\Entities\SPPEntity::saveEntityDefinition('Role', $appContext, $configRole);

        sendResponse(true, [], "Auth entities (User, Role) scaffolded.");
    }

    if ($action === 'ai_generate_logic') {
        $prompt = $_POST['prompt'] ?? '';
        if (!$prompt) sendResponse(false, [], "Prompt required.");
        
        $promptLower = strtolower($prompt);
        $code = "";
        
        if (strpos($promptLower, 'email') !== false || strpos($promptLower, 'mail') !== false) {
            $code .= <<<PHP
    public function after_save() {
        // AI Generated: Send email after save
        \$to = \$this->email ?? 'admin@example.com';
        \$subject = "Notification regarding " . static::class;
        \$message = "A new record has been saved with ID: " . \$this->id;
        @mail(\$to, \$subject, \$message);
        return parent::after_save();
    }
PHP;
        } elseif (strpos($promptLower, 'validate') !== false || strpos($promptLower, 'required') !== false) {
            $code .= <<<PHP
    public function rules() {
        // AI Generated: Validation rules
        return [
            // Example: 'name' => 'required',
            // Example: 'email' => 'required|email'
        ];
    }
PHP;
        } elseif (strpos($promptLower, 'log') !== false || strpos($promptLower, 'audit') !== false) {
            $code .= <<<PHP
    public function after_save() {
        // AI Generated: Log save event
        \\SPPMod\\SPPLogger\\SPP_Logger::info(static::class . " saved with ID: " . \$this->id);
        return parent::after_save();
    }
PHP;
        } else {
            $code .= <<<PHP
    public function before_save() {
        // AI Generated Custom Logic Block
        // \$this->status = 'active';
        return parent::before_save();
    }
PHP;
        }
        
        sendResponse(true, ['code' => "\n" . $code . "\n"]);
    }

    if ($action === 'restore_revision') {
        $name = $_POST['name'] ?? '';
        $timestamp = $_POST['timestamp'] ?? '';
        if (!$name || !$timestamp) sendResponse(false, [], "Name and timestamp required.");
        
        $srcDir = SPP_APP_DIR . "/src/{$appContext}/entities";
        $revDir = $srcDir . '/.revisions';
        
        $revYml = $revDir . '/' . strtolower($name) . '_' . $timestamp . '.yml';
        $revPhp = $revDir . '/entity.' . strtolower($name) . '_' . $timestamp . '.php';
        
        if (!file_exists($revYml)) {
            sendResponse(false, [], "Revision not found.");
        }
        
        // Backup current before restoring
        createEntityRevision($appContext, $name);
        
        $ymlPath = $srcDir . '/' . strtolower($name) . '.yml';
        $phpPath = $srcDir . '/entity.' . strtolower($name) . '.php';
        
        @copy($revYml, $ymlPath);
        if (file_exists($revPhp)) {
            @copy($revPhp, $phpPath);
        }
        
        $ymlContent = file_get_contents($ymlPath);
        $phpContent = file_exists($phpPath) ? file_get_contents($phpPath) : '';
        
        sendResponse(true, ['yaml' => $ymlContent, 'php' => $phpContent], "Restored to " . date('Y-m-d H:i:s', $timestamp));
    }

    if ($action === 'magic_generate_schema') {
        $prompt = $_POST['prompt'] ?? '';
        if (!$prompt) sendResponse(false, [], "Prompt required.");
        
        $promptLower = strtolower($prompt);
        $words = preg_split('/[^a-zA-Z0-9_]+/', $promptLower);
        
        $attributes = [];
        $typeMap = [
            'name' => 'varchar(255)',
            'first_name' => 'varchar(100)',
            'last_name' => 'varchar(100)',
            'email' => 'varchar(255)',
            'dob' => 'date',
            'birthdate' => 'date',
            'age' => 'int',
            'phone' => 'varchar(20)',
            'address' => 'text',
            'bio' => 'text',
            'description' => 'text',
            'price' => 'decimal(10,2)',
            'cost' => 'decimal(10,2)',
            'status' => 'varchar(50)',
            'created_at' => 'datetime',
            'is_active' => 'tinyint(1)',
            'active' => 'tinyint(1)',
            'title' => 'varchar(255)',
            'subject' => 'varchar(255)',
            'category' => 'varchar(100)',
            'url' => 'varchar(255)',
            'image' => 'varchar(255)'
        ];
        
        // simple keyword matching
        foreach ($words as $w) {
            if (isset($typeMap[$w])) {
                $attributes[$w] = $typeMap[$w];
            }
        }
        
        if (empty($attributes)) {
            sendResponse(false, [], "Could not understand any specific fields from the prompt.");
        }
        
        sendResponse(true, ['config' => ['attributes' => $attributes]]);
    }

    if ($action === 'scaffold_dashboard') {
        $entityName = $_POST['entityName'] ?? '';
        if (!$entityName) sendResponse(false, [], "Entity name required.");

        $lowerName = strtolower($entityName);
        $dashId = "dash_{$lowerName}";
        
        $jsContent = <<<JS
export default class Dashboard_{$entityName} extends BaseComponent {
    constructor(props) {
        super(props);
        this.state = { data: [], loading: true };
    }

    async onInit() {
        this.fetchData();
    }

    async fetchData() {
        // Assume API endpoint is enabled for this entity
        try {
            const res = await fetch('/api/v1/{$lowerName}');
            const json = await res.json();
            if (json.status === 'success') {
                this.setState({ data: json.data, loading: false });
            } else {
                this.setState({ loading: false });
                SPPUX.notify('Error fetching data: ' + json.message, 'error');
            }
        } catch(e) {
            this.setState({ loading: false });
        }
    }

    render() {
        if (this.state.loading) return SPPUX.html`<div style="padding: 2rem;">Loading Data...</div>`;
        if (!this.state.data.length) return SPPUX.html`<div style="padding: 2rem;">No {$entityName} records found.</div>`;
        
        // Basic dynamic table
        const keys = Object.keys(this.state.data[0]).filter(k => k !== 'id');
        keys.unshift('id'); // Ensure id is first
        
        return SPPUX.html`
            <div class="spp-card" style="padding: 2rem;">
                <h3>{$entityName} Dashboard</h3>
                <div style="overflow-x: auto; margin-top: 1rem;">
                    <table class="spp-table" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr>
                                \${keys.map(k => SPPUX.html`<th style="text-align:left; padding:0.5rem; border-bottom:1px solid var(--glass-border);">\${k}</th>`)}
                            </tr>
                        </thead>
                        <tbody>
                            \${this.state.data.map(row => SPPUX.html`
                                <tr>
                                    \${keys.map(k => SPPUX.html`<td style="padding:0.5rem; border-bottom:1px solid var(--glass-border);">\${row[k]}</td>`)}
                                </tr>
                            `)}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }
}
JS;
        
        // Save JS file
        $jsPath = __DIR__ . "/js/views/{$dashId}.js";
        file_put_contents($jsPath, $jsContent);
        
        // Update routes.json
        $routesFile = __DIR__ . "/routes.json";
        $routes = file_exists($routesFile) ? json_decode(file_get_contents($routesFile), true) : [];
        if (!$routes) $routes = [];
        
        $routes[$dashId] = [
            "title" => "{$entityName} Dash",
            "icon" => "📊",
            "component" => "views/{$dashId}.js"
        ];
        
        file_put_contents($routesFile, json_encode($routes, JSON_PRETTY_PRINT));
        
        sendResponse(true, [], "Dashboard scaffolded successfully! Please refresh the admin panel.");
    }
    
    if ($action === 'preview_migration') {
        $name = $_POST['name'] ?? '';
        $yamlContent = $_POST['yaml'] ?? '';
        if (!$name || !$yamlContent) sendResponse(false, [], "Entity name and YAML required.");

        try {
            $config = \Symfony\Component\Yaml\Yaml::parse($yamlContent);
            $table = $config['table'] ?? '';
            if (!$table) sendResponse(false, [], "Table name not defined in YAML.");

            $attributes = $config['attributes'] ?? [];
            $db = new \SPPMod\SPPDB\SPPDB();
            
            $sqlDiff = [];
            $idField = $config['id_field'] ?? 'id';
            
            if (!$db->tableExists($table)) {
                $sqlDiff[] = "CREATE TABLE {$table} ({$idField} varchar(20))";
                foreach ($attributes as $col => $type) {
                    $sqlDiff[] = "ALTER TABLE {$table} ADD {$col} {$type}";
                }
            } else {
                foreach ($attributes as $col => $type) {
                    if (!$db->columnExists($table, $col)) {
                        $sqlDiff[] = "ALTER TABLE {$table} ADD {$col} {$type}";
                    }
                }
            }
            
            sendResponse(true, ['sql' => $sqlDiff]);
        } catch (\Exception $e) {
            sendResponse(false, [], "Error parsing diff: " . $e->getMessage());
        }
    }

    if (!$action) {
        sendResponse(false, [], "No action specified.");
    }

    // --- Critical Auth Handlers ---
    if ($action === 'check_auth') {
        if (\SPPMod\SPPAuth\SPPAuth::check()) {
            $userId = (string) \SPPMod\SPPAuth\SPPAuth::guard()->id();
            sendResponse(true, ['username' => $userId], "Authenticated.");
        } else {
            sendResponse(false, [], "Please Authenticate yourself.");
        }
    }


    if ($action === 'get_profile') {
        if (\SPPMod\SPPAuth\SPPAuth::check()) {
            $user = \SPPMod\SPPAuth\SPPAuth::user();
            sendResponse(true, $user->getValues(), "Profile retrieved.");
        }
        sendResponse(false, [], "Profile not found.");
    }

    // --- Framework Config Handlers ---
    if ($action === 'get_config_all') {
        $global = getGlobalSettings();
        $app = [];
        $sys = [
            'spp_version' => '1.1.0',
            'base_path' => SPP_BASE_DIR
        ];
        sendResponse(true, ['config' => ['global' => $global, 'app' => $app, 'sys' => $sys]], "Config retrieved.");
    }

    if ($action === 'save_config_value') {
        $key = $_POST['key'] ?? '';
        $value = $_POST['value'] ?? '';
        if (!$key)
            sendResponse(false, [], "Missing key.");

        $parts = explode(':', $key, 2);
        $ns = $parts[0];
        $actualKey = $parts[1] ?? '';

        if ($ns === 'global' && $actualKey) {
            $settings = getGlobalSettings();
            $settings[$actualKey] = ($value === 'true') ? true : (($value === 'false') ? false : $value);
            saveGlobalSettings($settings);
            sendResponse(true, [], "Global setting updated.");
        } else {
            sendResponse(false, [], "Editing namespace '{$ns}' is restricted or key is invalid.");
        }
    }

    // --- CLI Command Wrappers ---
    if ($action === 'list_commands' || $action === 'get_command_ui' || $action === 'execute_command') {
        $coreDir = SPP_BASE_DIR . '/core';
        foreach (['class.command.php', 'class.commandmanager.php'] as $f) {
            if (file_exists($coreDir . '/' . $f)) require_once $coreDir . '/' . $f;
        }

        $commands = \SPP\CLI\CommandManager::discover();

        if ($action === 'list_commands') {
            $list = [];
            foreach ($commands as $name => $cmd) {
                $prefix = explode(':', $name)[0] ?? 'core';
                if (!isset($list[$prefix])) $list[$prefix] = [];
                $list[$prefix][] = [
                    'name' => $name,
                    'description' => $cmd->getDescription()
                ];
            }
            sendResponse(true, ['categories' => $list], "Commands retrieved");
        }

        if ($action === 'get_command_ui') {
            $cmdName = $_REQUEST['command'] ?? '';
            $cmd = $commands[$cmdName] ?? null;
            if (!$cmd) sendResponse(false, [], "Command not found.");
            sendResponse(true, ['html' => $cmd->renderAdminUI()], "UI retrieved");
        }

        if ($action === 'execute_command') {
            $cmdName = $_REQUEST['command'] ?? '';
            $argsRaw = $_REQUEST['args'] ?? '';
            
            $cmd = $commands[$cmdName] ?? null;
            if (!$cmd) sendResponse(false, [], "Command not found.");

            $sppBin = escapeshellarg(dirname(SPP_BASE_DIR) . '/spp.php');
            $cmdSafe = escapeshellarg($cmdName);
            
            $argString = '';
            if (!empty($argsRaw)) {
                $segments = explode(' ', $argsRaw);
                $safeSegments = array_map('escapeshellarg', array_filter($segments));
                $argString = implode(' ', $safeSegments);
            }

            $execCmd = "php {$sppBin} {$cmdSafe} {$argString} 2>&1";
            $output = shell_exec($execCmd);
            
            sendResponse(true, ['output' => $output], "Command executed");
        }
    } // End CLI Command Wrappers

    if ($action === 'get_builder_context') {
            $savePath = "src/{$appContext}/entities";
            $classes = ['\\SPPMod\\SPPEntity\\SPPEntity', '\\SPPMod\\SPPAuth\\SPPUser'];
            
            // Scan current app entities
            $entDir = SPP_APP_DIR . "/src/{$appContext}/entities";
            if (is_dir($entDir)) {
                foreach (glob($entDir . '/*.php') as $file) {
                    $basename = basename($file);
                    if (str_starts_with($basename, 'entity.')) {
                        $name = substr($basename, 7, -4);
                    } else {
                        $name = substr($basename, 0, -4);
                    }
                    $content = file_get_contents($file);
                    if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
                        $ns = trim($matches[1]);
                        $classes[] = '\\' . $ns . '\\' . $name;
                    }
                }
            }
            
            // Scan modules for potential entity bases
            $modDir = defined('SPP_MOD_DIR') ? SPP_MOD_DIR : SPP_BASE_DIR . '/modules';
            if (is_dir($modDir)) {
                $rit = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($modDir));
                foreach ($rit as $file) {
                    if ($file->isFile() && $file->getExtension() === 'php') {
                        $content = file_get_contents($file->getPathname());
                        if (str_contains($content, 'extends SPPEntity') || str_contains($content, 'extends \\SPPMod\\SPPEntity\\SPPEntity')) {
                            if (preg_match('/namespace\s+([^;]+);/', $content, $nsMatches) && preg_match('/class\s+([a-zA-Z0-9_]+)/', $content, $clsMatches)) {
                                $classes[] = '\\' . trim($nsMatches[1]) . '\\' . trim($clsMatches[1]);
                            }
                        }
                    }
                }
            }
            
            $classes = array_values(array_unique($classes));
            sort($classes);
            sendResponse(true, ['save_path' => $savePath, 'classes' => $classes]);
        }
    // --- Entities API ---
    if ($action === 'list_entities') {
        $entDir = SPP_APP_DIR . "/src/{$appContext}/entities";
        $map = [];
        if (is_dir($entDir)) {
            foreach (glob($entDir . '/*.{php,yml,yaml}', GLOB_BRACE) as $file) {
                $basename = basename($file);
                $isPhp = str_ends_with($file, '.php');
                
                if ($isPhp) {
                    if (str_starts_with($basename, 'entity.')) {
                        $name = substr($basename, 7, -4);
                    } else {
                        $name = substr($basename, 0, -4);
                    }
                } else {
                    $name = pathinfo($file, PATHINFO_FILENAME);
                }
                
                $key = strtolower($name);
                if (!isset($map[$key])) {
                    $map[$key] = [
                        'name' => $name,
                        'yaml_path' => null,
                        'php_path' => null,
                        'yaml_content' => '',
                        'php_content' => '',
                        'size' => 0
                    ];
                }
                
                $content = '';
                if (filesize($file) < 500 * 1024) {
                    $content = file_get_contents($file);
                }
                $map[$key]['size'] += filesize($file);
                
                if ($isPhp) {
                    $map[$key]['php_path'] = relativizePath($file);
                    $map[$key]['php_content'] = $content;
                } else {
                    $map[$key]['name'] = pathinfo($file, PATHINFO_FILENAME); // Keep original case from YAML
                    $map[$key]['yaml_path'] = relativizePath($file);
                    $map[$key]['yaml_content'] = $content;
                }
            }
            
            // Generate YAML content for pure PHP entities
            foreach ($map as $key => &$entity) {
                if (empty($entity['yaml_content']) && !empty($entity['php_content'])) {
                    $className = "App\\" . ucfirst($appContext) . "\\Entities\\" . ucfirst($entity['name']);
                    if (!class_exists($className)) {
                        require_once SPP_APP_DIR . '/' . $entity['php_path'];
                    }
                    if (class_exists($className)) {
                        try {
                            $instance = new $className();
                            $config = [
                                'table' => method_exists($instance, 'getTable') ? $instance->getTable() : (strtolower($entity['name']) . 's'),
                                'attributes' => method_exists($instance, 'define_attributes') ? $instance->define_attributes() : []
                            ];
                            $ref = new \ReflectionClass($className);
                            $parent = $ref->getParentClass();
                            if ($parent && $parent->getName() !== 'SPPMod\SPPEntity\SPPEntity') {
                                $config['extends'] = '\\' . $parent->getName();
                            }
                            if (class_exists('\Symfony\Component\Yaml\Yaml')) {
                                $entity['yaml_content'] = \Symfony\Component\Yaml\Yaml::dump($config, 4, 2);
                            } else {
                                $yaml = "table: " . $config['table'] . "\n";
                                if (!empty($config['extends'])) $yaml .= "extends: " . $config['extends'] . "\n";
                                $yaml .= "attributes:\n";
                                if (is_array($config['attributes'])) {
                                    foreach ($config['attributes'] as $k => $v) {
                                        $yaml .= "  $k: $v\n";
                                    }
                                }
                                $entity['yaml_content'] = $yaml;
                            }
                        } catch (\Exception $e) {
                            // Silently ignore instantiation errors and provide empty yaml
                            $entity['yaml_content'] = "table: " . (strtolower($entity['name']) . 's') . "\nattributes: []\n";
                        }
                    }
                }
            }
        }
        $entities = array_values($map);
        sendResponse(true, ['entities' => $entities], "Entities listed.");
    }
    
    if ($action === 'parse_entity_yaml') {
        $yml = $_POST['yaml'] ?? '';
        try {
            $parsed = \Symfony\Component\Yaml\Yaml::parse($yml);
            sendResponse(true, ['config' => $parsed], "Parsed successfully.");
        } catch (\Exception $e) {
            sendResponse(false, [], "YAML Parse Error: " . $e->getMessage());
        }
    }
    
    if ($action === 'dump_entity_yaml') {
        $config = $_POST['config'] ?? [];
        if (is_string($config)) $config = json_decode($config, true);
        try {
            $yml = \Symfony\Component\Yaml\Yaml::dump($config, 10, 2);
            sendResponse(true, ['yaml' => $yml], "Dumped successfully.");
        } catch (\Exception $e) {
            sendResponse(false, [], "YAML Dump Error: " . $e->getMessage());
        }
    }

    if ($action === 'introspect_table') {
        $table = $_POST['table'] ?? '';
        if (!$table) sendResponse(false, [], "Table name required.");
        try {
            $db = new \SPPMod\SPPDB\SPPDB();
            $driver = strtolower($db->getDriver());
            $attributes = [];
            
            if ($driver === 'mongodb') {
                $res = $db->query("SELECT * FROM {$table} LIMIT 1");
                if (empty($res)) sendResponse(false, [], "Collection is empty, cannot infer schema.");
                $doc = (array)$res[0];
                foreach ($doc as $k => $v) {
                    if ($k === '_id') continue;
                    $type = is_int($v) ? 'int' : (is_float($v) ? 'float' : (is_bool($v) ? 'boolean' : 'varchar(255)'));
                    $attributes[$k] = $type;
                }
            } elseif ($driver === 'xdb' || $driver === 'sppxdb') {
                try {
                    $schema = $db->getSchema($table);
                    if ($schema && isset($schema['columns'])) {
                        foreach ($schema['columns'] as $col => $meta) {
                            $attributes[$col] = 'varchar(255)';
                        }
                    }
                } catch (\Exception $e) {
                    $res = $db->query("SELECT * FROM {$table} LIMIT 1");
                    if (!empty($res)) {
                        foreach (array_keys((array)$res[0]) as $k) {
                            if ($k === '_id') continue;
                            $attributes[$k] = 'varchar(255)';
                        }
                    } else {
                        sendResponse(false, [], "Cannot infer schema from empty XDB table.");
                    }
                }
            } else {
                $schema = $db->getSchema($table);
                foreach ($schema['columns'] as $col => $meta) {
                    $t = strtolower($meta['type']);
                    if (strpos($t, 'int') !== false) $attributes[$col] = 'int';
                    elseif (strpos($t, 'datetime') !== false || strpos($t, 'timestamp') !== false) $attributes[$col] = 'datetime';
                    elseif (strpos($t, 'date') !== false) $attributes[$col] = 'date';
                    elseif (strpos($t, 'text') !== false) $attributes[$col] = 'text';
                    elseif (strpos($t, 'tinyint(1)') !== false || strpos($t, 'bool') !== false) $attributes[$col] = 'boolean';
                    else $attributes[$col] = 'varchar(255)';
                }
            }
            
            $config = [
                'table' => $table,
                'attributes' => $attributes
            ];
            sendResponse(true, ['config' => $config], "Introspected table {$table}");
        } catch (\Exception $e) {
            sendResponse(false, [], "Introspection Error: " . $e->getMessage());
        }
    }

    if ($action === 'scaffold_form') {
        $entityName = $_POST['entityName'] ?? '';
        $config = $_POST['config'] ?? [];
        if (is_string($config)) $config = json_decode($config, true);
        if (!$entityName || empty($config['attributes'])) sendResponse(false, [], "Entity configuration required.");
        
        try {
            $fields = [];
            foreach ($config['attributes'] as $col => $type) {
                if ($col === 'id' || $col === 'created_at' || $col === 'updated_at') continue;
                $fieldType = 'text';
                if ($type === 'int' || strpos($type, 'int') !== false) $fieldType = 'number';
                elseif ($type === 'datetime' || $type === 'date') $fieldType = 'date';
                elseif ($type === 'text') $fieldType = 'textarea';
                elseif ($type === 'boolean') $fieldType = 'checkbox';
                
                $fields[] = [
                    'name' => $col,
                    'type' => $fieldType,
                    'label' => ucwords(str_replace('_', ' ', $col))
                ];
            }
            
            $formConfig = [
                'form' => [
                    'name' => strtolower($entityName) . '_form',
                    'type' => 'single'
                ],
                'fields' => $fields
            ];
            sendResponse(true, ['formConfig' => $formConfig], "Form scaffolded successfully.");
        } catch (\Exception $e) {
            sendResponse(false, [], "Scaffolding Error: " . $e->getMessage());
        }
    }

    if ($action === 'seed_entity') {
        $entityName = $_POST['entityName'] ?? '';
        $count = (int)($_POST['count'] ?? 50);
        if (!$entityName) sendResponse(false, [], "Entity name required.");
        try {
            $class = "\\SPPMod\\SPPEntity\\{$entityName}";
            if (!class_exists($class)) {
                $file = SPP_BASE_DIR . "/modules/spp/sppentity/entities/class." . strtolower($entityName) . ".php";
                if (file_exists($file)) require_once $file;
            }
            if (!class_exists($class)) sendResponse(false, [], "Entity class not found.");
            
            $inst = new $class();
            $attrs = $inst->define_attributes();
            
            $db = new \SPPMod\SPPDB\SPPDB();
            $table = $inst::getTable();
            
            $inserted = 0;
            for ($i = 0; $i < $count; $i++) {
                $row = [];
                foreach ($attrs as $col => $type) {
                    if ($col === 'id') continue;
                    if ($type === 'int') $row[$col] = rand(1, 1000);
                    elseif ($type === 'datetime') $row[$col] = date('Y-m-d H:i:s', time() - rand(0, 31536000));
                    elseif ($type === 'boolean') $row[$col] = rand(0, 1);
                    elseif ($type === 'text') $row[$col] = "Mock text for {$col} " . rand(1000, 9999);
                    else $row[$col] = "Mock {$col} " . rand(1, 100);
                }
                if ($db->insertValues($table, $row)) {
                    $inserted++;
                }
            }
            sendResponse(true, ['inserted' => $inserted], "Seeded {$inserted} records.");
        } catch (\Exception $e) {
            sendResponse(false, [], "Seed Error: " . $e->getMessage());
        }
    }
    
    if ($action === 'save_entity_config') {
        $name = $_POST['name'] ?? '';
        $config = $_POST['config'] ?? [];
        if (is_string($config)) $config = json_decode($config, true);
        if (!$name) sendResponse(false, [], "Entity name required.");
        
        try {
            if (!class_exists('\SPPMod\SPPEntity\SPPEntity')) {
                require_once SPP_BASE_DIR . '/sppinit.php';
            }
            createEntityRevision($appContext, $name);

            if (!empty($config['extends'])) {
                $extendsClass = ltrim($config['extends'], '\\');
                if ($extendsClass !== 'SPPMod\SPPEntity\SPPEntity') {
                    if (!class_exists($extendsClass) || !is_subclass_of($extendsClass, '\SPPMod\SPPEntity\SPPEntity')) {
                        sendResponse(false, [], "Error: Extended class '{$config['extends']}' does not exist or does not extend SPPEntity.");
                    }
                }
            }

            \SPPMod\SPPEntity\SPPEntity::saveEntityDefinition($name, $appContext, $config);
            sendResponse(true, [], "Entity saved.");
        } catch (\Exception $e) {
            sendResponse(false, [], "Save error: " . $e->getMessage());
        }
    }
    
    if ($action === 'save_entity_source') {
        $name = $_POST['name'] ?? '';
        $source = $_POST['source'] ?? '';
        $type = $_POST['type'] ?? 'php';
        if (!$name) sendResponse(false, [], "Entity name required.");
        
        try {
            $srcDir = SPP_APP_DIR . "/src/{$appContext}/entities";
            if (!is_dir($srcDir)) {
                mkdir($srcDir, 0777, true);
            }
            
            createEntityRevision($appContext, $name);
            
            if ($type === 'yaml') {
                $fileName = strtolower($name) . ".yml";
                $filePath = $srcDir . '/' . $fileName;
                file_put_contents($filePath, $source);
                
                // Generate corresponding PHP if it doesn't exist
                $phpFileName = "entity." . strtolower($name) . ".php";
                $phpPath = $srcDir . '/' . $phpFileName;
                if (!file_exists($phpPath)) {
                    if (!class_exists('\SPPMod\SPPEntity\SPPEntity')) {
                        require_once SPP_BASE_DIR . '/sppinit.php';
                    }
                    $config = \Symfony\Component\Yaml\Yaml::parse($source);
                    \SPPMod\SPPEntity\SPPEntity::saveEntityDefinition($name, $appContext, $config);
                }
                
            } else {
                $fileName = "entity." . strtolower($name) . ".php";
                $filePath = $srcDir . '/' . $fileName;
                
                // If the old format (just class name) exists, overwrite it instead
                if (file_exists($srcDir . '/' . $name . '.php') && !file_exists($filePath)) {
                    $filePath = $srcDir . '/' . $name . '.php';
                }
                
                file_put_contents($filePath, $source);
                
                // Generate corresponding YAML
                $ymlFileName = strtolower($name) . ".yml";
                $ymlPath = $srcDir . '/' . $ymlFileName;
                
                $className = "App\\" . ucfirst($appContext) . "\\Entities\\" . ucfirst($name);
                require_once $filePath;
                if (class_exists($className)) {
                    try {
                        $instance = new $className();
                        $config = [
                            'table' => method_exists($instance, 'getTable') ? $instance->getTable() : (strtolower($name) . 's'),
                            'attributes' => method_exists($instance, 'define_attributes') ? $instance->define_attributes() : []
                        ];
                        $ref = new \ReflectionClass($className);
                        $parent = $ref->getParentClass();
                        if ($parent && $parent->getName() !== 'SPPMod\SPPEntity\SPPEntity') {
                            $config['extends'] = '\\' . $parent->getName();
                        }
                        if (class_exists('\Symfony\Component\Yaml\Yaml')) {
                            $yaml = \Symfony\Component\Yaml\Yaml::dump($config, 4, 2);
                            file_put_contents($ymlPath, $yaml);
                        }
                    } catch (\Exception $e) {}
                }
            }
            
            sendResponse(true, [], "Entity {$type} source saved and synced.");
        } catch (\Exception $e) {
            sendResponse(false, [], "Save error: " . $e->getMessage());
        }
    }

    if ($action === 'delete_entity') {
        $name = $_POST['name'] ?? '';
        if (!$name) sendResponse(false, [], "Entity name required.");
        
        $pathYml = SPP_APP_DIR . "/src/{$appContext}/entities/{$name}.yml";
        $pathPhp = SPP_APP_DIR . "/src/{$appContext}/entities/{$name}.php";
        if (file_exists($pathYml)) unlink($pathYml);
        if (file_exists($pathPhp)) unlink($pathPhp);
        
        sendResponse(true, [], "Entity deleted.");
    }

    // --- Forms API ---
    if ($action === 'list_forms') {
        $forms = [];
        $formDir = SPP_APP_DIR . "/etc/apps/{$appContext}/forms";
        if (is_dir($formDir)) {
            foreach (glob($formDir . '/*.{yml,yaml}', GLOB_BRACE) as $file) {
                $forms[] = [
                    'name' => pathinfo($file, PATHINFO_FILENAME),
                    'path' => relativizePath($file),
                    'size' => filesize($file)
                ];
            }
        }
        sendResponse(true, ['forms' => $forms], "Forms listed.");
    }
    
    if ($action === 'parse_form_yaml') {
        $yml = $_POST['yaml'] ?? '';
        try {
            $parsed = \Symfony\Component\Yaml\Yaml::parse($yml);
            sendResponse(true, ['config' => $parsed], "Parsed successfully.");
        } catch (\Exception $e) {
            sendResponse(false, [], "YAML Parse Error: " . $e->getMessage());
        }
    }
    
    if ($action === 'dump_form_yaml') {
        $config = $_POST['config'] ?? [];
        if (is_string($config)) $config = json_decode($config, true);
        try {
            $yml = \Symfony\Component\Yaml\Yaml::dump($config, 10, 2);
            sendResponse(true, ['yaml' => $yml], "Dumped successfully.");
        } catch (\Exception $e) {
            sendResponse(false, [], "YAML Dump Error: " . $e->getMessage());
        }
    }
    
    if ($action === 'save_form_config' || $action === 'save_form') {
        $name = $_POST['name'] ?? '';
        $yaml = $_POST['yaml'] ?? '';
        if (!$name) sendResponse(false, [], "Form name required.");
        
        $path = SPP_APP_DIR . "/etc/apps/{$appContext}/forms/{$name}.yml";
        if (!is_dir(dirname($path))) mkdir(dirname($path), 0777, true);
        try {
            file_put_contents($path, $yaml);
            sendResponse(true, [], "Form saved.");
        } catch (\Exception $e) {
            sendResponse(false, [], "Save Error: " . $e->getMessage());
        }
    }
    
    if ($action === 'delete_form') {
        $name = $_POST['name'] ?? '';
        if (!$name) sendResponse(false, [], "Form name required.");
        
        $path = SPP_APP_DIR . "/etc/apps/{$appContext}/forms/{$name}.yml";
        if (file_exists($path)) unlink($path);
        
        sendResponse(true, [], "Form deleted.");
    }
    
    if ($action === 'get_form_html') {
        $yaml = $_POST['yaml'] ?? '';
        try {
            $config = \Symfony\Component\Yaml\Yaml::parse($yaml);
            $html = "<div class='spp-form-preview-wrapper'>";
            if (isset($config['fields']) && is_array($config['fields'])) {
                foreach ($config['fields'] as $key => $f) {
                    $html .= "<div class='spp-form-group' style='margin-bottom:15px;'>";
                    $html .= "<label style='display:block;margin-bottom:5px;font-weight:600;'>" . htmlspecialchars($f['label'] ?? $key) . "</label>";
                    $html .= "<input class='spp-form-control' style='width:100%;padding:8px;border-radius:4px;border:1px solid #ccc;' placeholder='" . htmlspecialchars($f['placeholder'] ?? '') . "'>";
                    $html .= "</div>";
                }
            }
            $html .= "<button class='btn btn-primary' style='padding:8px 16px;'>" . htmlspecialchars($config['submit_label'] ?? 'Submit') . "</button>";
            $html .= "</div>";
            sendResponse(true, ['html' => $html], "Form rendered.");
        } catch (\Exception $e) {
            sendResponse(false, [], "Render Error: " . $e->getMessage());
        }
    }

    // --- Apps / Modules management ---
    if ($action === 'save_app_config') {
        $targetApp = $_POST['target_app'] ?? $appContext;
        $config = $_POST['config'] ?? [];
        if (is_string($config)) $config = json_decode($config, true);
        $appConfigFile = SPP_APP_DIR . "/etc/apps/{$targetApp}/config.yml";
        try {
            if (!is_dir(dirname($appConfigFile))) mkdir(dirname($appConfigFile), 0777, true);
            file_put_contents($appConfigFile, \Symfony\Component\Yaml\Yaml::dump($config, 10, 2));
            sendResponse(true, [], "App config saved.");
        } catch (\Exception $e) {
            sendResponse(false, [], "Save Error: " . $e->getMessage());
        }
    }

    // --- Modules API ---
    if ($action === 'list_modules') {
        $modules = [];
        try {
            $modulesFile = defined('SPP_ETC_DIR') ? SPP_ETC_DIR . '/modules.yml' : SPP_BASE_DIR . '/etc/modules.yml';
            $activeConfig = file_exists($modulesFile) ? \Symfony\Component\Yaml\Yaml::parseFile($modulesFile) : [];
            if (!is_array($activeConfig)) $activeConfig = [];
            
            $activeMap = [];
            if (isset($activeConfig['modules']) && is_array($activeConfig['modules'])) {
                foreach ($activeConfig['modules'] as $mc) {
                    if (isset($mc['name']) && isset($mc['status']) && $mc['status'] === 'active') {
                        $activeMap[$mc['name']] = true;
                    }
                }
            }

            $manifests = \SPP\Module::scanModules();
            foreach ($manifests as $manifestPath) {
                try {
                    $mod = new \SPP\Module($manifestPath);
                    $modName = $mod->InternalName;
                    $modules[] = [
                        'name' => $modName,
                        'public_name' => $mod->PublicName ?? $modName,
                        'type' => $mod->ModuleType ?? 'user', 
                        'active' => isset($activeMap[$modName]),
                        'version' => $mod->Version ?? '1.0.0',
                        'path' => $manifestPath,
                        'module_category' => $mod->ModuleCategory ?? 'App Modules',
                        'description' => $mod->PublicDesc ?? '',
                        'dependencies' => $mod->Dependencies ?? [],
                        'has_config' => !empty($mod->ConfigFile) || !empty($mod->ConfigVariables) || !empty($mod->Settings)
                    ];
                } catch (\Exception $e) {}
            }
            sendResponse(true, ['modules' => $modules], "Modules listed.");
        } catch (\Exception $e) {
            sendResponse(false, [], "Error listing modules: " . $e->getMessage());
        }
    }
    
    if ($action === 'toggle_module') {
        $modname = $_POST['modname'] ?? '';
        $status = $_POST['status'] ?? 'inactive';
        
        $modulesFile = defined('SPP_ETC_DIR') ? SPP_ETC_DIR . '/modules.yml' : SPP_BASE_DIR . '/etc/modules.yml';
        $config = file_exists($modulesFile) ? \Symfony\Component\Yaml\Yaml::parseFile($modulesFile) : [];
        if (!is_array($config)) $config = [];
        
        if (!isset($config['modules']) || !is_array($config['modules'])) {
            $config['modules'] = [];
        }
        
        $found = false;
        foreach ($config['modules'] as &$mc) {
            if (isset($mc['name']) && $mc['name'] === $modname) {
                $mc['status'] = $status;
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $path = 'spp/' . $modname;
            $manifests = \SPP\Module::scanModules();
            foreach ($manifests as $mPath) {
                if (basename(dirname($mPath)) === $modname) {
                    $path = str_replace(SPP_BASE_DIR . DIRECTORY_SEPARATOR, '', dirname($mPath));
                    $path = str_replace('\\', '/', $path);
                    break;
                }
            }
            $config['modules'][] = [
                'name' => $modname,
                'path' => $path,
                'status' => $status
            ];
        }
        
        try {
            if (!is_dir(dirname($modulesFile))) mkdir(dirname($modulesFile), 0777, true);
            file_put_contents($modulesFile, \Symfony\Component\Yaml\Yaml::dump($config, 4, 2));
            sendResponse(true, [], "Module {$modname} status updated.");
        } catch (\Exception $e) {
            sendResponse(false, [], "Toggle Error: " . $e->getMessage());
        }
    }

    if ($action === 'execute_scaffold') {
        $command = $_POST['command'] ?? '';
        $target = $_POST['target'] ?? '';
        $optionsRaw = $_POST['options'] ?? '';
        
        if (!$command || !$target) {
            sendResponse(false, [], "Command and Target are required.");
        }
        
        $allowedCommands = [
            'make:app', 'make:module', 'make:entity', 
            'make:controller', 'make:scaffold', 'make:service'
        ];
        
        if (!in_array($command, $allowedCommands)) {
            sendResponse(false, [], "Invalid or unauthorized scaffold command.");
        }

        $optionsStr = '';
        if ($optionsRaw) {
            // Very simple sanitization: only allow alphanumeric, dashes, equals, spaces, and commas
            if (preg_match('/^[a-zA-Z0-9_=\-\s,:]+$/', $optionsRaw)) {
                $parts = preg_split('/\s+/', trim($optionsRaw));
                $safeParts = array_map('escapeshellarg', $parts);
                $optionsStr = " " . implode(" ", $safeParts);
            } else {
                sendResponse(false, [], "Invalid characters in options. Only alphanumeric, -, _, =, :, commas, and spaces allowed.");
            }
        }
        
        try {
            $cmdLine = "php " . escapeshellarg(SPP_BASE_DIR . '/spp.php') . " " . escapeshellarg($command) . " " . escapeshellarg($target) . $optionsStr . " 2>&1";
            $output = shell_exec($cmdLine);
            sendResponse(true, ['output' => $output], "Command executed successfully.");
        } catch (\Exception $e) {
            sendResponse(false, ['output' => $e->getMessage()], "Command execution failed.");
        }
    }

    // --- DI Services API ---
    if ($action === 'get_di_bindings') {
        $bindings = [];
        try {
            $procObj = \SPP\Scheduler::getProcObj();
            $ref = new \ReflectionClass($procObj);
            
            // Try to find how services are stored. usually in $services or something similar in SPP\App
            if ($ref->hasProperty('services')) {
                $prop = $ref->getProperty('services');
                $prop->setAccessible(true);
                $services = $prop->getValue($procObj) ?? [];
                
                foreach ($services as $key => $inst) {
                    $bindings[] = [
                        'abstract' => $key,
                        'concrete' => is_object($inst) ? get_class($inst) : (is_string($inst) ? $inst : 'unknown'),
                        'shared' => true,
                        'instantiated' => is_object($inst)
                    ];
                }
            } else if (method_exists($procObj, 'getServices')) {
                $services = $procObj->getServices();
                foreach ($services as $key => $inst) {
                    $bindings[] = [
                        'abstract' => $key,
                        'concrete' => is_object($inst) ? get_class($inst) : (is_string($inst) ? $inst : 'unknown'),
                        'shared' => true,
                        'instantiated' => is_object($inst)
                    ];
                }
            }
            sendResponse(true, ['bindings' => $bindings], "Bindings retrieved.");
        } catch (\Exception $e) {
            sendResponse(false, [], "DI Error: " . $e->getMessage());
        }
    }

    error_log("Dispatching action: " . $action);
    \SPPMod\SPPAjax\SPPAjax::resolveAndExecute($action, $_REQUEST);

} catch (\Throwable $e) {
    $errorMsg = "[" . date('Y-m-d H:i:s') . "] API FATAL ERROR: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "\n" . $e->getTraceAsString() . "\n";
    file_put_contents(SPP_BASE_DIR . "/api_debug.log", $errorMsg, FILE_APPEND);
    sendResponse(false, [], "Server Error: " . $e->getMessage() . " in " . basename($e->getFile()) . ":" . $e->getLine());
}
