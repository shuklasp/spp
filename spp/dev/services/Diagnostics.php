<?php

/**
 * Diagnostics API Controller for SPPAdmin
 */

if (!function_exists('live_diagnostics_health')) {
    function live_diagnostics_health($la, $params)
    {
        $health = [
            'status' => 'UP',
            'timestamp' => date('c'),
            'components' => []
        ];

        // 1. Database Health
        try {
            $db = new \SPPMod\SPPDB\SPPDB();
            if ($db->getDriver() !== 'xdb') {
                $db->execute_query("SELECT 1");
            }
            $health['components']['database'] = ['status' => 'UP'];
        } catch (\Exception $e) {
            $health['status'] = 'DEGRADED';
            $health['components']['database'] = ['status' => 'DOWN', 'message' => $e->getMessage()];
        }

        // 2. Redis Health (If configured)
        try {
            if (\SPP\Module::getConfig('host', 'redis')) {
                if (class_exists('\SPP\Core\RedisCache')) {
                    $redis = \SPP\Core\RedisCache::getConnection();
                    $redis->ping();
                    $health['components']['redis'] = ['status' => 'UP'];
                } else {
                    $health['components']['redis'] = ['status' => 'DOWN', 'message' => 'Redis module not loaded'];
                }
            }
        } catch (\Throwable $e) {
            $health['status'] = 'DEGRADED';
            $health['components']['redis'] = ['status' => 'DOWN', 'message' => $e->getMessage()];
        }

        // 3. Filesystem Health
        $writeableDirs = [SPP_BASE_DIR . '/var', SPP_BASE_DIR . '/var/logs'];
        foreach ($writeableDirs as $dir) {
            if (!is_dir($dir))
                @mkdir($dir, 0777, true);
            $health['components']['fs_' . basename($dir)] = is_writable($dir) ? ['status' => 'UP'] : ['status' => 'DOWN'];
        }

        // 4. Memory Usage
        $health['components']['system'] = [
            'memory_usage' => round(memory_get_usage() / 1024 / 1024, 2) . ' MB',
            'php_version' => PHP_VERSION
        ];

        return $la->setData(['health' => $health]);
    }
}

if (!function_exists('live_list_queue')) {
    function live_list_queue($la, $params)
    {
        $jobs = [];
        if (class_exists('\\SPP\\Core\\Queue')) {
            // Mocking for now as SPP Queue API might vary
            $jobs = method_exists('\\SPP\\Core\\Queue', 'listJobs') ? \SPP\Core\Queue::listJobs() : [];
        }
        $la->setData(['jobs' => $jobs]);
    }
}

if (!function_exists('live_get_event_trace')) {
    function live_get_event_trace($la, $params)
    {
        $logDir = defined('SPP_LOG_DIR') ? SPP_LOG_DIR : SPP_BASE_DIR . '/var/logs';
        $jsonFile = $logDir . '/event_trace.json';
        $traces = [];
        if (file_exists($jsonFile)) {
            $content = @file_get_contents($jsonFile);
            if ($content) {
                $decoded = json_decode($content, true);
                if (is_array($decoded)) {
                    $traces = $decoded;
                }
            }
        }

        // Fallback to spp_event_trace.log if event_trace.json was empty
        if (empty($traces)) {
            $logFile = $logDir . '/spp_event_trace.log';
            if (file_exists($logFile)) {
                $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $lines = array_slice($lines, -50);
                foreach ($lines as $line) {
                    $parsed = json_decode($line, true);
                    if ($parsed && isset($parsed['request_uri'])) {
                        $traces[] = $parsed;
                    }
                }
            }
        }

        $la->setData(['traces' => $traces]);
    }
}

if (!function_exists('live_get_parikshak_trace')) {
    function live_get_parikshak_trace($la, $params)
    {
        $logDir = defined('SPP_LOG_DIR') ? SPP_LOG_DIR : SPP_BASE_DIR . '/var/logs';
        $files = [$logDir . '/parikshak_events.log', $logDir . '/parikshak.log'];
        $content = '';
        foreach ($files as $f) {
            if (file_exists($f)) {
                $c = @file_get_contents($f);
                if (!empty(trim($c))) {
                    $content = $c;
                    break;
                }
            }
        }
        $la->setData([
            'content' => $content ?: "No Parikshak activity logged yet.\nClick 'Trigger Evolutionary Scan' above or run 'php spp.php test:run' to generate test logs.",
            'traces' => []
        ]);
    }
}

if (!function_exists('live_run_parikshak_scan')) {
    function live_run_parikshak_scan($la, $params)
    {
        $app = $params['appname'] ?? $params['app'] ?? 'default';
        if (empty($app)) $app = 'default';

        $bridgePath = __DIR__ . '/CommandBridge.php';
        if (file_exists($bridgePath)) {
            require_once $bridgePath;
            $bridge = new \SPP\Admin\Services\CommandBridge();
            $res = $bridge->executeCommand('test:run', ['--app' => $app]);

            $output = $res['output'] ?? '';
            $logDir = defined('SPP_LOG_DIR') ? SPP_LOG_DIR : SPP_BASE_DIR . '/var/logs';
            if (!is_dir($logDir)) @mkdir($logDir, 0777, true);
            $logEntry = "[" . date('Y-m-d H:i:s') . "] Evolutionary Scan executed for '{$app}':\n" . $output . "\n----------------------------------------\n";
            @file_put_contents($logDir . '/parikshak_events.log', $logEntry, FILE_APPEND);

            if ($res['success'] ?? false) {
                return $la->setData(['output' => $output])->notify("Parikshak scan executed successfully for {$app}.", 'success');
            } else {
                return $la->setData(['output' => $output])->notify("Parikshak scan completed for {$app}.", 'info');
            }
        }
        return $la->setStatus('error')->notify("CommandBridge service not found", 'error');
    }
}

