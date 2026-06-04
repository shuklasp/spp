<?php

namespace SPP;

/**
 * class PolyglotBridge
 *
 * Orchestrates cross-language resource sharing and remote routine execution.
 * Supports Python, Perl, and C++ (MSVC) through a JSON-based protocol.
 */
class PolyglotBridge extends \SPP\SPPObject
{
    private static array $runtimes = [];

    /**
     * static function setup()
     *
     * Performs runtime discovery and initializes the shared bridge environment.
     */
    public static function setup(): array
    {
        $log = [];
        $log[] = "Initializing Generic Polyglot Bridge Setup...";

        // 1. Discovery
        $runtimes = self::discoverRuntimes();
        $log[] = "Discovery complete: Found " . count(array_filter($runtimes, fn ($v) => !empty($v['path']))) . " active runtimes.";

        // 2. Directory Management
        $sharedDir = \SPP\Module::getConfig('shared_dir', 'bridge') ?: 'var/shared';

        // Fallback to relative if the configured absolute path does not exist (e.g., WSL vs Windows environment drift)
        if ((str_starts_with($sharedDir, '/') || str_contains($sharedDir, ':')) && !is_dir($sharedDir)) {
            $sharedDir = 'var/shared';
        }

        if (!str_starts_with($sharedDir, '/') && !str_contains($sharedDir, ':')) {
            $sharedDir = SPP_BASE_DIR . SPP_DS . '..' . SPP_DS . $sharedDir;
        }

        if (!is_dir($sharedDir)) {
            @mkdir($sharedDir, 0777, true);
        }

        $sharedDir = realpath($sharedDir) ?: $sharedDir;

        $bridgeDir = $sharedDir . SPP_DS . 'bridge';
        if (!is_dir($bridgeDir)) {
            mkdir($bridgeDir, 0777, true);
            $log[] = "Created bridge directory: " . $bridgeDir;
        }

        // 3. Generate Dispatchers inside SPP directory
        $frameworkLibDir = SPP_BASE_DIR . SPP_DS . 'lib' . SPP_DS . 'polyglot';
        if (!is_dir($frameworkLibDir)) {
            @mkdir($frameworkLibDir, 0777, true);
        }
        self::generateDispatchers($frameworkLibDir);
        $log[] = "Language dispatchers updated in " . $frameworkLibDir;

        // 4. Export Configuration
        $config = self::exportConfig($sharedDir, $runtimes);
        $log[] = "Bridge configuration exported to " . $sharedDir . SPP_DS . 'bridge_config.json';

        return ['success' => true, 'log' => $log, 'config' => $config];
    }

