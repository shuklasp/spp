<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class EnvStatusCommand extends Command
{
    protected string $name = 'env:status';
    protected string $description = 'Display system health and environment status';

    public function execute(array $args): void
    {
        $appname = 'default';
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--app=')) {
                $appname = substr($arg, 6);
            }
        }

        echo "SPP Environment Status\n";
        echo str_repeat("=", 80) . "\n\n";

        \SPP\Scheduler::withContext($appname, function () use ($appname) {
            echo "Context:        {$appname}\n";
            echo "PHP Version:    " . PHP_VERSION . "\n";
            echo "OS:             " . PHP_OS . "\n";
            echo "Memory Limit:   " . ini_get('memory_limit') . "\n";

            $dbStatus = 'Disconnected';
            ob_start();
            try {
                if (class_exists('\\SPPMod\\SPPDB\\SPPDB')) {
                    $db = new \SPPMod\SPPDB\SPPDB();
                    $dbStatus = 'Connected';
                }
            } catch (\Exception $e) {
                $dbStatus = 'Disconnected (' . $e->getMessage() . ')';
            }
            ob_end_clean();

            echo "Database:       {$dbStatus}\n";

            $fsStatus = is_writable(SPP_BASE_DIR) ? 'Write Access Confirmed' : 'Restricted Permissions';
            echo "Filesystem:     {$fsStatus}\n";

            $middleware = \SPP\Registry::get('__middleware=>global') ?: [];
            echo "Middleware:     " . count($middleware) . " global layers\n";

            $queueSize = method_exists('\SPP\Core\Queue', 'size') ? \SPP\Core\Queue::size() : 0;
            echo "Queue Size:     {$queueSize}\n";

            $sessionPath = session_save_path() ?: sys_get_temp_dir();
            $activeSessions = count(glob($sessionPath . '/sess_*')) ?: 1;
            echo "Sessions:       {$activeSessions} active\n";

            $cachePath = SPP_BASE_DIR . '/etc/registry.json';
            $cacheSize = file_exists($cachePath) ? round(filesize($cachePath) / 1024, 2) . ' KB' : 'N/A';
            echo "Cache Size:     {$cacheSize}\n";

            echo "\nHealth Checks:\n";
            echo str_repeat("-", 80) . "\n";

            $checks = [];
            $checks[] = ['name' => 'Database', 'status' => $dbStatus === 'Connected' ? 'OK' : 'FAIL'];
            $checks[] = ['name' => 'Filesystem', 'status' => is_writable(SPP_BASE_DIR) ? 'OK' : 'WARN'];
            $checks[] = ['name' => 'Memory Limit', 'status' => (int) ini_get('memory_limit') >= 128 ? 'OK' : 'WARN'];

            $score = 0;
            foreach ($checks as $c) {
                if ($c['status'] === 'OK')
                    $score += 33;
                echo str_pad($c['name'], 30) . "[{$c['status']}]\n";
            }
            if ($score > 100)
                $score = 100;

            echo "\nOverall Health Score: {$score}%\n";
        });
    }
}
