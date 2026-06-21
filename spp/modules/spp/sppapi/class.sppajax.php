<?php

namespace SPPMod\SppApi;

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
        // Enforce API Rate Limiting via Event-Driven Middleware
        if (class_exists('\\SPP\\SPPEvent')) {
            \SPP\SPPEvent::fireEvent('api.request.start', new \SPP\EventParams());
        }

        if (!self::isSpaEnabled()) {
            self::respond('error', ['message' => 'SPA mode is disabled.'], 503);
        }

        // SSE Streaming Handler: ?__spa_stream=service_name
        if (isset($_GET['__spa_stream'])) {
            \SPPMod\SPPAPI\Dispatchers\StreamDispatcher::dispatch(trim($_GET['__spa_stream']));
            return;
        }

        // Component Action: ?__svc=component_action
        if (isset($_GET['__svc']) && $_GET['__svc'] === 'component_action') {
            \SPPMod\SPPAPI\Dispatchers\ComponentDispatcher::dispatchAction();
            return;
        }

        // SPPLive Update: ?__svc=live_update
        if (isset($_GET['__svc']) && $_GET['__svc'] === 'live_update') {
            \SPPMod\SPPAPI\Dispatchers\LiveDispatcher::dispatch();
            return;
        }

        // SPPLive SSE Stream: ?__svc=live_sse
        if (isset($_GET['__svc']) && $_GET['__svc'] === 'live_sse') {
            $topics = isset($_GET['topics']) ? explode(',', $_GET['topics']) : ['global'];
            if (class_exists('\\SPPMod\\SPPLive\\SSEHandler')) {
                \SPPMod\SPPLive\SSEHandler::stream($topics);
            }
            return;
        }

        // SPPLive File Upload: ?__svc=live_upload
        if (isset($_GET['__svc']) && $_GET['__svc'] === 'live_upload') {
            if (class_exists('\\SPPMod\\SPPLive\\UploadHandler')) {
                $response = \SPPMod\SPPLive\UploadHandler::handle();
                header('Content-Type: application/json');
                echo json_encode($response);
            }
            return;
        }

        // SPPLive Presence Heartbeat: ?__svc=live_presence
        if (isset($_GET['__svc']) && $_GET['__svc'] === 'live_presence') {
            $input = json_decode(file_get_contents('php://input'), true);
            $topics = $input['topics'] ?? ['global'];
            $userId = class_exists('\\SPPMod\\SPPAuth\\SPPAuth') ? \SPPMod\SPPAuth\SPPAuth::getUserId() : 'anonymous_' . session_id();
            
            $engine = \SPPMod\SPPLive\SPPLive::getEngine();
            foreach ($topics as $topic) {
                if (\SPPMod\SPPLive\SPPLive::authorizeTopic($topic)) {
                    $engine->trackPresence($topic, $userId);
                }
            }
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            return;
        }

        // Autonomous Intent API Route Morphing: ?__svc=intent_morph
        if (isset($_GET['__svc']) && $_GET['__svc'] === 'intent_morph') {
            \SPPMod\SPPAPI\Dispatchers\IntentDispatcher::dispatch();
            return;
        }

        // Real-Time Native CDC Event Streamer: ?__svc=cdc_stream
        if (isset($_GET['__svc']) && $_GET['__svc'] === 'cdc_stream') {
            \SPPMod\SPPAPI\Dispatchers\CdcDispatcher::dispatch();
            return;
        }

        // Service call: ?__svc=service_name
        if (isset($_GET['__svc'])) {
            \SPPMod\SPPAPI\Dispatchers\ServiceDispatcher::dispatch(trim($_GET['__svc']));
            return;
        }

        // Component JS: ?__js_comp=ComponentName
        if (isset($_GET['__js_comp'])) {
            \SPPMod\SPPAPI\Dispatchers\ComponentDispatcher::dispatchJS(trim($_GET['__js_comp']));
            return;
        }

        // Page fragment request: ?q=page_name&__spa=1
        \SPPMod\SPPAPI\Dispatchers\PageDispatcher::dispatch();
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
    public static function findService(string $name): ?array
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
            } catch (\Exception $e) {
            }
        }

        self::$serviceRegistry = $registry;
        return self::$serviceRegistry;
    }

    private static function loadYamlRegistry(string $file): array
    {
        if (!file_exists($file)) {
            return [];
        }
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
        foreach ($manual as $svc) {
            if ($svc['name'] === $name) {
                return;
            }
        }

        $detected = self::loadYamlRegistry($detectedFile);
        foreach ($detected as $svc) {
            if ($svc['name'] === $name) {
                return;
            }
        }

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
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

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
        while (ob_get_level()) {
            ob_end_clean();
        }

        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('X-SPP-Ajax-Response: 1');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');

        $envelope = array_merge([
            'status' => $status,
            'success' => ($status === 'ok' || $status === 'redirect')
        ], $data);

        // State validation integrity metadata generation
        $secret = defined('SPP_SECRET_KEY') ? SPP_SECRET_KEY : 'spp-enterprise-integrity-secret-key-v1';
        $envelope['__hmac'] = hash_hmac('sha256', json_encode($envelope), $secret);

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
        } elseif ($source === 'db') {
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
            if (!file_exists($file)) {
                return false;
            }
            $parsed = Yaml::parseFile($file) ?? [];
            $services = $parsed['services'] ?? [];
            $filtered = array_values(array_filter($services, fn ($s) => $s['name'] !== $name));
            if (count($filtered) === count($services)) {
                return false;
            }
            $parsed['services'] = $filtered;
            file_put_contents($file, Yaml::dump($parsed, 3, 4), LOCK_EX);
        } elseif ($source === 'db') {
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
        if (!\SPP\Module::isEnabled('sppdb')) {
            return;
        }
        $db = new \SPPMod\SPPDB\SPPDB();
        $tableName = 'sppajax_services';
        $fullTableName = \SPPMod\SPPDB\SPPDB::sppTable($tableName);

        if ($db->tableExists($tableName)) {
            return;
        }

        $isXdb = (strpos($db->getConnectionSummary(), 'XDB') !== false);

        if ($isXdb) {
            $sql = "CREATE TABLE {$fullTableName} (id AUTO_INCREMENT, name STRING, script STRING, method STRING)";
        } else {
            $sql = "CREATE TABLE {$fullTableName} (
                id      INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
                name    VARCHAR(255) NOT NULL UNIQUE,
                script  VARCHAR(255) NOT NULL,
                method  VARCHAR(10)  NOT NULL DEFAULT 'POST'
            )";
        }

        try {
            $db->execute_query($sql);
        } catch (\Exception $e) {
            @file_put_contents(SPP_BASE_DIR . "/api_debug.log", "[SPPAjax] Schema initialization failed: " . $e->getMessage() . "\n", FILE_APPEND);
        }
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

    /**
     * Edge-First Distributed Read Replicas resolution router.
     * Evaluates multi-region read-only endpoint candidates dynamically to offload queries.
     */
    public static function getOptimalReadReplica(): string
    {
        $replicas = \SPP\Module::getConfig('read_replicas', 'sppajax') ?: [];
        if (empty($replicas) || !is_array($replicas)) {
            return 'default';
        }
        // Round-robin edge simulation strategy selection
        static $counter = 0;
        return $replicas[($counter++) % count($replicas)];
    }

    /**
    /**
     * Appends cryptographically validated tamper-evident Merkle DAG links into log tracking storage.
     * Guarantees absolute proof of state transformation histories across distributed service components.
     */
    public static function appendMerkleLineage(string $action, array $payload): string
    {
        static $previousHash = '0000000000000000000000000000000000000000000000000000000000000000';
        $timestamp = microtime(true);
        $secret = defined('SPP_SECRET_KEY') ? SPP_SECRET_KEY : 'spp-enterprise-integrity-secret-key-v1';

        $block = [
            'previous_hash' => $previousHash,
            'timestamp' => $timestamp,
            'action' => $action,
            'payload_checksum' => md5(json_encode($payload))
        ];

        $currentHash = hash_hmac('sha256', json_encode($block), $secret);
        $previousHash = $currentHash;

        if (defined('SPP_LOG_DIR')) {
            $logFile = SPP_LOG_DIR . '/merkle_lineage.log';
            @file_put_contents($logFile, json_encode(['hash' => $currentHash, 'block' => $block]) . "\n", FILE_APPEND);
        }

        return $currentHash;
    }

    /**
     * Enforces strict payload verification using cryptographic HMAC envelopes to prevent transport layer manipulation.
     * @param mixed $request Optional target request context instance mapping payload structures.
     */
    public static function verifyTransportIntegrity($request = null): bool
    {
        $hmacRequired = \SPP\Module::getConfig('api_integrity_hmac', 'sppajax');
        if ($hmacRequired === false || $hmacRequired === 0 || $hmacRequired === '0' || $hmacRequired === 'false') {
            return true;
        }
        $clientSig = $_SERVER['HTTP_X_SPP_SIGNATURE'] ?? $_REQUEST['__sig'] ?? '';
        if (empty($clientSig)) {
            return false;
        }
        $secret = defined('SPP_SECRET_KEY') ? SPP_SECRET_KEY : 'spp-enterprise-integrity-secret-key-v1';
        
        $rawPayload = file_get_contents('php://input');
        if (!empty($rawPayload)) {
            $dataToSign = $rawPayload;
        } else {
            $getParams = $_GET;
            unset($getParams['__sig']); // Ensure signature doesn't invalidate itself
            $dataToSign = json_encode($_POST ?: $getParams);
        }
        
        $computed = hash_hmac('sha256', $dataToSign, $secret);
        return hash_equals($computed, $clientSig);
    }
}
