<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class SysStatusCommand extends Command
{
    protected string $name = 'sys:status';
    protected string $description = 'Displays framework health, environment diagnostics, and polyglot bridge status';

    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $isJson = $this->hasFlag($args, 'json');

        // System Info
        $dbStatus = 'Disconnected';
        try {
            if (class_exists('\\SPPMod\\SPPDB\\SPPDB')) {
                $db = new \SPPMod\SPPDB\SPPDB();
                $dbStatus = 'Connected';
            }
        } catch (\Exception $e) {
            $dbStatus = 'Disconnected (' . $e->getMessage() . ')';
        }

        $middleware = \SPP\Registry::get('__middleware=>global') ?: [];

        $stats = [
            'middleware_count' => count($middleware),
            'queue_size' => method_exists('\SPP\Core\Queue', 'size') ? \SPP\Core\Queue::size() : 0,
            'bundling_enabled' => defined('SPP_ASSET_BUNDLING') ? SPP_ASSET_BUNDLING : false,
            'active_sessions' => count(glob(session_save_path() . '/sess_*')) ?: 1
        ];

        // Dynamic Health Report
        $checks = [];
        $checks[] = ['name' => 'Database', 'status' => $dbStatus === 'Connected' ? 'OK' : 'FAIL', 'detail' => $dbStatus === 'Connected' ? 'Pool responsive.' : 'Connection failed.'];
        $checks[] = ['name' => 'Filesystem', 'status' => is_writable(SPP_BASE_DIR) ? 'OK' : 'WARN', 'detail' => is_writable(SPP_BASE_DIR) ? 'Write access confirmed.' : 'Restricted permissions.'];
        $checks[] = ['name' => 'Memory Limit', 'status' => (int) ini_get('memory_limit') >= 128 ? 'OK' : 'WARN', 'detail' => ini_get('memory_limit') . ' allocated.'];
        
        // Expanded Health Checks
        $cpuLoad = function_exists('sys_getloadavg') ? sys_getloadavg()[0] : 0;
        $checks[] = ['name' => 'CPU Load', 'status' => $cpuLoad < 5.0 ? 'OK' : 'WARN', 'detail' => "Current load: $cpuLoad"];
        
        $diskFree = disk_free_space(SPP_BASE_DIR);
        $diskTotal = disk_total_space(SPP_BASE_DIR);
        $diskPct = $diskTotal > 0 ? round(($diskFree / $diskTotal) * 100) : 0;
        $checks[] = ['name' => 'Disk Space', 'status' => $diskPct > 10 ? 'OK' : 'WARN', 'detail' => "$diskPct% free space remaining."];

        $score = 0;
        foreach ($checks as $c) {
            if ($c['status'] === 'OK') {
                $score += (100 / count($checks));
            } elseif ($c['status'] === 'WARN') {
                $score += (50 / count($checks));
            }
        }
        $score = round($score);

        $system = [
            'spp_version' => '11.4.2-Core',
            'php_version' => PHP_VERSION,
            'os' => PHP_OS,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'CLI',
            'spp_base' => SPP_BASE_DIR,
            'app_dir' => SPP_APP_DIR,
            'db_status' => $dbStatus,
            'stats' => $stats,
            'health_report' => [
                'score' => $score,
                'checks' => $checks
            ],
            'orion' => [
                'cache_exists' => file_exists(SPP_BASE_DIR . '/var/shared/registry.json'),
                'cache_size' => file_exists(SPP_BASE_DIR . '/var/shared/registry.json') ? round(filesize(SPP_BASE_DIR . '/var/shared/registry.json') / 1024, 2) . ' KB' : 'N/A'
            ]
        ];

        // Polyglot Bridge Info
        $sharedDir = SPP_BASE_DIR . '/var/shared';
        $configPath = SPP_BASE_DIR . '/etc/bridge.json';

        $runtimes = [
            'java' => ['name' => 'Java VM', 'path' => null, 'version' => 'N/A'],
            'python' => ['name' => 'Python 3', 'path' => null, 'version' => 'N/A'],
            'node' => ['name' => 'Node.js', 'path' => null, 'version' => 'N/A'],
            'dotnet' => ['name' => '.NET Core', 'path' => null, 'version' => 'N/A'],
            'go' => ['name' => 'Go', 'path' => null, 'version' => 'N/A'],
            'compiler' => ['name' => 'C++ Compiler', 'path' => null, 'version' => 'N/A']
        ];

        if (class_exists('\\SPP\\PolyglotBridge')) {
            \SPP\PolyglotBridge::setup();
        }

        $isWin = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
        if ($isWin) {
            $fallbacks = [
                'java' => ['C:\Program Files\Common Files\Oracle\Java\javapath\java.exe', 'C:\Program Files (x86)\Common Files\Oracle\Java\javapath\java.exe'],
                'node' => ['C:\Program Files\nodejs\node.exe', 'C:\Program Files (x86)\nodejs\node.exe'],
                'dotnet' => ['C:\Program Files\dotnet\dotnet.exe'],
                'go' => ['C:\Program Files\Go\bin\go.exe'],
                'python' => ['C:\Python312\python.exe', 'C:\Python311\python.exe', 'C:\Python310\python.exe'],
                'compiler' => [
                    'C:\Program Files\Microsoft Visual Studio\2022\Community\VC\Tools\MSVC\14.34.31933\bin\Hostx64\x64\cl.exe',
                    'C:\Program Files\Microsoft Visual Studio\2022\Professional\VC\Tools\MSVC\14.34.31933\bin\Hostx64\x64\cl.exe'
                ]
            ];
        } else {
            $fallbacks = [
                'java' => ['/usr/bin/java', '/usr/local/bin/java', '/usr/lib/jvm/default-java/bin/java'],
                'node' => ['/usr/bin/node', '/usr/local/bin/node', '/usr/bin/nodejs'],
                'dotnet' => ['/usr/bin/dotnet', '/usr/local/bin/dotnet', '/opt/dotnet/dotnet'],
                'go' => ['/usr/bin/go', '/usr/local/bin/go', '/usr/local/go/bin/go'],
                'python' => ['/usr/bin/python3', '/usr/bin/python', '/usr/local/bin/python3'],
                'compiler' => ['/usr/bin/gcc', '/usr/bin/clang', '/usr/local/bin/gcc']
            ];
        }

        foreach ($runtimes as $id => &$r) {
            $found = false;
            if ($id === 'python') {
                $searchNames = ['python', 'python3'];
            } elseif ($id === 'node') {
                $searchNames = ['node', 'nodejs'];
            } elseif ($id === 'compiler') {
                $searchNames = ['cl', 'gcc', 'clang'];
            } else {
                $searchNames = [$id];
            }

            foreach ($searchNames as $name) {
                $whereCmd = $isWin ? "where $name 2>&1" : "which $name 2>&1";
                $out = [];
                $res = null;
                exec($whereCmd, $out, $res);
                if ($res === 0 && !empty($out)) {
                    $r['path'] = trim($out[0]);
                    $found = true;
                    break;
                }
            }

            if (!$found && $isWin && isset($fallbacks[$id])) {
                foreach ($fallbacks[$id] as $fb) {
                    if (file_exists($fb)) {
                        $r['path'] = $fb;
                        $found = true;
                        break;
                    }
                }
            }

            if (!$found && $isWin) {
                foreach ($searchNames as $name) {
                    $out = [];
                    $res = null;
                    exec("powershell -Command \"(Get-Command $name -ErrorAction SilentlyContinue).Source\"", $out, $res);
                    if ($res === 0 && !empty($out) && !empty(trim($out[0]))) {
                        $r['path'] = trim($out[0]);
                        $found = true;
                        break;
                    }
                }
            }

            if ($found) {
                $exe = escapeshellarg($r['path']);
                if ($id === 'java') {
                    $cmd = "$exe -version 2>&1";
                } elseif ($id === 'go') {
                    $cmd = "$exe version 2>&1";
                } else {
                    $cmd = "$exe --version 2>&1";
                }

                $vOut = [];
                $vRes = null;
                exec($cmd, $vOut, $vRes);
                if ($vRes === 0 && !empty($vOut)) {
                    $r['version'] = trim($vOut[0]);
                    // Clean up multi-line outputs
                    $parts = explode("\n", $r['version']);
                    $r['version'] = $parts[0];
                }
            }
        }

        $bridge = [
            'shared_dir' => $sharedDir,
            'config_exists' => file_exists($configPath),
            'last_sync' => file_exists($configPath) ? date('Y-m-d H:i:s', filemtime($configPath)) : null,
            'runtimes' => $runtimes
        ];

        if ($isJson) {
            echo json_encode(['system' => $system, 'bridge' => $bridge]);
            return;
        }

        echo "=== System Status ===\n";
        echo "SPP Version: {$system['spp_version']}\n";
        echo "PHP Version: {$system['php_version']} ({$system['os']})\n";
        echo "Database: {$system['db_status']}\n\n";

        echo "=== Health Report (Score: {$system['health_report']['score']}) ===\n";
        foreach ($system['health_report']['checks'] as $check) {
            echo str_pad("[{$check['status']}]", 8) . " {$check['name']}: {$check['detail']}\n";
        }
        
        echo "\n=== Runtimes ===\n";
        foreach ($bridge['runtimes'] as $id => $r) {
            $status = $r['path'] ? "DETECTED ({$r['version']})" : 'MISSING';
            echo str_pad($r['name'] . ":", 18) . $status . "\n";
        }
    }
}