    /**
     * static function discoverRuntimes()
     *
     * Scans the system for Python, Perl, and C++ compilers.
     */
    public static function discoverRuntimes(): array
    {
        $runtimes = [
            'python'   => ['name' => 'Python', 'path' => '', 'version' => ''],
            'perl'     => ['name' => 'Perl',   'path' => '', 'version' => ''],
            'java'     => ['name' => 'Java',   'path' => '', 'version' => ''],
            'node'     => ['name' => 'Node.js', 'path' => '', 'version' => ''],
            'dotnet'   => ['name' => '.NET',   'path' => '', 'version' => ''],
            'go'       => ['name' => 'Go',     'path' => '', 'version' => ''],
            'compiler' => ['name' => 'C++ Compiler', 'path' => '', 'version' => '']
        ];

        $isWindows = PHP_OS_FAMILY === 'Windows';

        // Python (Prefer python3 on Linux/Unix)
        $pyBinaries = $isWindows ? ['python'] : ['python3', 'python'];
        foreach ($pyBinaries as $bin) {
            $path = self::findBinary($bin);
            if ($path) {
                $runtimes['python']['path'] = $path;
                $runtimes['python']['version'] = trim(@shell_exec("\"$path\" --version 2>&1") ?: 'Unknown');
                break;
            }
        }

        // Perl
        $perlPath = self::findBinary('perl');
        if ($perlPath) {
            $runtimes['perl']['path'] = $perlPath;
            $ver = @shell_exec("\"$perlPath\" -v 2>&1");
            if (preg_match('/v(\d+\.\d+\.\d+)/', $ver, $m)) {
                $runtimes['perl']['version'] = $m[1];
            }
        }

        // Java
        $javaPath = self::findBinary('java');
        if ($javaPath) {
            $runtimes['java']['path'] = $javaPath;
            $ver = @shell_exec("\"$javaPath\" -version 2>&1");
            if (preg_match('/version "([^"]+)"/i', $ver, $m)) {
                $runtimes['java']['version'] = $m[1];
            } elseif (preg_match('/openjdk version "([^"]+)"/i', $ver, $m)) {
                $runtimes['java']['version'] = $m[1];
            }
        }

        // .NET
        $dotnetPath = self::findBinary('dotnet');
        if ($dotnetPath) {
            $runtimes['dotnet']['path'] = $dotnetPath;
            $ver = @shell_exec("\"$dotnetPath\" --version 2>&1");
            if ($ver && !str_contains($ver, 'is not recognized')) {
                $runtimes['dotnet']['version'] = trim($ver);
            }
        }

        // Go
        $goPath = self::findBinary('go');
        if ($goPath) {
            $runtimes['go']['path'] = $goPath;
            $ver = @shell_exec("\"$goPath\" version 2>&1");
            if (preg_match('/go(\d+\.\d+\.\d+)/', $ver, $m)) {
                $runtimes['go']['version'] = $m[1];
            }
        }

        // Node.js
        $nodeBinaries = $isWindows ? ['node'] : ['node', 'nodejs'];
        foreach ($nodeBinaries as $bin) {
            $path = self::findBinary($bin);
            if ($path) {
                $runtimes['node']['path'] = $path;
                $ver = @shell_exec("\"$path\" --version 2>&1");
                if ($ver) {
                    $runtimes['node']['version'] = trim($ver);
                }
                break;
            }
        }

        // C++ Compiler
        if ($isWindows) {
            $clPath = self::findBinary('cl');
            if ($clPath) {
                $runtimes['compiler']['path'] = $clPath;
                $runtimes['compiler']['name'] = 'MSVC';
            } else {
                // Fallback scan for MSVC
                $vsPaths = [
                    'C:\Program Files\Microsoft Visual Studio\2022\Community\VC\Tools\MSVC',
                    'C:\Program Files\Microsoft Visual Studio\2022\Professional\VC\Tools\MSVC',
                    'C:\Program Files\Microsoft Visual Studio\2022\Enterprise\VC\Tools\MSVC',
                    'C:\Program Files (x86)\Microsoft Visual Studio\2019\Community\VC\Tools\MSVC',
                    'C:\Program Files (x86)\Microsoft Visual Studio\2017\Community\VC\Tools\MSVC'
                ];
                foreach ($vsPaths as $base) {
                    if (is_dir($base)) {
                        $tools = glob($base . '\*', GLOB_ONLYDIR);
                        if (!empty($tools)) {
                            $latest = end($tools);
                            $bin = $latest . '\bin\Hostx64\x64\cl.exe';
                            if (file_exists($bin)) {
                                $runtimes['compiler']['path'] = $bin;
                                $runtimes['compiler']['name'] = 'MSVC';
                                $vcvars = dirname($base, 2) . '\Auxiliary\Build\vcvars64.bat';
                                if (file_exists($vcvars)) {
                                    $runtimes['compiler']['vcvars'] = $vcvars;
                                }
                                break;
                            }
                        }
                    }
                }
            }
        } else {
            // Linux/Unix - Detect GCC or Clang
            foreach (['gcc', 'clang'] as $bin) {
                $path = self::findBinary($bin);
                if ($path) {
                    $runtimes['compiler']['path'] = $path;
                    $runtimes['compiler']['name'] = strtoupper($bin);
                    break;
                }
            }
        }

        if ($runtimes['compiler']['path']) {
            $id = @shell_exec("\"{$runtimes['compiler']['path']}\"" . ($isWindows ? " 2>&1" : " --version 2>&1"));
            if (preg_match('/(Version|(\d+\.\d+\.\d+)) ([\d\.]+)/', $id, $m)) {
                $runtimes['compiler']['version'] = $m[3] ?? $m[2] ?? 'Unknown';
            } elseif (preg_match('/(\d+\.\d+\.\d+)/', $id, $m)) {
                $runtimes['compiler']['version'] = $m[1];
            }
        }

        return $runtimes;
    }

    /**
     * private static function findBinary(string $name)
     *
     * Handles OS-specific binary discovery (which/where).
     */
    private static function findBinary(string $name): string
    {
        $cmd = PHP_OS_FAMILY === 'Windows' ? "where {$name} 2>&1" : "which {$name} 2>&1";
        $out = @shell_exec($cmd);
        if ($out && !str_contains($out, "not found") && !str_contains($out, "Could not find")) {
            return trim(explode("\n", $out)[0]);
        }
        return '';
    }

