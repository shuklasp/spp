<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class AdminDiagnosticsCommand extends Command
{
    protected string $name = 'admin:diagnostics';
    protected string $description = 'Manage Admin Diagnostics operations. Usage: admin:diagnostics <action> [--payload=...] [--json]';

    public function isHidden(): bool { return true; }

    public function execute(array $args): void
    {
        $action = $this->getArgument($args, 0) ?? 'default';
        $payloadRaw = $this->getOption($args, 'payload', '{}');
        $payload = json_decode($payloadRaw, true) ?: [];

        $methodName = 'handle' . str_replace(' ', '', ucwords(str_replace('_', ' ', $action)));
        if (method_exists($this, $methodName)) {
            $this->$methodName($payload, $args);
        } else {
            $this->json(['success' => false, 'error' => "Unknown action: $action"], $args);
        }
    }

    private function handleHealth(array $payload, array $args): void {

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

        $this->json(['health' => $health], $args); return;
        return;
    
    }

    private function handleListQueue(array $payload, array $args): void {

        $jobs = [];
        if (class_exists('\\SPP\\Core\\Queue')) {
            // Mocking for now as SPP Queue API might vary
            $jobs = method_exists('\\SPP\\Core\\Queue', 'listJobs') ? \SPP\Core\Queue::listJobs() : [];
        }
        $this->json(['jobs' => $jobs], $args); return;
    
    }

    private function handleGetEventTrace(array $payload, array $args): void {

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
        $this->json(['traces' => $traces], $args); return;
    
    }

    private function handleGetParikshakTrace(array $payload, array $args): void {

        $traces = [];
        $logFile = defined('SPP_LOG_DIR') ? SPP_LOG_DIR . '/parikshak.log' : SPP_BASE_DIR . '/var/logs/parikshak.log';
        if (file_exists($logFile)) {
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $lines = array_slice($lines, -100); // Last 100
            foreach ($lines as $line) {
                $traces[] = ['raw' => $line];
            }
        }
        $this->json(['traces' => $traces], $args); return;
    
    }

}
