<?php

/**
 * Diagnostics API Controller for SPPAdmin
 */

if (!function_exists('live_diagnostics_health')) {
    function live_diagnostics_health($la, $params) {
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
            if (!is_dir($dir)) @mkdir($dir, 0777, true);
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
    function live_list_queue($la, $params) {
        $jobs = [];
        if (class_exists('\\SPP\\Core\\Queue')) {
            // Mocking for now as SPP Queue API might vary
            $jobs = method_exists('\\SPP\\Core\\Queue', 'listJobs') ? \SPP\Core\Queue::listJobs() : [];
        }
        $la->setData(['jobs' => $jobs]);
    }
}

if (!function_exists('live_get_event_trace')) {
    function live_get_event_trace($la, $params) {
        // Read from spp_event_trace.log if it exists
        $traces = [];
        $logFile = defined('SPP_LOG_DIR') ? SPP_LOG_DIR . '/spp_event_trace.log' : SPP_BASE_DIR . '/var/logs/spp_event_trace.log';
        if (file_exists($logFile)) {
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $lines = array_slice($lines, -100); // Last 100
            foreach ($lines as $line) {
                $traces[] = ['raw' => $line];
            }
        }
        $la->setData(['traces' => $traces]);
    }
}

if (!function_exists('live_get_parikshak_trace')) {
    function live_get_parikshak_trace($la, $params) {
        $traces = [];
        $logFile = defined('SPP_LOG_DIR') ? SPP_LOG_DIR . '/parikshak.log' : SPP_BASE_DIR . '/var/logs/parikshak.log';
        if (file_exists($logFile)) {
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $lines = array_slice($lines, -100); // Last 100
            foreach ($lines as $line) {
                $traces[] = ['raw' => $line];
            }
        }
        $la->setData(['traces' => $traces]);
    }
}