    /**
     * Dispatch the method call asynchronously.
     *
     * @param string $lang
     * @param string $module
     * @param string $func
     * @param array $args
     * @param bool $daemon
     * @return void
     */
    public static function callAsync(string $lang, string $module, string $func, array $args = [], bool $daemon = false): void
    {
        // Simple non-blocking background execution using proc_open or shell_exec
        $bridgeScript = __DIR__ . '/async_bridge.php';

        // If async_bridge.php doesn't exist, we fallback to a simple CLI one-liner
        $argsJson = base64_encode(json_encode([
            'lang' => $lang,
            'module' => $module,
            'func' => $func,
            'args' => $args,
            'daemon' => $daemon
        ]));

        $sppCli = realpath(SPP_BASE_DIR . '/spp.php');
        $cmd = "php \"{$sppCli}\" polyglot:async \"{$argsJson}\"";

        if (PHP_OS_FAMILY === 'Windows') {
            pclose(popen("start /B cmd /c \"{$cmd} > NUL 2>&1\"", "r"));
        } else {
            shell_exec("{$cmd} > /dev/null 2>&1 &");
        }
    }

    /**
     * static function call()
     *
     * Direct invocation of external routines.
     */
    public static function call(string $lang, string $module, string $func, array $args = [], bool $daemon = false): array
    {
        $lang = strtolower($lang);
        $sharedDir = \SPP\Module::getConfig('shared_dir', 'bridge') ?: 'var/shared';

        // Fallback to relative if the configured absolute path does not exist
        if ((str_starts_with($sharedDir, '/') || str_contains($sharedDir, ':')) && !is_dir($sharedDir)) {
            $sharedDir = 'var/shared';
        }

        if (!str_starts_with($sharedDir, '/') && !str_contains($sharedDir, ':')) {
            $sharedDir = SPP_BASE_DIR . SPP_DS . '..' . SPP_DS . $sharedDir;
        }

        if (!is_dir($sharedDir)) {
            @mkdir($sharedDir, 0777, true);
        }

        $sharedDir = realpath($sharedDir);

        // --- DAEMON MODE LOGIC ---
        if ($daemon) {
            $daemonsDir = $sharedDir . SPP_DS . 'bridge' . SPP_DS . 'daemons';
            @mkdir($daemonsDir, 0777, true);
            $hash = md5(realpath($module) ?: $module);
            $portFile = $daemonsDir . '/' . $hash . '.port';

            // Auto-spawn if port file doesn't exist
            if (!file_exists($portFile)) {
                $sppCli = realpath(SPP_BASE_DIR . '/spp.php');
                $spawnCmd = "php \"{$sppCli}\" polyglot:worker start \"{$module}\" \"{$lang}\"";
                $spawnOut = shell_exec($spawnCmd); // This blocks for up to 5s to wait for port file
                error_log("Daemon Mode: Auto-spawn output: " . trim($spawnOut));
            }

            if (file_exists($portFile)) {
                $port = (int)trim(file_get_contents($portFile));
                error_log("Daemon Mode: Attempting to connect to 127.0.0.1:{$port} for module {$module}");
                $fp = null;
                for ($retry = 0; $retry < 10; $retry++) {
                    $fp = @fsockopen("127.0.0.1", $port, $errno, $errstr, 2);
                    if ($fp) {
                        break;
                    }
                    usleep(100000); // Wait 100ms
                }

                if ($fp) {
                    error_log("Daemon Mode: Connected. Sending payload...");
                    $payload = json_encode(['func' => $func, 'args' => $args]) . "\n";
                    fwrite($fp, $payload);
                    $response = '';
                    while (!feof($fp)) {
                        $chunk = fgets($fp, 4096);
                        if ($chunk === false) {
                            break;
                        }
                        $response .= $chunk;
                        if (str_ends_with($chunk, "\n")) {
                            break;
                        }
                    }
                    fclose($fp);
                    error_log("Daemon Mode: Received response: " . trim($response));

                    $decoded = json_decode($response, true);
                    if ($decoded !== null) {
                        return ['success' => true, 'data' => $decoded];
                    } else {
                        return ['success' => false, 'error' => 'Invalid JSON from daemon: ' . $response];
                    }
                } else {
                    error_log("Daemon Mode: fsockopen failed after retries. Errno: {$errno}, Errstr: {$errstr}");
                    // Socket connection failed, fallback to ephemeral
                    @unlink($portFile);
                }
            } else {
                error_log("Daemon Mode: portFile {$portFile} does not exist after spawn attempt.");
            }
        }
        // --- END DAEMON LOGIC ---

        $runtimes = self::discoverRuntimes();
        $binary = $runtimes[$lang]['path'] ?? $lang;
        if (empty($binary) && $lang !== 'compiler') {
            return ['success' => false, 'error' => "Runtime for {$lang} not discovered."];
        }

        if ($lang === 'java') {
            $javaLib = SPP_BASE_DIR . SPP_DS . 'lib' . SPP_DS . 'java';
            $cpSep = PHP_OS_FAMILY === 'Windows' ? ';' : ':';
            $argsJson = base64_encode(json_encode($args));
            $command = "\"{$binary}\" -cp \".{$cpSep}{$javaLib}\" \"{$module}\" \"{$func}\" \"{$argsJson}\"";
        } elseif ($lang === 'dotnet') {
            if (is_dir($module) || str_ends_with($module, '.csproj')) {
                $command = "\"{$binary}\" run --project \"{$module}\"";
            } else {
                $command = "\"{$binary}\" \"{$module}\"";
            }
        } elseif ($lang === 'go') {
            $moduleAbs = realpath($module) ?: $module;
            $moduleDir = dirname($moduleAbs);
            $moduleFile = basename($moduleAbs);
            $cdCmd = PHP_OS_FAMILY === 'Windows' ? "cd /D \"{$moduleDir}\"" : "cd \"{$moduleDir}\"";
            $command = "{$cdCmd} && \"{$binary}\" run \"{$moduleFile}\" \"{$func}\"";
        } elseif ($lang === 'compiler') {
            $compiler = $runtimes['compiler']['path'];
            if (!$compiler) {
                return ['success' => false, 'error' => "C++ Compiler not found."];
            }

            $outputExe = $sharedDir . SPP_DS . 'bridge' . SPP_DS . 'temp_bin.exe';
            if (PHP_OS_FAMILY === 'Windows') {
                $vcvars = $runtimes['compiler']['vcvars'] ?? '';
                $prefix = $vcvars ? "call \"{$vcvars}\" && " : "";
                $compileCmd = "{$prefix}\"{$compiler}\" /EHsc \"{$module}\" /Fe:\"{$outputExe}\" 2>&1";
            } else {
                $outputExe = $sharedDir . SPP_DS . 'bridge' . SPP_DS . 'temp_bin';
                $compileCmd = "\"{$compiler}\" \"{$module}\" -o \"{$outputExe}\" 2>&1";
            }

            $cOut = @shell_exec($compileCmd);
            if (!file_exists($outputExe)) {
                return ['success' => false, 'error' => "Compilation failed: " . $cOut];
            }
            $command = "\"{$outputExe}\"";
        } else {
            $ext = ($lang === 'python' ? 'py' : ($lang === 'perl' ? 'pl' : ($lang === 'node' ? 'js' : '')));
            $frameworkLibDir = SPP_BASE_DIR . SPP_DS . 'lib' . SPP_DS . 'polyglot';
            $dispatchScript = $frameworkLibDir . SPP_DS . 'dispatch.' . $ext;

            if (!file_exists($dispatchScript)) {
                return ['success' => false, 'error' => "Dispatcher script for {$lang} not found."];
            }
            $command = "\"{$binary}\" \"{$dispatchScript}\" \"{$module}\" \"{$func}\"";
        }

        $env = array_merge(getenv(), [
            'DOTNET_CLI_HOME' => $sharedDir . SPP_DS . 'bridge' . SPP_DS . '.dotnet',
            'GOCACHE' => $sharedDir . SPP_DS . 'bridge' . SPP_DS . '.gocache',
            'DOTNET_NOLOGO' => '1',
            'DOTNET_SKIP_FIRST_TIME_EXPERIENCE' => '1'
        ]);

        $descriptors = [
            0 => ["pipe", "r"], // stdin
            1 => ["pipe", "w"], // stdout
            2 => ["pipe", "w"]  // stderr
        ];

        $process = proc_open($command, $descriptors, $pipes, $sharedDir . SPP_DS . 'bridge', $env);

        if (is_resource($process)) {
            fwrite($pipes[0], json_encode($args));
            fclose($pipes[0]);

            $stdout = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);

            $status = proc_close($process);

            if ($status !== 0) {
                return ['success' => false, 'error' => "Runtime Error [{$status}]: " . $stderr];
            }

            $result = json_decode($stdout, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                // If not JSON, return the raw stdout as the data
                return ['success' => true, 'data' => trim($stdout)];
            }

            return ['success' => true, 'data' => $result];
        }

