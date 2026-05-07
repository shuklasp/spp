<?php

namespace SPPMod\SPPAjax;

use Symfony\Component\Yaml\Yaml;

/**
 * class SPPAjax
 *
 * SPA (Single Page Application) dispatch engine for the SPP framework.
 *
 * Handles two request tracks:
 *  - Page fragments: resolves a page via Pages::getPage(), captures its HTML,
 *    and returns a JSON envelope { status, html, title }.
 *  - Services: resolves a named service from the registry, includes the
 *    service script from /src/serv/, and returns the $response array as JSON.
 *
 * All SPA requests are identified by the presence of ?__spa=1 or the
 * X-SPP-Ajax: 1 HTTP header.
 *
 * Entry point: SPPAjax::handle() — call this in index.php before showPage().
 *
 * @author Satya Prakash Shukla
 */
class SPPAjax extends \SPP\SPPObject
{
    /** @var array<string,mixed>|null Parsed services.yml cache */
    private static ?array $serviceRegistry = null;

    // -------------------------------------------------------------------------
    // Public entry points
    // -------------------------------------------------------------------------

    /**
     * Main entry point. Called from index.php when isAjaxRequest() is true.
     * Routes to page dispatch or service dispatch based on GET parameters.
     */
    public static function handle(): void
    {
        if (!self::isSpaEnabled()) {
            self::respond('error', ['message' => 'SPA mode is disabled.'], 503);
        }

        // Component Action: ?__svc=component_action
        if (isset($_GET['__svc']) && $_GET['__svc'] === 'component_action') {
            self::dispatchComponentAction();
            return;
        }

        // Service call: ?__svc=service_name
        if (isset($_GET['__svc'])) {
            self::dispatchService(trim($_GET['__svc']));
            return;
        }

        // Component JS: ?__js_comp=ComponentName
        if (isset($_GET['__js_comp'])) {
            self::dispatchComponentJS(trim($_GET['__js_comp']));
            return;
        }

        // Page fragment request: ?q=page_name&__spa=1
        self::dispatchPage();
    }

    /**
     * Handles an AJAX action from a generated JS component by routing it
     * back to the corresponding PHPComponent class and method.
     */
    public static function dispatchComponentAction(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $compName = $input['component'] ?? null;
        $method = $input['method'] ?? null;
        $data = $input['data'] ?? [];

        if (!$compName || !$method) {
            self::respond('error', ['message' => 'Invalid component action request.']);
        }

        try {
            $app = \SPP\Scheduler::getContext();
            $className = "App\\" . ucfirst($app) . "\\Components\\" . $compName;
            
            if (!class_exists($className)) {
                self::respond('error', ['message' => "Component '{$compName}' not found."]);
            }

            $component = new $className();
            if (!method_exists($component, $method)) {
                self::respond('error', ['message' => "Method '{$method}' not found in component '{$compName}'."]);
            }

            // Execute the action
            $result = $component->$method($data);
            
            self::respond('ok', [
                'result' => $result,
                'state' => $component->getState()
            ]);
        } catch (\Throwable $e) {
            self::respond('error', ['message' => 'Component Action Error: ' . $e->getMessage()]);
        }
    }

    /**
     * Dynamically generates and serves the JS for a PHP component.
     */
    public static function dispatchComponentJS(string $name): void
    {
        header('Content-Type: application/javascript; charset=utf-8');
        try {
            $app = \SPP\Scheduler::getContext();
            $className = "App\\" . ucfirst($app) . "\\Components\\" . $name;
            echo \SPPMod\SPPView\JSGenerator::generate($className);
        } catch (\Exception $e) {
            echo "// Error generating component JS: " . $e->getMessage();
        }
        exit;
    }

    /**
     * Returns true if this is an SPA request.
     */
    public static function isAjaxRequest(): bool
    {
        return (isset($_GET['__spa']) && $_GET['__spa'] === '1')
            || (isset($_SERVER['HTTP_X_SPP_AJAX']) && $_SERVER['HTTP_X_SPP_AJAX'] === '1')
            || (isset($_SERVER['X-SPP-Ajax']) && $_SERVER['X-SPP-Ajax'] === '1');
    }

