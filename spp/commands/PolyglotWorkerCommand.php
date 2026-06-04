<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class PolyglotWorkerCommand extends Command
{
    protected string $name = 'polyglot:worker';
    protected string $description = 'Manage Polyglot persistent workers';

    public function execute(array $args): void
    {
        $action = $args[2] ?? null;
        $module = $args[3] ?? null;
        $lang = $args[4] ?? null;

        if (!$action || !in_array($action, ['start', 'stop', 'restart', 'status'])) {
            echo "Usage: spp polyglot:worker [start|stop|restart|status] <module> [<lang>]\n";
            return;
        }

        $sharedDir = \SPP\Module::getConfig('shared_dir', 'bridge') ?: 'var/shared';
        if ((str_starts_with($sharedDir, '/') || str_contains($sharedDir, ':')) && !is_dir($sharedDir)) {
            $sharedDir = 'var/shared';
        }
        if (!str_starts_with($sharedDir, '/') && !str_contains($sharedDir, ':')) {
            $sharedDir = SPP_BASE_DIR . SPP_DS . '..' . SPP_DS . $sharedDir;
        }
        $sharedDir = realpath($sharedDir);
        
        $daemonsDir = $sharedDir . SPP_DS . 'bridge' . SPP_DS . 'daemons';
        if (!is_dir($daemonsDir)) {
            @mkdir($daemonsDir, 0777, true);
        }

        if ($action === 'async') {
            $this->executeAsync($args);
            return;
        }

        if ($module) {
            $this->manageWorker($action, $module, $lang, $daemonsDir, $sharedDir);
        } else {
            if ($action === 'status') {
                $files = glob($daemonsDir . '/*.port');
                if (empty($files)) {
                    echo "No running polyglot workers found.\n";
                } else {
                    foreach ($files as $file) {
                        $port = trim(file_get_contents($file));
                        $modulePath = trim(@file_get_contents(str_replace('.port', '.module', $file)));
                        echo "Worker running on port {$port} -> {$modulePath}\n";
                    }
                }
            } else {
                echo "Module path required for action {$action}\n";
            }
        }
    }

    private function manageWorker(string $action, string $module, ?string $lang, string $daemonsDir, string $sharedDir): void
    {
        $hash = md5(realpath($module) ?: $module);
        $portFile = $daemonsDir . '/' . $hash . '.port';
        $pidFile = $daemonsDir . '/' . $hash . '.pid';
        $moduleFile = $daemonsDir . '/' . $hash . '.module';

        if ($action === 'stop' || $action === 'restart') {
            if (file_exists($pidFile)) {
                $pid = trim(file_get_contents($pidFile));
                if (PHP_OS_FAMILY === 'Windows') {
                    @exec("taskkill /F /PID {$pid} 2>nul");
                } else {
                    @exec("kill -9 {$pid} 2>/dev/null");
                }
                @unlink($pidFile);
                @unlink($portFile);
                @unlink($moduleFile);
                echo "Worker for {$module} stopped.\n";
            } else {
                echo "Worker for {$module} is not running.\n";
            }
            if ($action === 'stop') return;
        }

        if ($action === 'start' || $action === 'restart') {
            if (file_exists($pidFile)) {
                echo "Worker for {$module} is already running.\n";
                return;
            }

            if (!$lang) {
                // Infer language from extension
                $ext = pathinfo($module, PATHINFO_EXTENSION);
                $langMap = ['py' => 'python', 'js' => 'node', 'go' => 'go', 'cs' => 'dotnet', 'pl' => 'perl', 'java' => 'java'];
                $lang = $langMap[$ext] ?? null;
            }

            if (!$lang) {
                echo "Could not infer language for {$module}. Please provide lang argument.\n";
                return;
            }

            // Command execution mapping
            $runtimes = \SPP\PolyglotBridge::discoverRuntimes();
            $binary = $runtimes[$lang]['path'] ?? $lang;

            // Construct the daemon command
            // We pass '--daemon' flag to the dispatch scripts.
            $command = "";
            $frameworkLibDir = SPP_BASE_DIR . SPP_DS . 'lib' . SPP_DS . 'polyglot';
            if ($lang === 'python') {
                $dispatchScript = $frameworkLibDir . '/dispatch.py';
                $command = "\"{$binary}\" \"{$dispatchScript}\" \"{$module}\" --daemon \"{$portFile}\"";
            } elseif ($lang === 'node') {
                $dispatchScript = $frameworkLibDir . '/dispatch.js';
                $command = "\"{$binary}\" \"{$dispatchScript}\" \"{$module}\" --daemon \"{$portFile}\"";
            } elseif ($lang === 'perl') {
                $dispatchScript = $frameworkLibDir . '/dispatch.pl';
                $command = "\"{$binary}\" \"{$dispatchScript}\" \"{$module}\" --daemon \"{$portFile}\"";
            } elseif ($lang === 'java') {
                $javaLib = SPP_BASE_DIR . SPP_DS . 'lib' . SPP_DS . 'java';
                $cpSep = PHP_OS_FAMILY === 'Windows' ? ';' : ':';
                $command = "\"{$binary}\" -cp \".{$cpSep}{$javaLib}\" \"{$module}\" --daemon \"{$portFile}\"";
            } elseif ($lang === 'go') {
                $moduleDir = dirname(realpath($module) ?: $module);
                $moduleFile = basename($module);
                $cdCmd = PHP_OS_FAMILY === 'Windows' ? "cd /D \"{$moduleDir}\"" : "cd \"{$moduleDir}\"";
                $command = "{$cdCmd} && \"{$binary}\" run \"{$moduleFile}\" --daemon \"{$portFile}\"";
            } elseif ($lang === 'dotnet') {
                $proj = (is_dir($module) || str_ends_with($module, '.csproj')) ? "--project \"{$module}\"" : "\"{$module}\"";
                $command = "\"{$binary}\" run {$proj} -- --daemon \"{$portFile}\"";
            } elseif ($lang === 'compiler') {
                $outputExe = $daemonsDir . '/' . $hash . (PHP_OS_FAMILY === 'Windows' ? '.exe' : '.bin');
                if (PHP_OS_FAMILY === 'Windows') {
                    $runtimes = \SPP\PolyglotBridge::discoverRuntimes();
                    $vcvars = $runtimes['compiler']['vcvars'] ?? '';
                    $prefix = $vcvars ? "call \"{$vcvars}\" && " : "";
                    $compileCmd = "{$prefix}\"{$binary}\" /EHsc \"{$module}\" /Fe:\"{$outputExe}\" 2>&1";
                } else {
                    $compileCmd = "\"{$binary}\" \"{$module}\" -o \"{$outputExe}\" 2>&1";
                }
                $cOut = shell_exec($compileCmd);
                if (!file_exists($outputExe)) {
                    echo "C++ Compilation failed: {$cOut}\n";
                    return;
                }
                $command = "\"{$outputExe}\" --daemon \"{$portFile}\"";
            }

            if (!$command) {
                echo "Daemon mode not supported for language {$lang} yet.\n";
                return;
            }

            // Launch process in background and capture PID
            $logFile = $daemonsDir . '/' . $hash . '.log';
            if (PHP_OS_FAMILY === 'Windows') {
                $vbsFile = $daemonsDir . '/daemon_runner.vbs';
                if (!file_exists($vbsFile)) {
                    $vbsCode = "Set objShell = CreateObject(\"WScript.Shell\")\n";
                    $vbsCode .= "objShell.CurrentDirectory = WScript.Arguments(1)\n";
                    $vbsCode .= "objShell.Run WScript.Arguments(0), 0, False\n";
                    file_put_contents($vbsFile, $vbsCode);
                }
                $batFile = $daemonsDir . '/' . $hash . '.bat';
                file_put_contents($batFile, "{$command} > \"{$logFile}\" 2>&1");
                $projectRoot = realpath(SPP_BASE_DIR . '/..');
                $cmd = "cscript //nologo \"{$vbsFile}\" \"{$batFile}\" \"{$projectRoot}\"";
                pclose(popen($cmd, "r"));
            } else {
                $cmd = "cd " . escapeshellarg(realpath(SPP_BASE_DIR . '/..')) . " && nohup {$command} > \"{$logFile}\" 2>&1 & echo $!";
                $pid = trim(shell_exec($cmd));
                file_put_contents($pidFile, $pid);
            }

            file_put_contents($moduleFile, $module);
            echo "Started worker for {$module}...\n";
            
            // Wait for port file to appear (max 30 seconds for compiled languages)
            $attempts = 0;
            while (!file_exists($portFile) && $attempts < 300) {
                usleep(100000); // 100ms
                $attempts++;
            }
            if (file_exists($portFile)) {
                echo "Worker bound to port " . trim(file_get_contents($portFile)) . "\n";
            } else {
                echo "Warning: Worker did not bind to a port in time. Check logs at {$logFile}\n";
            }
        }
    }

    private function executeAsync(array $args): void
    {
        if (empty($args[3])) {
            error_log("PolyglotAsync: Missing payload.");
            return;
        }

        $payload = json_decode(base64_decode($args[3]), true);
        if (!$payload) {
            error_log("PolyglotAsync: Invalid payload.");
            return;
        }

        $lang = $payload['lang'];
        $module = $payload['module'];
        $func = $payload['func'];
        $funcArgs = $payload['args'] ?? [];
        $daemon = $payload['daemon'] ?? false;

        \SPP\PolyglotBridge::call($lang, $module, $func, $funcArgs, $daemon);
    }
}