        return ['success' => false, 'error' => "Failed to spawn process."];
    }

    private static function generateDispatchers(string $bridgeDir): void
    {
        $writeIfDifferent = function ($file, $content) {
            if (!file_exists($file) || file_get_contents($file) !== $content) {
                file_put_contents($file, $content);
            }
        };

        // Python Dispatcher
        $py = '
import sys, json, importlib, os, socket

def load_module(module_name):
    bridge_dir = os.path.dirname(os.path.abspath(__file__))
    if bridge_dir not in sys.path: sys.path.insert(0, bridge_dir)
    
    if "/" in module_name or "\\\\" in module_name:
        import importlib.util
        if not module_name.endswith(".py"): module_name += ".py"
        spec = importlib.util.spec_from_file_location("dynamic_module", module_name)
        module = importlib.util.module_from_spec(spec)
        sys.modules["dynamic_module"] = module
        spec.loader.exec_module(module)
        return module
    return importlib.import_module(module_name)

def main():
    module_name = sys.argv[1]
    
    if "--daemon" in sys.argv:
        port_file = sys.argv[sys.argv.index("--daemon") + 1]
        try:
            module = load_module(module_name)
        except Exception as e:
            sys.stderr.write(str(e))
            sys.exit(1)
            
        s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        s.bind(("127.0.0.1", 0))
        s.listen(5)
        port = s.getsockname()[1]
        with open(port_file, "w") as f: f.write(str(port))
        
        while True:
            conn, addr = s.accept()
            data = b""
            while b"\n" not in data:
                chunk = conn.recv(4096)
                if not chunk: break
                data += chunk
            if not data:
                conn.close()
                continue
            try:
                req = json.loads(data.decode("utf-8").strip())
                func = getattr(module, req["func"])
                args = req.get("args", [])
                if isinstance(args, list): res = func(*args)
                elif isinstance(args, dict): res = func(**args)
                else: res = func()
                conn.sendall(json.dumps(res).encode("utf-8") + b"\n")
            except Exception as e:
                pass
            finally:
                conn.close()
    else:
        func_name = sys.argv[2]
        try:
            module = load_module(module_name)
            args_raw = sys.stdin.read()
            args = json.loads(args_raw) if args_raw else []
            func = getattr(module, func_name)
            if isinstance(args, list): result = func(*args)
            elif isinstance(args, dict): result = func(**args)
            else: result = func()
            print(json.dumps(result))
        except Exception as e:
            sys.stderr.write(str(e))
            sys.exit(1)

if __name__ == "__main__":
    main()
';
        $writeIfDifferent($bridgeDir . SPP_DS . 'dispatch.py', trim($py));

        // Perl Dispatcher
        $pl = '
use strict;
use warnings;
use JSON;
use File::Basename;
use IO::Socket::INET;
use lib dirname(__FILE__);
use Cwd qw(abs_path);

my $module_file = abs_path($ARGV[0]) || $ARGV[0];

# Deduce package name from file
my $pkg = "main";
if (open my $fh, "<", $module_file) {
    while (<$fh>) {
        if (/^\s*package\s+([A-Za-z0-9_:]+)\s*;/) {
            $pkg = $1;
            last;
        }
    }
    close $fh;
}

if (grep { $_ eq "--daemon" } @ARGV) {
    my $port_file = "";
    for (my $i = 0; $i < @ARGV; $i++) {
        if ($ARGV[$i] eq "--daemon") { $port_file = $ARGV[$i+1]; last; }
    }
    
    eval "require \'$module_file\'";
    if ($@) { die $@; }
    
    my $server = IO::Socket::INET->new(LocalHost => "127.0.0.1", LocalPort => 0, Proto => "tcp", Listen => 5, Reuse => 1) or die "Cannot create socket: $!";
    open(my $fh, ">", $port_file) or die "Cannot write to $port_file: $!";
    print $fh $server->sockport();
    close($fh);
    
    while (my $client = $server->accept()) {
        my $data = "";
        while (<$client>) {
            $data .= $_;
            last if /\n/;
        }
        next unless $data;
        my $req = eval { decode_json($data) };
        if (!$@ && $req) {
            my $func = $req->{func};
            my $args = $req->{args} || [];
            my $result;
            if (ref($args) eq "ARRAY") {
                no strict "refs";
                $result = eval { &{"${pkg}::${func}"}(@$args) };
            } else {
                no strict "refs";
                $result = eval { &{"${pkg}::${func}"}($args) };
            }
            print $client encode_json($result) . "\n" if !$@;
        }
        close($client);
    }
} else {
    my $func = $ARGV[1];
    my $args_raw = do { local $/; <STDIN> };
    my $args = $args_raw ? decode_json($args_raw) : [];
    eval "require \'$module_file\'";
    if ($@) { die $@; }
    my $result;
    if (ref($args) eq "ARRAY") {
        no strict "refs";
        $result = &{"${pkg}::${func}"}(@$args);
    } else {
        no strict "refs";
        $result = &{"${pkg}::${func}"}($args);
    }
    print encode_json($result);
}
';
        $writeIfDifferent($bridgeDir . SPP_DS . 'dispatch.pl', trim($pl));

        // Node.js Dispatcher
        $js = '
const fs = require("fs");
const path = require("path");
const net = require("net");

function loadModule(moduleName) {
    if (fs.existsSync(moduleName) || fs.existsSync(moduleName + ".js")) {
        return require(path.resolve(moduleName));
    }
    return require(moduleName);
}

async function main() {
    const moduleName = process.argv[2];
    
    if (process.argv.includes("--daemon")) {
        const portFile = process.argv[process.argv.indexOf("--daemon") + 1];
        let mod;
        try { mod = loadModule(moduleName); } catch(e) { console.error(e); process.exit(1); }
        
        const server = net.createServer((socket) => {
            let buffer = "";
            socket.on("data", async (data) => {
                buffer += data.toString();
                if (buffer.includes("\n")) {
                    try {
                        const req = JSON.parse(buffer.trim());
                        const func = mod[req.func];
                        const args = req.args || [];
                        let result = func(...(Array.isArray(args) ? args : [args]));
                        if (result instanceof Promise) result = await result;
                        socket.write(JSON.stringify(result) + "\n");
                    } catch (e) {
                        // ignore or error
                    }
                    socket.end();
                }
            });
        });
        
        server.listen(0, "127.0.0.1", () => {
            fs.writeFileSync(portFile, server.address().port.toString());
        });
    } else {
        const funcName = process.argv[3];
        const argsRaw = fs.readFileSync(0, "utf8");
        const args = argsRaw ? JSON.parse(argsRaw) : [];
        try {
            const mod = loadModule(moduleName);
            const func = mod[funcName];
            let result = func(...(Array.isArray(args) ? args : [args]));
            if (result instanceof Promise) result = await result;
            process.stdout.write(JSON.stringify(result));
        } catch (e) {
            process.stderr.write(e.stack || e.message);
            process.exit(1);
        }
    }
}
main();
';
        $writeIfDifferent($bridgeDir . SPP_DS . 'dispatch.js', trim($js));
    }

    private static function exportConfig(string $sharedDir, array $runtimes): array
    {
        $dbConfig = [
            'dbtype' => \SPP\Module::getConfig('dbtype', 'sppdb'),
            'dbhost' => \SPP\Module::getConfig('dbhost', 'sppdb'),
            'dbname' => \SPP\Module::getConfig('dbname', 'sppdb'),
            'dbuser' => \SPP\Module::getConfig('dbuser', 'sppdb'),
            'dbpasswd' => \SPP\Module::getConfig('dbpasswd', 'sppdb'),
        ];

        $bridgeData = [
            'timestamp' => time(),
            'spp_version' => defined('SPP_VER') ? SPP_VER : '0.5',
            'base_dir' => SPP_BASE_DIR,
            'database' => $dbConfig,
            'modules' => \SPP\Registry::get('__mods') ?: [],
            'runtimes' => $runtimes,
            'bridge_settings' => [
                'shared_dir' => $sharedDir,
            ]
        ];

        $configPath = $sharedDir . SPP_DS . 'bridge_config.json';
        $jsonStr = json_encode($bridgeData, JSON_PRETTY_PRINT);
        
        if ($jsonStr === false) {
            $jsonStr = '{}'; // Fallback if encoding fails
        }
        
        $result = @file_put_contents($configPath, $jsonStr);
        if ($result === false) {
            $error = error_get_last();
            error_log("PolyglotBridge Error: Failed to write config to {$configPath}. Reason: " . ($error['message'] ?? 'Unknown'));
        }
        
        return $bridgeData;
    }
}