    /**
     * Returns true when spa_enabled is set to true in module config.
     */
    public static function isSpaEnabled(): bool
    {
        $val = \SPP\Module::getConfig('spa_enabled', 'sppajax');
        return filter_var($val, FILTER_VALIDATE_BOOLEAN);
    }

    // -------------------------------------------------------------------------
    // Page fragment dispatcher
    // -------------------------------------------------------------------------

    /**
     * Resolves the requested page via Pages::getPage(), captures its output,
     * and returns JSON { status, html, title }.
     */
    public static function dispatchPage(): void
    {
        $q = isset($_GET['q']) ? trim($_GET['q']) : null;

        try {
            $page = \SPPMod\SPPView\Pages::getPage($q);
        } catch (\SPP\SPPException $e) {
            self::respond('error', ['message' => $e->getMessage()], 404);
        }

        if (empty($page['url'])) {
            self::respond('error', ['message' => 'Page not found.'], 404);
        }

        $pageDir = \SPP\Module::getConfig('spa_page_dir', 'sppajax') ?: '/src/pages';
        $filename = SPP_APP_DIR . $pageDir . '/' . ltrim($page['url'], '/');

        // Resolve symlinks and prevent path traversal
        $realBase = realpath(SPP_APP_DIR . $pageDir);
        $realFile = realpath($filename);

        if ($realFile === false || !str_starts_with($realFile, $realBase)) {
            self::respond('error', ['message' => 'Forbidden.'], 403);
        }

        if (!file_exists($realFile) || !is_file($realFile)) {
            self::respond('error', ['message' => 'Page file not found.'], 404);
        }

        // Capture the page output
        ob_start();
        try {
            include $realFile;
        } catch (\Throwable $e) {
            ob_end_clean();
            \SPPMod\SPPLogger\SPP_Logger::error("SPPAjax Page Exception ($filename): " . $e->getMessage());
            self::respond('error', ['message' => 'Page render error: ' . $e->getMessage()], 500);
        }
        $html = ob_get_clean();

        self::respond('ok', [
            'html' => $html,
            'title' => \SPPMod\SPPView\ViewPage::getPageTitle() ?? $page['name'] ?? '',
            'page' => $page['name'] ?? '',
            'params' => $page['params'] ?? [],
        ]);
    }

    // -------------------------------------------------------------------------
    // Service dispatcher
    // -------------------------------------------------------------------------

    /**
     * Dispatches to a registered service script and returns its $response as JSON.
     * Only services declared in services.yml can be called.
     */
    public static function dispatchService(string $name): void
    {
        // Sanitize
        $name = preg_replace('/[^a-zA-Z0-9_\-]/', '', $name);

        $service = self::findService($name);
        if ($service === null) {
            self::respond('error', ['message' => 'Unknown service: ' . $name], 403);
        }

        // SPA Native Auth interceptor protecting endpoint dynamically
        if (!empty($service['requires_auth']) && filter_var($service['requires_auth'], FILTER_VALIDATE_BOOLEAN)) {
            if (!\SPPMod\SPPAuth\SPPAuth::authSessionExists()) {
                self::respond('error', ['message' => 'Unauthorized component execution.'], 401);
            }
        }

        // Enforce HTTP method constraint
        $allowedMethod = strtoupper($service['method'] ?? 'GET');
        if ($_SERVER['REQUEST_METHOD'] !== $allowedMethod) {
            self::respond('error', [
                'message' => "Service '{$name}' requires {$allowedMethod}.",
            ], 405);
        }

        // Resolve script path securely
        if (isset($service['runtime']) && isset($service['target'])) {
            // Polyglot Service Execution
            try {
                $args = array_merge($_GET, $_POST, json_decode(file_get_contents('php://input'), true) ?: []);
                $res = \SPP\PolyglotBridge::call($service['runtime'], $service['target'], $service['method'] ?? 'main', $args);
                
                if ($res['success']) {
                    $la = new \SPPMod\SPPAjax\LiveAction();
                    $data = $res['data'] ?? [];
                    if (isset($data['status'])) {
                        $la->setStatus($data['status']);
                        unset($data['status']);
                    }
                    if (isset($data['message'])) {
                        $la->notify($data['message']);
                        unset($data['message']);
                    }
                    $la->setData($data);
                    $la->send();
                    exit;
                } else {
                    self::respond('error', ['message' => 'Polyglot Service Error: ' . ($res['error'] ?? 'Unknown')], 500);
                }
            } catch (\Exception $e) {
                self::respond('error', ['message' => 'Bridge Exception: ' . $e->getMessage()], 500);
            }
        }

        $servDir = \SPP\Module::getConfig('spa_service_dir', 'sppajax') ?: '/src/serv';
        $script = basename($service['script']); // strip any directory component
        $fullPath = SPP_APP_DIR . $servDir . '/' . $script;

        $realBase = realpath(SPP_APP_DIR . $servDir);
        $realFile = realpath($fullPath);

        if ($realFile === false || !str_starts_with($realFile, $realBase)) {
            self::respond('error', ['message' => 'Forbidden.'], 403);
        }

        if (!file_exists($realFile) || !is_file($realFile)) {
            self::respond('error', ['message' => "Service script '{$script}' not found."], 404);
        }

        // Execute the service script — it must set $response array
        $response = [];
        try {
            include $realFile;
        } catch (\Throwable $e) {
            self::respond('error', ['message' => 'Service error: ' . $e->getMessage()], 500);
        }

        self::respond($response['status'] ?? 'ok', $response['data'] ?? [], $response['message'] ?? '');
    }

    /**
     * Advanced Dispatcher: Resolves and executes a service using Dynamic Discovery.
     * This is the core of the "Zero-Boring-Code" architecture.
     */
    public static function resolveAndExecute(string $action, array $params = []): void
    {
        $serviceFile = null;
        $funcName = null;

        // 1. Try Registry First (Dual Architecture - Manual & Detected)
        $svc = self::findService($action);
        if ($svc) {
            // Polyglot Service Check
            if (isset($svc['runtime']) && isset($svc['target'])) {
                try {
                    $args = array_merge($params, json_decode(file_get_contents('php://input'), true) ?: []);
                    $res = \SPP\PolyglotBridge::call($svc['runtime'], $svc['target'], $svc['method'] ?? 'main', $args);
                    if ($res['success']) {
                        $la = new \SPPMod\SPPAjax\LiveAction();
                        $data = $res['data'] ?? [];
                        if (isset($data['status'])) { $la->setStatus($data['status']); unset($data['status']); }
                        if (isset($data['message'])) { $la->notify($data['message']); unset($data['message']); }
                        $la->setData($data);
                        $la->send();
                        exit;
                    } else {
                        self::respond('error', ['message' => 'Polyglot Service Error: ' . ($res['error'] ?? 'Unknown')], 500);
                    }
                } catch (\Exception $e) {
                    self::respond('error', ['message' => 'Bridge Exception: ' . $e->getMessage()], 500);
                }
            }

            $serviceFile = $svc['script'];
            $funcName = $svc['method'] ?? null;
            if ($funcName && ($funcName === 'POST' || $funcName === 'ANY' || $funcName === 'GET')) $funcName = null;
            
            if (!str_starts_with($serviceFile, '/') && !str_contains($serviceFile, ':')) {
                $serviceFile = SPP_APP_DIR . '/' . ltrim($serviceFile, '/');
            }
            
            $serviceFile = realpath($serviceFile);

            if ($serviceFile && file_exists($serviceFile)) {
                require_once $serviceFile;
            }
        }

        if (!$serviceFile) {
            // 2. Fallback: Dynamic Discovery
            $context = \SPP\Scheduler::getContext();
            $srcPath = \SPP\App::getAppConf('src_path', $context) ?: ('src/' . $context);
            $servicesPath = \SPP\App::getAppConf('services_path', $context) ?: (rtrim($srcPath, '/') . '/services');
            $servicesDir = SPP_APP_DIR . '/' . ltrim($servicesPath, '/');

            // Fallback 1: Standalone file in services directory
            $standaloneFile = $servicesDir . '/' . $action . '.php';
            if (file_exists($standaloneFile)) {
                $serviceFile = $standaloneFile;
            }

            // Fallback 2: Standalone file in src/serv directory (SPA pattern)
            if (!$serviceFile) {
                $servFile = SPP_APP_DIR . '/' . ltrim($srcPath, '/') . '/serv/' . $action . '.php';
                if (file_exists($servFile)) $serviceFile = $servFile;
            }

            // Fallback 3: Grouped service (e.g. User.Save -> User.php with live_Save)
            if (!$serviceFile && strpos($action, '.') !== false) {
                $parts = explode('.', $action);
                $group = $parts[0];
                $method = $parts[1];
                
                $groupFile = $servicesDir . '/' . $group . '.php';
                if (file_exists($groupFile)) {
                    $testFunc = 'live_' . $method;
                    require_once $groupFile;
                    if (function_exists($testFunc)) {
                        $serviceFile = $groupFile;
                        $funcName = $testFunc;
                    }
                }
            }
            
            // Fallback 4: Check in General.php
            if (!$serviceFile) {
                $generalFile = $servicesDir . '/General.php';
                if (!file_exists($generalFile) && \SPP\Scheduler::getContext() === 'sppadmin') {
                    $generalFile = SPP_BASE_DIR . '/admin/services/General.php';
                }

                if (file_exists($generalFile)) {
                    require_once $generalFile;
                    $testFunc = 'live_' . $action;
                    if (function_exists($testFunc) || function_exists('\\' . $testFunc)) {
                        $serviceFile = $generalFile;
                        $funcName = $testFunc;
                    }
                }
            }

            // Fallback 5: Check in module-specific services
            if (!$serviceFile && isset($params['modname'])) {
                $mod = $params['modname'];
                $modServiceFile = SPP_MODULES_DIR . '/spp/' . $mod . '/services/' . $action . '.php';
                if (file_exists($modServiceFile)) $serviceFile = $modServiceFile;
            }

            // 3. Persistence: If discovered dynamically, cache it for future calls
            if ($serviceFile) {
                self::persistDetectedService($action, $serviceFile, $funcName);
            }
        }

        if ($serviceFile) {
            $serviceFile = realpath($serviceFile);
            $la = new \SPPMod\SPPAjax\LiveAction();
            
            ob_start();
            if ($funcName) {
                if (function_exists($funcName)) {
                    $funcName($la, $params);
                } else {
                    $globalFunc = '\\' . $funcName;
                    if (function_exists($globalFunc)) {
                        $globalFunc($la, $params);
                    } else {
                        throw new \Exception("Service function '$funcName' not found.");
                    }
                }
            } else {
                if ($serviceFile && file_exists($serviceFile)) {
                    require_once $serviceFile;
                }
            }
            $output = ob_get_clean();
            
            // Auto-capture echoed HTML
            if (!empty($output)) {
                $currentData = $la->getData();
                if (empty($currentData['html'])) {
                    $la->setData(array_merge($currentData, ['html' => $output]));
                }
            }
            
            $la->send();
            exit;
        }

        // If no discovery worked, return error
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => "Service '{$action}' could not be resolved via Dynamic Discovery.",
            'data' => []
        ]);
        exit;
    }

    // -------------------------------------------------------------------------
    // Service registry
    // -------------------------------------------------------------------------

    /**
     * Returns a flattened list of all registered services from both YAML and DB.
     */
    public static function listServices(): array
    {
        return self::loadServiceRegistry();
    }

    /**
     * Looks up a service by name from the services registry YAML.
     * @return array<string,string>|null
     */
    private static function findService(string $name): ?array
    {
        $registry = self::loadServiceRegistry();
        foreach ($registry as $svc) {
            if (isset($svc['name']) && $svc['name'] === $name) {
                return $svc;
            }
        }
        return null;
    }

    private static function loadServiceRegistry(): array
    {
        if (self::$serviceRegistry !== null) {
            return self::$serviceRegistry;
        }

        $registry = [];
        
        // 1. Load from services.yml (Manual)
        $file = self::getServiceRegistryFile();
        $registry = array_merge($registry, self::loadYamlRegistry($file));

        // 2. Load from detected-services.yml (Auto-discovered)
        $detectedFile = self::getDetectedServicesFile();
        $registry = array_merge($registry, self::loadYamlRegistry($detectedFile));

        // 3. Load from Database
        if (\SPP\Module::isEnabled('sppdb')) {
            self::ensureDbSchema();
            try {
                $db = new \SPPMod\SPPDB\SPPDB();
                $dbServices = $db->execute_query('SELECT name, script, method FROM ' . \SPPMod\SPPDB\SPPDB::sppTable('sppajax_services'));
                $dbSummary = $db->getConnectionSummary();
                foreach ($dbServices as &$svc) {
                    $svc['source'] = 'db';
                    $svc['db_summary'] = $dbSummary;
                }
                $registry = array_merge($registry, $dbServices);
            } catch (\Exception $e) {}
        }

        self::$serviceRegistry = $registry;
        return self::$serviceRegistry;
    }

    private static function loadYamlRegistry(string $file): array
    {
        if (!file_exists($file)) return [];
        try {
            $parsed = Yaml::parseFile($file);
            $services = $parsed['services'] ?? [];
            foreach ($services as &$svc) {
                $svc['source'] = 'yaml';
                $svc['source_path'] = $file;
            }
            return $services;
        } catch (\Exception $e) {
            return [];
        }
    }

    private static function persistDetectedService(string $name, string $file, ?string $method): void
    {
        $regFile = self::getServiceRegistryFile();
        $detectedFile = self::getDetectedServicesFile();

        // 1. Don't persist if it's already in the manual registry or previously detected
        $manual = self::loadYamlRegistry($regFile);
        foreach ($manual as $svc) if ($svc['name'] === $name) return;

        $detected = self::loadYamlRegistry($detectedFile);
        foreach ($detected as $svc) if ($svc['name'] === $name) return;

        // 2. Add new entry
        $relPath = str_replace(realpath(SPP_APP_DIR), '', realpath($file));
        $relPath = ltrim(str_replace('\\', '/', $relPath), '/');

        $newSvc = [
            'name' => $name,
            'script' => $relPath,
            'method' => $method ?: 'POST'
        ];
        
        $detected[] = $newSvc;

        // 3. Save to detected-services.yml
        $dir = dirname($detectedFile);
        if (!is_dir($dir)) mkdir($dir, 0777, true);

        $yaml = "################################################################################\n";
        $yaml .= "# SPP Detected Services Registry\n";
        $yaml .= "# This file is automatically managed by the SPPAjax Dynamic Discovery engine.\n";
        $yaml .= "################################################################################\n\n";
        $yaml .= Yaml::dump(['services' => $detected], 4, 2);
        
        file_put_contents($detectedFile, $yaml, LOCK_EX);
    }

    // -------------------------------------------------------------------------
    // Response builder
    // -------------------------------------------------------------------------

    /**
     * Sends a JSON response and terminates execution.
     *
     * @param string              $status HTTP semantic status string: ok|redirect|error|reload
     * @param array<string,mixed> $data   Payload merged into the response envelope
     * @param int                 $code   HTTP status code
     */
    public static function respond(string $status, array $data, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('X-SPP-Ajax-Response: 1');

        $envelope = array_merge([
            'status' => $status,
            'success' => ($status === 'ok' || $status === 'redirect')
        ], $data);

        if (defined('SPP_LOG_DIR')) {
            $logFile = SPP_LOG_DIR . '/api_debug.log';
            $action = $_REQUEST['action'] ?? 'unknown';
            error_log("[" . date('Y-m-d H:i:s') . "] SPPAjax Response: status=$status, action=$action\n", 3, $logFile);
        }

        echo json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // -------------------------------------------------------------------------
    // Registry management helpers (PHP-side, for setup/admin use)
    // -------------------------------------------------------------------------

    /**
     * Registers a new service programmatically into either services.yml or the database.
     */
    public static function registerService(string $name, string $script, string $method = 'POST', string $source = 'yaml'): bool
    {
        if ($source === 'yaml') {
            $file = self::getServiceRegistryFile();
            $parsed = [];
            if (file_exists($file)) {
                $parsed = Yaml::parseFile($file) ?? [];
            }
            $services = $parsed['services'] ?? [];
            $updated = false;
            foreach ($services as &$svc) {
                if ($svc['name'] === $name) {
                    $svc['script'] = basename($script);
                    $svc['method'] = strtoupper($method);
                    $updated = true;
                    break;
                }
            }
            if (!$updated) {
                $services[] = [
                    'name' => preg_replace('/[^a-zA-Z0-9_\-]/', '', $name),
                    'script' => basename($script),
                    'method' => strtoupper($method),
                ];
            }
            $parsed['services'] = $services;
            file_put_contents($file, Yaml::dump($parsed, 3, 4), LOCK_EX);
        } else if ($source === 'db') {
            self::ensureDbSchema();
            $db = new \SPPMod\SPPDB\SPPDB();
            $db->execute_query(
                'REPLACE INTO ' . \SPPMod\SPPDB\SPPDB::sppTable('sppajax_services') . ' (name, script, method) VALUES (?, ?, ?)',
                [$name, basename($script), strtoupper($method)]
            );
        }

        // Bust cache
        self::$serviceRegistry = null;
        return true;
    }

    /**
     * Removes a service from either services.yml or the database.
     */
    public static function unregisterService(string $name, string $source = 'yaml'): bool
    {
        if ($source === 'yaml') {
            $file = self::getServiceRegistryFile();
            if (!file_exists($file)) return false;
            $parsed = Yaml::parseFile($file) ?? [];
            $services = $parsed['services'] ?? [];
            $filtered = array_values(array_filter($services, fn($s) => $s['name'] !== $name));
            if (count($filtered) === count($services)) return false;
            $parsed['services'] = $filtered;
            file_put_contents($file, Yaml::dump($parsed, 3, 4), LOCK_EX);
        } else if ($source === 'db') {
            if (\SPP\Module::isEnabled('sppdb')) {
                $db = new \SPPMod\SPPDB\SPPDB();
                $db->execute_query('DELETE FROM ' . \SPPMod\SPPDB\SPPDB::sppTable('sppajax_services') . ' WHERE name=?', [$name]);
            }
        }

        self::$serviceRegistry = null;
        return true;
    }

    /**
     * Ensures the database schema for AJAX services exists.
     */
    public static function ensureDbSchema(): void
    {
        if (!\SPP\Module::isEnabled('sppdb')) return;
        $db = new \SPPMod\SPPDB\SPPDB();
        $db->execute_query('CREATE TABLE IF NOT EXISTS ' . \SPPMod\SPPDB\SPPDB::sppTable('sppajax_services') . ' (
            id      INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name    VARCHAR(255) NOT NULL UNIQUE,
            script  VARCHAR(255) NOT NULL,
            method  VARCHAR(10)  NOT NULL DEFAULT "POST"
        )');
    }

    /**
     * Internal helper to resolve the service registry file path correctly.
     */
    private static function getServiceRegistryFile(): string
    {
        $appname = \SPP\Scheduler::getContext();
        $file = APP_ETC_DIR . SPP_DS . $appname . SPP_DS . 'services.yml';
        
        if (!file_exists($file)) {
            $registryPath = \SPP\Module::getConfig('spa_services_registry', 'sppajax');
            if ($registryPath) {
                $file = SPP_APP_DIR . $registryPath;
            } else {
                $file = SPP_APP_DIR . '/etc/services.yml';
            }
        }
        return $file;
    }

    private static function getDetectedServicesFile(): string
    {
        $regFile = self::getServiceRegistryFile();
        return str_replace('services.yml', 'detected-services.yml', $regFile);
    }
}
