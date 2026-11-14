<?php
declare(strict_types=1);

namespace SPP\Admin\Services;

if (!class_exists(\SPP\CLI\Command::class)) {
    require_once dirname(__DIR__, 2) . '/core/class.command.php';
}
if (!class_exists(\SPP\CLI\CommandManager::class)) {
    require_once dirname(__DIR__, 2) . '/core/class.commandmanager.php';
}
if (!defined('SPP_BASE_DIR')) {
    define('SPP_BASE_DIR', dirname(__DIR__, 2));
}

require_once __DIR__ . '/DomainAdminService.php';
require_once __DIR__ . '/DatabaseAdminService.php';
require_once __DIR__ . '/StorageAdminService.php';
require_once __DIR__ . '/CronQueueAdminService.php';
require_once __DIR__ . '/DeployAdminService.php';
require_once __DIR__ . '/DeveloperAdminService.php';
require_once __DIR__ . '/ManifestAdminService.php';
require_once __DIR__ . '/MailAdminService.php';
require_once __DIR__ . '/ServerAdminService.php';
require_once __DIR__ . '/AppsAdminService.php';
require_once __DIR__ . '/RuntimeAdminService.php';
require_once __DIR__ . '/ResellerAdminService.php';
require_once __DIR__ . '/CpanelImporterService.php';
require_once __DIR__ . '/TimeMachineManager.php';
require_once __DIR__ . '/AiSreService.php';
require_once __DIR__ . '/WebhookAdminService.php';
require_once __DIR__ . '/ApiStudioService.php';
require_once __DIR__ . '/ApmAdminService.php';
require_once __DIR__ . '/FleetAdminService.php';
require_once __DIR__ . '/MetricsAdminService.php';
require_once __DIR__ . '/ShieldAdminService.php';
require_once __DIR__ . '/PolyglotAdminService.php';
require_once __DIR__ . '/DatabaseProvisionerService.php';
require_once __DIR__ . '/RedisAdminService.php';
require_once dirname(__DIR__, 2) . '/core/server/drivers/DockerEngineDriver.php';
require_once __DIR__ . '/SystemPackageService.php';
require_once __DIR__ . '/WatchdogService.php';
require_once __DIR__ . '/BindDaemonService.php';
require_once __DIR__ . '/HostProvisionService.php';
require_once __DIR__ . '/SystemDoctorService.php';
require_once __DIR__ . '/SecretsService.php';
require_once __DIR__ . '/OperationEngine.php';
if (file_exists(dirname(__DIR__, 2) . '/agent/src/Client.php')) {
    require_once dirname(__DIR__, 2) . '/agent/src/Client.php';
}

use SPP\CLI\CommandManager;
use Symfony\Component\Yaml\Yaml;

/**
 * Class CommandBridge
 * Central Web-CLI synchronization gateway.
 * Maps Web UI actions directly to underlying SPP CLI commands and provides command introspection.
 */
class CommandBridge
{
    /**
     * Executes a native CLI command securely from the Web UI.
     */
    public static function executeCommand(string $commandName, array $args = []): array
    {
        $commands = CommandManager::discover();
        if (!isset($commands[$commandName])) {
            return ['success' => false, 'error' => "Command '{$commandName}' not found."];
        }

        $cmdObj = $commands[$commandName];

        // Ensure --json flag is passed for structured output where supported
        if (!isset($args['json']) && !in_array('--json', $args, true)) {
            $args['json'] = '1';
        }

        // If command is marked CLI-only and running under Web SAPI (Apache/FPM):
        if ($cmdObj->isCLIOnly() && PHP_SAPI !== 'cli') {
            // Interactive REPL shells are strictly disallowed from web execution
            if (in_array($commandName, ['shell', 'tinker'], true)) {
                return ['success' => false, 'error' => "Security Exception: Command '{$commandName}' requires an interactive terminal and cannot be run from the Web UI."];
            }

            // Bridge execution via real CLI subprocess where PHP_SAPI === 'cli'
            return self::executeViaCliProcess($commandName, $args);
        }

        $result = CommandManager::execute($commandName, $args);

        // Check if output is JSON
        if ($result['success'] && !empty($result['output'])) {
            $decoded = json_decode(trim($result['output']), true);
            if (is_array($decoded)) {
                return array_merge(['success' => true], $decoded, ['output' => $result['output'], 'raw_output' => $result['output']]);
            }
        }

        return $result;
    }

    /**
     * Executes a command via a real CLI subprocess where PHP_SAPI === 'cli'.
     */
    public static function executeViaCliProcess(string $commandName, array $args = []): array
    {
        $phpBinary = defined('PHP_BINARY') && file_exists(PHP_BINARY) ? PHP_BINARY : 'php';
        $sppPath = dirname(__DIR__, 2) . '/spp.php';
        if (!file_exists($sppPath)) {
            $sppPath = dirname(__DIR__, 2) . '/spp';
        }

        $cmdParts = [escapeshellarg($phpBinary), escapeshellarg($sppPath), escapeshellarg($commandName)];
        foreach ($args as $k => $v) {
            if (is_numeric($k)) {
                $cmdParts[] = escapeshellarg((string) $v);
            } else {
                if (str_starts_with($k, '-')) {
                    $cmdParts[] = escapeshellarg("{$k}={$v}");
                } else {
                    $cmdParts[] = escapeshellarg("--{$k}={$v}");
                }
            }
        }

        $cmdLine = implode(' ', $cmdParts);
        $tmpOut = tempnam(sys_get_temp_dir(), 'spp_cli_out_');
        $tmpErr = tempnam(sys_get_temp_dir(), 'spp_cli_err_');

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', $tmpOut, 'w'],
            2 => ['file', $tmpErr, 'w']
        ];

        $proc = @proc_open($cmdLine, $descriptors, $pipes, dirname(__DIR__, 2));
        if (!is_resource($proc)) {
            @unlink($tmpOut);
            @unlink($tmpErr);
            return ['success' => false, 'error' => "Failed to spawn CLI subprocess for '{$commandName}'."];
        }

        fclose($pipes[0]);
        $exitCode = proc_close($proc);

        $output = file_exists($tmpOut) ? (string)file_get_contents($tmpOut) : '';
        $stderr = file_exists($tmpErr) ? (string)file_get_contents($tmpErr) : '';
        @unlink($tmpOut);
        @unlink($tmpErr);

        if ($exitCode !== 0 && empty($output)) {
            return ['success' => false, 'error' => !empty($stderr) ? trim($stderr) : "Command exited with code {$exitCode}."];
        }

        $trimmed = trim($output);
        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return array_merge(['success' => true], $decoded, ['output' => $trimmed, 'raw_output' => $trimmed]);
        }

        return ['success' => $exitCode === 0, 'output' => $trimmed, 'raw_output' => $trimmed];
    }

    /**
     * Executes an interactive or batch shell command in the workspace directory.
     * Supports polyglot CLI tools, SPP commands, git, composer, and system utilities.
     * Optionally delivers output chunks in real time via $onChunk callback.
     */
    public static function runShellCommand(string $command, string $appContext = 'default', ?callable $onChunk = null): array
    {
        $command = trim($command);
        if ($command === '') {
            return ['success' => false, 'error' => 'No command provided.'];
        }

        // Block interactive commands that would hang stdin without a pseudo-TTY
        $tokens = preg_split('/\s+/', $command);
        $firstToken = strtolower($tokens[0] ?? '');
        $blockedInteractive = [
            'nano', 'vi', 'vim', 'pico', 'emacs', 'top', 'htop', 'less', 'more',
            'ssh', 'telnet', 'ftp', 'sftp', 'login', 'passwd', 'su'
        ];
        if (in_array($firstToken, $blockedInteractive, true) ||
            preg_match('/^(php\s+spp\.php|spp)\s+(shell|tinker)/i', $command)) {
            $msg = "Command '{$firstToken}' requires interactive TTY input and cannot be run in web terminal.\nPlease use non-interactive commands (e.g. 'php spp.php sys:status', 'git status', 'ls', 'composer show').";
            if ($onChunk !== null) {
                $onChunk($msg, 'stderr');
            }
            return [
                'success' => false,
                'output' => $msg,
                'exit_code' => 1
            ];
        }

        // Translate common Unix aliases on Windows when executing in cmd.exe
        if (PHP_OS_FAMILY === 'Windows') {
            if (preg_match('/^ls(\s+-[a-zA-Z0-9]+)?(\s+.*)?$/i', $command, $m)) {
                $flags = strtolower($m[1] ?? '');
                $rest = trim($m[2] ?? '');
                $command = (str_contains($flags, 'l') ? 'dir' : 'dir /b') . ($rest !== '' ? ' ' . $rest : '');
            } elseif (strtolower($command) === 'll') {
                $command = 'dir';
            } elseif (strtolower($command) === 'pwd') {
                $command = 'cd';
            } elseif (preg_match('/^cat\s+(.+)$/i', $command, $m)) {
                $command = 'type ' . $m[1];
            } elseif (preg_match('/^which\s+(.+)$/i', $command, $m)) {
                $command = 'where ' . $m[1];
            }
        }

        // Rewrite 'spp ...' to 'php spp.php ...' for universal execution
        if (preg_match('/^spp\s+/i', $command)) {
            $command = preg_replace('/^spp\s+/i', 'php spp.php ', $command);
        }

        // Auto-scope app context for commands that would otherwise scan all apps in the workspace
        if (!empty($appContext) && $appContext !== 'default') {
            if (preg_match('/^(php\s+spp\.php)\s+(test|route:list|routes|module:doctor|eval)\b/i', $command) && !str_contains($command, '--app')) {
                $command .= ' --app=' . escapeshellarg($appContext);
            }
        }

        // Inject safe.directory=* into git commands to eliminate dubious ownership exceptions across web/daemon user contexts
        if (preg_match('/^git\s+/i', $command)) {
            $command = preg_replace('/^git\s+/i', 'git -c safe.directory=* ', $command);
        }

        // Determine working directory: prefer SPP_APP_DIR (project root)
        $cwd = defined('SPP_APP_DIR') && is_dir(SPP_APP_DIR) ? SPP_APP_DIR : dirname(__DIR__, 2);

        $tmpOut = tempnam(sys_get_temp_dir(), 'spp_cmd_out_');
        $tmpErr = tempnam(sys_get_temp_dir(), 'spp_cmd_err_');

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', $tmpOut, 'w'],
            2 => ['file', $tmpErr, 'w']
        ];

        // On Windows, route standard CLI binaries and tools through cmd.exe for instant (~15ms) launch
        if (PHP_OS_FAMILY === 'Windows') {
            if (preg_match('/^(powershell|pwsh)\b/i', $command)) {
                $cmdLine = 'powershell.exe -NoProfile -NonInteractive -ExecutionPolicy Bypass -Command ' . escapeshellarg($command);
            } else {
                $cmdLine = 'cmd.exe /d /c ' . $command;
            }
        } else {
            $cmdLine = 'bash -c ' . escapeshellarg($command);
        }

        $proc = @proc_open($cmdLine, $descriptors, $pipes, $cwd);
        if (!is_resource($proc)) {
            @unlink($tmpOut);
            @unlink($tmpErr);
            $err = "Failed to spawn shell process for '{$command}'.";
            if ($onChunk !== null) {
                $onChunk($err, 'stderr');
            }
            return ['success' => false, 'error' => $err];
        }

        // Close stdin immediately so process does not block waiting for input
        fclose($pipes[0]);

        $stdout = '';
        $stderr = '';
        $fhOut = @fopen($tmpOut, 'rb');
        $fhErr = @fopen($tmpErr, 'rb');

        $timeout = 90;
        $startTime = time();
        while (true) {
            if ($fhOut) {
                clearstatcache(false, $tmpOut);
                $chunk = fread($fhOut, 8192);
                if ($chunk !== false && strlen($chunk) > 0) {
                    $stdout .= $chunk;
                    if ($onChunk !== null) {
                        $onChunk($chunk, 'stdout');
                    }
                }
            }
            if ($fhErr) {
                clearstatcache(false, $tmpErr);
                $chunk = fread($fhErr, 8192);
                if ($chunk !== false && strlen($chunk) > 0) {
                    $stderr .= $chunk;
                    if ($onChunk !== null) {
                        $onChunk($chunk, 'stderr');
                    }
                }
            }

            $status = proc_get_status($proc);
            if (!$status['running']) {
                break;
            }
            if (time() - $startTime > $timeout) {
                proc_terminate($proc, 9);
                $timeoutMsg = "\n[Execution timed out after {$timeout} seconds]";
                $stderr .= $timeoutMsg;
                if ($onChunk !== null) {
                    $onChunk($timeoutMsg, 'stderr');
                }
                break;
            }
            usleep(30000); // 30ms polling pause for tight chunk emission
        }

        // Drain any remaining buffered output
        if ($fhOut) {
            while (!feof($fhOut)) {
                $chunk = fread($fhOut, 8192);
                if ($chunk !== false && strlen($chunk) > 0) {
                    $stdout .= $chunk;
                    if ($onChunk !== null) {
                        $onChunk($chunk, 'stdout');
                    }
                } else {
                    break;
                }
            }
            fclose($fhOut);
        }
        if ($fhErr) {
            while (!feof($fhErr)) {
                $chunk = fread($fhErr, 8192);
                if ($chunk !== false && strlen($chunk) > 0) {
                    $stderr .= $chunk;
                    if ($onChunk !== null) {
                        $onChunk($chunk, 'stderr');
                    }
                } else {
                    break;
                }
            }
            fclose($fhErr);
        }

        $exitCode = proc_close($proc);
        @unlink($tmpOut);
        @unlink($tmpErr);

        $combined = trim($stdout);
        if (!empty($stderr)) {
            $trimmedErr = trim($stderr);
            $combined = empty($combined) ? $trimmedErr : ($combined . "\n" . $trimmedErr);
        }

        return [
            'success' => $exitCode === 0,
            'output' => $combined,
            'stdout' => trim($stdout),
            'stderr' => trim($stderr),
            'exit_code' => $exitCode,
            'cwd' => $cwd,
            'command' => $command
        ];
    }

    /**
     * Executes a shell command and streams its output via Server-Sent Events (SSE).
     */
    public static function streamShellCommand(string $command, string $appContext = 'default'): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        if (!headers_sent()) {
            header('Content-Type: text/event-stream; charset=utf-8');
            header('Cache-Control: no-cache, no-transform');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no');
        }

        echo "event: start\ndata: " . json_encode(['command' => $command, 'app' => $appContext], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
        @flush();

        $result = self::runShellCommand($command, $appContext, function (string $chunk, string $type) {
            echo "event: chunk\ndata: " . json_encode(['chunk' => $chunk, 'type' => $type], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
            @flush();
        });

        echo "event: done\ndata: " . json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
        @flush();
        exit;
    }

    /**
     * Discovers all visible CLI commands categorized cleanly for the UI.
     */
    public static function listCommands(): array
    {
        $all = CommandManager::discover();
        $categories = [];

        foreach ($all as $name => $cmd) {
            if ($cmd->isHidden()) {
                continue;
            }

            // Determine category
            $parts = explode(':', $name);
            $cat = count($parts) > 1 ? $parts[0] : 'general';

            // Canonical category normalization
            $catMap = [
                // Database
                'db' => 'database',
                'dbsettings' => 'database',
                'ent' => 'database',
                'entity' => 'database',
                'interdb' => 'database',
                'mesh' => 'database',
                'migrate' => 'database',
                'storage' => 'database',
                'xdb' => 'database',
                // IAM
                'auth' => 'iam',
                'group' => 'iam',
                'oauth' => 'iam',
                'role' => 'iam',
                'scim' => 'iam',
                'user' => 'iam',
                'userprofile' => 'iam',
                // System & Observability
                'audit' => 'system',
                'cache' => 'system',
                'clear' => 'system',
                'config' => 'system',
                'env' => 'system',
                'event' => 'system',
                'kernel' => 'system',
                'logger' => 'system',
                'optimize' => 'system',
                'profile' => 'system',
                'session' => 'system',
                'sys' => 'system',
                'verify' => 'system',
                // Deploy
                'pkg' => 'deploy',
                'diff' => 'deploy',
                // App & Module
                'ext' => 'app',
                'import' => 'app',
                'manifest' => 'app',
                'module' => 'app',
                'site' => 'app',
                'delete' => 'app',
                // View & Frontend
                'blade' => 'view',
                'component' => 'view',
                'drishyam' => 'view',
                'form' => 'view',
                'frontend' => 'view',
                'i18n' => 'view',
                'lang' => 'view',
                'lekhak' => 'view',
                'live' => 'view',
                'theme' => 'view',
                'ui' => 'view',
                'ux' => 'view',
                // API & Services
                'bridge' => 'api',
                'di' => 'api',
                'integration' => 'api',
                'middleware' => 'api',
                'service' => 'api',
                // Queue & Schedule
                'cron' => 'schedule',
                'queue' => 'schedule',
                'workflow' => 'schedule',
                // Documentation
                'man' => 'docs',
                'sppdocs' => 'docs',
                // General
                'serve' => 'general'
            ];
            $canonicalCat = $catMap[$cat] ?? $cat;

            if (!isset($categories[$canonicalCat])) {
                $categories[$canonicalCat] = [];
            }

            $categories[$canonicalCat][] = [
                'name' => $name,
                'description' => $cmd->getDescription(),
                'is_cli_only' => $cmd->isCLIOnly()
            ];
        }

        ksort($categories);
        foreach ($categories as $cat => &$list) {
            usort($list, fn($a, $b) => strcmp($a['name'], $b['name']));
        }

        return $categories;
    }

    /**
     * Introspects a command to produce an interactive UI form schema.
     */
    public static function getCommandUi(string $commandName): array
    {
        $all = CommandManager::discover();
        if (!isset($all[$commandName])) {
            return ['success' => false, 'error' => "Command not found."];
        }

        $cmd = $all[$commandName];
        $ref = new \ReflectionClass($cmd);

        // Check if command defines getDefinition()
        $definition = [];
        if ($ref->hasMethod('getDefinition')) {
            $definition = $cmd->getDefinition();
        }

        $doc = $ref->getDocComment() ?: '';
        $desc = $cmd->getDescription();

        // Build HTML form fragment for commands.js UI
        $html = '<div class="cmd-form-container">';
        $html .= '<h3 style="margin-bottom:0.5rem; color:var(--primary);">' . htmlspecialchars($commandName) . '</h3>';
        $html .= '<p style="margin-bottom:1.5rem; color:var(--text-dim);">' . htmlspecialchars($desc) . '</p>';
        $html .= '<form id="cmdExecForm" onsubmit="event.preventDefault(); window.submitActiveCommand();">';
        
        $html .= '<div class="input-group" style="margin-bottom:1rem;">';
        $html .= '<label style="display:block; font-size:0.85rem; margin-bottom:0.3rem;">Arguments & Options</label>';
        $html .= '<input type="text" id="cmdArgs" class="spp-element" placeholder="e.g. MyEntity --app=school" style="width:100%; padding:8px; border-radius:6px; background:rgba(0,0,0,0.2); border:1px solid var(--glass-border); color:var(--text-bright);">';
        $html .= '</div>';

        $html .= '<div style="display:flex; gap:10px; margin-top:1rem;">';
        $html .= '<button type="submit" class="btn primary-btn shine-effect">⚡ Run Command</button>';
        $html .= '<button type="button" class="btn secondary-btn" onclick="document.getElementById(\'cmdArgs\').value=\'\';">Reset</button>';
        $html .= '</div>';
        $html .= '</form>';
        $html .= '</div>';

        return [
            'success' => true,
            'name' => $commandName,
            'description' => $desc,
            'definition' => $definition,
            'html' => $html
        ];
    }

    /**
     * Resolves and dispatches high-level framework operations directly via CLI or Core APIs.
     */
    public static function handle(string $action, array $params = []): array
    {
        return self::handleAction($action, $params);
    }

    public static function dispatch(string $action, array $params = []): array
    {
        return self::handleAction($action, $params);
    }

    public static function handleAction(string $action, array $params = []): array
    {
        if (!isset($_SESSION)) {
            $_SESSION = [];
        }
        $appContext = $params['appname'] ?? $params['context'] ?? ($_SESSION['spp_admin_selected_app'] ?? (class_exists('\\SPP\\Scheduler') ? \SPP\Scheduler::getContext() : null)) ?? 'default';
        if (!empty($params['appname']) || !empty($params['context'])) {
            $_SESSION['spp_admin_selected_app'] = $appContext;
        }

        switch ($action) {
            case 'switch_context':
            case 'set_context':
                $newApp = $params['appname'] ?? $params['context'] ?? 'default';
                $_SESSION['spp_admin_selected_app'] = $newApp;
                if (class_exists('\\SPP\\Scheduler')) {
                    try {
                        try {
                            \SPP\Scheduler::getProcObj($newApp);
                        } catch (\Throwable $e) {
                            if (class_exists('\\SPP\\App')) new \SPP\App($newApp);
                        }
                        \SPP\Scheduler::setContext($newApp);
                    } catch (\Throwable $e) {}
                }
                return ['success' => true, 'data' => ['appname' => $newApp], 'message' => "Context switched to {$newApp}."];

            case 'login':
                $username = trim($params['username'] ?? '');
                $password = $params['password'] ?? '';
                if (empty($username) || empty($password)) {
                    return ['success' => false, 'error' => 'Username and password are required.'];
                }

                $success = false;
                if (class_exists('\\SPPMod\\SPPAuth\\SPPAuth')) {
                    try {
                        $success = \SPPMod\SPPAuth\SPPAuth::login($username, $password);
                    } catch (\Throwable $e) {
                        $msg = $e->getMessage();
                        if (str_starts_with($msg, 'MFA_REQUIRED:')) {
                            $token = explode(':', $msg)[1];
                            return ['success' => true, 'data' => ['mfa_challenge' => $token, 'user' => $username]];
                        }
                    }
                }

                if (!$success) {
                    $settings = \SPP\App::getGlobalSettings();
                    $adminAuth = $settings['admin_auth'] ?? [];
                    $globalUser = $adminAuth['username'] ?? ($settings['admin_username'] ?? 'admin');
                    $configuredHash = $adminAuth['password_hash'] ?? null;
                    $rawPass = $adminAuth['password'] ?? null;

                    if (strcasecmp($username, (string)$globalUser) === 0) {
                        if ($password === 'admin123' || $password === 'admin') {
                            $allowInsecureDefault = !empty($adminAuth['allow_insecure_defaults']);
                            if (!$allowInsecureDefault && defined('SPP_PROFILE') && SPP_PROFILE !== 'dev') {
                                return ['success' => false, 'error' => "Default credentials 'admin123' are disabled in production. Configure a secure password hash."];
                            }
                        }

                        if ($configuredHash && password_verify($password, $configuredHash)) {
                            $success = true;
                        } elseif ($rawPass && hash_equals((string)$rawPass, $password)) {
                            $success = true;
                        }
                    }
                }

                if ($success) {
                    \SPP\SPPSession::regenerateId(true);
                    $_SESSION['spp_admin_user'] = $username;
                    \SPP\SPPSession::setSessionVar('__sppauth_user__', $username);
                    \SPP\SPPSession::setSessionVar('__username__', $username);
                    \SPP\SPPSession::setSessionVar('__role_id__', 1);
                    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
                    \SPP\SPPSession::setSessionVar('__sppauth_fingerprint__', hash('sha256', $ip . $ua));
                    return ['success' => true, 'data' => ['user' => $username], 'message' => 'Login successful.'];
                }

                return ['success' => false, 'error' => 'Invalid username or password.'];

            // Unified Operation Engine & SPP Server Agent Gateway
            case 'operation_preview':
            case 'operation_plan':
                $opName = (string)($params['operation'] ?? '');
                if ($opName === '') return ['success' => false, 'error' => 'Operation name is required.'];
                $engine = new OperationEngine();
                $plan = $engine->plan($opName, $params['parameters'] ?? [], $params);
                return ['success' => true, 'data' => $plan];

            case 'operation_execute':
                $opName = (string)($params['operation'] ?? '');
                if ($opName === '') return ['success' => false, 'error' => 'Operation name is required.'];
                $engine = new OperationEngine();
                $execRes = $engine->execute($opName, $params['parameters'] ?? [], $params);
                return array_merge(['success' => !empty($execRes['success'])], $execRes);

            case 'agent_health':
                $client = new \SPP\Agent\Client();
                try {
                    $health = $client->call('agent.health');
                    return ['success' => true, 'data' => $health];
                } catch (\Throwable $e) {
                    return ['success' => false, 'error' => $e->getMessage()];
                }

            case 'agent_capabilities':
                $caps = \SPP\Agent\Authorization\CapabilityDetector::detect();
                return ['success' => true, 'data' => $caps];

            case 'agent_operations':
                $reg = new \SPP\Agent\Operations\OperationRegistry();
                $ops = [];
                foreach ($reg->all() as $name => $op) {
                    $ops[] = [
                        'name'        => $name,
                        'description' => $op->getDescription(),
                        'risk_tier'   => $op->getRiskLevel(),
                        'schema'      => $op->getParameterSchema(),
                        'supported'   => $op->isSupportedOnHost(),
                    ];
                }
                return ['success' => true, 'data' => $ops, 'count' => count($ops)];

            case 'agent_call':
                $opName = (string)($params['operation'] ?? '');
                if ($opName === '') return ['success' => false, 'error' => 'Operation name is required.'];
                $client = new \SPP\Agent\Client();
                try {
                    $res = $client->call($opName, $params['parameters'] ?? [], $params);
                    return ['success' => true, 'data' => $res];
                } catch (\Throwable $e) {
                    return ['success' => false, 'error' => $e->getMessage()];
                }

            case 'check_auth':
                $username = null;
                $userId = '1';
                if (isset($_SESSION['spp_admin_fallback'])) {
                    $username = 'admin';
                    $userId = '0';
                } elseif (isset($_SESSION['spp_admin_user'])) {
                    $username = $_SESSION['spp_admin_user'];
                } elseif (\SPP\SPPSession::sessionVarExists('__sppauth_user__')) {
                    $username = \SPP\SPPSession::getSessionVar('__sppauth_user__');
                } elseif (class_exists('\\SPPMod\\SPPAuth\\SPPAuth') && \SPPMod\SPPAuth\SPPAuth::check()) {
                    $user = \SPPMod\SPPAuth\SPPAuth::user();
                    $userId = (string) \SPPMod\SPPAuth\SPPAuth::guard()->id();
                    $username = $user ? ($user->username ?? $user->get('UserName') ?? $userId) : $userId;
                }
                if ($username) {
                    return [
                        'success' => true,
                        'data' => [
                            'username' => $username,
                            'user_id' => $userId,
                            'role' => 'Administrator'
                        ],
                        'message' => 'Authenticated.'
                    ];
                }
                return ['success' => false, 'data' => ['authenticated' => false]];

            case 'get_profile':
                $username = null;
                $role = 'Administrator';
                $email = 'admin@spp.local';
                $id = 1;
                if (isset($_SESSION['spp_admin_fallback'])) {
                    $username = 'admin';
                    $role = 'System Administrator';
                    $email = 'system@spp.local';
                    $id = 0;
                } elseif (isset($_SESSION['spp_admin_user'])) {
                    $username = $_SESSION['spp_admin_user'];
                } elseif (\SPP\SPPSession::sessionVarExists('__sppauth_user__')) {
                    $username = \SPP\SPPSession::getSessionVar('__sppauth_user__');
                } elseif (class_exists('\\SPPMod\\SPPAuth\\SPPAuth') && \SPPMod\SPPAuth\SPPAuth::check()) {
                    $user = \SPPMod\SPPAuth\SPPAuth::user();
                    if ($user) {
                        return ['success' => true, 'data' => $user->getValues(), 'message' => 'Profile retrieved.'];
                    }
                }
                if ($username) {
                    if (class_exists('\\SPPMod\\SPPAuth\\SPPUser')) {
                        try {
                            $uObj = new \SPPMod\SPPAuth\SPPUser($username);
                            $uVals = $uObj->getValues();
                            if (!empty($uVals)) {
                                return ['success' => true, 'data' => $uVals, 'message' => 'Profile retrieved.'];
                            }
                        } catch (\Throwable $t) {}
                    }
                    return [
                        'success' => true,
                        'data' => [
                            'id' => $id,
                            'username' => $username,
                            'email' => $email,
                            'role' => $role
                        ],
                        'message' => 'Profile retrieved.'
                    ];
                }
                return ['success' => false, 'data' => null];

            case 'logout':
                if (isset($_SESSION['spp_admin_user'])) {
                    unset($_SESSION['spp_admin_user']);
                }
                if (isset($_SESSION['spp_admin_fallback'])) {
                    unset($_SESSION['spp_admin_fallback']);
                }
                if (\SPP\SPPSession::sessionVarExists('__sppauth_user__')) {
                    \SPP\SPPSession::unsetSessionVar('__sppauth_user__');
                }
                if (class_exists('\\SPPMod\\SPPAuth\\SPPAuth')) {
                    try {
                        \SPPMod\SPPAuth\SPPAuth::logout();
                    } catch (\Throwable $t) {}
                }
                return ['success' => true, 'message' => 'Logged out successfully.'];

            case 'list_commands':
                return ['success' => true, 'categories' => self::listCommands()];

            case 'get_command_ui':
                $cmd = $params['command'] ?? '';
                return self::getCommandUi($cmd);

            case 'execute_command':
                $cmd = $params['command'] ?? $params['command_name'] ?? $params['cmd'] ?? '';
                $rawArgs = $params['args'] ?? '';
                $args = [];
                if (is_array($rawArgs)) {
                    foreach ($rawArgs as $k => $v) {
                        if (is_string($v) && str_starts_with($v, '--')) {
                            $kv = explode('=', substr($v, 2), 2);
                            $args[$kv[0]] = $kv[1] ?? '1';
                        } else {
                            $args[$k] = $v;
                        }
                    }
                } elseif (is_string($rawArgs) && !empty(trim($rawArgs))) {
                    $parts = preg_split('/\s+/', trim($rawArgs));
                    foreach ($parts as $p) {
                        if (str_starts_with($p, '--')) {
                            $kv = explode('=', substr($p, 2), 2);
                            $args[$kv[0]] = $kv[1] ?? '1';
                        } else {
                            $args[] = $p;
                        }
                    }
                }
                return self::executeCommand($cmd, $args);

            case 'run_command':
                $cmd = trim($params['command'] ?? '');
                if (empty($cmd)) {
                    return ['success' => false, 'error' => 'No command provided.'];
                }
                return self::runShellCommand($cmd, $appContext);

            case 'run_command_stream':
                $cmd = trim($params['command'] ?? '');
                if (empty($cmd)) {
                    return ['success' => false, 'error' => 'No command provided.'];
                }
                self::streamShellCommand($cmd, $appContext);
                exit;

            case 'get_dashboard_metrics':
            case 'dashboard_summary':
                $settings = \SPP\App::getGlobalSettings('', false);
                $rawApps = $settings['apps'] ?? [];
                $apps = [];
                foreach ($rawApps as $appName => $meta) {
                    $apps[] = [
                        'name' => $appName,
                        'title' => $meta['admin_title'] ?? ucfirst($appName),
                        'icon' => $meta['admin_icon'] ?? '📱',
                        'base_url' => $meta['base_url'] ?? ('/' . $appName)
                    ];
                }

                $modules = \SPP\Module::listAvailableModules($appContext);

                $entitiesList = [];
                try {
                    $entities = \SPP\Scheduler::withContext($appContext, function () {
                        return class_exists('\SPPMod\SPPDB\SPPEntity') ? \SPPMod\SPPDB\SPPEntity::listAvailableEntities() : [];
                    });
                    $entitiesList = array_values($entities ?: []);
                } catch (\Throwable $e) {}

                $pages = [];
                $pagesFile = SPP_APP_DIR . "/src/{$appContext}/etc/pages.yml";
                if (!file_exists($pagesFile)) {
                    $pagesFile = SPP_APP_DIR . "/etc/apps/{$appContext}/pages.yml";
                }
                if (file_exists($pagesFile)) {
                    try {
                        $pData = \Symfony\Component\Yaml\Yaml::parseFile($pagesFile);
                        $pages = array_keys($pData['pages'] ?? $pData);
                    } catch (\Throwable $e) {}
                }

                $workflows = [];
                $wfDirs = [
                    SPP_APP_DIR . "/src/{$appContext}/etc/workflows",
                    SPP_APP_DIR . "/etc/apps/{$appContext}/workflows"
                ];
                foreach ($wfDirs as $wd) {
                    if (is_dir($wd)) {
                        foreach (scandir($wd) as $wf) {
                            if (str_ends_with($wf, '.yml') || str_ends_with($wf, '.yaml')) {
                                $workflows[] = pathinfo($wf, PATHINFO_FILENAME);
                            }
                        }
                    }
                }

                $auditLogs = [];
                try {
                    if (class_exists('\SPPMod\SPPDB\SPPDB')) {
                        $db = \SPPMod\SPPDB\SPPDB::getConnection();
                        if ($db) {
                            $stmt = $db->query("SELECT * FROM spp_audit_logs ORDER BY id DESC LIMIT 10");
                            if ($stmt) {
                                $auditLogs = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                            }
                        }
                    }
                } catch (\Throwable $e) {}

                $health = [
                    'status' => 'UP',
                    'timestamp' => date('c'),
                    'php_version' => PHP_VERSION,
                    'sapi' => PHP_SAPI,
                    'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
                    'active_app' => $appContext,
                    'profile' => $settings['profile'] ?? 'dev'
                ];

                return [
                    'success' => true,
                    'data' => [
                        'apps' => $apps,
                        'modules' => $modules,
                        'entities' => $entitiesList,
                        'pages' => $pages,
                        'workflows' => array_values(array_unique($workflows)),
                        'audit_logs' => $auditLogs,
                        'health' => $health
                    ]
                ];

            case 'list_apps':
                $settings = \SPP\App::getGlobalSettings('', true);
                $apps = [];
                $registry = $settings['apps'] ?? [];
                if (!class_exists(\SPP\Core\Router\DomainRouter::class)) {
                    require_once dirname(__DIR__, 2) . '/core/router/class.domainrouter.php';
                }
                \SPP\Core\Router\DomainRouter::loadConfig();
                $sharedGroups = $settings['shared_groups'] ?? [];

                $rootDir = defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(SPP_BASE_DIR);
                $knownPackageIds = [];
                $appPackagesMap = [];

                // Discover packages belonging to each registered app
                foreach ($registry as $appName => $meta) {
                    $srcPath = $meta['src_path'] ?? ('src/' . $appName);
                    $fullPath = $rootDir . '/' . ltrim(str_replace('\\', '/', $srcPath), '/');
                    $packagesDir = $fullPath . '/packages';
                    if (is_dir($packagesDir)) {
                        $appPackages = [];
                        foreach (scandir($packagesDir) as $item) {
                            if ($item === '.' || $item === '..' || !is_dir($packagesDir . '/' . $item)) {
                                continue;
                            }
                            $manifestFile = $packagesDir . '/' . $item . '/module.yml';
                            $pkgMeta = [
                                'id'          => $item,
                                'name'        => ucfirst($item),
                                'version'     => '1.0.0',
                                'description' => '',
                                'category'    => 'General',
                            ];
                            if (file_exists($manifestFile)) {
                                try {
                                    $parsed = \Symfony\Component\Yaml\Yaml::parseFile($manifestFile) ?: [];
                                    $pkgMeta['id']          = $parsed['id'] ?? $item;
                                    $pkgMeta['name']        = $parsed['name'] ?? ucfirst($item);
                                    $pkgMeta['version']     = $parsed['version'] ?? '1.0.0';
                                    $pkgMeta['description'] = $parsed['description'] ?? '';
                                    $pkgMeta['category']    = $parsed['category'] ?? 'General';
                                } catch (\Throwable $e) {}
                            }
                            $appPackages[] = $pkgMeta;
                            $knownPackageIds[strtolower($item)] = $appName;
                            if (!empty($pkgMeta['id'])) {
                                $knownPackageIds[strtolower($pkgMeta['id'])] = $appName;
                            }
                        }
                        $appPackagesMap[$appName] = $appPackages;
                    }
                }

                // Known legacy/alias packages that belong to SPPDocs
                $sppdocsKnownPackages = ['cms', 'lekhak', 'samvaad', 'social', 'spp_docs', 'erp', 'vshiksha', 'pm', 'lms', 'infra'];
                foreach ($sppdocsKnownPackages as $skp) {
                    if (!isset($knownPackageIds[$skp])) {
                        $knownPackageIds[$skp] = 'SPPDocs';
                    }
                }

                foreach ($registry as $appName => $meta) {
                    // Packages relating to an app should be treated as part of that app, not shown as standalone apps
                    if (!empty($meta['is_package']) || !empty($meta['parent_app'])) {
                        continue;
                    }
                    if (isset($knownPackageIds[strtolower($appName)]) && strtolower($knownPackageIds[strtolower($appName)]) !== strtolower($appName)) {
                        continue;
                    }

                    $restr = \SPP\Core\Router\DomainRouter::getAppRestrictions($appName);
                    $packages = $appPackagesMap[$appName] ?? [];
                    $isSystem = in_array(strtolower($appName), ['default', 'sppadmin', 'sppdev', 'admin', 'spp', 'core', 'system'], true) || str_starts_with($restr['src_path'] ?? ($meta['src_path'] ?? ''), 'spp/');
                    $isDevModeDependent = in_array(strtolower($appName), ['sppadmin', 'sppdev'], true);
                    $profile = strtolower($settings['profile'] ?? 'dev');
                    $isDevMode = ($profile === 'dev');
                    $isEnabled = $isDevModeDependent ? $isDevMode : true;

                    $apps[] = [
                        'name' => $appName,
                        'title' => $meta['admin_title'] ?? ($appName === 'sppadmin' ? 'SPP Admin Workbench' : ($appName === 'sppdev' ? 'SPP Developer Studio' : ucfirst($appName))),
                        'icon' => $meta['admin_icon'] ?? ($isSystem ? '🛡️' : '📱'),
                        'type' => $isSystem ? 'system' : ($meta['type'] ?? 'native'),
                        'is_system' => $isSystem,
                        'is_protected' => $isSystem,
                        'dev_mode_dependent' => $isDevModeDependent,
                        'is_enabled' => $isEnabled,
                        'is_base' => ($appName === ($settings['base_app'] ?? 'default')),
                        'is_base_app' => ($appName === ($settings['base_app'] ?? 'default')),
                        'is_installed' => true,
                        'status' => $isEnabled ? 'active' : 'disabled',
                        'table_prefix' => $meta['table_prefix'] ?? '',
                        'shared_group' => $meta['shared_group'] ?? 'core',
                        'base_url' => $meta['base_url'] ?? '/' . $appName,
                        'is_domain_app' => $restr['is_domain_app'],
                        'installed_domain' => $restr['installed_domain'],
                        'domain_only' => $restr['domain_only'],
                        'allowed_domains' => $restr['allowed_domains'],
                        'src_path' => $restr['src_path'],
                        'has_admin' => file_exists(SPP_BASE_DIR . '/' . $restr['src_path'] . '/admin') || file_exists(SPP_BASE_DIR . '/src/' . $appName . '/admin'),
                        'packages' => $packages,
                    ];
                }

                // Discover dormant / config-uninstalled applications preserved in src/
                $srcDir = $rootDir . '/src';
                $reservedDirs = ['modules', 'etc', 'pages', 'serv', 'old', 'tests', 'default', 'sppadmin', 'sppdev'];
                if (is_dir($srcDir)) {
                    $srcItems = array_diff(scandir($srcDir), ['.', '..']);
                    foreach ($srcItems as $item) {
                        if (!is_dir($srcDir . '/' . $item) || in_array($item, $reservedDirs, true)) {
                            continue;
                        }
                        if (isset($registry[$item])) {
                            continue;
                        }
                        if (isset($knownPackageIds[strtolower($item)])) {
                            continue; // This is a package of an app, not a standalone app
                        }
                        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $item)) {
                            continue;
                        }

                        // Strictly verify this is actually a dormant / uninstalled app (marked by .uninstalled or app.yml with uninstalled: true)
                        $hasMarker = file_exists($srcDir . '/' . $item . '/.uninstalled');
                        $appYmlPath = $srcDir . '/' . $item . '/etc/app.yml';
                        $hasUninstalledYaml = false;
                        if (file_exists($appYmlPath)) {
                            try {
                                $yData = \Symfony\Component\Yaml\Yaml::parseFile($appYmlPath);
                                if (!empty($yData['is_package']) || !empty($yData['parent_app'])) {
                                    continue;
                                }
                                if (!empty($yData['uninstalled']) || (isset($yData['installed']) && $yData['installed'] === false)) {
                                    $hasUninstalledYaml = true;
                                }
                            } catch (\Throwable $e) {}
                        }

                        if (!$hasMarker && !$hasUninstalledYaml) {
                            continue;
                        }

                        $apps[] = [
                            'name'             => $item,
                            'title'            => ucfirst($item),
                            'icon'             => '📦',
                            'is_base'          => false,
                            'is_base_app'      => false,
                            'is_installed'     => false,
                            'status'           => 'uninstalled',
                            'state_label'      => 'Config Uninstalled',
                            'table_prefix'     => $item . '_',
                            'shared_group'     => 'core',
                            'base_url'         => '/' . $item,
                            'is_domain_app'    => false,
                            'installed_domain' => null,
                            'domain_only'      => false,
                            'allowed_domains'  => [],
                            'src_path'         => 'src/' . $item,
                            'has_admin'        => file_exists($srcDir . '/' . $item . '/admin'),
                            'has_code'         => true,
                        ];
                    }
                }
                // Multi-Language Guest Applications & Microservices
                $foreignApps = [];
                $foreignServices = [];
                try {
                    if (class_exists(\SPP\Core\Apps\AppSupervisor::class)) {
                        $foreignApps = array_values(\SPP\Core\Apps\AppSupervisor::getAllStatuses());
                    }
                    if (class_exists(\SPP\Core\Apps\ForeignAppRegistry::class)) {
                        $foreignServices = \SPP\Core\Apps\ForeignAppRegistry::listExposedServices();
                    }
                } catch (\Throwable $e) {
                    // Gracefully ignore if etc/apps.yml not configured
                }

                $profile = strtolower($settings['profile'] ?? 'dev');
                return [
                    'success' => true,
                    'apps' => $apps,
                    'dev_mode' => ($profile === 'dev'),
                    'profile' => $profile,
                    'shared_groups' => $sharedGroups,
                    'foreign_apps' => $foreignApps,
                    'foreign_services' => $foreignServices
                ];

            case 'apps_activation_matrix':
                return [
                    'success' => true,
                    'data' => \SPP\Core\App\ApplicationRegistryService::getInstance()->getActivationMatrix(),
                ];

            case 'apps_set_activation':
                $appId = $params['app_id'] ?? $params['app'] ?? '';
                $domainId = $params['domain_id'] ?? $params['domain'] ?? '';
                $enabled = !empty($params['enabled']);
                $res = \SPP\Core\App\ApplicationRegistryService::getInstance()->setDomainActivation($appId, $domainId, $enabled);
                return [
                    'success' => $res,
                    'message' => $res ? "Activation updated for {$appId} on {$domainId}." : "Failed to update activation.",
                ];

            case 'apps_global_list':
                return [
                    'success' => true,
                    'data' => array_values(\SPP\Core\App\ApplicationRegistryService::getInstance()->getGlobalApplications()),
                ];

            case 'apps_domain_list':
                $domainId = $params['domain_id'] ?? $params['domain'] ?? 'default';
                return [
                    'success' => true,
                    'data' => array_values(\SPP\Core\App\ApplicationRegistryService::getInstance()->getDomainApplications($domainId)),
                ];

            case 'apps_install_global':
                $appId = $params['app_id'] ?? $params['app'] ?? '';
                return \SPP\Core\App\ApplicationLifecycleService::getInstance()->installGlobalApp($appId, $params);

            case 'apps_install_domain':
                $domainId = $params['domain_id'] ?? $params['domain'] ?? 'default';
                $appId = $params['app_id'] ?? $params['app'] ?? '';
                return \SPP\Core\App\ApplicationLifecycleService::getInstance()->installDomainApp($domainId, $appId, $params);

            case 'apps_uninstall_global':
                $appId = $params['app_id'] ?? $params['app'] ?? '';
                $force = !empty($params['force']);
                $delData = !empty($params['delete_data']);
                return \SPP\Core\App\ApplicationLifecycleService::getInstance()->uninstallGlobalApp($appId, $force, $delData);

            case 'apps_config_get':
                $appId = $params['app_id'] ?? $params['app'] ?? '';
                $domainId = $params['domain_id'] ?? $params['domain'] ?? null;
                $config = \SPP\Core\Config\ConfigurationResolver::getInstance()->resolveAppConfig($appId, $domainId);
                return ['success' => true, 'data' => $config];

            case 'apps_config_save_global':
                $appId = $params['app_id'] ?? $params['app'] ?? '';
                $cfg = is_array($params['config'] ?? null) ? $params['config'] : [];
                $res = \SPP\Core\Config\ConfigurationResolver::getInstance()->savePlatformConfig($appId, $cfg);
                return ['success' => $res, 'message' => $res ? "Global configuration saved for {$appId}." : "Failed to save configuration."];

            case 'apps_config_save_domain':
                $appId = $params['app_id'] ?? $params['app'] ?? '';
                $domainId = $params['domain_id'] ?? $params['domain'] ?? 'default';
                $cfg = is_array($params['config'] ?? null) ? $params['config'] : [];
                $res = \SPP\Core\Config\ConfigurationResolver::getInstance()->saveDomainConfig($appId, $domainId, $cfg);
                return ['success' => $res, 'message' => $res ? "Domain configuration override saved for {$appId} on {$domainId}." : "Failed to save configuration."];

            case 'apps_config_diff':
                $appId = $params['app_id'] ?? $params['app'] ?? '';
                $domainId = $params['domain_id'] ?? $params['domain'] ?? 'default';
                $diff = \SPP\Core\Config\ConfigurationResolver::getInstance()->diffConfig($appId, $domainId);
                return ['success' => true, 'data' => $diff];

            case 'apps_config_reset':
                $appId = $params['app_id'] ?? $params['app'] ?? '';
                $domainId = $params['domain_id'] ?? $params['domain'] ?? 'default';
                $res = \SPP\Core\Config\ConfigurationResolver::getInstance()->resetDomainConfigToDefault($appId, $domainId);
                return ['success' => $res, 'message' => "Configuration reset to global defaults for {$appId}."];

            case 'database_contexts_list':
                $domainId = $params['domain_id'] ?? $params['domain'] ?? null;
                $list = \SPP\Core\Database\DatabaseContextResolver::getInstance()->listAvailableContexts($domainId);
                return ['success' => true, 'data' => $list];

            case 'audit_logs_query':
                $records = \SPP\Core\Audit\AuditLogService::getInstance()->query($params);
                return ['success' => true, 'data' => $records];

            case 'apps_mesh_status':
                $statuses = [];
                $services = [];
                try {
                    if (class_exists(\SPP\Core\Apps\AppSupervisor::class)) {
                        $statuses = array_values(\SPP\Core\Apps\AppSupervisor::getAllStatuses());
                    }
                    if (class_exists(\SPP\Core\Apps\ForeignAppRegistry::class)) {
                        $services = \SPP\Core\Apps\ForeignAppRegistry::listExposedServices();
                    }
                } catch (\Throwable $e) {}
                return [
                    'success' => true,
                    'statuses' => $statuses,
                    'services' => $services
                ];

            case 'apps_mesh_start':
                $appId = (string)($params['app_id'] ?? '');
                if (empty($appId)) {
                    return ['success' => false, 'error' => 'Application ID is required.'];
                }
                return \SPP\Core\Apps\AppSupervisor::start($appId);

            case 'apps_mesh_stop':
                $appId = (string)($params['app_id'] ?? '');
                if (empty($appId)) {
                    return ['success' => false, 'error' => 'Application ID is required.'];
                }
                return \SPP\Core\Apps\AppSupervisor::stop($appId);

            case 'apps_mesh_restart':
                $appId = (string)($params['app_id'] ?? '');
                if (empty($appId)) {
                    return ['success' => false, 'error' => 'Application ID is required.'];
                }
                return \SPP\Core\Apps\AppSupervisor::restart($appId);

            case 'apps_mesh_logs':
                $appId = (string)($params['app_id'] ?? '');
                $lines = (int)($params['lines'] ?? 100);
                return [
                    'success' => true,
                    'app_id'  => $appId,
                    'logs'    => \SPP\Core\Apps\AppSupervisor::getLogs($appId, $lines)
                ];

            case 'apps_mesh_call':
                $appId = (string)($params['app_id'] ?? '');
                $endpoint = (string)($params['endpoint'] ?? '/');
                $method = strtoupper((string)($params['method'] ?? 'POST'));
                $payload = $params['payload'] ?? null;
                if (is_string($payload)) {
                    $payload = json_decode($payload, true) ?: [];
                }
                $start = microtime(true);
                try {
                    $resp = \SPP\Mesh::call($appId, $endpoint, $payload, $method);
                    $durationMs = round((microtime(true) - $start) * 1000, 2);
                    return [
                        'success'     => true,
                        'app_id'      => $appId,
                        'endpoint'    => $endpoint,
                        'method'      => $method,
                        'response'    => $resp,
                        'duration_ms' => $durationMs
                    ];
                } catch (\Throwable $e) {
                    $durationMs = round((microtime(true) - $start) * 1000, 2);
                    return [
                        'success'     => false,
                        'error'       => $e->getMessage(),
                        'duration_ms' => $durationMs
                    ];
                }

            case 'apps_mesh_publish_event':
                $event = (string)($params['event'] ?? '');
                $payload = $params['payload'] ?? [];
                if (is_string($payload)) {
                    $payload = json_decode($payload, true) ?: [];
                }
                \SPP\Mesh::publishEvent($event, $payload);
                return ['success' => true, 'event' => $event, 'message' => "Event '{$event}' fired to mesh."];

            case 'list_modules':
                $modules = \SPP\Module::listAvailableModules($appContext);
                return ['success' => true, 'modules' => $modules];

            case 'toggle_module':
                $modname = $params['modname'] ?? '';
                $enabled = !empty($params['enabled']);
                $cmd = $enabled ? 'module:enable' : 'module:disable';
                return self::executeCommand($cmd, [$modname, '--app=' . $appContext]);

            case 'list_entities':
                $entities = \SPP\Scheduler::withContext($appContext, function () {
                    return \SPPMod\SPPDB\SPPEntity::listAvailableEntities();
                });
                return ['success' => true, 'entities' => array_values($entities ?: [])];

            case 'list_users':
                require_once dirname(__DIR__) . '/services/IAM.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_IAM_ListUsers($la, array_merge(['context' => $appContext, 'appname' => $appContext], $params));
                $laData = $la->getData();
                $items = [];
                if (!empty($laData['sources'])) {
                    foreach ($laData['sources'] as $src) {
                        if (!empty($src['items'])) {
                            $items = array_merge($items, $src['items']);
                        }
                    }
                }
                return [
                    'success' => true,
                    'users' => $items,
                    'sources' => $laData['sources'] ?? [],
                    'data' => $laData
                ];

            case 'create_user':
                return self::executeCommand('user:create', [
                    $params['username'] ?? '',
                    '--email=' . ($params['email'] ?? ''),
                    '--password=' . ($params['password'] ?? ''),
                    '--role=' . ($params['role'] ?? ''),
                    '--json' => '1'
                ]);

            case 'delete_user':
                return self::executeCommand('user:delete', [
                    $params['username'] ?? $params['id'] ?? '',
                    '--json' => '1'
                ]);

            case 'list_roles':
                require_once dirname(__DIR__) . '/services/IAM.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_IAM_ListRoles($la, array_merge(['context' => $appContext, 'appname' => $appContext], $params));
                $laData = $la->getData();
                $items = [];
                if (!empty($laData['sources'])) {
                    foreach ($laData['sources'] as $src) {
                        if (!empty($src['items'])) {
                            $items = array_merge($items, $src['items']);
                        }
                    }
                }
                return [
                    'success' => true,
                    'roles' => $items,
                    'sources' => $laData['sources'] ?? [],
                    'data' => $laData
                ];

            case 'list_rights':
                require_once dirname(__DIR__) . '/services/IAM.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_IAM_ListRights($la, array_merge(['context' => $appContext, 'appname' => $appContext], $params));
                $laData = $la->getData();
                return [
                    'success' => true,
                    'sources' => $laData['sources'] ?? [],
                    'data' => $laData
                ];

            case 'list_rbac':
                require_once dirname(__DIR__) . '/services/IAM.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_IAM_ListRBAC($la, array_merge(['context' => $appContext, 'appname' => $appContext], $params));
                $laData = $la->getData();
                return [
                    'success' => true,
                    'sources' => $laData['sources'] ?? [],
                    'data' => $laData
                ];

            case 'list_groups':
                $appname = $params['appname'] ?? $appContext ?? 'default';
                require_once dirname(__DIR__) . '/services/IAM.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_IAM_ListGroups($la, ['appname' => $appname]);
                $laData = $la->getData();
                return [
                    'success' => true,
                    'sources' => $laData['sources'] ?? [],
                    'data' => $laData
                ];

            case 'list_group_members':
                require_once dirname(__DIR__) . '/services/IAM.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_IAM_ListGroupMembers($la, $params);
                $laData = $la->getData();
                return [
                    'success' => $la->getStatus() !== 'error',
                    'members' => $laData['members'] ?? [],
                    'data' => $laData
                ];

            case 'add_group_member':
                require_once dirname(__DIR__) . '/services/IAM.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_IAM_AddGroupMember($la, $params);
                return [
                    'success' => $la->getStatus() !== 'error',
                    'message' => 'Member added to group.'
                ];

            case 'remove_group_member':
                require_once dirname(__DIR__) . '/services/IAM.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_IAM_RemoveGroupMember($la, $params);
                return [
                    'success' => $la->getStatus() !== 'error',
                    'message' => 'Member removed from group.'
                ];

            case 'save_group':
                require_once dirname(__DIR__) . '/services/IAM.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_IAM_SaveGroup($la, $params);
                return [
                    'success' => $la->getStatus() !== 'error',
                    'message' => 'Group saved successfully.'
                ];

            case 'delete_group':
                require_once dirname(__DIR__) . '/services/IAM.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_IAM_DeleteGroup($la, $params);
                return [
                    'success' => $la->getStatus() !== 'error',
                    'message' => 'Group deleted successfully.'
                ];

            case 'run_migrations':
                return self::executeCommand('migrate', ['--app=' . $appContext, '--json' => '1']);

            case 'sys_upgrade':
                return self::executeCommand('sys:upgrade', ['--app=' . $appContext, '--json' => '1']);

            case 'run_auto_tests':
                return self::executeCommand('sys:test:auto', ['--app=' . $appContext, '--json' => '1']);

            case 'clear_cache':
                return self::executeCommand('cache:clear', ['--json' => '1']);

            case 'tail_logs':
                $lines = (int)($params['lines'] ?? 50);
                return self::executeCommand('logger:tail', ['--lines=' . $lines]);

            case 'get_global_settings':
                $settings = \SPP\App::getGlobalSettings();
                $path = (defined('SPP_ETC_DIR') ? SPP_ETC_DIR : SPP_BASE_DIR . '/etc') . '/global-settings.yml';
                $raw = file_exists($path) ? file_get_contents($path) : Yaml::dump($settings, 10, 2);
                return [
                    'success' => true,
                    'data' => [
                        'parsed' => $settings,
                        'raw' => $raw,
                        'settings' => $settings
                    ],
                    'parsed' => $settings,
                    'raw' => $raw,
                    'settings' => $settings
                ];

            case 'save_global_settings':
                $path = (defined('SPP_ETC_DIR') ? SPP_ETC_DIR : SPP_BASE_DIR . '/etc') . '/global-settings.yml';
                $mode = $params['mode'] ?? 'form';
                if ($mode === 'yaml') {
                    $yaml = $params['yaml'] ?? '';
                    try {
                        $parsed = Yaml::parse($yaml);
                        file_put_contents($path, $yaml);
                        return ['success' => true, 'message' => 'Global settings saved via YAML.'];
                    } catch (\Throwable $e) {
                        return ['success' => false, 'error' => 'YAML Parse Error: ' . $e->getMessage()];
                    }
                } else {
                    $parsed = null;
                    if (isset($params['data'])) {
                        $parsed = is_array($params['data']) ? $params['data'] : json_decode($params['data'], true);
                    } elseif (isset($params['settings'])) {
                        $parsed = is_array($params['settings']) ? $params['settings'] : json_decode($params['settings'], true);
                    }
                    if ($parsed && is_array($parsed)) {
                        file_put_contents($path, Yaml::dump($parsed, 10, 2));
                        return ['success' => true, 'message' => 'Global settings updated successfully.'];
                    }
                    return ['success' => false, 'error' => 'Invalid settings payload.'];
                }

            case 'toggle_dev_mode':
                $path = (defined('SPP_ETC_DIR') ? SPP_ETC_DIR : SPP_BASE_DIR . '/etc') . '/global-settings.yml';
                $currentSettings = \SPP\App::getGlobalSettings('', true);
                $currentProfile = strtolower($currentSettings['profile'] ?? 'dev');
                $targetMode = isset($params['mode']) ? strtolower((string)$params['mode']) : null;
                if ($targetMode === null && isset($params['enable'])) {
                    $targetMode = $params['enable'] ? 'dev' : 'prod';
                }
                $newProfile = ($targetMode === 'dev' || $targetMode === 'prod') ? $targetMode : ($currentProfile === 'dev' ? 'prod' : 'dev');

                if (file_exists($path)) {
                    $raw = file_get_contents($path);
                    if (str_starts_with($raw, "\xEF\xBB\xBF")) {
                        $raw = substr($raw, 3);
                    }
                    $config = Yaml::parse($raw) ?: [];
                    $config['profile'] = $newProfile;
                    if (!isset($config['settings'])) {
                        $config['settings'] = [];
                    }
                    $config['settings']['debug'] = ($newProfile === 'dev');
                    file_put_contents($path, Yaml::dump($config, 10, 2));
                }

                $xmlPath = (defined('SPP_BASE_DIR') ? SPP_BASE_DIR : dirname($path)) . '/etc/settings.xml';
                if (file_exists($xmlPath)) {
                    $xmlContent = file_get_contents($xmlPath);
                    $xmlContent = preg_replace('/<profile>.*?<\/profile>/', '<profile>' . ($newProfile === 'dev' ? 'dev' : 'production') . '</profile>', $xmlContent);
                    file_put_contents($xmlPath, $xmlContent);
                }

                $cacheFile = (defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 4)) . '/var/cache/system/config.php';
                if (file_exists($cacheFile)) {
                    @unlink($cacheFile);
                }

                return [
                    'success' => true,
                    'profile' => $newProfile,
                    'is_dev' => ($newProfile === 'dev'),
                    'message' => "Switched system profile to '{$newProfile}'. System apps (sppadmin, sppdev) are now " . ($newProfile === 'dev' ? 'enabled' : 'disabled') . "."
                ];

            case 'diagnostics_health':
                $health = [
                    'status' => 'UP',
                    'timestamp' => date('c'),
                    'php_version' => PHP_VERSION,
                    'sapi' => PHP_SAPI,
                    'opcache_enabled' => function_exists('opcache_get_status') && !empty(opcache_get_status()),
                    'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
                    'active_app' => $appContext,
                    'profile' => \SPP\App::getGlobalSettings()['profile'] ?? 'dev',
                    'components' => []
                ];

                // 1. Database Component
                try {
                    $db = new \SPPMod\SPPDB\SPPDB();
                    if ($db->getDriver() !== 'xdb') {
                        $db->execute_query("SELECT 1");
                    }
                    $health['components']['database'] = ['status' => 'UP', 'message' => 'Connected (' . $db->getDriver() . ')'];
                } catch (\Throwable $e) {
                    $health['status'] = 'DEGRADED';
                    $health['components']['database'] = ['status' => 'DOWN', 'message' => $e->getMessage()];
                }

                // 2. Redis Component (if configured)
                try {
                    if (class_exists('\\SPP\\Module') && \SPP\Module::getConfig('host', 'redis') && extension_loaded('redis')) {
                        if (class_exists('\\SPP\\Core\\RedisCache')) {
                            $redis = \SPP\Core\RedisCache::getConnection();
                            $redis->ping();
                            $health['components']['redis'] = ['status' => 'UP', 'message' => 'Active'];
                        } else {
                            $health['components']['redis'] = ['status' => 'UP', 'message' => 'File Cache Fallback'];
                        }
                    } else {
                        $health['components']['redis'] = ['status' => 'UP', 'message' => 'File Cache (Redis Optional)'];
                    }
                } catch (\Throwable $e) {
                    $health['components']['redis'] = ['status' => 'UP', 'message' => 'File Cache (' . $e->getMessage() . ')'];
                }

                // 3. Filesystem Component
                $writeableDirs = [SPP_BASE_DIR . '/var', SPP_BASE_DIR . '/var/logs'];
                foreach ($writeableDirs as $dir) {
                    if (!is_dir($dir)) {
                        @mkdir($dir, 0777, true);
                    }
                    $key = 'fs_' . basename($dir);
                    $isWritable = is_writable($dir);
                    if (!$isWritable) {
                        $health['status'] = 'DEGRADED';
                    }
                    $health['components'][$key] = [
                        'status' => $isWritable ? 'UP' : 'DOWN',
                        'path' => $dir,
                        'writable' => $isWritable
                    ];
                }

                // 4. System Component
                $health['components']['system'] = [
                    'status' => 'UP',
                    'memory_usage' => $health['memory_usage'],
                    'php_version' => PHP_VERSION,
                    'sapi' => PHP_SAPI,
                    'opcache' => $health['opcache_enabled'] ? 'Enabled' : 'Disabled'
                ];

                return array_merge(['success' => true, 'database' => $health['components']['database']['message'] ?? 'Connected'], $health);

            case 'get_event_trace':
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
                return ['success' => true, 'traces' => $traces];

            case 'get_parikshak_trace':
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
                return [
                    'success' => true,
                    'content' => $content ?: "No Parikshak activity logged yet.\nClick 'Trigger Evolutionary Scan' or run 'php spp.php test:run' to generate test logs.",
                    'traces' => []
                ];

            case 'run_parikshak_scan':
                $app = $params['appname'] ?? $params['app'] ?? 'default';
                if (empty($app)) $app = 'default';
                $res = self::executeCommand('test:run', ['--app' => $app]);
                $output = $res['output'] ?? '';
                $logDir = defined('SPP_LOG_DIR') ? SPP_LOG_DIR : SPP_BASE_DIR . '/var/logs';
                if (!is_dir($logDir)) @mkdir($logDir, 0777, true);
                $logEntry = "[" . date('Y-m-d H:i:s') . "] Evolutionary Scan executed for '{$app}':\n" . $output . "\n----------------------------------------\n";
                @file_put_contents($logDir . '/parikshak_events.log', $logEntry, FILE_APPEND);
                return [
                    'success' => true,
                    'output' => $output,
                    'message' => "Parikshak scan executed successfully for {$app}."
                ];

            case 'report_api':
                $reportApi = SPP_BASE_DIR . '/modules/optional/sppreport/api.php';
                if (!file_exists($reportApi)) {
                    $reportApi = SPP_BASE_DIR . '/modules/spp/sppreport/api.php';
                }
                if (file_exists($reportApi)) {
                    require_once $reportApi;
                    $controller = new \SPPMod\SPPReport\ReportController();
                    $controller->handleRequest();
                    exit;
                }
                return ['success' => false, 'error' => 'SPPReport module is not installed.'];

            case 'list_reports':
                $root = dirname(SPP_BASE_DIR);
                $app = $params['appname'] ?? $params['app'] ?? $appContext ?? 'default';
                $reportDirs = [
                    $root . "/src/{$app}/etc/sppreports",
                    $root . "/etc/apps/{$app}/reports",
                    $root . "/etc/sppreports",
                    SPP_BASE_DIR . "/etc/sppreports"
                ];
                $reports = [];
                foreach ($reportDirs as $dir) {
                    if (is_dir($dir)) {
                        foreach (glob($dir . '/*.yml') as $file) {
                            $baseName = basename($file, '.yml');
                            if (!isset($reports[$baseName])) {
                                try {
                                    $parsed = Yaml::parseFile($file);
                                    $repData = $parsed['report'] ?? $parsed;
                                    $reports[$baseName] = [
                                        'name' => $baseName,
                                        'title' => $repData['name'] ?? ucfirst(str_replace('_', ' ', $baseName)),
                                        'description' => $repData['description'] ?? '',
                                        'table' => $repData['table'] ?? ($repData['data_source']['table'] ?? 'users'),
                                        'path' => $file,
                                        'config' => $parsed
                                    ];
                                } catch (\Throwable $e) {
                                    $reports[$baseName] = [
                                        'name' => $baseName,
                                        'title' => ucfirst(str_replace('_', ' ', $baseName)),
                                        'description' => 'Report definition file',
                                        'table' => 'users',
                                        'path' => $file,
                                        'config' => []
                                    ];
                                }
                            }
                        }
                    }
                }
                return ['success' => true, 'reports' => array_values($reports)];

            case 'load_report':
                $name = $params['name'] ?? $params['report_name'] ?? '';
                if (!$name) {
                    return ['success' => false, 'error' => 'Report name is required.'];
                }
                $root = dirname(SPP_BASE_DIR);
                $app = $params['appname'] ?? $params['app'] ?? $appContext ?? 'default';
                $searchFiles = [
                    $root . "/src/{$app}/etc/sppreports/{$name}.yml",
                    $root . "/etc/apps/{$app}/reports/{$name}.yml",
                    $root . "/etc/sppreports/{$name}.yml"
                ];
                foreach ($searchFiles as $file) {
                    if (file_exists($file)) {
                        $parsed = Yaml::parseFile($file);
                        return ['success' => true, 'name' => $name, 'config' => $parsed, 'raw' => file_get_contents($file)];
                    }
                }
                return ['success' => false, 'error' => "Report '{$name}' not found."];

            case 'save_report':
                $name = preg_replace('/[^a-zA-Z0-9_-]/', '', strtolower($params['name'] ?? $params['report_name'] ?? ''));
                if (!$name) {
                    return ['success' => false, 'error' => 'Valid report name is required.'];
                }
                $config = $params['config'] ?? $params['data'] ?? [];
                if (is_string($config)) {
                    $decoded = json_decode($config, true);
                    if ($decoded) $config = $decoded;
                }
                $root = dirname(SPP_BASE_DIR);
                $app = $params['appname'] ?? $params['app'] ?? $appContext ?? 'default';
                $targetDir = $root . "/src/{$app}/etc/sppreports";
                if (!is_dir($targetDir)) {
                    @mkdir($targetDir, 0777, true);
                }
                $targetFile = $targetDir . "/{$name}.yml";
                $yamlStr = is_array($config) ? Yaml::dump($config, 10, 2) : (string)$config;
                file_put_contents($targetFile, $yamlStr);
                return ['success' => true, 'message' => "Report '{$name}' saved successfully.", 'path' => $targetFile];

            case 'delete_report':
                $name = preg_replace('/[^a-zA-Z0-9_-]/', '', strtolower($params['name'] ?? ''));
                if (!$name) return ['success' => false, 'error' => 'Report name is required.'];
                $root = dirname(SPP_BASE_DIR);
                $app = $params['appname'] ?? $params['app'] ?? $appContext ?? 'default';
                $searchFiles = [
                    $root . "/src/{$app}/etc/sppreports/{$name}.yml",
                    $root . "/etc/apps/{$app}/reports/{$name}.yml",
                    $root . "/etc/sppreports/{$name}.yml"
                ];
                foreach ($searchFiles as $file) {
                    if (file_exists($file)) {
                        @unlink($file);
                        return ['success' => true, 'message' => "Report '{$name}' deleted."];
                    }
                }
                return ['success' => false, 'error' => "Report '{$name}' not found."];

            case 'report_schema':
                try {
                    $root = dirname(defined('SPP_BASE_DIR') ? SPP_BASE_DIR : dirname(__DIR__, 2));
                    $schema = [];
                    $driver = 'sqlite';

                    if (!class_exists('\\SPPMod\\SPPDB\\SPPDB')) {
                        $sppDbPaths = [
                            (defined('SPP_BASE_DIR') ? SPP_BASE_DIR : dirname(__DIR__, 2)) . '/modules/optional/sppdb/class.sppdb.php',
                            (defined('SPP_BASE_DIR') ? SPP_BASE_DIR : dirname(__DIR__, 2)) . '/modules/spp/sppdb/class.sppdb.php'
                        ];
                        foreach ($sppDbPaths as $p) {
                            if (file_exists($p)) { require_once $p; break; }
                        }
                    }

                    if (class_exists('\\SPPMod\\SPPDB\\SPPDB')) {
                        try {
                            $db = new \SPPMod\SPPDB\SPPDB();
                            $driver = $db->getDriver();
                            if ($driver === 'sqlite') {
                                $tables = [];
                                try {
                                    $tables = $db->execute_query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
                                } catch (\Throwable $e) {}
                                
                                if (!empty($tables)) {
                                    foreach ($tables as $t) {
                                        $tbl = $t['name'];
                                        $cols = $db->execute_query("PRAGMA table_info(\"{$tbl}\")");
                                        $schema[$tbl] = array_column($cols, 'name');
                                    }
                                }
                            } else {
                                $tables = $db->execute_query("SHOW TABLES");
                                foreach ($tables as $t) {
                                    $tbl = array_values($t)[0];
                                    $cols = $db->execute_query("SHOW COLUMNS FROM `{$tbl}`");
                                    $schema[$tbl] = array_column($cols, 'Field');
                                }
                            }
                        } catch (\Throwable $e) {}
                    }

                    if (empty($schema)) {
                        $dbPaths = [
                            $root . '/var/db/school.sqlite',
                            $root . '/var/db/default.sqlite',
                            (defined('SPP_BASE_DIR') ? SPP_BASE_DIR : dirname(__DIR__, 2)) . '/var/db/default.sqlite'
                        ];
                        foreach ($dbPaths as $dbp) {
                            if (file_exists($dbp)) {
                                $pdo = new \PDO("sqlite:" . $dbp);
                                $tRows = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(\PDO::FETCH_ASSOC);
                                if (!empty($tRows)) {
                                    foreach ($tRows as $t) {
                                        $tbl = $t['name'];
                                        $cols = $pdo->query("PRAGMA table_info(\"{$tbl}\")")->fetchAll(\PDO::FETCH_ASSOC);
                                        $schema[$tbl] = array_column($cols, 'name');
                                    }
                                    break;
                                }
                            }
                        }
                    }
                    return ['success' => true, 'status' => 'success', 'driver' => $driver, 'schema' => $schema, 'tables' => array_keys($schema)];
                } catch (\Throwable $e) {
                    return ['success' => false, 'status' => 'error', 'error' => $e->getMessage(), 'schema' => [], 'tables' => []];
                }

            case 'preview_report':
                $sql = trim($params['sql'] ?? $params['query'] ?? '');
                $limit = (int)($params['limit'] ?? 100);
                if ($limit <= 0 || $limit > 1000) $limit = 100;

                if (!$sql) {
                    $table = $params['table'] ?? 'users';
                    $cols = $params['columns'] ?? ['*'];
                    if (is_array($cols)) {
                        $colParts = [];
                        foreach ($cols as $c) {
                            if (is_array($c)) {
                                $field = $c['field'] ?? '*';
                                $agg = strtoupper(trim($c['aggregate'] ?? ''));
                                $alias = $c['alias'] ?? '';
                                $expr = $agg ? "{$agg}({$field})" : $field;
                                if ($alias) $expr .= " AS \"{$alias}\"";
                                $colParts[] = $expr;
                            } else {
                                $colParts[] = (string)$c;
                            }
                        }
                        $colsStr = implode(', ', $colParts) ?: '*';
                    } else {
                        $colsStr = (string)$cols ?: '*';
                    }
                    $sql = "SELECT {$colsStr} FROM {$table}";
                    if (!empty($params['where'])) {
                        $sql .= " WHERE " . $params['where'];
                    }
                    if (!empty($params['groupBy'])) {
                        $groupBy = is_array($params['groupBy']) ? implode(', ', $params['groupBy']) : $params['groupBy'];
                        $sql .= " GROUP BY " . $groupBy;
                    }
                    if (!empty($params['orderBy'])) {
                        $sql .= " ORDER BY " . $params['orderBy'];
                    }
                    $sql .= " LIMIT {$limit}";
                }

                try {
                    $start = microtime(true);
                    $rows = [];
                    $root = dirname(SPP_BASE_DIR);
                    $app = $params['appname'] ?? $params['app'] ?? $params['context'] ?? null;
                    
                    $executed = false;
                    if ($app && class_exists('\\SPP\\Scheduler')) {
                        try {
                            \SPP\Scheduler::withContext($app, function() use ($sql, &$rows, &$executed) {
                                $db = new \SPPMod\SPPDB\SPPDB();
                                $rows = $db->execute_query($sql);
                                $executed = true;
                            });
                        } catch (\Throwable $e) {}
                    }
                    
                    if (!$executed) {
                        try {
                            $db = new \SPPMod\SPPDB\SPPDB();
                            $rows = $db->execute_query($sql);
                            $executed = true;
                        } catch (\Throwable $e) {
                            $dbPaths = [
                                $root . '/var/db/school.sqlite',
                                $root . '/var/db/default.sqlite',
                                SPP_BASE_DIR . '/var/db/default.sqlite'
                            ];
                            foreach ($dbPaths as $dbp) {
                                if (file_exists($dbp)) {
                                    try {
                                        $pdo = new \PDO("sqlite:" . $dbp);
                                        $stmt = $pdo->query($sql);
                                        if ($stmt) {
                                            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                                            $executed = true;
                                            break;
                                        }
                                    } catch (\Throwable $ex) {}
                                }
                            }
                            if (!$executed) throw $e;
                        }
                    }

                    $duration = round((microtime(true) - $start) * 1000, 2);
                    $columns = !empty($rows) ? array_keys($rows[0]) : [];
                    return [
                        'success' => true,
                        'status' => 'success',
                        'sql' => $sql,
                        'rows' => $rows,
                        'columns' => $columns,
                        'count' => count($rows),
                        'duration_ms' => $duration
                    ];
                } catch (\Throwable $e) {
                    return [
                        'success' => false,
                        'status' => 'error',
                        'sql' => $sql,
                        'error' => $e->getMessage()
                    ];
                }

            case 'get_di_bindings':
                $bindings = [];
                if (class_exists('\\SPP\\Core\\Container')) {
                    $container = \SPP\Core\Container::getInstance();
                    if (method_exists($container, 'getBindings')) {
                        $bindings = $container->getBindings();
                    }
                }
                if (empty($bindings)) {
                    $bindings = [
                        ['abstract' => 'SPP\\Core\\App', 'concrete' => 'SPP\\Core\\App', 'shared' => true, 'instantiated' => true],
                        ['abstract' => 'SPPMod\\SPPDB\\SPPDB', 'concrete' => 'SPPMod\\SPPDB\\SPPDB', 'shared' => true, 'instantiated' => true],
                        ['abstract' => 'SPPMod\\SPPAuth\\SPPAuth', 'concrete' => 'SPPMod\\SPPAuth\\SPPAuth', 'shared' => true, 'instantiated' => false],
                        ['abstract' => 'SPPMod\\SPPAI\\SPPAI', 'concrete' => 'SPPMod\\SPPAI\\SPPAI', 'shared' => true, 'instantiated' => false],
                        ['abstract' => 'SPPMod\\SPPReport\\SPPReport', 'concrete' => 'SPPMod\\SPPReport\\SPPReport', 'shared' => false, 'instantiated' => false],
                    ];
                }
                return ['success' => true, 'bindings' => $bindings];

            case 'list_xdb_databases':
                return self::executeCommand('xdb:list-dbs', ['--json' => '1']);

            case 'list_xdb_tables':
                $db = $params['dbname'] ?? $params['db'] ?? $params['database'] ?? 'default';
                return self::executeCommand('xdb:list-tables', ['--db=' . $db, '--json' => '1']);

            case 'get_xdb_table_columns':
                $db = $params['dbname'] ?? $params['db'] ?? $params['database'] ?? 'default';
                $table = $params['table'] ?? $params['tableName'] ?? '';
                return self::executeCommand('xdb:describe', [$table, '--db=' . $db, '--json' => '1']);

            case 'run_xdb_query':
                $db = $params['dbname'] ?? $params['db'] ?? $params['database'] ?? 'default';
                $sql = $params['sql'] ?? $params['query'] ?? '';
                $type = $params['type'] ?? 'sql';
                return self::executeCommand('xdb:query', [$sql, '--db=' . $db, '--type=' . $type, '--json' => '1']);

            case 'get_xdb_table_data':
                $db = $params['dbname'] ?? $params['db'] ?? $params['database'] ?? 'default';
                $table = $params['table'] ?? $params['tableName'] ?? '';
                $limit = (int)($params['limit'] ?? 100);
                $res = self::executeCommand('xdb:query', ["SELECT * FROM {$table} LIMIT {$limit}", '--db=' . $db, '--type=sql', '--json' => '1']);
                if (!empty($res['success'])) {
                    return ['success' => true, 'rows' => $res['results'] ?? []];
                }
                return $res;

            case 'xdb_migrate':
                return self::executeCommand('xdb:migrate', ['--json' => '1']);

            case 'xdb_seed':
                return self::executeCommand('xdb:seed', ['--json' => '1']);

            case 'editor_list_files':
                $relPath = trim($params['path'] ?? '', '/\\');
                if (str_contains($relPath, '..')) {
                    return ['success' => false, 'error' => 'Invalid path: directory traversal prohibited.'];
                }
                $baseDir = defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(SPP_BASE_DIR);
                $targetDir = empty($relPath) ? $baseDir : $baseDir . DIRECTORY_SEPARATOR . $relPath;
                if (!is_dir($targetDir)) {
                    return ['success' => false, 'error' => "Directory not found: {$relPath}"];
                }
                $items = [];
                $entries = scandir($targetDir) ?: [];
                foreach ($entries as $entry) {
                    if ($entry === '.' || $entry === '..' || $entry === '.git') continue;
                    $full = $targetDir . DIRECTORY_SEPARATOR . $entry;
                    $isDir = is_dir($full);
                    $itemPath = empty($relPath) ? $entry : $relPath . '/' . $entry;
                    $items[] = [
                        'name' => $entry,
                        'path' => $itemPath,
                        'is_dir' => $isDir,
                        'size' => $isDir ? 0 : (filesize($full) ?: 0)
                    ];
                }
                usort($items, function($a, $b) {
                    if ($a['is_dir'] !== $b['is_dir']) return $a['is_dir'] ? -1 : 1;
                    return strcasecmp($a['name'], $b['name']);
                });
                return ['success' => true, 'path' => $relPath, 'items' => $items];

            case 'editor_read_file':
                $relPath = trim($params['path'] ?? '', '/\\');
                if (str_contains($relPath, '..')) {
                    return ['success' => false, 'error' => 'Invalid path: directory traversal prohibited.'];
                }
                $baseDir = defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(SPP_BASE_DIR);
                $full = $baseDir . DIRECTORY_SEPARATOR . $relPath;
                if (!is_file($full)) {
                    return ['success' => false, 'error' => "File not found: {$relPath}"];
                }
                return ['success' => true, 'path' => $relPath, 'content' => file_get_contents($full) ?: ''];

            case 'editor_write_file':
                $relPath = trim($params['path'] ?? '', '/\\');
                if (str_contains($relPath, '..')) {
                    return ['success' => false, 'error' => 'Invalid path: directory traversal prohibited.'];
                }
                $baseDir = defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(SPP_BASE_DIR);
                $full = $baseDir . DIRECTORY_SEPARATOR . $relPath;
                $content = $params['content'] ?? '';
                $dir = dirname($full);
                if (!is_dir($dir)) {
                    @mkdir($dir, 0777, true);
                }
                $res = file_put_contents($full, $content);
                if ($res === false) {
                    return ['success' => false, 'error' => "Failed to write file: {$relPath}"];
                }
                return ['success' => true, 'message' => 'File saved successfully.'];

            case 'editor_get_completions':
                $completions = [
                    ['label' => 'SPP\\App', 'kind' => 7, 'detail' => 'Core Application Facade'],
                    ['label' => 'SPP\\Module', 'kind' => 7, 'detail' => 'Module Manager'],
                    ['label' => 'SPP\\SPPConfig', 'kind' => 7, 'detail' => 'Configuration Access'],
                    ['label' => 'SPP\\Registry', 'kind' => 7, 'detail' => 'Global In-Memory Registry'],
                    ['label' => 'SPP\\SPPEvent', 'kind' => 7, 'detail' => 'Event Bus'],
                    ['label' => 'SPPMod\\SPPDB\\SPPEntity', 'kind' => 7, 'detail' => 'ActiveRecord Entity Model'],
                    ['label' => 'SPPMod\\SPPXDB\\SPP_XDB', 'kind' => 7, 'detail' => 'XML/SQLite Database Engine'],
                    ['label' => 'SPPMod\\SPPAI\\SPPAI', 'kind' => 7, 'detail' => 'AI Provider Gateway'],
                    ['label' => 'SPPMod\\SPPAPI\\LiveAction', 'kind' => 7, 'detail' => 'SPA API Response Builder'],
                ];
                return ['success' => true, 'completions' => $completions];

            // -------------------------------------------------------------
            // DOMAINS & VIRTUAL HOSTS (SPP Platform Operations)
            // -------------------------------------------------------------
            case 'domains_list':
                return DomainAdminService::listDomains();

            case 'domain_save':
            case 'domains_save':
                return DomainAdminService::saveDomain($params['pattern'] ?? ($params['domain'] ?? ''), $params);

            case 'domain_delete':
            case 'domains_delete':
                return DomainAdminService::deleteDomain($params['pattern'] ?? ($params['domain'] ?? ''));

            case 'domains_subapp_save':
                return DomainAdminService::saveSubAppRoute($params['domain'] ?? '', $params['route'] ?? '', $params);

            case 'domains_subapp_delete':
                return DomainAdminService::deleteSubAppRoute($params['domain'] ?? '', $params['route'] ?? '');

            case 'domains_subapp_probe':
                return DomainAdminService::probeSubAppPath($params['target_path'] ?? ($params['path'] ?? ''));

            case 'domain_test':
                return DomainAdminService::testDomain($params['host'] ?? '', $params['path'] ?? '/', $params['method'] ?? 'GET');

            case 'domain_check_ssl':
                return DomainAdminService::checkSsl($params['host'] ?? '');

            case 'domains_get_settings':
                return DomainAdminService::getDomainSettings();

            case 'domains_set_domain_mode':
            case 'domains_set_architecture_mode':
                return DomainAdminService::setDomainMode($params['mode'] ?? ($params['domain_mode'] ?? 'single'));

            case 'domains_set_mode':
                $requestedMode = strtolower(trim((string)($params['mode'] ?? ($params['domain_mode'] ?? ''))));
                if (in_array($requestedMode, \SPP\Core\Router\DomainRouter::DOMAIN_MODES, true) || isset($params['domain_mode'])) {
                    return DomainAdminService::setDomainMode($params['domain_mode'] ?? $requestedMode);
                }
                return DomainAdminService::setVirtualHostMode($params['mode'] ?? 'strict', $params['unmapped_subdomain_policy'] ?? null);

            case 'domains_set_virtual_host_mode':
                return DomainAdminService::setVirtualHostMode($params['mode'] ?? ($params['virtual_host_mode'] ?? 'strict'), $params['unmapped_subdomain_policy'] ?? null);

            case 'domains_addon_list':
                return DomainAdminService::listAddonDomains($params['account'] ?? null);

            case 'domains_addon_add':
                return DomainAdminService::addAddonDomain(
                    $params['domain'] ?? '',
                    $params['default_app'] ?? 'default',
                    $params['account'] ?? 'default',
                    $params
                );

            case 'domains_addon_remove':
                return DomainAdminService::removeAddonDomain($params['domain'] ?? '');

            case 'domains_get_apps':
                return DomainAdminService::listDomainApps($params['domain'] ?? null);

            case 'domains_get_domain_apps':
                return DomainAdminService::getDomainApps($params['domain'] ?? '');

            case 'domains_set_default_app':
                return DomainAdminService::setDomainDefaultApp(
                    $params['domain'] ?? '',
                    $params['app_name'] ?? ($params['app'] ?? ''),
                    $params['demoted_route'] ?? null
                );

            case 'domains_install_app':
                return DomainAdminService::installDomainApp(
                    $params['domain'] ?? '',
                    $params['app_name'] ?? ($params['app'] ?? ''),
                    $params
                );

            case 'domains_set_app_scope':
                $allowed = isset($params['allowed_domains'])
                    ? (is_array($params['allowed_domains']) ? $params['allowed_domains'] : explode(',', (string)$params['allowed_domains']))
                    : [];
                return DomainAdminService::setAppDomainScope(
                    $params['app_name'] ?? ($params['app'] ?? ''),
                    !empty($params['domain_only']),
                    $allowed
                );

            case 'domains_delete_app':
                return DomainAdminService::deleteDomainApp(
                    $params['domain'] ?? '',
                    $params['app_name'] ?? ($params['app'] ?? ''),
                    !empty($params['delete_files'])
                );

            case 'domains_track_dns':
                return DomainAdminService::trackDNS(
                    $params['hostname'] ?? ($params['host'] ?? ''),
                    $params['type'] ?? 'ANY',
                    !empty($params['trace'])
                );

            case 'domains_proxy_get':
                return DomainAdminService::getReverseProxy($params['pattern'] ?? ($params['domain'] ?? ''));

            case 'domains_proxy_save':
                return DomainAdminService::saveReverseProxy(
                    $params['pattern'] ?? ($params['domain'] ?? ''),
                    $params['proxy'] ?? $params
                );

            case 'domains_proxy_probe':
                return DomainAdminService::probeProxyTarget($params['target'] ?? ($params['url'] ?? ''));

            case 'domains_vhost_inspect':
                return DomainAdminService::inspectVhost($params['domain'] ?? ($params['pattern'] ?? ''), $params);

            case 'domains_vhost_test':
                return DomainAdminService::testVhostConfig($params['content'] ?? null, $params['driver'] ?? null);

            case 'domains_fastcgi_purge':
                return DomainAdminService::purgeFastCgiCache($params['domain'] ?? ($params['pattern'] ?? ''));

            case 'domains_webserver_drivers':
                return DomainAdminService::listWebserverDrivers();

            case 'domains_set_webserver_driver':
                return DomainAdminService::setWebserverDriver($params['driver'] ?? 'auto');

            case 'domains_autossl_issue':
            case 'domains_autossl_provision':
                return DomainAdminService::autoProvisionSSL(
                    $params['domain'] ?? '',
                    $params['token'] ?? '',
                    $params['key_auth'] ?? '',
                    $params['email'] ?? '',
                    !empty($params['staging']),
                    !empty($params['dry_run']),
                    isset($params['execute']) ? !empty($params['execute']) : (!empty($params['email']) || !empty($params['staging']) || !empty($params['dry_run']))
                );

            case 'domains_autossl_renew_all':
                return DomainAdminService::renewExpiringCertificates(
                    (int)($params['days_threshold'] ?? 30),
                    !empty($params['dry_run'])
                );

            case 'domains_sovereign_ssl_overview':
                return DomainAdminService::getSovereignSslOverview();

            // Certbot Host SSL Provisioner & DNS Pre-flight
            case 'domains_certbot_status':
                return DomainAdminService::getCertbotStatus();

            case 'domains_certbot_install':
                return DomainAdminService::installCertbot($params['webserver'] ?? 'apache');

            case 'domains_dns_preflight':
                $domains = isset($params['domains'])
                    ? (is_array($params['domains']) ? $params['domains'] : array_map('trim', explode(',', (string)$params['domains'])))
                    : [trim((string)($params['domain'] ?? ''))];
                return DomainAdminService::checkDnsPreflight($domains, $params['server_ip'] ?? null);

            case 'domains_certbot_issue':
                $domains = isset($params['domains'])
                    ? (is_array($params['domains']) ? $params['domains'] : array_map('trim', explode(',', (string)$params['domains'])))
                    : [trim((string)($params['domain'] ?? ''))];
                return DomainAdminService::issueCertbotSsl(
                    $domains,
                    $params['email'] ?? '',
                    $params['method'] ?? 'apache',
                    !empty($params['staging']),
                    !empty($params['dry_run']),
                    isset($params['force_redirect']) ? !empty($params['force_redirect']) : true
                );

            // Child Nameserver & TLD Root Glue Diagnostics
            case 'domains_glue_matrix':
                $nameservers = isset($params['nameservers'])
                    ? (is_array($params['nameservers']) ? $params['nameservers'] : array_map('trim', explode(',', (string)$params['nameservers'])))
                    : [];
                return DomainAdminService::getGlueDiagnosticMatrix(
                    $params['domain'] ?? ($_SERVER['HTTP_HOST'] ?? 'example.com'),
                    $nameservers,
                    $params['server_ip'] ?? null
                );

            case 'domains_tld_glue':
                return DomainAdminService::queryTldRootGlue($params['nameserver'] ?? ('ns1.' . ($_SERVER['HTTP_HOST'] ?? 'example.com')));

            case 'domains_registry_whois':
                return DomainAdminService::queryRegistryWhois($params['nameserver'] ?? ('ns1.' . ($_SERVER['HTTP_HOST'] ?? 'example.com')));

            // BIND9 Daemon & Zone Synchronization
            case 'bind_daemon_status':
                return BindDaemonService::getDaemonStatus();

            case 'bind_daemon_install':
                return BindDaemonService::installDaemon();

            case 'bind_zone_sync':
                return BindDaemonService::syncZone($params['domain'] ?? ($_SERVER['HTTP_HOST'] ?? 'example.com'));

            case 'bind_all_zones_sync':
                return BindDaemonService::syncAllZones();

            case 'bind_zone_test':
                return BindDaemonService::testZone($params['domain'] ?? ($_SERVER['HTTP_HOST'] ?? 'example.com'));

            case 'bind_daemon_reload':
                return BindDaemonService::reloadDaemon();

            // Sovereign System Doctor Diagnostics & Self-Healing
            case 'doctor_diagnostics':
                return SystemDoctorService::runDiagnostics($params);

            case 'doctor_repair':
                return SystemDoctorService::autoRepair($params['check_id'] ?? ($params['id'] ?? 'fix_all'));

            // 1-Click OS-Neutral Host Provisioner
            case 'host_inspect':
                return HostProvisionService::inspectHost();

            case 'host_provision':
                return HostProvisionService::provisionHost($params);

            case 'host_deconflict':
                return HostProvisionService::deconflictWebserver($params['webserver'] ?? 'apache');

            // DNS Zone Editor & Global Propagation
            case 'domains_zone_get':
            case 'domain_get_zone':
                return DomainAdminService::getZoneRecords($params['domain'] ?? $params['host'] ?? '', $params['server_ip'] ?? '127.0.0.1');

            case 'domains_zone_save_record':
            case 'domain_save_record':
                return DomainAdminService::saveZoneRecord($params['domain'] ?? $params['host'] ?? '', $params['record'] ?? $params);

            case 'domains_zone_delete_record':
            case 'domain_delete_record':
                return DomainAdminService::deleteZoneRecord($params['domain'] ?? $params['host'] ?? '', $params['record_id'] ?? ($params['id'] ?? ''));

            case 'domains_zone_reset':
            case 'domain_reset_zone':
                return DomainAdminService::resetZone($params['domain'] ?? $params['host'] ?? '', $params['server_ip'] ?? '127.0.0.1');

            case 'domains_zone_export':
            case 'domain_export_zone':
                return DomainAdminService::exportZone($params['domain'] ?? $params['host'] ?? '');

            case 'domains_zone_import':
            case 'domain_import_zone':
                return DomainAdminService::importZone($params['domain'] ?? $params['host'] ?? '', $params['zone_text'] ?? ($params['content'] ?? ''));

            case 'domains_zone_history':
            case 'domain_get_zone_history':
            case 'dns_zone_history':
                return DomainAdminService::getZoneHistory($params['domain'] ?? ($params['host'] ?? ''));

            case 'domains_preview_rollback':
            case 'domain_preview_rollback':
            case 'dns_preview_rollback':
                return DomainAdminService::previewZoneRollback(
                    $params['domain'] ?? ($params['host'] ?? ''),
                    $params['rev_id'] ?? ($params['revision'] ?? '')
                );

            case 'domains_rollback_zone':
            case 'domain_rollback_zone':
            case 'dns_rollback_zone':
                return DomainAdminService::rollbackZone(
                    $params['domain'] ?? ($params['host'] ?? ''),
                    $params['rev_id'] ?? ($params['revision'] ?? '')
                );

            case 'domains_sync_manifest':
            case 'domain_sync_manifest':
            case 'dns_sync_manifest':
                return DomainAdminService::syncZoneToManifest($params['domain'] ?? ($params['host'] ?? ''));

            case 'domains_check_propagation':
            case 'domain_check_propagation':
                return DomainAdminService::checkGlobalPropagation($params['hostname'] ?? ($params['domain'] ?? ''), $params['type'] ?? 'A');

            case 'domains_dnssec_get':
            case 'domain_get_dnssec':
                return DomainAdminService::getDnssec($params['domain'] ?? $params['host'] ?? '');

            case 'domains_dnssec_toggle':
            case 'domain_toggle_dnssec':
                return DomainAdminService::toggleDnssec($params['domain'] ?? $params['host'] ?? '', !empty($params['enabled']));

            // Cloudflare Edge DNS & Edge SSL Bridge
            case 'domains_cf_get_settings':
                return DomainAdminService::getCloudflareSettings();

            case 'domains_cf_save_settings':
                return DomainAdminService::saveCloudflareSettings($params['settings'] ?? $params);

            case 'domains_cf_verify_token':
                return DomainAdminService::verifyCloudflareToken($params['token'] ?? null);

            case 'domains_cf_list_zones':
                return DomainAdminService::listCloudflareZones();

            case 'domains_cf_list_records':
                return DomainAdminService::listCloudflareRecords($params['domain'] ?? $params['host'] ?? '', $params);

            case 'domains_cf_create_record':
                return DomainAdminService::createCloudflareRecord($params['domain'] ?? $params['host'] ?? '', $params['record'] ?? $params);

            case 'domains_cf_update_record':
                return DomainAdminService::updateCloudflareRecord($params['domain'] ?? $params['host'] ?? '', (string)($params['record_id'] ?? ($params['id'] ?? '')), $params['record'] ?? $params);

            case 'domains_cf_delete_record':
                return DomainAdminService::deleteCloudflareRecord($params['domain'] ?? $params['host'] ?? '', (string)($params['record_id'] ?? ($params['id'] ?? '')));

            case 'domains_cf_purge_cache':
                return DomainAdminService::purgeCloudflareCache($params['domain'] ?? $params['host'] ?? '', !empty($params['purge_everything']), (array)($params['files'] ?? []));

            case 'domains_cf_sync_zone':
                return DomainAdminService::syncFromCloudflare($params['domain'] ?? $params['host'] ?? '');

            case 'domains_cf_auto_provision':
                return DomainAdminService::autoProvisionCloudflare($params['hostname'] ?? ($params['domain'] ?? ''), $params['target_ip'] ?? null, isset($params['proxied']) ? (bool)$params['proxied'] : true);

            // -------------------------------------------------------------
            // RELATIONAL & NOSQL DATABASE STUDIO (SPP Database Studio)
            // -------------------------------------------------------------
            case 'db_list_databases':
                return DatabaseAdminService::listDatabases($params['context'] ?? $params['appname'] ?? null);

            case 'db_list_tables':
                return DatabaseAdminService::listTables($params['connection'] ?? ($params['database'] ?? null), $params['context'] ?? $params['appname'] ?? null);

            case 'db_describe_table':
                return DatabaseAdminService::describeTable($params['table'] ?? '', $params['connection'] ?? ($params['database'] ?? null), $params['context'] ?? $params['appname'] ?? null);

            case 'db_query_table':
                return DatabaseAdminService::queryTable(
                    $params['table'] ?? '',
                    (int)($params['page'] ?? 1),
                    (int)($params['page_size'] ?? 25),
                    $params['sort_col'] ?? null,
                    $params['sort_dir'] ?? 'ASC',
                    $params['search'] ?? null,
                    $params['connection'] ?? ($params['database'] ?? null),
                    $params['context'] ?? $params['appname'] ?? null
                );

            case 'db_save_row':
                return DatabaseAdminService::saveRow(
                    $params['table'] ?? '',
                    $params['data'] ?? [],
                    $params['primary_key'] ?? null,
                    $params['primary_key_value'] ?? null,
                    $params['connection'] ?? ($params['database'] ?? null),
                    $params['context'] ?? $params['appname'] ?? null
                );

            case 'db_delete_row':
                return DatabaseAdminService::deleteRow(
                    $params['table'] ?? '',
                    $params['primary_key'] ?? '',
                    $params['primary_key_value'] ?? null,
                    $params['connection'] ?? ($params['database'] ?? null),
                    $params['context'] ?? $params['appname'] ?? null
                );

            case 'db_delete_rows_batch':
                return DatabaseAdminService::deleteRowsBatch(
                    $params['table'] ?? '',
                    $params['primary_key'] ?? 'id',
                    $params['values'] ?? ($params['ids'] ?? []),
                    $params['connection'] ?? ($params['database'] ?? null),
                    $params['context'] ?? $params['appname'] ?? null
                );

            case 'db_execute_sql':
                return DatabaseAdminService::executeSql($params['sql'] ?? '', $params['connection'] ?? ($params['database'] ?? null), $params['context'] ?? $params['appname'] ?? null);

            // phpMyAdmin & cPanel DDL & Management Extensions
            case 'db_check_database':
                return DatabaseAdminService::checkDatabase($params['connection'] ?? ($params['database'] ?? null), $params['context'] ?? $params['appname'] ?? null);

            case 'db_repair_database':
                return DatabaseAdminService::repairDatabase($params['connection'] ?? ($params['database'] ?? null), $params['context'] ?? $params['appname'] ?? null);

            case 'db_create_table':
                return DatabaseAdminService::createTable(
                    $params['table'] ?? '',
                    $params['columns'] ?? [],
                    $params['primary_key'] ?? 'id',
                    $params['connection'] ?? ($params['database'] ?? null),
                    $params['context'] ?? $params['appname'] ?? null
                );

            case 'db_drop_table':
                return DatabaseAdminService::dropTable($params['table'] ?? '', $params['connection'] ?? ($params['database'] ?? null), $params['context'] ?? $params['appname'] ?? null);

            case 'db_truncate_table':
                return DatabaseAdminService::truncateTable($params['table'] ?? '', $params['connection'] ?? ($params['database'] ?? null), $params['context'] ?? $params['appname'] ?? null);

            case 'db_rename_table':
                return DatabaseAdminService::renameTable($params['old_name'] ?? ($params['table'] ?? ''), $params['new_name'] ?? '', $params['connection'] ?? ($params['database'] ?? null), $params['context'] ?? $params['appname'] ?? null);

            case 'db_duplicate_table':
                return DatabaseAdminService::duplicateTable($params['source_table'] ?? ($params['table'] ?? ''), $params['target_table'] ?? ($params['new_table'] ?? ''), !empty($params['with_data']), $params['connection'] ?? ($params['database'] ?? null), $params['context'] ?? $params['appname'] ?? null);

            case 'db_add_column':
                return DatabaseAdminService::addColumn($params['table'] ?? '', $params['column'] ?? $params, $params['connection'] ?? ($params['database'] ?? null), $params['context'] ?? $params['appname'] ?? null);

            case 'db_drop_column':
                return DatabaseAdminService::dropColumn($params['table'] ?? '', $params['column'] ?? ($params['column_name'] ?? ''), $params['connection'] ?? ($params['database'] ?? null), $params['context'] ?? $params['appname'] ?? null);

            case 'db_add_index':
                return DatabaseAdminService::addIndex($params['table'] ?? '', $params['index_name'] ?? ($params['name'] ?? ''), (array)($params['columns'] ?? [$params['column'] ?? '']), !empty($params['unique']) || !empty($params['is_unique']), $params['connection'] ?? ($params['database'] ?? null), $params['context'] ?? $params['appname'] ?? null);

            case 'db_drop_index':
                return DatabaseAdminService::dropIndex($params['table'] ?? '', $params['index_name'] ?? ($params['name'] ?? ''), $params['connection'] ?? ($params['database'] ?? null), $params['context'] ?? $params['appname'] ?? null);

            case 'db_create_database':
                return DatabaseAdminService::createDatabase($params['name'] ?? ($params['database_name'] ?? ''), $params['driver'] ?? 'sqlite', $params['charset'] ?? 'utf8mb4');

            case 'db_delete_database':
                return DatabaseAdminService::deleteDatabase(
                    $params['name'] ?? ($params['database_name'] ?? ''),
                    $params['connection'] ?? ($params['database'] ?? null),
                    $params['driver'] ?? ($params['engine'] ?? null)
                );

            case 'db_export_dump':
                return DatabaseAdminService::exportDump(
                    $params['tables'] ?? [],
                    $params['format'] ?? 'sql',
                    $params['connection'] ?? null,
                    $params['context'] ?? $params['appname'] ?? null
                );

            case 'db_import_dump':
                return DatabaseAdminService::importDump(
                    $params['content'] ?? ($params['dump'] ?? ($params['sql'] ?? '')),
                    $params['connection'] ?? null,
                    $params['context'] ?? $params['appname'] ?? null
                );

            case 'db_search_database':
                return DatabaseAdminService::searchDatabase(
                    $params['term'] ?? ($params['query'] ?? ''),
                    $params['tables'] ?? [],
                    $params['connection'] ?? null,
                    $params['context'] ?? $params['appname'] ?? null
                );

            case 'db_get_configs':
                return DatabaseAdminService::getConfigs();

            case 'db_save_config':
                return DatabaseAdminService::saveConfig(
                    $params['name'] ?? '',
                    $params['config'] ?? []
                );

            case 'db_delete_config':
                return DatabaseAdminService::deleteConfig($params['name'] ?? '');

            case 'db_test_connection':
                return DatabaseAdminService::testConnection($params['config'] ?? []);

            case 'db_schema_graph':
                return DatabaseAdminService::getSchemaGraph($params['connection'] ?? null, $params['appname'] ?? null);

            case 'db_export_erd_svg':
                return ['success' => true, 'svg' => DatabaseAdminService::exportErdSvg($params['connection'] ?? null, $params['appname'] ?? null)];

            case 'db_create_snapshot':
                return DatabaseAdminService::createSnapshot(
                    $params['name'] ?? '',
                    $params['connection'] ?? null,
                    $params['appname'] ?? null,
                    $params['comment'] ?? null
                );

            case 'db_list_snapshots':
                return DatabaseAdminService::listSnapshots($params['connection'] ?? null, $params['appname'] ?? null);

            case 'db_compare_snapshot':
                return DatabaseAdminService::compareSnapshot(
                    $params['name'] ?? '',
                    $params['connection'] ?? null,
                    $params['appname'] ?? null
                );

            case 'db_rollback_snapshot':
                return DatabaseAdminService::rollbackSnapshot(
                    $params['name'] ?? '',
                    !empty($params['execute']),
                    $params['connection'] ?? null,
                    $params['appname'] ?? null
                );

            // Time Machine Point-in-Time Database & Codebase Snapshots
            case 'timemachine_create_snapshot':
            case 'timemachine_create':
                return TimeMachineManager::createSnapshot(
                    $params['label'] ?? ($params['name'] ?? ''),
                    $params['appname'] ?? null,
                    $params['connection'] ?? null
                );

            case 'timemachine_list_snapshots':
            case 'timemachine_list':
                return TimeMachineManager::listSnapshots($params['appname'] ?? null);

            case 'timemachine_diff':
            case 'timemachine_compare':
                return TimeMachineManager::diffSnapshot(
                    $params['snapshot_id'] ?? ($params['id'] ?? ''),
                    $params['appname'] ?? null,
                    $params['connection'] ?? null
                );

            case 'timemachine_rollback':
            case 'timemachine_restore':
                return TimeMachineManager::rollbackSnapshot(
                    $params['snapshot_id'] ?? ($params['id'] ?? ''),
                    !empty($params['execute']),
                    $params['appname'] ?? null,
                    $params['connection'] ?? null
                );

            case 'timemachine_delete':
                return TimeMachineManager::deleteSnapshot($params['snapshot_id'] ?? ($params['id'] ?? ''));

            case 'db_generate_migration':
                return DatabaseAdminService::generateMigration(
                    $params['table'] ?? '',
                    $params['columns'] ?? [],
                    $params['action'] ?? 'create',
                    $params['appname'] ?? null
                );

            case 'db_seed_entity':
                return DatabaseAdminService::seedEntity(
                    $params['entity'] ?? '',
                    (int)($params['count'] ?? 10),
                    !empty($params['state']) ? (string)$params['state'] : (!empty($params['workflow_state']) ? (string)$params['workflow_state'] : null),
                    !empty($params['truncate']),
                    $params['app'] ?? ($params['appname'] ?? $appContext)
                );

            case 'db_list_seeders':
                return DatabaseAdminService::listSeeders($params['app'] ?? ($params['appname'] ?? $appContext));

            case 'db_run_seeder':
                return DatabaseAdminService::runSeeder(
                    $params['seeder'] ?? ($params['class'] ?? ''),
                    $params['app'] ?? ($params['appname'] ?? $appContext)
                );

            // Enterprise Multi-Engine Database & User Provisioner
            case 'db_provision_list':
                return DatabaseProvisionerService::listProvisionedEntities($params['engine'] ?? 'mysql');

            case 'db_provision_create_database':
                return DatabaseProvisionerService::createDatabase(
                    $params['name'] ?? '',
                    $params['charset'] ?? 'utf8mb4',
                    $params['collation'] ?? 'utf8mb4_unicode_ci',
                    $params['engine'] ?? ($params['driver'] ?? 'sqlite')
                );

            case 'db_provision_create_user':
                return DatabaseProvisionerService::createUser(
                    $params['username'] ?? '',
                    $params['password'] ?? '',
                    $params['host'] ?? 'localhost',
                    $params['engine'] ?? 'mysql'
                );

            case 'db_provision_grant':
                return DatabaseProvisionerService::grantPrivileges(
                    $params['database'] ?? '',
                    $params['username'] ?? '',
                    (array)($params['privileges'] ?? ['ALL']),
                    $params['host'] ?? 'localhost',
                    $params['engine'] ?? 'mysql'
                );

            case 'db_provision_revoke':
                return DatabaseProvisionerService::revokePrivileges(
                    $params['database'] ?? '',
                    $params['username'] ?? '',
                    $params['host'] ?? 'localhost',
                    $params['engine'] ?? 'mysql'
                );

            case 'db_provision_delete_db':
                return DatabaseProvisionerService::deleteDatabase(
                    $params['name'] ?? '',
                    $params['engine'] ?? ($params['driver'] ?? 'mysql'),
                    $params['path'] ?? ''
                );

            case 'db_provision_delete_user':
                return DatabaseProvisionerService::deleteUser($params['username'] ?? '', $params['host'] ?? 'localhost', $params['engine'] ?? 'mysql');

            case 'db_provision_change_password':
                return DatabaseProvisionerService::changePassword(
                    $params['username'] ?? '',
                    $params['password'] ?? '',
                    $params['host'] ?? 'localhost',
                    $params['engine'] ?? 'mysql'
                );

            case 'db_provision_remote_hosts_get':
                return DatabaseProvisionerService::listRemoteHosts();

            case 'db_provision_remote_host_add':
                return DatabaseProvisionerService::addRemoteHost($params['ip'] ?? '');

            case 'db_provision_remote_host_remove':
                return DatabaseProvisionerService::removeRemoteHost($params['ip'] ?? '');

            case 'db_provision_optimize_tables':
                return DatabaseProvisionerService::optimizeTables(
                    $params['database'] ?? '',
                    (array)($params['tables'] ?? [])
                );

            // In-Memory Redis Workstation
            case 'redis_status':
                return RedisAdminService::getStatus();

            case 'redis_keys':
                return RedisAdminService::listKeys($params['pattern'] ?? '*', (int)($params['limit'] ?? 100));

            case 'redis_flush_db':
                return RedisAdminService::flushDb();

            case 'redis_flush_all':
                return RedisAdminService::flushAll();

            // -------------------------------------------------------------
            // ENTERPRISE COMMUNICATIONS & MAIL GATEWAY (SPPMail)
            // -------------------------------------------------------------
            case 'mail_queue_list':
                return MailAdminService::getQueue($params);

            case 'mail_send_test':
            case 'mail_deliverability_send_test':
                return MailAdminService::sendTestMail($params);

            case 'mail_queue_retry':
                return MailAdminService::retryQueueItem($params['id'] ?? '');

            case 'mail_queue_delete':
                return MailAdminService::deleteQueueItem($params['id'] ?? '');

            case 'mail_queue_purge':
            case 'mail_queue_flush':
                return MailAdminService::purgeQueue($params['status'] ?? 'sent');

            case 'mail_dkim_generate':
            case 'mail_deliverability_generate_dkim':
                return MailAdminService::generateDkim(
                    $params['domain'] ?? '',
                    $params['selector'] ?? 'default'
                );

            case 'mail_deliverability_audit':
            case 'mail_deliverability_align_dns':
                return MailAdminService::auditDeliverability(
                    $params['domain'] ?? '',
                    $params['selector'] ?? 'default'
                );

            case 'mail_deliverability_align':
            case 'evaarta_deliverability_align':
                return MailAdminService::alignDnsRecords(
                    $params['domain'] ?? '',
                    $params['selector'] ?? 'default'
                );

            case 'mail_stream_counts':
            case 'evaarta_stream_counts':
                return MailAdminService::getStreamCounts($params['account_id'] ?? null);

            case 'mail_settings_get':
                return ['success' => true, 'settings' => MailAdminService::getSettings()];

            case 'mail_settings_save':
                return MailAdminService::saveSettings($params['settings'] ?? $params);

            // -------------------------------------------------------------
            // e-VAARTA OMNICHANNEL COMMUNICATIONS & MAIL SUITE
            // -------------------------------------------------------------
            case 'mail_inbox_list':
            case 'evaarta_inbox_list':
                return MailAdminService::getInbox($params);

            case 'mail_thread_get':
            case 'evaarta_thread_get':
                return MailAdminService::getThread($params['id'] ?? ($params['thread_id'] ?? ''));

            case 'mail_thread_update':
            case 'evaarta_thread_update':
                return MailAdminService::updateThread($params['id'] ?? ($params['thread_id'] ?? ''), $params['updates'] ?? $params);

            case 'mail_thread_mark_read':
            case 'evaarta_thread_mark_read':
                return MailAdminService::markThreadRead(
                    $params['id'] ?? ($params['thread_id'] ?? ''),
                    isset($params['is_read']) ? (bool)$params['is_read'] : true
                );

            case 'mail_thread_delete':
            case 'evaarta_thread_delete':
                return MailAdminService::deleteThread($params['id'] ?? ($params['thread_id'] ?? ''));

            case 'mail_thread_bulk':
            case 'evaarta_thread_bulk':
                return MailAdminService::bulkAction(
                    (array)($params['ids'] ?? ($params['thread_ids'] ?? [])),
                    (string)($params['bulk_action'] ?? ($params['action'] ?? '')),
                    $params
                );

            case 'mail_send':
            case 'evaarta_send':
                return MailAdminService::sendMail($params);

            case 'mail_attachment_download':
            case 'evaarta_attachment_download':
                MailAdminService::downloadAttachment(
                    (string)($params['msg_id'] ?? ($params['message_id'] ?? '')),
                    (string)($params['filename'] ?? ''),
                    !empty($params['inline']) || !empty($params['view'])
                );
                exit;

            case 'mail_tag_add':
            case 'evaarta_tag_add':
                return MailAdminService::addTag($params['thread_id'] ?? '', $params['tag'] ?? '');

            case 'mail_tag_remove':
            case 'evaarta_tag_remove':
                return MailAdminService::removeTag($params['thread_id'] ?? '', $params['tag'] ?? '');

            case 'mail_tags_list':
            case 'evaarta_tags_list':
                return MailAdminService::listTags();

            case 'mail_tag_create':
            case 'evaarta_tag_create':
                return MailAdminService::createTag($params['name'] ?? '', $params['color'] ?? null, $params['icon'] ?? null);

            case 'mail_note_add':
                return MailAdminService::addNote($params['thread_id'] ?? '', $params['content'] ?? $params['note'] ?? '', $params['user_name'] ?? $params['author'] ?? 'Admin');

            case 'mail_action_toggle':
            case 'evaarta_action_toggle':
                return MailAdminService::toggleActionItem($params['thread_id'] ?? '', (int)($params['index'] ?? 0), !empty($params['completed']));

            case 'mail_ai_summary':
            case 'evaarta_ai_summary':
                return MailAdminService::getAiSummary($params['thread_id'] ?? '');

            case 'mail_ai_draft':
            case 'evaarta_ai_draft':
                return MailAdminService::getAiDraft($params['thread_id'] ?? '', $params['tone'] ?? 'friendly');

            case 'mail_workflow_transition':
            case 'evaarta_workflow_transition':
                return MailAdminService::applyTransition(
                    $params['thread_id'] ?? '',
                    $params['transition'] ?? '',
                    $params['user'] ?? 'Admin',
                    $params['comment'] ?? ''
                );

            case 'mail_search':
            case 'evaarta_search':
                return MailAdminService::searchInbox($params['query'] ?? '', (int)($params['limit'] ?? 50));

            case 'mail_imap_sync':
            case 'evaarta_imap_sync':
                return MailAdminService::syncImap($params);

            // e-Vaarta Automation Rules
            case 'rules_list':
            case 'rule_list':
            case 'mail_rules_list':
            case 'mail_rule_list':
            case 'evaarta_rule_list':
            case 'mail_rules':
                return MailAdminService::listRules();

            case 'rule_save':
            case 'mail_rule_save':
            case 'evaarta_rule_save':
                return MailAdminService::saveRule($params);

            case 'rule_delete':
            case 'mail_rule_delete':
            case 'evaarta_rule_delete':
                return MailAdminService::deleteRule($params['id'] ?? '');

            case 'rule_toggle':
            case 'mail_rule_toggle':
            case 'evaarta_rule_toggle':
                return MailAdminService::toggleRule($params['id'] ?? '', !empty($params['is_active']));

            case 'rule_test':
            case 'mail_rule_test':
            case 'evaarta_rule_test':
                return MailAdminService::testRule($params);

            // e-Vaarta Screener & Sender Triage
            case 'screener_list':
            case 'screeners_list':
            case 'mail_screener_list':
            case 'evaarta_screener_list':
            case 'mail_screeners':
                return MailAdminService::listScreeners();

            case 'screener_action':
            case 'screener_set':
            case 'mail_screener_action':
            case 'mail_screener_set':
            case 'mail_screen_sender':
            case 'evaarta_screener_set':
            case 'evaarta_screen_sender':
                return MailAdminService::screenSender($params['email'] ?? ($params['sender_email'] ?? ''), $params['action'] ?? ($params['status'] ?? 'accepted'));

            case 'mail_screen_thread':
            case 'evaarta_screen_thread':
                return MailAdminService::screenThread($params['thread_id'] ?? ($params['id'] ?? ''), $params['action'] ?? ($params['status'] ?? 'accepted'));

            case 'mail_screener_delete':
            case 'evaarta_screener_delete':
                return MailAdminService::deleteScreener($params['email'] ?? ($params['id'] ?? ''));

            // e-Vaarta Snooze
            case 'mail_thread_snooze':
            case 'mail_snooze':
            case 'evaarta_thread_snooze':
            case 'evaarta_snooze':
                return MailAdminService::snoozeThread($params['thread_id'] ?? ($params['id'] ?? ''), $params['until'] ?? date('Y-m-d H:i:s', strtotime('+3 hours')));

            case 'mail_thread_unsnooze':
            case 'mail_unsnooze':
            case 'evaarta_thread_unsnooze':
            case 'evaarta_unsnooze':
                return MailAdminService::unsnoozeThread($params['thread_id'] ?? ($params['id'] ?? ''));

            case 'mail_snooze_process':
            case 'evaarta_snooze_process':
                return MailAdminService::processSnooze();

            // e-Vaarta Multi-Account Orchestration
            case 'mail_account_list':
            case 'evaarta_account_list':
            case 'mail_accounts':
            case 'evaarta_accounts':
                return MailAdminService::listAccounts();

            case 'mail_account_get':
            case 'evaarta_account_get':
                return MailAdminService::getAccount($params['id'] ?? '');

            case 'mail_account_save':
            case 'evaarta_account_save':
                return MailAdminService::saveAccount($params);

            case 'mail_account_delete':
            case 'evaarta_account_delete':
                return MailAdminService::deleteAccount($params['id'] ?? '');

            case 'mail_account_default':
            case 'evaarta_account_default':
                return MailAdminService::setDefaultAccount($params['id'] ?? '');

            case 'mail_account_test':
            case 'evaarta_account_test':
                return MailAdminService::testAccount($params);

            case 'mail_account_sync':
            case 'evaarta_account_sync':
                return MailAdminService::syncAccount(
                    $params['id'] ?? ($params['account_id'] ?? ''),
                    (int)($params['limit'] ?? 50),
                    [
                        'mode' => $params['mode'] ?? 'latest',
                        'before_uid' => !empty($params['before_uid']) ? (int)$params['before_uid'] : null
                    ]
                );

            case 'mail_account_sync_older':
            case 'evaarta_account_sync_older':
            case 'mail_sync_older':
                return MailAdminService::syncOlder(
                    $params['id'] ?? ($params['account_id'] ?? ''),
                    (int)($params['limit'] ?? 50)
                );

            case 'mail_account_sync_all':
            case 'evaarta_account_sync_all':
                return MailAdminService::syncAllAccounts((int)($params['limit'] ?? 25));

            case 'mail_account_autodiscover':
            case 'evaarta_account_autodiscover':
                return MailAdminService::autodiscoverAccount($params['email'] ?? '');

            case 'mail_account_presets':
            case 'evaarta_account_presets':
                return MailAdminService::getPresets();

            // Local Server Mailboxes & Virtual Provisioning (Hosted Domains)
            case 'server_mailboxes_list':
            case 'server_mailbox_list':
            case 'server_mailboxes':
            case 'mail_server_accounts_list':
            case 'mail_virtual_list':
            case 'mail_virtual_mailboxes':
            case 'mail_server_mailboxes':
                return MailAdminService::listVirtualMailboxes($params['domain'] ?? null, $params['search'] ?? null);

            case 'server_mailbox_get':
            case 'mail_server_account_get':
            case 'mail_virtual_get':
                return MailAdminService::getVirtualMailbox($params['email'] ?? '');

            case 'server_mailbox_save':
            case 'server_mailbox_create':
            case 'mail_server_account_create':
            case 'mail_virtual_create':
                return MailAdminService::createVirtualMailbox($params);

            case 'server_mailbox_password':
            case 'mail_server_account_password':
            case 'mail_server_account_passwd':
            case 'mail_virtual_password':
                return MailAdminService::setVirtualMailboxPassword($params['email'] ?? '', $params['password'] ?? ($params['new_password'] ?? ''));

            case 'server_mailbox_quota':
            case 'mail_server_account_quota':
            case 'mail_virtual_quota':
                return MailAdminService::setVirtualMailboxQuota($params['email'] ?? '', (int)($params['quota_mb'] ?? 1024));

            case 'server_mailbox_status':
            case 'mail_server_account_status':
            case 'mail_virtual_status':
                return MailAdminService::setVirtualMailboxStatus($params['email'] ?? '', $params['status'] ?? 'active');

            case 'server_mailbox_device_config':
            case 'mail_device_config':
                return MailAdminService::getDeviceConfig($params['email'] ?? '', $params['format'] ?? 'mobileconfig');

            case 'server_mailbox_delete':
            case 'mail_server_account_delete':
            case 'mail_virtual_delete':
                return MailAdminService::deleteVirtualMailbox($params['email'] ?? '', !empty($params['purge_files']) || !isset($params['purge_files']) || !empty($params['purge']));

            case 'server_domains_list':
            case 'server_domains':
            case 'server_domain_list':
            case 'mail_server_domains':
            case 'mail_virtual_domains':
                return MailAdminService::listServerDomains();

            case 'server_forwarders_list':
            case 'server_forwarder_list':
            case 'server_forwarders':
            case 'mail_server_forwarders_list':
            case 'mail_forwarders_list':
            case 'mail_forwarder_list':
                return MailAdminService::listVirtualForwarders($params['domain'] ?? null);

            case 'server_forwarder_save':
            case 'server_forwarder_create':
            case 'mail_server_forwarder_create':
            case 'mail_forwarder_create':
                return MailAdminService::createVirtualForwarder($params['source_email'] ?? ($params['source'] ?? ''), $params['destination_email'] ?? ($params['destination'] ?? ''));

            case 'server_forwarder_delete':
            case 'mail_server_forwarder_delete':
            case 'mail_forwarder_delete':
                return MailAdminService::deleteVirtualForwarder($params['source_email'] ?? ($params['id'] ?? ''));

            case 'server_autoresponder_set':
            case 'mail_server_autoresponder_set':
            case 'mail_autoresponder_set':
                return MailAdminService::setAutoresponder($params['email'] ?? '', !empty($params['enabled']), $params['subject'] ?? '', $params['body'] ?? '');

            // -------------------------------------------------------------
            // FILE MANAGER & STORAGE QUOTA (SPP Storage Manager)
            // -------------------------------------------------------------
            case 'storage_list_directory':
            case 'storage_list_files':
                return StorageAdminService::listFiles($params['path'] ?? '');

            case 'storage_read_file':
                return StorageAdminService::readFile($params['path'] ?? '');

            case 'coder_save_file':
            case 'storage_write_file':
                return StorageAdminService::writeFile($params['path'] ?? '', $params['content'] ?? '');

            case 'storage_create_file':
                return StorageAdminService::createFile(
                    $params['path'] ?? '',
                    $params['properties'] ?? ($params['props'] ?? [])
                );

            case 'storage_create_directory':
                return StorageAdminService::createDirectory(
                    $params['path'] ?? '',
                    $params['properties'] ?? ($params['props'] ?? [])
                );

            case 'storage_get_file_props':
                return StorageAdminService::getFileProperties($params['path'] ?? '');

            case 'storage_save_file_props':
                return StorageAdminService::saveFileProperties(
                    $params['path'] ?? '',
                    $params['properties'] ?? ($params['props'] ?? $params)
                );

            case 'storage_get_directory_props':
            case 'files_get_directory_props':
                return StorageAdminService::getDirectoryProperties($params['path'] ?? '');

            case 'storage_save_directory_props':
            case 'files_save_directory_props':
                return StorageAdminService::saveDirectoryProperties(
                    $params['path'] ?? '',
                    $params['properties'] ?? ($params['props'] ?? $params)
                );

            case 'storage_get_manifest_raw':
            case 'files_get_manifest_raw':
                return StorageAdminService::getManifestRaw($params['path'] ?? '');

            case 'storage_save_manifest_raw':
            case 'files_save_manifest_raw':
                return StorageAdminService::saveManifestRaw(
                    $params['path'] ?? '',
                    $params['yaml'] ?? ($params['content'] ?? '')
                );

            case 'storage_validate_manifest_yaml':
            case 'files_validate_manifest_yaml':
                return StorageAdminService::validateManifestYaml(
                    $params['yaml'] ?? ($params['content'] ?? '')
                );

            case 'storage_diff_manifest':
            case 'files_diff_manifest':
                return StorageAdminService::diffManifest(
                    $params['path'] ?? '',
                    $params['yaml'] ?? ($params['content'] ?? '')
                );

            case 'storage_manifest_health':
            case 'files_manifest_health':
                return StorageAdminService::getManifestHealth($params['path'] ?? '');

            case 'storage_heal_manifest':
            case 'files_heal_manifest':
            case 'props_heal':
                return StorageAdminService::healManifest($params['path'] ?? '');

            case 'storage_compile_manifests':
            case 'files_compile_manifests':
            case 'props_compile':
                return StorageAdminService::compileManifests($params['app'] ?? null);

            case 'storage_verify_integrity':
            case 'files_verify_integrity':
            case 'props_verify':
                return StorageAdminService::verifyFileIntegrity($params['path'] ?? '');

            case 'storage_sign_file':
            case 'files_sign_file':
                return StorageAdminService::signFile($params['path'] ?? '');

            case 'storage_sign_directory':
            case 'files_sign_directory':
                return StorageAdminService::signDirectory($params['path'] ?? '', !empty($params['recursive']));

            case 'storage_check_capabilities':
            case 'files_check_capabilities':
                return StorageAdminService::checkCapabilities($params['path'] ?? '', $params['capability'] ?? null);

            case 'props_search':
                return [
                    'success' => true,
                    'data' => \SPPMod\SPPStorage\FilePropsManager::search(
                        $params['query'] ?? '',
                        $params['field'] ?? 'all'
                    )
                ];

            case 'props_lint':
                $linter = new \SPPMod\SPPStorage\ArchitecturalBoundaryLinter();
                $violations = $linter->lintRepository(defined('SPP_BASE_DIR') ? SPP_BASE_DIR : getcwd());
                return [
                    'success' => true,
                    'violations' => $violations,
                    'count' => count($violations)
                ];

            case 'storage_delete_item':
                return StorageAdminService::deleteItem($params['path'] ?? '');

            case 'storage_bulk_delete':
                $paths = $params['paths'] ?? [];
                if (is_string($paths)) $paths = json_decode($paths, true) ?: explode(',', $paths);
                return StorageAdminService::bulkDelete($paths);

            case 'storage_chmod':
                return StorageAdminService::chmodItem(
                    $params['path'] ?? '',
                    (string)($params['mode'] ?? '0644'),
                    !empty($params['recursive'])
                );

            case 'storage_bulk_chmod':
                $paths = $params['paths'] ?? [];
                if (is_string($paths)) $paths = json_decode($paths, true) ?: explode(',', $paths);
                return StorageAdminService::bulkChmod(
                    $paths,
                    (string)($params['mode'] ?? '0644'),
                    !empty($params['recursive'])
                );

            case 'storage_rename_item':
                return StorageAdminService::renameItem($params['old_path'] ?? '', $params['new_path'] ?? '');

            case 'storage_move_item':
                return StorageAdminService::moveItem($params['source'] ?? ($params['source_path'] ?? ''), $params['destination'] ?? ($params['dest_dir'] ?? ''));

            case 'storage_bulk_move':
                $paths = $params['paths'] ?? [];
                if (is_string($paths)) $paths = json_decode($paths, true) ?: explode(',', $paths);
                return StorageAdminService::bulkMove($paths, $params['destination'] ?? ($params['dest_dir'] ?? ''));

            case 'storage_copy_item':
                return StorageAdminService::copyItem($params['source'] ?? ($params['source_path'] ?? ''), $params['destination'] ?? ($params['dest_dir'] ?? ''));

            case 'storage_bulk_copy':
                $paths = $params['paths'] ?? [];
                if (is_string($paths)) $paths = json_decode($paths, true) ?: explode(',', $paths);
                return StorageAdminService::bulkCopy($paths, $params['destination'] ?? ($params['dest_dir'] ?? ''));

            case 'storage_compress_items':
                $paths = $params['paths'] ?? [];
                if (is_string($paths)) $paths = json_decode($paths, true) ?: explode(',', $paths);
                return StorageAdminService::compressItems($paths, $params['output_path'] ?? null);

            case 'storage_download_folder':
                StorageAdminService::downloadFolderAsZip($params['path'] ?? '');
                exit;

            case 'storage_download_selected':
                $paths = $params['paths'] ?? [];
                if (is_string($paths)) $paths = json_decode($paths, true) ?: explode(',', $paths);
                StorageAdminService::downloadSelectedAsZip($paths, $params['zip_name'] ?? null);
                exit;

            case 'storage_upload_file':
                $fileData = $params['file'] ?? ($params['uploaded_files']['file'] ?? ($params['uploaded_files']['files'][0] ?? []));
                if (empty($fileData) && !empty($params['content_base64'])) {
                    $fileData = ['base64' => $params['content_base64'], 'name' => $params['filename'] ?? ''];
                } elseif (empty($fileData) && isset($params['content'])) {
                    $fileData = ['content' => $params['content'], 'name' => $params['filename'] ?? ''];
                }
                return StorageAdminService::uploadFile($params['path'] ?? '', $fileData, $params['filename'] ?? null);

            case 'storage_download_file':
                return StorageAdminService::downloadFile($params['path'] ?? '');

            case 'storage_tree':
                return StorageAdminService::getDirectoryTree($params['root'] ?? '', (int)($params['depth'] ?? 3));

            case 'storage_roots':
            case 'files_roots':
                return [
                    'success' => true,
                    'roots' => StorageAdminService::getAvailableRoots(),
                    'available_roots' => StorageAdminService::getAvailableRoots(),
                    'system_root' => StorageAdminService::getSystemRoot(),
                    'workspace_root' => StorageAdminService::getWorkspaceRoot(),
                    'site_roots' => StorageAdminService::getConfiguredSiteRoots()
                ];

            case 'storage_site_roots':
            case 'domains_site_roots':
            case 'domains_site_directories':
                return [
                    'success' => true,
                    'site_roots' => StorageAdminService::getConfiguredSiteRoots()
                ];

            case 'storage_extract_zip':
                return StorageAdminService::extractZip($params['zip_path'] ?? '', $params['dest_dir'] ?? null);

            case 'archive_create':
                return ArchiveAdminService::createArchive(
                    $params['source_path'] ?? '',
                    $params['format'] ?? 'zip',
                    $params['output_path'] ?? null,
                    $params['options'] ?? []
                );

            case 'archive_extract':
                return ArchiveAdminService::extractArchive(
                    $params['archive_path'] ?? '',
                    $params['dest_dir'] ?? null,
                    $params['options'] ?? []
                );

            case 'archive_inspect':
                return ArchiveAdminService::inspectArchive(
                    $params['archive_path'] ?? '',
                    (int)($params['max_entries'] ?? 100)
                );

            case 'archive_verify':
                return ArchiveAdminService::verifyArchive($params['archive_path'] ?? '');

            case 'storage_disk_usage':
                return StorageAdminService::getDiskUsage();

            case 'storage_get_error_pages':
                return StorageAdminService::getErrorPages($params['docroot'] ?? null);

            case 'storage_save_error_page':
                return StorageAdminService::saveErrorPage(
                    (int)($params['code'] ?? 404),
                    $params['content'] ?? '',
                    $params['docroot'] ?? null
                );

            case 'storage_get_indexing':
                return StorageAdminService::getDirectoryIndexing($params['path'] ?? '');

            case 'storage_toggle_indexing':
                $enable = !empty($params['enable']) || (!empty($params['indexed']) && $params['indexed'] !== 'false');
                return StorageAdminService::toggleDirectoryIndexing($params['path'] ?? '', $enable);

            case 'storage_list_mime_types':
                return StorageAdminService::listMimeTypes($params['docroot'] ?? null);

            case 'storage_save_mime_type':
                return StorageAdminService::saveMimeType(
                    $params['extension'] ?? '',
                    $params['mime_type'] ?? '',
                    $params['docroot'] ?? null
                );

            case 'storage_remove_mime_type':
                return StorageAdminService::removeMimeType(
                    $params['extension'] ?? '',
                    $params['docroot'] ?? null
                );

            case 'storage_disk_treemap':
                return StorageAdminService::getDiskUsageTreemap(
                    $params['root'] ?? '',
                    (int)($params['depth'] ?? 3)
                );

            case 'storage_access_log_info':
                return StorageAdminService::getAccessLogInfo();

            case 'storage_access_log_read':
                return StorageAdminService::readAccessLog(
                    $params['log_path'] ?? '',
                    (int)($params['lines'] ?? 100)
                );

            // File Manager v2: FTP Accounts
            case 'storage_ftp_list':
            case 'files_ftp_list':
                return StorageAdminService::listFtpAccounts();

            case 'storage_ftp_create':
            case 'files_ftp_create':
                return StorageAdminService::createFtpAccount(
                    $params['username'] ?? '',
                    $params['password'] ?? '',
                    $params['dir'] ?? '',
                    (int)($params['quota_mb'] ?? 1024)
                );

            case 'storage_ftp_delete':
            case 'files_ftp_delete':
                return StorageAdminService::deleteFtpAccount($params['username'] ?? '');

            // File Manager v2: Trash Bin
            case 'storage_trash_move':
            case 'files_trash_move':
                return StorageAdminService::moveToTrash($params['path'] ?? '', $params['docroot'] ?? '');

            case 'storage_trash_list':
            case 'files_trash_list':
                return StorageAdminService::listTrash($params['docroot'] ?? '');

            case 'storage_trash_restore':
            case 'files_trash_restore':
                return StorageAdminService::restoreFromTrash($params['trash_id'] ?? '', $params['docroot'] ?? '');

            case 'storage_trash_empty':
            case 'files_trash_empty':
                return StorageAdminService::emptyTrash($params['docroot'] ?? '');

            // File Manager v2: Image Processor
            case 'storage_image_process':
            case 'files_image_process':
                return StorageAdminService::processImage($params['path'] ?? '', $params['operation'] ?? 'info', $params);

            // File Manager v2: Git Workstation
            case 'storage_git_status':
            case 'files_git_status':
                return StorageAdminService::gitStatus($params['path'] ?? '');

            case 'storage_git_pull':
            case 'files_git_pull':
                return StorageAdminService::gitPull($params['path'] ?? '', $params['branch'] ?? 'main');

            case 'storage_git_log':
            case 'files_git_log':
                return StorageAdminService::gitLog($params['path'] ?? '', (int)($params['limit'] ?? 10));

            // -------------------------------------------------------------
            // MANIFEST OPERATING SYSTEM & KNOWLEDGE GRAPH
            // -------------------------------------------------------------
            case 'manifest_graph':
                $force = !empty($params['refresh']) || !empty($params['sync']);
                return ManifestAdminService::getGraphData($params['app'] ?? ($params['appname'] ?? null), $force);

            case 'manifest_catalog':
                $force = !empty($params['refresh']) || !empty($params['sync']);
                return ManifestAdminService::getCatalog(
                    $params['category'] ?? null,
                    $params['app'] ?? ($params['appname'] ?? null),
                    $force
                );

            case 'manifest_bundle':
                $force = !empty($params['refresh']) || !empty($params['sync']);
                $appName = $params['app'] ?? ($params['appname'] ?? null);
                return [
                    'success' => true,
                    'data' => [
                        'graph' => ManifestAdminService::getGraphData($appName, $force),
                        'catalog' => ManifestAdminService::getCatalog('partials', $appName, $force)
                    ]
                ];

            case 'manifest_test_partial':
                return ManifestAdminService::renderPartialPreview(
                    $params['partial'] ?? '',
                    is_array($params['mock_props'] ?? null) ? $params['mock_props'] : (json_decode($params['mock_props'] ?? '{}', true) ?: []),
                    $params['app'] ?? ($params['appname'] ?? null),
                    isset($params['custom_source']) && $params['custom_source'] !== '' ? (string)$params['custom_source'] : null
                );

            case 'manifest_generate_mock':
                if (!class_exists('\SPPMod\SPPStorage\SyntheticMockGenerator')) {
                    $mockGenFile = dirname(__DIR__, 2) . '/modules/spp/sppstorage/src/SyntheticMockGenerator.php';
                    if (file_exists($mockGenFile)) {
                        require_once $mockGenFile;
                    }
                }
                $mock = \SPPMod\SPPStorage\SyntheticMockGenerator::generatePropsForPartial(
                    $params['partial'] ?? ''
                );
                return [
                    'success' => true,
                    'mock_props' => $mock
                ];

            case 'manifest_playground_template':
                return [
                    'success' => true,
                    'html' => ManifestAdminService::renderPlaygroundPage($params)
                ];

            case 'manifest_get_partial_source':
                return ManifestAdminService::getPartialSource(
                    $params['partial'] ?? ($params['file'] ?? '')
                );

            case 'manifest_save_partial_source':
                return ManifestAdminService::savePartialSource(
                    $params['partial'] ?? ($params['file'] ?? ''),
                    $params['content'] ?? '',
                    $params['app'] ?? ($params['appname'] ?? null)
                );

            case 'manifest_integrate_directive':
                return ManifestAdminService::integrateDirectiveIntoFile(
                    $params['target'] ?? '',
                    $params['partial'] ?? '',
                    $params['placement'] ?? 'append'
                );

            case 'manifest_impact':
                return ManifestAdminService::getBlastRadius(
                    $params['path'] ?? ($params['file'] ?? ''),
                    (int)($params['depth'] ?? 5)
                );

            case 'manifest_ai_directives':
                return ManifestAdminService::getAiDirectives(
                    $params['path'] ?? ($params['file'] ?? '')
                );

            case 'manifest_cache_graph':
                return ManifestAdminService::getCacheGraph(
                    $params['dir'] ?? null
                );

            case 'manifest_data_governance':
                return ManifestAdminService::getDataGovernance(
                    $params['path'] ?? ($params['file'] ?? '')
                );

            // -------------------------------------------------------------
            // CRON & QUEUE ORCHESTRATION (SPP Task Scheduler)
            // -------------------------------------------------------------
            case 'cron_list_jobs':
                return CronQueueAdminService::listCronJobs();

            case 'cron_save_job':
                return CronQueueAdminService::saveCronJob($params['id'] ?? '', $params);

            case 'cron_delete_job':
                return CronQueueAdminService::deleteCronJob($params['id'] ?? '');

            case 'cron_run_now':
                return CronQueueAdminService::runCronNow($params['command'] ?? '');

            case 'queue_list_jobs':
                return CronQueueAdminService::listQueueJobs($params['status'] ?? null);

            case 'queue_retry_job':
                return CronQueueAdminService::retryQueueJob($params['job_id'] ?? '');

            case 'queue_clear':
                return CronQueueAdminService::clearQueue($params['status'] ?? null);

            case 'queue_dag_status':
                return CronQueueAdminService::getDagWorkflowStatus();

            // Phase 8: Dead-Letter Queue (DLQ) & Custom DAG Pipelines
            case 'queue_dlq_list':
                return CronQueueAdminService::getDeadLetterQueue();

            case 'queue_dlq_replay':
                return CronQueueAdminService::replayDeadLetterJob($params['job_id'] ?? $params['id'] ?? '');

            case 'queue_dlq_purge':
                return CronQueueAdminService::purgeDeadLetterQueue();

            case 'dag_pipeline_save':
                return CronQueueAdminService::saveDagPipeline($params['id'] ?? 'pipeline_' . time(), (array)($params['stages'] ?? []));

            // -------------------------------------------------------------
            // DEPLOYMENTS & SNAPSHOT BACKUPS (SPP Deployment Orchestrator)
            // -------------------------------------------------------------
            case 'deploy_git_status':
                return DeployAdminService::getGitStatus();

            case 'deploy_push':
                return DeployAdminService::deployPush($params['target'] ?? 'production', $params['token'] ?? 'default', $params['options'] ?? []);

            case 'deploy_rollback':
                return DeployAdminService::deployRollback($params['target'] ?? 'production', $params['version'] ?? 'previous');

            case 'backup_create':
                return DeployAdminService::createBackup($params['type'] ?? 'full');

            case 'backup_list':
                return DeployAdminService::listBackups();

            case 'backup_restore':
                return DeployAdminService::restoreBackup($params['filename'] ?? '');

            case 'backup_delete':
                return DeployAdminService::deleteBackup($params['filename'] ?? '');

            case 'backup_incremental_create':
                return DeployAdminService::createIncrementalBackup($params['root'] ?? '');

            case 'backup_destinations_get':
                return DeployAdminService::getDestinations();

            case 'backup_destination_save':
                return DeployAdminService::saveDestination($params);

            case 'backup_destination_delete':
                return DeployAdminService::deleteDestination($params['name'] ?? '');

            case 'backup_destination_test':
                return DeployAdminService::testDestination($params['name'] ?? '');

            case 'backup_destination_upload':
                return DeployAdminService::uploadToDestination(
                    $params['filename'] ?? '',
                    $params['destination'] ?? ''
                );

            case 'backup_schedules_get':
                return DeployAdminService::getSchedules();

            case 'backup_schedule_save':
                return DeployAdminService::saveSchedule($params);

            case 'backup_schedule_delete':
                return DeployAdminService::deleteSchedule($params['id'] ?? '');

            case 'backup_schedules_run_due':
                return DeployAdminService::runDueSchedules();

            case 'backup_retention_enforce':
                return DeployAdminService::enforceRetention($params);

            // SPPDeploy Remote Engine & Command Mode
            case 'deploy_spp_status':
                return DeployAdminService::getDeployStatus();

            case 'deploy_spp_health':
                return DeployAdminService::getTargetHealth($params['target'] ?? 'production');

            case 'deploy_spp_plan':
                return DeployAdminService::getDeployPlan($params['target'] ?? 'production', !empty($params['no_db']), $params['options'] ?? $params);

            case 'deploy_spp_push':
                return DeployAdminService::deployPush($params['target'] ?? 'production', $params['token'] ?? 'default', $params['options'] ?? $params);

            case 'deploy_spp_rollback':
                return DeployAdminService::deployRollback($params['target'] ?? 'production', $params['version'] ?? 'previous');

            case 'deploy_spp_history':
                return DeployAdminService::getRemoteHistory($params['target'] ?? 'production');

            case 'deploy_spp_backups':
                return DeployAdminService::getRemoteBackups($params['target'] ?? 'production');

            case 'deploy_spp_cleanup':
                return DeployAdminService::cleanupRemoteBackups($params['target'] ?? 'production', (int)($params['keep'] ?? 5));

            case 'deploy_spp_maintenance':
                return DeployAdminService::toggleMaintenance($params['target'] ?? 'production', !empty($params['enable']));

            case 'deploy_spp_logs':
                return DeployAdminService::getRemoteLogs($params['target'] ?? 'production', (int)($params['offset'] ?? -1), (int)($params['lines'] ?? 100));

            case 'deploy_spp_run':
                return DeployAdminService::runRemoteCommand($params['target'] ?? 'production', $params['command'] ?? '');

            case 'deploy_build_package':
                return DeployAdminService::buildPackage($params);

            case 'deploy_list_packages':
                return DeployAdminService::listPackages();

            case 'deploy_upload_package':
                $uploadedFiles = $params['uploaded_files'] ?? ($_FILES ?? []);
                $file = $uploadedFiles['package_file'] ?? ($uploadedFiles['file'] ?? reset($uploadedFiles));
                return DeployAdminService::uploadPackage(is_array($file) ? $file : [], $params);

            case 'deploy_sync_helpers':
                return DeployAdminService::syncRemoteHelpers($params['target'] ?? 'production');

            case 'deploy_download_package':
                $file = basename($params['file'] ?? '');
                $rootDir = defined('SPP_APP_DIR') ? SPP_APP_DIR : (defined('SPP_BASE_DIR') ? dirname(SPP_BASE_DIR) : getcwd());
                $candidates = [
                    $rootDir . '/var/bundles/' . $file,
                    $rootDir . '/dist/packages/' . $file,
                    $rootDir . '/var/packages/' . $file,
                ];
                foreach ($candidates as $cand) {
                    if (file_exists($cand) && is_file($cand)) {
                        header('Content-Description: File Transfer');
                        header('Content-Type: application/octet-stream');
                        header('Content-Disposition: attachment; filename="' . $file . '"');
                        header('Content-Length: ' . filesize($cand));
                        header('Pragma: public');
                        readfile($cand);
                        exit;
                    }
                }
                return ['success' => false, 'error' => "Package '{$file}' not found."];

            case 'deploy_spp_clusters':
                return DeployAdminService::getClustersStatus();

            case 'deploy_spp_save_config':
                return DeployAdminService::saveDeployConfig($params['yaml'] ?? '');

            case 'deploy_spp_rotate_token':
                return DeployAdminService::rotateDeployToken($params['target'] ?? 'production');

            case 'deploy_spp_test_webhook':
                return DeployAdminService::testWebhook($params['url'] ?? '');

            case 'deploy_spp_env_keys':
                return DeployAdminService::getEnvComparison($params['target'] ?? 'production');

            case 'deploy_spp_env_push':
                return DeployAdminService::pushRemoteEnv($params['target'] ?? 'production', $params['key'] ?? '', $params['value'] ?? '');

            case 'deploy_spp_target_get':
                return DeployAdminService::getTarget($params['name'] ?? $params['target'] ?? 'production');

            case 'deploy_spp_target_save':
                return DeployAdminService::saveTarget($params['name'] ?? $params['target'] ?? '', $params);

            case 'deploy_spp_target_delete':
                return DeployAdminService::deleteTarget($params['name'] ?? $params['target'] ?? '');

            // SPPDeploy Lifecycle Console Actions
            case 'lifecycle_get_envs':
                $deployStatus = DeployAdminService::getDeployStatus();
                return [
                    'success' => true,
                    'environments' => $deployStatus['environments'] ?? []
                ];

            case 'lifecycle_get_security':
                $token = class_exists(\SPPMod\SPPDeploy\SPPDeploy::class)
                    ? \SPPMod\SPPDeploy\SPPDeploy::configuredToken()
                    : (function_exists('getDeploymentToken') ? getDeploymentToken() : '');
                return [
                    'success' => true,
                    'token' => $token
                ];

            case 'lifecycle_rotate_token':
                return DeployAdminService::rotateDeployToken($params['target'] ?? 'production');

            case 'lifecycle_save_env':
            case 'lifecycle_save_target':
                if (isset($params['yaml'])) {
                    require_once __DIR__ . '/Lifecycle.php';
                    $la = new \SPPMod\SPPAPI\LiveAction();
                    live_lifecycle_save_target($la, $params);
                    return array_merge(['success' => $la->getStatus() === 'ok'], $la->getData());
                }
                return DeployAdminService::saveTarget($params['name'] ?? $params['target'] ?? '', $params);

            case 'lifecycle_deploy_history':
                return DeployAdminService::getRemoteHistory($params['target'] ?? 'production');

            case 'lifecycle_list_backups':
                return DeployAdminService::getRemoteBackups($params['target'] ?? 'production');

            case 'lifecycle_restore_backup':
                return DeployAdminService::deployRollback($params['target'] ?? 'production', $params['filename'] ?? $params['version'] ?? 'previous');

            case 'lifecycle_remote_logs':
                return DeployAdminService::getRemoteLogs($params['target'] ?? 'production', (int)($params['offset'] ?? -1), (int)($params['lines'] ?? 100));

            case 'lifecycle_remote_run':
                return DeployAdminService::runRemoteCommand($params['target'] ?? 'production', $params['command'] ?? '');

            case 'lifecycle_cluster_status':
                return DeployAdminService::getClustersStatus();

            case 'lifecycle_get_webhooks':
                $deployStatus = DeployAdminService::getDeployStatus();
                return [
                    'success' => true,
                    'webhooks' => $deployStatus['webhooks'] ?? []
                ];

            case 'lifecycle_save_webhooks':
                require_once __DIR__ . '/Lifecycle.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_lifecycle_save_webhooks($la, $params);
                return array_merge(['success' => $la->getStatus() === 'ok'], $la->getData());

            case 'lifecycle_test_webhook':
                return DeployAdminService::testWebhook($params['url'] ?? '');

            case 'lifecycle_health_check':
                return DeployAdminService::getTargetHealth($params['target'] ?? 'production');

            case 'lifecycle_compare':
                return DeployAdminService::getDeployPlan($params['target'] ?? 'production', !empty($params['no_db']));

            case 'lifecycle_push':
                return DeployAdminService::deployPush($params['target'] ?? 'production', $params['token'] ?? 'default', $params['options'] ?? $params);

            case 'lifecycle_backup':
                require_once __DIR__ . '/Lifecycle.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_lifecycle_backup($la, $params);
                return array_merge(['success' => $la->getStatus() === 'ok'], $la->getData());

            case 'lifecycle_maintenance_toggle':
                return DeployAdminService::toggleMaintenance($params['target'] ?? 'production', !empty($params['enable']));

            // -------------------------------------------------------------
            // DEVELOPER BENCH: PARIKSHAK, TINKER, MESH & TRACE
            // -------------------------------------------------------------
            case 'parikshak_run':
                return DeveloperAdminService::runParikshak(
                    $params['entity'] ?? null,
                    $params['app'] ?? $appContext,
                    !empty($params['coverage'])
                );

            case 'tinker_eval':
                return DeveloperAdminService::evaluateTinker(
                    $params['code'] ?? '',
                    $params['app'] ?? $appContext
                );

            case 'mesh_list_services':
                return DeveloperAdminService::listIntegrationServices($params['app'] ?? null);

            case 'mesh_call_rpc':
                return DeveloperAdminService::callIntegrationRpc(
                    $params['service'] ?? '',
                    $params['method'] ?? '',
                    $params['params'] ?? []
                );

            case 'trace_waterfall':
                return DeveloperAdminService::getTraceWaterfall((int)($params['limit'] ?? 50));

            case 'ai_get_recipes':
                return DeveloperAdminService::getAiRecipes();

            case 'ai_lint_app':
                return DeveloperAdminService::lintAppCode($params['app'] ?? ($params['appname'] ?? $appContext));

            case 'mesh_events_list':
                return DeveloperAdminService::listEventBus($params['app'] ?? ($params['appname'] ?? $appContext));

            case 'mesh_event_dispatch':
                return DeveloperAdminService::dispatchEvent(
                    $params['event'] ?? '',
                    is_array($params['payload'] ?? null) ? $params['payload'] : (json_decode($params['payload'] ?? '{}', true) ?: [])
                );

            case 'list_middleware':
                return DeveloperAdminService::listMiddleware($params['app'] ?? ($params['appname'] ?? $appContext));

            case 'routing_middleware_stack':
                return DeveloperAdminService::getRouteMiddlewarePipeline(
                    $params['route'] ?? ($params['path'] ?? '/'),
                    $params['method'] ?? 'GET'
                );

            case 'render_partial_preview':
                return DeveloperAdminService::renderPartialPreview(
                    $params['partial'] ?? '',
                    is_array($params['data'] ?? null) ? $params['data'] : (json_decode($params['data'] ?? '{}', true) ?: []),
                    $params['app'] ?? ($params['appname'] ?? $appContext)
                );

            // -------------------------------------------------------------
            // SPPCODER: NATIVE DEVELOPER STUDIO SERVICES
            // -------------------------------------------------------------
            case 'coder_lint':
                return \SPPMod\SPPCoder\SPPCoder::lint(
                    $params['filename'] ?? ($params['file'] ?? 'file.php'),
                    $params['content'] ?? '',
                    isset($params['cursor_line']) ? (int)$params['cursor_line'] : null
                );

            case 'coder_symbols':
                return \SPPMod\SPPCoder\SPPCoder::getSymbols(
                    $params['filename'] ?? ($params['file'] ?? 'file.php'),
                    $params['content'] ?? ''
                );

            case 'coder_format':
                return \SPPMod\SPPCoder\SPPCoder::format(
                    $params['filename'] ?? ($params['file'] ?? 'file.php'),
                    $params['content'] ?? '',
                    $params['language'] ?? 'php',
                    (int)($params['indent_size'] ?? 4)
                );

            case 'coder_quick_search':
                return \SPPMod\SPPCoder\SPPCoder::quickSearch(
                    $params['query'] ?? '',
                    (int)($params['limit'] ?? 20)
                );

            case 'coder_search_in_files':
                return \SPPMod\SPPCoder\SPPCoder::searchInFiles(
                    $params['query'] ?? '',
                    $params
                );

            case 'coder_replace_in_files':
                return \SPPMod\SPPCoder\SPPCoder::replaceInFiles(
                    $params['query'] ?? '',
                    $params['replacement'] ?? '',
                    $params['files'] ?? [],
                    $params
                );

            case 'coder_test_discover':
                return \SPPMod\SPPCoder\SPPCoder::discoverParikshakTests(
                    $params['app'] ?? $appContext
                );

            case 'coder_diagnostics':
                return \SPPMod\SPPCoder\SPPCoder::getProjectDiagnostics(
                    $params['app'] ?? $appContext
                );

            case 'coder_watch_eval':
                return \SPPMod\SPPCoder\SPPCoder::evaluateWatchExpressions(
                    $params['expressions'] ?? [],
                    $params['app'] ?? $appContext
                );

            case 'coder_git_diff':
                return \SPPMod\SPPCoder\SPPCoder::getGitDiffMarkers(
                    $params['path'] ?? ($params['file'] ?? '')
                );

            case 'coder_tinker':
                return \SPPMod\SPPCoder\SPPCoder::evaluateInTinker(
                    $params['code'] ?? '',
                    $params['app'] ?? $appContext
                );

            case 'coder_run_test':
                return \SPPMod\SPPCoder\SPPCoder::runParikshakTest(
                    $params['file'] ?? ($params['test_file'] ?? ''),
                    $params['app'] ?? $appContext,
                    $params['filter'] ?? ($params['method'] ?? null)
                );

            case 'coder_autocomplete':
                return \SPPMod\SPPCoder\SPPCoder::autocomplete(
                    $params['filename'] ?? ($params['file'] ?? ''),
                    $params['prefix'] ?? '',
                    $params['context_line'] ?? ($params['line_content'] ?? ''),
                    (int)($params['char_pos'] ?? ($params['pos'] ?? 0)),
                    $params['content'] ?? ''
                );

            case 'coder_signature_help':
                return \SPPMod\SPPCoder\SPPCoder::signatureHelp(
                    $params['filename'] ?? ($params['file'] ?? ''),
                    $params['content'] ?? '',
                    (int)($params['pos'] ?? 0),
                    $params['symbol'] ?? null,
                    (int)($params['param_index'] ?? ($params['activeParameter'] ?? 0))
                );

            case 'coder_definition':
                return \SPPMod\SPPCoder\SPPCoder::resolveDefinition(
                    $params['filename'] ?? ($params['file'] ?? ''),
                    $params['symbol'] ?? '',
                    $params['content'] ?? ''
                );

            case 'coder_hover':
                return \SPPMod\SPPCoder\SPPCoder::getHoverDoc(
                    $params['filename'] ?? ($params['file'] ?? ''),
                    $params['symbol'] ?? '',
                    $params['content'] ?? ''
                );

            case 'coder_references':
                return \SPPMod\SPPCoder\SPPCoder::findReferences(
                    $params['symbol'] ?? '',
                    (int)($params['limit'] ?? 50)
                );

            case 'coder_ai_ghost':
                return \SPPMod\SPPCoder\SPPCoder::aiGhostComplete(
                    $params['prefix'] ?? '',
                    $params['suffix'] ?? '',
                    $params['language'] ?? 'php'
                );

            case 'coder_ai_edit':
                return \SPPMod\SPPCoder\SPPCoder::aiPromptEdit(
                    $params['instruction'] ?? '',
                    $params['selected_code'] ?? ($params['code'] ?? ''),
                    $params['file_context'] ?? ''
                );

            case 'coder_ai_fix':
                $errorMsg = $params['error_message'] ?? ($params['error'] ?? '');
                $line = (int)($params['line'] ?? 1);
                $code = $params['code_snippet'] ?? ($params['code'] ?? ($params['content'] ?? ''));

                if (empty($errorMsg) && !empty($params['errors']) && is_array($params['errors'])) {
                    $firstErr = $params['errors'][0] ?? [];
                    $errorMsg = $firstErr['message'] ?? 'Syntax error';
                    $line = (int)($firstErr['line'] ?? 1);
                }

                $res = \SPPMod\SPPCoder\SPPCoder::aiDiagnoseAndFix($errorMsg, $line, $code);
                if (isset($res['fixed']) && !isset($res['fixed_content'])) {
                    $res['fixed_content'] = $res['fixed'];
                }
                return $res;

            case 'coder_ai_chat':
                return \SPPMod\SPPCoder\SPPCoder::aiChat(
                    $params['messages'] ?? [],
                    $params['workspace_context'] ?? []
                );

            case 'coder_git_status':
                return \SPPMod\SPPCoder\SPPCoder::gitStatus();

            case 'coder_git_stage':
                return \SPPMod\SPPCoder\SPPCoder::gitStage($params['file'] ?? '');

            case 'coder_git_unstage':
                return \SPPMod\SPPCoder\SPPCoder::gitUnstage($params['file'] ?? '');

            case 'coder_git_commit':
                return \SPPMod\SPPCoder\SPPCoder::gitCommit($params['message'] ?? '');

            case 'coder_git_file_diff':
                return \SPPMod\SPPCoder\SPPCoder::gitFileDiff(
                    $params['file'] ?? '',
                    !empty($params['staged'])
                );

            case 'coder_git_blame':
                return \SPPMod\SPPCoder\SPPCoder::gitLineBlame(
                    $params['file'] ?? '',
                    (int)($params['line'] ?? 1)
                );

            case 'coder_terminal_exec':
                return \SPPMod\SPPCoder\SPPCoder::terminalExec(
                    $params['command'] ?? ($params['cmd'] ?? ''),
                    $params['cwd'] ?? null
                );

            case 'coder_terminal_env':
                return \SPPMod\SPPCoder\SPPCoder::terminalEnv();

            case 'get_workflow_graph':
            case 'coder_workflow_graph':
                $file = $params['file'] ?? ($params['name'] ?? 'workflow.yml');
                $content = $params['content'] ?? ($params['yaml'] ?? '');
                if (empty($content) && !empty($file)) {
                    $appname = $params['appname'] ?? $params['context'] ?? 'default';
                    $candidateDirs = array_filter(array_unique([
                        (defined('APP_ETC_DIR') ? APP_ETC_DIR : (defined('SPP_APP_DIR') ? SPP_APP_DIR . '/etc/apps' : '')) . '/' . $appname . '/workflows',
                        (defined('SPP_APP_DIR') ? SPP_APP_DIR . '/etc/apps/' . $appname . '/workflows' : ''),
                        (defined('SPP_APP_DIR') ? SPP_APP_DIR . '/src/' . $appname . '/etc/workflows' : ''),
                        (defined('SPP_APP_DIR') ? SPP_APP_DIR . '/src/' . $appname . '/workflows' : ''),
                        (defined('SPP_BASE_DIR') ? SPP_BASE_DIR . '/etc/apps/' . $appname . '/workflows' : ''),
                    ]), fn($d) => !empty($d) && is_dir($d));

                    foreach ($candidateDirs as $dir) {
                        foreach (['.yml', '.yaml'] as $ext) {
                            $p = $dir . '/' . strtolower($file) . $ext;
                            if (file_exists($p)) {
                                $content = (string)@file_get_contents($p);
                                break 2;
                            }
                        }
                    }
                }
                return \SPPMod\SPPCoder\SPPCoder::workflowGraph($file, $content);

            case 'list_workflows':
                require_once __DIR__ . '/Workflows.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_Workflows_List($la, $params);
                return ['success' => true, 'workflows' => $la->getData()['workflows'] ?? []];

            case 'save_workflow':
                require_once __DIR__ . '/Workflows.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_Workflows_Save($la, $params);
                $isOk = $la->getStatus() === 'ok';
                $instructions = $la->getInstructions();
                $msg = !empty($instructions) ? ($instructions[0]['message'] ?? '') : '';
                return ['success' => $isOk, 'message' => $msg];

            case 'delete_workflow':
                require_once __DIR__ . '/Workflows.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_Workflows_Delete($la, $params);
                $isOk = $la->getStatus() === 'ok';
                $instructions = $la->getInstructions();
                $msg = !empty($instructions) ? ($instructions[0]['message'] ?? '') : '';
                return ['success' => $isOk, 'message' => $msg];

            case 'list_pages_studio':
            case 'list_pages':
                require_once __DIR__ . '/PageAdminService.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_list_pages_studio($la, $params);
                return array_merge(['success' => $la->getStatus() === 'ok'], $la->getData());

            case 'get_page_studio':
            case 'get_page':
                require_once __DIR__ . '/PageAdminService.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_get_page_studio($la, $params);
                return array_merge(['success' => $la->getStatus() === 'ok'], $la->getData());

            case 'save_page_studio':
            case 'save_page':
                require_once __DIR__ . '/PageAdminService.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_save_page_studio($la, $params);
                $isOk = $la->getStatus() === 'ok';
                $instructions = $la->getInstructions();
                $msg = !empty($instructions) ? ($instructions[0]['message'] ?? '') : '';
                return ['success' => $isOk, 'message' => $msg];

            case 'delete_page_studio':
            case 'delete_page':
                require_once __DIR__ . '/PageAdminService.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_delete_page_studio($la, $params);
                $isOk = $la->getStatus() === 'ok';
                $instructions = $la->getInstructions();
                $msg = !empty($instructions) ? ($instructions[0]['message'] ?? '') : '';
                return ['success' => $isOk, 'message' => $msg];

            case 'studio_context':
                require_once __DIR__ . '/PageAdminService.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_studio_context($la, $params);
                return array_merge(['success' => $la->getStatus() === 'ok'], $la->getData());

            case 'get_entity_schema_studio':
            case 'get_entity_schema':
                require_once __DIR__ . '/PageAdminService.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_get_entity_schema_studio($la, $params);
                return array_merge(['success' => $la->getStatus() === 'ok'], $la->getData());

            case 'compile_page_studio':
            case 'compile_page':
                require_once __DIR__ . '/PageAdminService.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_compile_page_studio($la, $params);
                $isOk = $la->getStatus() === 'ok';
                $instructions = $la->getInstructions();
                $msg = !empty($instructions) ? ($instructions[0]['message'] ?? '') : '';
                return array_merge(['success' => $isOk, 'message' => $msg], $la->getData());

            case 'get_page_source':
            case 'get_page_source_studio':
                require_once __DIR__ . '/PageAdminService.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_get_page_source($la, $params);
                return array_merge(['success' => $la->getStatus() === 'ok'], $la->getData());

            case 'save_page_source':
            case 'save_page_source_studio':
                require_once __DIR__ . '/PageAdminService.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_save_page_source($la, $params);
                $isOk = $la->getStatus() === 'ok';
                $instructions = $la->getInstructions();
                $msg = !empty($instructions) ? ($instructions[0]['message'] ?? '') : '';
                return array_merge(['success' => $isOk, 'message' => $msg], $la->getData());

            case 'introspect_transitions':
            case 'introspect_transitions_studio':
                require_once __DIR__ . '/PageAdminService.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_introspect_transitions($la, $params);
                return array_merge(['success' => $la->getStatus() === 'ok'], $la->getData());

            case 'get_app_blueprint':
            case 'get_app_blueprint_studio':
                require_once __DIR__ . '/PageAdminService.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_get_app_blueprint_studio($la, $params);
                return array_merge(['success' => $la->getStatus() === 'ok'], $la->getData());

            case 'get_app_config_studio':
                require_once __DIR__ . '/PageAdminService.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_get_app_config_studio($la, $params);
                return array_merge(['success' => $la->getStatus() === 'ok'], $la->getData());

            case 'save_app_config_studio':
                require_once __DIR__ . '/PageAdminService.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_save_app_config_studio($la, $params);
                $isOk = $la->getStatus() === 'ok';
                $instructions = $la->getInstructions();
                $msg = !empty($instructions) ? ($instructions[0]['message'] ?? '') : '';
                return array_merge(['success' => $isOk, 'message' => $msg], $la->getData());

            case 'get_magic_db_data_studio':
                require_once __DIR__ . '/PageAdminService.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_get_magic_db_data_studio($la, $params);
                return array_merge(['success' => $la->getStatus() === 'ok'], $la->getData());

            case 'save_magic_db_row_studio':
                require_once __DIR__ . '/PageAdminService.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_save_magic_db_row_studio($la, $params);
                $isOk = $la->getStatus() === 'ok';
                $instructions = $la->getInstructions();
                $msg = !empty($instructions) ? ($instructions[0]['message'] ?? '') : '';
                return array_merge(['success' => $isOk, 'message' => $msg], $la->getData());

            case 'generate_mock_data_studio':
                require_once __DIR__ . '/PageAdminService.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_generate_mock_data_studio($la, $params);
                $isOk = $la->getStatus() === 'ok';
                $instructions = $la->getInstructions();
                $msg = !empty($instructions) ? ($instructions[0]['message'] ?? '') : '';
                return array_merge(['success' => $isOk, 'message' => $msg], $la->getData());

            case 'generate_crud_interface_studio':
                require_once __DIR__ . '/PageAdminService.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_generate_crud_interface_studio($la, $params);
                $isOk = $la->getStatus() === 'ok';
                $instructions = $la->getInstructions();
                $msg = !empty($instructions) ? ($instructions[0]['message'] ?? '') : '';
                return array_merge(['success' => $isOk, 'message' => $msg], $la->getData());

            case 'test_api_endpoint_studio':
                require_once __DIR__ . '/PageAdminService.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_test_api_endpoint_studio($la, $params);
                return array_merge(['success' => $la->getStatus() === 'ok'], $la->getData());

            case 'run_parikshak_test_studio':
                require_once __DIR__ . '/PageAdminService.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_run_parikshak_test_studio($la, $params);
                $isOk = $la->getStatus() === 'ok';
                $instructions = $la->getInstructions();
                $msg = !empty($instructions) ? ($instructions[0]['message'] ?? '') : '';
                return array_merge(['success' => $isOk, 'message' => $msg], $la->getData());

            case 'ai_prompt_component_studio':
                require_once __DIR__ . '/PageAdminService.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_ai_prompt_component_studio($la, $params);
                $isOk = $la->getStatus() === 'ok';
                $instructions = $la->getInstructions();
                $msg = !empty($instructions) ? ($instructions[0]['message'] ?? '') : '';
                return array_merge(['success' => $isOk, 'message' => $msg], $la->getData());

            case 'execute_sql_query_studio':
                require_once __DIR__ . '/PageAdminService.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_execute_sql_query_studio($la, $params);
                return array_merge(['success' => $la->getStatus() === 'ok'], $la->getData());

            case 'alter_table_schema_studio':
                require_once __DIR__ . '/PageAdminService.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_alter_table_schema_studio($la, $params);
                $isOk = $la->getStatus() === 'ok';
                $instructions = $la->getInstructions();
                $msg = !empty($instructions) ? ($instructions[0]['message'] ?? '') : '';
                return array_merge(['success' => $isOk, 'message' => $msg], $la->getData());

            case 'get_database_erd_studio':
                require_once __DIR__ . '/PageAdminService.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_get_database_erd_studio($la, $params);
                return array_merge(['success' => $la->getStatus() === 'ok'], $la->getData());

            case 'get_relational_record_studio':
                require_once __DIR__ . '/PageAdminService.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_get_relational_record_studio($la, $params);
                return array_merge(['success' => $la->getStatus() === 'ok'], $la->getData());

            case 'import_csv_data_studio':
                require_once __DIR__ . '/PageAdminService.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_import_csv_data_studio($la, $params);
                $isOk = $la->getStatus() === 'ok';
                $instructions = $la->getInstructions();
                $msg = !empty($instructions) ? ($instructions[0]['message'] ?? '') : '';
                return array_merge(['success' => $isOk, 'message' => $msg], $la->getData());

            case 'execute_cli_command_studio':
                require_once __DIR__ . '/PageAdminService.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_execute_cli_command_studio($la, $params);
                return array_merge(['success' => $la->getStatus() === 'ok'], $la->getData());

            case 'stream_app_logs_studio':
                require_once __DIR__ . '/PageAdminService.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_stream_app_logs_studio($la, $params);
                return array_merge(['success' => $la->getStatus() === 'ok'], $la->getData());

            case 'get_git_status_studio':
                require_once __DIR__ . '/PageAdminService.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_get_git_status_studio($la, $params);
                return array_merge(['success' => $la->getStatus() === 'ok'], $la->getData());

            case 'global_search_studio':
                require_once __DIR__ . '/PageAdminService.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_global_search_studio($la, $params);
                return array_merge(['success' => $la->getStatus() === 'ok'], $la->getData());

            case 'audit_page_quality_studio':
                require_once __DIR__ . '/PageAdminService.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_audit_page_quality_studio($la, $params);
                return array_merge(['success' => $la->getStatus() === 'ok'], $la->getData());

            case 'auto_fix_page_issues_studio':
                require_once __DIR__ . '/PageAdminService.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_auto_fix_page_issues_studio($la, $params);
                $isOk = $la->getStatus() === 'ok';
                $instructions = $la->getInstructions();
                $msg = !empty($instructions) ? ($instructions[0]['message'] ?? '') : '';
                return array_merge(['success' => $isOk, 'message' => $msg], $la->getData());

            case 'save_api_collection_studio':
                require_once __DIR__ . '/PageAdminService.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_save_api_collection_studio($la, $params);
                $isOk = $la->getStatus() === 'ok';
                $instructions = $la->getInstructions();
                $msg = !empty($instructions) ? ($instructions[0]['message'] ?? '') : '';
                return array_merge(['success' => $isOk, 'message' => $msg], $la->getData());

            case 'list_api_collections_studio':
                require_once __DIR__ . '/PageAdminService.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_list_api_collections_studio($la, $params);
                return array_merge(['success' => $la->getStatus() === 'ok'], $la->getData());

            case 'extract_partial_studio':
                require_once __DIR__ . '/PageAdminService.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_extract_partial_studio($la, $params);
                $isOk = $la->getStatus() === 'ok';
                $instructions = $la->getInstructions();
                $msg = !empty($instructions) ? ($instructions[0]['message'] ?? '') : '';
                return array_merge(['success' => $isOk, 'message' => $msg], $la->getData());

            case 'purge_studio_cache':
            case 'live_purge_studio_cache':
                require_once __DIR__ . '/PageAdminService.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_purge_studio_cache($la, $params);
                $isOk = $la->getStatus() === 'ok';
                $instructions = $la->getInstructions();
                $msg = !empty($instructions) ? ($instructions[0]['message'] ?? '') : '';
                return array_merge(['success' => $isOk, 'message' => $msg], $la->getData());

            case 'check_page_health_studio':
            case 'live_check_page_health_studio':
                require_once __DIR__ . '/PageAdminService.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_check_page_health_studio($la, $params);
                $isOk = $la->getStatus() === 'ok';
                $instructions = $la->getInstructions();
                $msg = !empty($instructions) ? ($instructions[0]['message'] ?? '') : '';
                return array_merge(['success' => $isOk, 'message' => $msg], $la->getData());

            case 'apply_auto_fix_studio':
            case 'live_apply_auto_fix_studio':
                require_once __DIR__ . '/PageAdminService.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_apply_auto_fix_studio($la, $params);
                $isOk = $la->getStatus() === 'ok';
                $instructions = $la->getInstructions();
                $msg = !empty($instructions) ? ($instructions[0]['message'] ?? '') : '';
                return array_merge(['success' => $isOk, 'message' => $msg], $la->getData());

            case 'list_media_assets_studio':
            case 'live_list_media_assets_studio':
                require_once __DIR__ . '/PageAdminService.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_list_media_assets_studio($la, $params);
                return array_merge(['success' => $la->getStatus() === 'ok'], $la->getData());

            case 'dispatch_test_live_event_studio':
            case 'live_dispatch_test_live_event_studio':
                require_once __DIR__ . '/PageAdminService.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_dispatch_test_live_event_studio($la, $params);
                $isOk = $la->getStatus() === 'ok';
                $instructions = $la->getInstructions();
                $msg = !empty($instructions) ? ($instructions[0]['message'] ?? '') : '';
                return array_merge(['success' => $isOk, 'message' => $msg], $la->getData());

            case 'get_entity_schema_studio':
            case 'live_get_entity_schema_studio':
                require_once __DIR__ . '/PageAdminService.php';
                $la = new \SPPMod\SPPAPI\LiveAction();
                live_get_entity_schema_studio($la, $params);
                return array_merge(['success' => $la->getStatus() === 'ok'], $la->getData());

            case 'coder_file_props_get':
                return \SPPMod\SPPCoder\SPPCoder::getFileProps($params['path'] ?? '');

            case 'coder_file_props_save':
                return \SPPMod\SPPCoder\SPPCoder::saveFileProps(
                    $params['path'] ?? '',
                    $params['properties'] ?? ($params['props'] ?? $params)
                );

            // -------------------------------------------------------------
            // SPPSHIELD SECURITY HARDENING SUITE (Firewall, WAF, Advisor, Malware, SSH)
            // -------------------------------------------------------------
            case 'shield_overview':
                require_once __DIR__ . '/ShieldAdminService.php';
                return \SPP\Admin\Services\ShieldAdminService::getOverview();

            case 'shield_firewall_get':
                require_once __DIR__ . '/ShieldAdminService.php';
                return \SPP\Admin\Services\ShieldAdminService::getFirewall();

            case 'shield_firewall_block':
                require_once __DIR__ . '/ShieldAdminService.php';
                return \SPP\Admin\Services\ShieldAdminService::blockIp(
                    $params['ip'] ?? '',
                    $params['reason'] ?? '',
                    isset($params['duration']) ? (int)$params['duration'] : null
                );

            case 'shield_firewall_unblock':
                require_once __DIR__ . '/ShieldAdminService.php';
                return \SPP\Admin\Services\ShieldAdminService::unblockIp($params['ip'] ?? '');

            case 'shield_whitelist_add':
                require_once __DIR__ . '/ShieldAdminService.php';
                return \SPP\Admin\Services\ShieldAdminService::addWhitelist(
                    $params['ip'] ?? '',
                    $params['note'] ?? ''
                );

            case 'shield_whitelist_remove':
                require_once __DIR__ . '/ShieldAdminService.php';
                return \SPP\Admin\Services\ShieldAdminService::removeWhitelist($params['ip'] ?? '');

            // Port & Protocol Firewall Management (UFW)
            case 'shield_ports_get':
                require_once __DIR__ . '/ShieldAdminService.php';
                return \SPP\Admin\Services\ShieldAdminService::getPortRules();

            case 'shield_port_allow':
                require_once __DIR__ . '/ShieldAdminService.php';
                return \SPP\Admin\Services\ShieldAdminService::allowPort(
                    (int)($params['port'] ?? 80),
                    $params['proto'] ?? 'tcp',
                    $params['from'] ?? null
                );

            case 'shield_port_deny':
                require_once __DIR__ . '/ShieldAdminService.php';
                return \SPP\Admin\Services\ShieldAdminService::denyPort(
                    (int)($params['port'] ?? 80),
                    $params['proto'] ?? 'tcp',
                    $params['from'] ?? null
                );

            case 'shield_port_delete':
                require_once __DIR__ . '/ShieldAdminService.php';
                return \SPP\Admin\Services\ShieldAdminService::deletePortRule((int)($params['num'] ?? ($params['rule_num'] ?? 1)));

            // Fail2ban Jail Status & 1-Click Unban
            case 'shield_fail2ban_status':
                require_once __DIR__ . '/ShieldAdminService.php';
                return \SPP\Admin\Services\ShieldAdminService::getFail2banStatus();

            case 'shield_fail2ban_unban':
                require_once __DIR__ . '/ShieldAdminService.php';
                return \SPP\Admin\Services\ShieldAdminService::unbanFail2banIp(
                    $params['jail'] ?? 'sshd',
                    $params['ip'] ?? ''
                );

            // SSH Daemon Hardening
            case 'shield_ssh_hardening_get':
                require_once __DIR__ . '/ShieldAdminService.php';
                return \SPP\Admin\Services\ShieldAdminService::getSshHardeningStatus();

            case 'shield_ssh_hardening_save':
                require_once __DIR__ . '/ShieldAdminService.php';
                return \SPP\Admin\Services\ShieldAdminService::updateSshHardening($params);

            case 'shield_bruteforce_get':
                require_once __DIR__ . '/ShieldAdminService.php';
                return \SPP\Admin\Services\ShieldAdminService::getBruteForce();

            case 'shield_bruteforce_clear':
                require_once __DIR__ . '/ShieldAdminService.php';
                return \SPP\Admin\Services\ShieldAdminService::clearBruteForce($params['ip'] ?? '');

            case 'shield_scan_run':
                require_once __DIR__ . '/ShieldAdminService.php';
                return \SPP\Admin\Services\ShieldAdminService::runScan(
                    $params['path'] ?? '',
                    !empty($params['quarantine'])
                );

            case 'shield_quarantine_get':
                require_once __DIR__ . '/ShieldAdminService.php';
                return \SPP\Admin\Services\ShieldAdminService::getQuarantine();

            case 'shield_quarantine_restore':
                require_once __DIR__ . '/ShieldAdminService.php';
                return \SPP\Admin\Services\ShieldAdminService::restoreQuarantine($params['id'] ?? '');

            case 'shield_quarantine_delete':
                require_once __DIR__ . '/ShieldAdminService.php';
                return \SPP\Admin\Services\ShieldAdminService::deleteQuarantine($params['id'] ?? '');

            case 'shield_audit_run':
                require_once __DIR__ . '/ShieldAdminService.php';
                return \SPP\Admin\Services\ShieldAdminService::runAudit();

            case 'shield_ssh_keys_get':
                require_once __DIR__ . '/ShieldAdminService.php';
                return \SPP\Admin\Services\ShieldAdminService::getSSHKeys();

            case 'shield_ssh_keys_add':
                require_once __DIR__ . '/ShieldAdminService.php';
                return \SPP\Admin\Services\ShieldAdminService::addSSHKey(
                    $params['key'] ?? '',
                    $params['comment'] ?? ''
                );

            case 'shield_ssh_keys_remove':
                require_once __DIR__ . '/ShieldAdminService.php';
                return \SPP\Admin\Services\ShieldAdminService::removeSSHKey($params['fingerprint'] ?? '');

            case 'shield_ssh_keys_generate':
                require_once __DIR__ . '/ShieldAdminService.php';
                return \SPP\Admin\Services\ShieldAdminService::generateSSHKey(
                    $params['comment'] ?? '',
                    $params['type'] ?? 'ed25519'
                );

            case 'metrics_overview':
                require_once __DIR__ . '/MetricsAdminService.php';
                return \SPP\Admin\Services\MetricsAdminService::getOverview();

            case 'metrics_hud_get':
                require_once __DIR__ . '/MetricsAdminService.php';
                return \SPP\Admin\Services\MetricsAdminService::getLiveHud();

            case 'metrics_traffic_get':
                require_once __DIR__ . '/MetricsAdminService.php';
                return \SPP\Admin\Services\MetricsAdminService::getTrafficAnalytics(
                    $params['log_path'] ?? '',
                    (int)($params['limit'] ?? 5000)
                );

            case 'metrics_history_get':
                require_once __DIR__ . '/MetricsAdminService.php';
                return \SPP\Admin\Services\MetricsAdminService::getHistoricalMetrics(
                    (int)($params['hours'] ?? 24)
                );

            case 'metrics_snapshot_record':
                require_once __DIR__ . '/MetricsAdminService.php';
                return \SPP\Admin\Services\MetricsAdminService::recordSnapshot();

            case 'metrics_report_generate':
                require_once __DIR__ . '/MetricsAdminService.php';
                return \SPP\Admin\Services\MetricsAdminService::generateReport(
                    $params['period'] ?? '7d'
                );

            // SPPServer Actions
            case 'server_infra_summary':
            case 'server_infra':
            case 'deploy_infra_summary':
                return \SPP\Admin\Services\ServerAdminService::getInfraSummary();

            case 'server_services_get':
                return \SPP\Admin\Services\ServerAdminService::listServices($params['filter'] ?? null);

            case 'server_service_control':
                return \SPP\Admin\Services\ServerAdminService::controlService(
                    $params['service'] ?? '',
                    $params['control_action'] ?? ($params['action_type'] ?? 'restart')
                );

            case 'server_processes_get':
                return \SPP\Admin\Services\ServerAdminService::listProcesses(
                    $params['search'] ?? null,
                    (int)($params['limit'] ?? 50),
                    $params['sort_by'] ?? 'memory'
                );

            case 'server_process_kill':
                return \SPP\Admin\Services\ServerAdminService::killProcess(
                    (int)($params['pid'] ?? 0),
                    !empty($params['force'])
                );

            case 'server_process_renice':
                return \SPP\Admin\Services\ServerAdminService::reniceProcess(
                    (int)($params['pid'] ?? 0),
                    $params['priority'] ?? 'normal'
                );

            case 'server_accounts_get':
                return \SPP\Admin\Services\ServerAdminService::listAccounts();

            case 'server_account_create':
                return \SPP\Admin\Services\ServerAdminService::createAccount($params);

            case 'server_account_transition':
                return \SPP\Admin\Services\ServerAdminService::transitionAccount(
                    $params['username'] ?? '',
                    $params['transition'] ?? '',
                    $params['comment'] ?? ''
                );

            case 'server_account_delete':
                return \SPP\Admin\Services\ServerAdminService::deleteAccount(
                    $params['username'] ?? '',
                    !empty($params['purge_files'])
                );

            case 'server_archive_inspect':
                return \SPP\Admin\Services\ServerAdminService::inspectArchive($params['archive'] ?? '');

            case 'server_archive_import':
                return \SPP\Admin\Services\ServerAdminService::importArchive(
                    $params['archive'] ?? '',
                    $params
                );

            // Docker Container Workstation
            case 'server_docker_status':
            case 'server_docker_list':
                return (new \SPP\Core\Server\Drivers\DockerEngineDriver())->listContainers(!empty($params['all']));

            case 'server_docker_action':
            case 'server_docker_control':
                return (new \SPP\Core\Server\Drivers\DockerEngineDriver())->controlContainer(
                    $params['id'] ?? ($params['container_id'] ?? ''),
                    $params['control_action'] ?? ($params['action_name'] ?? ($params['docker_action'] ?? 'restart'))
                );

            case 'server_docker_logs':
                return ['success' => true, 'logs' => (new \SPP\Core\Server\Drivers\DockerEngineDriver())->getLogs($params['id'] ?? '', (int)($params['tail'] ?? 100))];

            case 'server_docker_prune':
                return (new \SPP\Core\Server\Drivers\DockerEngineDriver())->prune();

            // OS Package Updates, Swap & Timezone
            case 'server_pkg_updates_check':
                return SystemPackageService::checkUpdates();

            case 'server_swap_status':
            case 'server_swap_info':
                return SystemPackageService::getSwapInfo();

            case 'server_swap_create':
                return SystemPackageService::createSwap((int)($params['size_mb'] ?? 2048));

            case 'server_timezone_get':
                return SystemPackageService::getTimezoneInfo();

            case 'server_timezone_set':
                return SystemPackageService::setTimezone($params['timezone'] ?? 'UTC');

            // Self-Healing Watchdog & Proactive Alerts
            case 'watchdog_status':
            case 'watchdog_settings_get':
                return array_merge(['success' => true], WatchdogService::getSettings());

            case 'watchdog_configure':
            case 'watchdog_settings_save':
                return WatchdogService::saveSettings($params);

            case 'watchdog_audit_run':
                return WatchdogService::runHealthAudit();

            case 'watchdog_test_alert':
            case 'watchdog_alert_test':
                return WatchdogService::testChannel($params['channel'] ?? 'telegram');

            case 'apps_set_domain_scope':
                if (!class_exists(\SPP\Core\Router\DomainRouter::class)) {
                    require_once dirname(__DIR__, 2) . '/core/router/class.domainrouter.php';
                }
                \SPP\Core\Router\DomainRouter::loadConfig();
                $appName = trim((string)($params['app_name'] ?? ($params['app'] ?? 'default')));
                $domainOnly = !empty($params['domain_only']);
                $allowed = [];
                if (isset($params['allowed_domains'])) {
                    if (is_array($params['allowed_domains'])) {
                        $allowed = $params['allowed_domains'];
                    } else {
                        $allowed = array_filter(array_map('trim', explode(',', (string)$params['allowed_domains'])));
                    }
                }
                \SPP\Core\Router\DomainRouter::setAppDomainScope($appName, $domainOnly, $allowed);
                \SPP\Core\Router\DomainRouter::saveConfig();
                return ['success' => true, 'message' => "Domain scope updated for {$appName}."];

            case 'set_base_app':
                if (!class_exists(\SPP\Core\Router\DomainRouter::class)) {
                    require_once dirname(__DIR__, 2) . '/core/router/class.domainrouter.php';
                }
                \SPP\Core\Router\DomainRouter::loadConfig();
                $targetApp = trim((string)($params['target_app'] ?? ($params['app'] ?? 'default')));
                \SPP\Core\Router\DomainRouter::setGlobalDefaultApp($targetApp);
                \SPP\Core\Router\DomainRouter::saveConfig();
                $gsPath = SPP_BASE_DIR . '/etc/global-settings.yml';
                if (file_exists($gsPath)) {
                    $gs = \Symfony\Component\Yaml\Yaml::parseFile($gsPath) ?: [];
                    $gs['base_app'] = $targetApp;
                    file_put_contents($gsPath, \Symfony\Component\Yaml\Yaml::dump($gs, 4, 2));
                }
                return ['success' => true, 'message' => "Base application set to '{$targetApp}'."];

            case 'make_app':
                $name = trim((string)($params['name'] ?? ($params['app_name'] ?? '')));
                if (!$name) return ['success' => false, 'error' => 'App name is required.'];
                return self::runCliSubprocess('make:app', [$name]);

            case 'make_controller':
                $name = trim((string)($params['name'] ?? ($params['controller_name'] ?? '')));
                if (!$name) return ['success' => false, 'error' => 'Controller name is required.'];
                $cmdArgs = [$name];
                if (!empty($params['app'])) $cmdArgs['app'] = $params['app'];
                return self::runCliSubprocess('make:controller', $cmdArgs);

            case 'make_service':
                $name = trim((string)($params['name'] ?? ($params['service_name'] ?? '')));
                if (!$name) return ['success' => false, 'error' => 'Service name is required.'];
                $cmdArgs = [$name];
                if (!empty($params['app'])) $cmdArgs['app'] = $params['app'];
                return self::runCliSubprocess('make:service', $cmdArgs);

            case 'call_service':
                $svc = trim((string)($params['service'] ?? ''));
                if (!$svc) return ['success' => false, 'error' => 'Service name required.'];
                $svcParams = [];
                if (!empty($params['params'])) {
                    $svcParams = is_array($params['params']) ? $params['params'] : (json_decode((string)$params['params'], true) ?: []);
                }
                ob_start();
                \SPPMod\SPPAPI\Dispatchers\ServiceDispatcher::resolveAndExecute($svc, $svcParams);
                $out = ob_get_clean();
                $decoded = json_decode($out, true);
                return is_array($decoded) ? $decoded : ['success' => true, 'output' => $out];

            case 'subscribe':
                return ['success' => true, 'channel' => $params['channel'] ?? 'default'];

            case 'live_update':
                return ['success' => true, 'state' => $params['state'] ?? []];

            case 'sync_token':
                return ['success' => true, 'message' => 'Token synchronized.'];

            // SPPApps Actions
            case 'apps_list_framework':
            case 'apps_framework_list':
                return \SPP\Admin\Services\AppsAdminService::listFrameworkApps();

            case 'apps_uninstall':
            case 'apps_uninstall_framework':
                return \SPP\Admin\Services\AppsAdminService::uninstallFrameworkApp(
                    $params['app'] ?? ($params['app_name'] ?? ''),
                    $params
                );

            case 'apps_reinstall':
            case 'apps_reinstall_framework':
                return \SPP\Admin\Services\AppsAdminService::reinstallFrameworkApp(
                    $params['app'] ?? ($params['app_name'] ?? ''),
                    $params
                );

            case 'apps_backup_create':
            case 'apps_create_backup':
                return \SPP\Admin\Services\AppsAdminService::createAppBackup(
                    $params['app'] ?? ($params['app_name'] ?? '')
                );

            case 'apps_list_backups':
            case 'apps_backups_list':
                return \SPP\Admin\Services\AppsAdminService::listAppBackups();

            case 'apps_restore_backup':
            case 'apps_backup_restore':
                return \SPP\Admin\Services\AppsAdminService::restoreAppBackup(
                    $params['file'] ?? ($params['backup_id'] ?? ($params['identifier'] ?? '')),
                    $params
                );

            case 'apps_delete_backup':
            case 'apps_backup_delete':
                return \SPP\Admin\Services\AppsAdminService::deleteAppBackup(
                    $params['file'] ?? ($params['backup_id'] ?? ($params['identifier'] ?? ''))
                );

            case 'apps_purge_backups':
                return \SPP\Admin\Services\AppsAdminService::purgeAppBackups(
                    $params['app'] ?? ($params['app_name'] ?? '')
                );

            case 'apps_catalog_get':
                return \SPP\Admin\Services\AppsAdminService::listApps();

            case 'apps_install':
                return \SPP\Admin\Services\AppsAdminService::installApp(
                    $params['app'] ?? '',
                    $params['dir'] ?? '',
                    $params
                );

            case 'apps_wp_inspect':
                return \SPP\Admin\Services\AppsAdminService::inspectWordPress($params['path'] ?? '');

            case 'apps_wp_harden':
                return \SPP\Admin\Services\AppsAdminService::hardenWordPress(
                    $params['path'] ?? '',
                    $params['rules'] ?? []
                );

            case 'apps_wp_cli':
                return \SPP\Admin\Services\AppsAdminService::runWpCli(
                    $params['path'] ?? '',
                    $params['cli_command'] ?? ($params['command'] ?? 'core version')
                );

            case 'apps_staging_create':
                return \SPP\Admin\Services\AppsAdminService::createStaging(
                    $params['prod_path'] ?? ($params['production_path'] ?? ''),
                    $params['staging_path'] ?? '',
                    $params
                );

            case 'apps_staging_sync':
                return \SPP\Admin\Services\AppsAdminService::syncStaging(
                    $params['staging_path'] ?? '',
                    $params['prod_path'] ?? ($params['production_path'] ?? ''),
                    !isset($params['take_backup']) || !empty($params['take_backup'])
                );

            case 'apps_staging_list':
                return \SPP\Admin\Services\AppsAdminService::listStaging();

            // 1-Click cPanel & WHM Migration Engine
            case 'cpanel_import_inspect':
                return \SPP\Admin\Services\CpanelImporterService::inspect(
                    $params['archive_path'] ?? ($params['archive'] ?? '')
                );

            case 'cpanel_import_execute':
            case 'cpanel_import':
                return \SPP\Admin\Services\CpanelImporterService::import(
                    $params['archive_path'] ?? ($params['archive'] ?? ''),
                    $params
                );

            case 'cpanel_import_list_archives':
                return \SPP\Admin\Services\CpanelImporterService::listArchives(
                    $params['dir'] ?? null
                );

            // SPPRuntime Actions
            case 'runtime_php_get':
                return \SPP\Admin\Services\RuntimeAdminService::getPhpVersions();

            case 'runtime_php_switch':
                return \SPP\Admin\Services\RuntimeAdminService::switchPhpVersion(
                    $params['version'] ?? ($params['series'] ?? ''),
                    $params['docroot'] ?? null
                );

            case 'runtime_fpm_pools_get':
                return \SPP\Admin\Services\RuntimeAdminService::getFpmPools();

            case 'runtime_fpm_pool_save':
                return \SPP\Admin\Services\RuntimeAdminService::saveFpmPool($params);

            case 'runtime_fpm_pool_delete':
                return \SPP\Admin\Services\RuntimeAdminService::deleteFpmPool($params['name'] ?? '');

            case 'runtime_fpm_test':
                return \SPP\Admin\Services\RuntimeAdminService::testFpmConnectivity($params['listen'] ?? '');

            case 'runtime_ini_get':
                return \SPP\Admin\Services\RuntimeAdminService::getIniDirectives($params['ini_path'] ?? null);

            case 'runtime_ini_set':
                return \SPP\Admin\Services\RuntimeAdminService::updateIniDirective(
                    $params['key'] ?? '',
                    $params['value'] ?? '',
                    $params['ini_path'] ?? null
                );

            case 'runtime_ini_override':
                return \SPP\Admin\Services\RuntimeAdminService::createLocalIniOverride(
                    $params['docroot'] ?? '',
                    $params['directives'] ?? []
                );

            case 'runtime_opcache_status':
                return \SPP\Admin\Services\RuntimeAdminService::getOpcacheStatus();

            case 'runtime_opcache_reset':
                return \SPP\Admin\Services\RuntimeAdminService::resetOpcache();

            case 'runtime_opcache_warmup':
                return \SPP\Admin\Services\RuntimeAdminService::warmupOpcache($params['paths'] ?? []);

            case 'runtime_extensions_matrix':
                return \SPP\Admin\Services\RuntimeAdminService::getExtensionsMatrix();

            case 'runtime_sizing_advisor':
                return \SPP\Admin\Services\RuntimeAdminService::getHardwareSizingAdvisor();

            case 'polyglot_inventory':
                return \SPP\Admin\Services\PolyglotAdminService::getInventory(!empty($params['refresh']));

            case 'polyglot_toggle':
                $lang = $params['language'] ?? $params['lang'] ?? '';
                $state = isset($params['state']) ? (bool)$params['state'] : null;
                return \SPP\Admin\Services\PolyglotAdminService::toggleLanguage((string)$lang, $state);

            case 'polyglot_services':
                return \SPP\Admin\Services\PolyglotAdminService::getServices($appContext);

            case 'polyglot_evaluate':
                $lang = $params['language'] ?? $params['lang'] ?? 'formula';
                $code = $params['code'] ?? '';
                $context = is_array($params['context'] ?? null) 
                    ? $params['context'] 
                    : (json_decode($params['context'] ?? '{}', true) ?: []);
                $daemon = !empty($params['daemon']);
                return \SPP\Admin\Services\PolyglotAdminService::evaluate((string)$lang, (string)$code, $context, $daemon);

            // SPPReseller Actions
            case 'reseller_list':
                return \SPP\Admin\Services\ResellerAdminService::getResellers();

            case 'reseller_create':
                return \SPP\Admin\Services\ResellerAdminService::createReseller($params);

            case 'reseller_update':
                return \SPP\Admin\Services\ResellerAdminService::updateReseller(
                    $params['username'] ?? '',
                    $params
                );

            case 'reseller_transition':
                return \SPP\Admin\Services\ResellerAdminService::transitionReseller(
                    $params['username'] ?? '',
                    $params['transition'] ?? '',
                    $params['comment'] ?? ''
                );

            case 'reseller_delete':
                return \SPP\Admin\Services\ResellerAdminService::deleteReseller($params['username'] ?? '');

            case 'reseller_features_get':
                return \SPP\Admin\Services\ResellerAdminService::getFeatureLists();

            case 'reseller_features_save':
                return \SPP\Admin\Services\ResellerAdminService::saveFeatureList(
                    $params['name'] ?? '',
                    $params['features'] ?? [],
                    $params['description'] ?? ''
                );

            case 'reseller_features_delete':
                return \SPP\Admin\Services\ResellerAdminService::deleteFeatureList($params['name'] ?? '');

            case 'reseller_brand_get':
                return \SPP\Admin\Services\ResellerAdminService::getBrandConfig($params['username'] ?? '');

            case 'reseller_brand_save':
                return \SPP\Admin\Services\ResellerAdminService::saveBrandConfig(
                    $params['username'] ?? '',
                    $params['brand'] ?? $params
                );

            case 'reseller_limits_check':
                return \SPP\Admin\Services\ResellerAdminService::checkLimits($params['username'] ?? '');

            // Phase 2 Superpowers: Sovereign AI SRE & Auto-Healer
            case 'ai_sre_diagnose':
                return \SPP\Admin\Services\AiSreService::diagnose($params);

            case 'ai_sre_summarize':
                return \SPP\Admin\Services\AiSreService::summarize($params);

            case 'ai_sre_copilot':
                return \SPP\Admin\Services\AiSreService::copilot($params);

            case 'ai_sre_apply_patch':
                return \SPP\Admin\Services\AiSreService::applyPatch($params);

            // Phase 2 Superpowers: Atomic Blue/Green Canary Deployments
            case 'deploy_canary_push':
            case 'deploy_canary_deploy':
                return \SPP\Admin\Services\DeployAdminService::blueGreenDeploy($params['app'] ?? 'default', $params);

            case 'deploy_canary_rollback':
                return \SPP\Admin\Services\DeployAdminService::blueGreenRollback($params['app'] ?? 'default', $params['target'] ?? null);

            case 'deploy_canary_releases':
            case 'deploy_canary_status':
                return \SPP\Admin\Services\DeployAdminService::getBlueGreenReleases($params['app'] ?? 'default');

            // Mixed Strategy Deployment Orchestrator ("Best of All Worlds")
            case 'deploy_mixed_run':
            case 'deploy_mixed_deploy':
                return \SPP\Admin\Services\DeployAdminService::runMixedDeploy($params['app'] ?? 'default', $params);

            case 'deploy_mixed_plan':
                return \SPP\Admin\Services\DeployAdminService::getMixedDeployPlan($params['app'] ?? 'default', $params);

            case 'deploy_mixed_status':
                return \SPP\Admin\Services\DeployAdminService::getMixedDeployStatus($params['app'] ?? 'default');

            case 'deploy_mixed_rollback':
                return \SPP\Admin\Services\DeployAdminService::mixedDeployRollback($params['app'] ?? 'default', $params['target'] ?? null);

            // Pre-Deployment Preview, Comparison & Timeline
            case 'deploy_predeploy_comparison':
                return \SPP\Admin\Services\DeployAdminService::getPreDeployComparison($params['app'] ?? 'default', $params);

            case 'deploy_preflight_inspect':
                return \SPP\Admin\Services\DeployAdminService::runPreflightInspection($params['app'] ?? 'default', $params);

            case 'deploy_canary_weight':
                return \SPP\Admin\Services\DeployAdminService::updateCanaryWeight($params['app'] ?? 'default', (int)($params['weight'] ?? 100));

            case 'deploy_release_timeline':
                return \SPP\Admin\Services\DeployAdminService::getReleaseTimeline($params['app'] ?? 'default');

            // Phase 4: Standalone Host & Fleet VPS Installer
            case 'host_installer_summary':
                require_once __DIR__ . '/HostInstallerService.php';
                return \SPP\Admin\Services\HostInstallerService::getInstallerSummary($params);

            case 'host_installer_generate':
                require_once __DIR__ . '/HostInstallerService.php';
                return [
                    'success' => true,
                    'script' => \SPP\Admin\Services\HostInstallerService::generateScript($params)
                ];

            case 'host_installer_save':
                require_once __DIR__ . '/HostInstallerService.php';
                $path = \SPP\Admin\Services\HostInstallerService::writeInstallerScript($params['path'] ?? null, $params);
                return [
                    'success' => true,
                    'message' => 'Installer script written successfully.',
                    'path' => $path
                ];



            // Phase 2 Superpowers: Autonomous Zero-Trust Shield & AST Malware Scanner
            case 'shield_ast_scan':
                return \SPP\Admin\Services\ShieldAdminService::runAstScan($params['path'] ?? '', !empty($params['quarantine']));

            case 'shield_waf_patches_list':
                return \SPP\Admin\Services\ShieldAdminService::getVirtualPatches();

            case 'shield_waf_patch_toggle':
                return \SPP\Admin\Services\ShieldAdminService::toggleVirtualPatch($params['rule_id'] ?? '', !empty($params['enabled']));

            case 'shield_waf_inspect':
                return \SPP\Admin\Services\ShieldAdminService::inspectWafPayload($params);

            // Phase 5: Hotlink Protection & Directory Privacy
            case 'shield_hotlink_get':
                return \SPP\Admin\Services\ShieldAdminService::getHotlinkProtection();

            case 'shield_hotlink_save':
                return \SPP\Admin\Services\ShieldAdminService::saveHotlinkProtection($params);

            case 'shield_privacy_list':
                return \SPP\Admin\Services\ShieldAdminService::getDirectoryPrivacy();

            case 'shield_privacy_save':
                return \SPP\Admin\Services\ShieldAdminService::saveDirectoryPrivacy($params['path'] ?? '', $params);

            case 'shield_privacy_delete':
                return \SPP\Admin\Services\ShieldAdminService::deleteDirectoryPrivacy($params['path'] ?? '');

            // Phase 6: Sovereign Cloud Backup Vault
            case 'backup_vault_list':
                require_once __DIR__ . '/BackupAdminService.php';
                return \SPP\Admin\Services\BackupAdminService::listBackups();

            case 'backup_vault_create':
                require_once __DIR__ . '/BackupAdminService.php';
                return \SPP\Admin\Services\BackupAdminService::createBackup($params);

            case 'backup_vault_verify':
                require_once __DIR__ . '/BackupAdminService.php';
                return \SPP\Admin\Services\BackupAdminService::verifyBackup($params['filename'] ?? '');

            case 'backup_vault_restore':
                require_once __DIR__ . '/BackupAdminService.php';
                return \SPP\Admin\Services\BackupAdminService::restoreBackup($params['filename'] ?? '', $params);

            case 'backup_vault_delete':
                require_once __DIR__ . '/BackupAdminService.php';
                return \SPP\Admin\Services\BackupAdminService::deleteBackup($params['filename'] ?? '');

            // Phase 6: Sovereign SSH Key Vault & Remote Access Guard
            case 'ssh_vault_list':
                require_once __DIR__ . '/SshAdminService.php';
                return \SPP\Admin\Services\SshAdminService::listKeys();

            case 'ssh_vault_generate':
                require_once __DIR__ . '/SshAdminService.php';
                return \SPP\Admin\Services\SshAdminService::generateKeyPair(
                    $params['name'] ?? '',
                    $params['type'] ?? 'ed25519',
                    $params['passphrase'] ?? null
                );

            case 'ssh_vault_import':
                require_once __DIR__ . '/SshAdminService.php';
                return \SPP\Admin\Services\SshAdminService::importPublicKey(
                    $params['public_key'] ?? '',
                    $params['comment'] ?? ''
                );

            case 'ssh_vault_revoke':
                require_once __DIR__ . '/SshAdminService.php';
                return \SPP\Admin\Services\SshAdminService::revokeKey($params['fingerprint'] ?? $params['key'] ?? '');

            case 'ssh_vault_delete':
                require_once __DIR__ . '/SshAdminService.php';
                return \SPP\Admin\Services\SshAdminService::deleteKey($params['name'] ?? '');

            case 'ssh_vault_connect_info':
                require_once __DIR__ . '/SshAdminService.php';
                return \SPP\Admin\Services\SshAdminService::getConnectInfo($params['key_name'] ?? 'default');

            case 'ssh_vault_audit':
                require_once __DIR__ . '/SshAdminService.php';
                return \SPP\Admin\Services\SshAdminService::auditSshSecurity();


            // Phase 2 Superpowers: Visual Slow Query Advisor
            case 'db_slow_queries_list':
                return \SPP\Admin\Services\DatabaseAdminService::getSlowQueries($params['connection'] ?? null, (int)($params['limit'] ?? 50));

            case 'db_slow_query_explain':
                return \SPP\Admin\Services\DatabaseAdminService::analyzeSlowQuery($params['connection'] ?? 'default', $params['sql'] ?? '');

            case 'db_slow_query_apply_index':
                return \SPP\Admin\Services\DatabaseAdminService::applySlowQueryIndex(
                    $params['connection'] ?? 'default',
                    $params['table'] ?? '',
                    $params['column'] ?? '',
                    $params['index'] ?? null
                );

            case 'db_slow_query_clear':
                return \SPP\Admin\Services\DatabaseAdminService::clearSlowQueryLog();

            // -------------------------------------------------------------
            // PHASE 3: MULTI-SERVER FLEET ORCHESTRATOR & DNS CLUSTER (WHM KILLER)
            // -------------------------------------------------------------
            case 'fleet_list':
                return \SPP\Admin\Services\FleetAdminService::listNodes();

            case 'fleet_add':
                return \SPP\Admin\Services\FleetAdminService::addNode($params);

            case 'fleet_remove':
                return \SPP\Admin\Services\FleetAdminService::removeNode($params['id'] ?? $params['node_id'] ?? '');

            case 'fleet_ping':
                return \SPP\Admin\Services\FleetAdminService::pingNode($params['id'] ?? $params['node_id'] ?? '');

            case 'fleet_broadcast':
                return \SPP\Admin\Services\FleetAdminService::broadcastCommand(
                    $params['command'] ?? $params['cmd'] ?? '',
                    (array)($params['node_ids'] ?? [])
                );

            case 'fleet_sync_dns':
                return \SPP\Admin\Services\FleetAdminService::syncDns($params['zone'] ?? null);

            case 'fleet_status':
            case 'fleet_summary':
                return \SPP\Admin\Services\FleetAdminService::getFleetSummary();

            // -------------------------------------------------------------
            // PHASE 3: NATIVE IN-PANEL APM & FLAME GRAPH PROFILER
            // -------------------------------------------------------------
            case 'apm_overview':
                return \SPP\Admin\Services\ApmAdminService::getApmOverview();

            case 'apm_flame_graph':
                return \SPP\Admin\Services\ApmAdminService::getFlameGraph($params['transaction_id'] ?? $params['id'] ?? null);

            case 'apm_slow_transactions':
                return \SPP\Admin\Services\ApmAdminService::getSlowTransactions((int)($params['limit'] ?? 20));

            case 'apm_record_sample':
                return \SPP\Admin\Services\ApmAdminService::recordSample($params);

            // Phase 7: SPP Ignition Visual Exception Debugger
            case 'ignition_list':
                require_once __DIR__ . '/IgnitionService.php';
                return \SPP\Admin\Services\IgnitionService::listExceptions((int)($params['limit'] ?? 50));

            case 'ignition_get':
                require_once __DIR__ . '/IgnitionService.php';
                return \SPP\Admin\Services\IgnitionService::getException($params['id'] ?? '');

            case 'ignition_resolve':
                require_once __DIR__ . '/IgnitionService.php';
                return \SPP\Admin\Services\IgnitionService::resolveException($params['id'] ?? '');

            case 'ignition_clear':
                require_once __DIR__ . '/IgnitionService.php';
                return \SPP\Admin\Services\IgnitionService::clearExceptions();

            case 'ignition_simulate':
                require_once __DIR__ . '/IgnitionService.php';
                return [
                    'success' => true,
                    'incident' => \SPP\Admin\Services\IgnitionService::simulateTestException()
                ];

            // Phase 7: W3C Distributed Tracing Engine
            case 'trace_generate':
                require_once __DIR__ . '/TraceAdminService.php';
                return [
                    'success' => true,
                    'traceparent' => \SPP\Admin\Services\TraceAdminService::generateTraceparent(
                        $params['trace_id'] ?? null,
                        $params['parent_id'] ?? null,
                        !isset($params['sampled']) || !empty($params['sampled'])
                    )
                ];

            case 'trace_parse':
                require_once __DIR__ . '/TraceAdminService.php';
                $parsed = \SPP\Admin\Services\TraceAdminService::parseTraceparent($params['header'] ?? '');
                return [
                    'success' => $parsed !== null,
                    'parsed' => $parsed
                ];

            case 'trace_journey':
                require_once __DIR__ . '/TraceAdminService.php';
                return \SPP\Admin\Services\TraceAdminService::getTraceJourney($params['trace_id'] ?? bin2hex(random_bytes(16)));

            // -------------------------------------------------------------
            // PHASE 3: WEBAUTHN FIDO2 BIOMETRIC ZERO-TRUST PASSKEYS
            // -------------------------------------------------------------
            case 'webauthn_register_options':
                return \SPP\System\Admin\Services\WebAuthnService::generateRegistrationOptions(
                    $params['username'] ?? 'admin',
                    $params['display_name'] ?? 'SPP Administrator'
                );

            case 'webauthn_register_verify':
                return \SPP\System\Admin\Services\WebAuthnService::verifyRegistration(
                    $params['username'] ?? 'admin',
                    (array)($params['credential'] ?? $params),
                    $params['name'] ?? 'Biometric Device'
                );

            case 'webauthn_auth_options':
                return \SPP\System\Admin\Services\WebAuthnService::generateAuthenticationOptions(
                    $params['username'] ?? 'admin'
                );

            case 'webauthn_auth_verify':
                return \SPP\System\Admin\Services\WebAuthnService::verifyAuthentication(
                    $params['username'] ?? 'admin',
                    (array)($params['assertion'] ?? $params)
                );

            case 'webauthn_list':
                return \SPP\System\Admin\Services\WebAuthnService::listPasskeys(
                    $params['username'] ?? 'admin'
                );

            case 'webauthn_delete':
                return \SPP\System\Admin\Services\WebAuthnService::deletePasskey(
                    $params['username'] ?? 'admin',
                    $params['credential_id'] ?? $params['id'] ?? ''
                );

            // -------------------------------------------------------------
            // PHASE 3: E-VAARTA TEAM COLLABORATION & COLLISION DETECTION
            // -------------------------------------------------------------
            case 'evaarta_collision_ping':
            case 'mail_collision_ping':
                return \SPPMod\SPPMail\Collaboration\TeamCollisionHub::pingPresence(
                    $params['thread_id'] ?? '',
                    $params['user_id'] ?? 'user_1',
                    $params['user_name'] ?? 'Admin',
                    $params['presence_action'] ?? ($params['action'] ?? 'viewing')
                );

            case 'evaarta_collision_viewers':
            case 'mail_collision_viewers':
                $viewers = \SPPMod\SPPMail\Collaboration\TeamCollisionHub::getActiveViewers(
                    $params['thread_id'] ?? '',
                    $params['current_user_id'] ?? null
                );
                return [
                    'success' => true,
                    'thread_id' => $params['thread_id'] ?? '',
                    'viewers' => $viewers,
                    'total_viewers' => count($viewers)
                ];

            case 'evaarta_note_add':
            case 'mail_note_add':
                return \SPPMod\SPPMail\Collaboration\TeamCollisionHub::addInternalNote(
                    $params['thread_id'] ?? '',
                    $params['author'] ?? $params['user_name'] ?? 'Admin',
                    $params['content'] ?? $params['note'] ?? ''
                );

            case 'evaarta_note_list':
            case 'mail_note_list':
                return \SPPMod\SPPMail\Collaboration\TeamCollisionHub::getInternalNotes(
                    $params['thread_id'] ?? ''
                );

            case 'evaarta_thread_assign':
                return \SPPMod\SPPMail\Collaboration\TeamCollisionHub::assignThread(
                    $params['thread_id'] ?? '',
                    $params['assigned_to'] ?? '',
                    $params['assigned_by'] ?? 'admin'
                );

            // -------------------------------------------------------------
            // PHASE 4: SOVEREIGN INBOUND WEBHOOK RELAY & INSPECTOR (NGROK KILLER)
            // -------------------------------------------------------------
            case 'webhook_list':
                return \SPP\Admin\Services\WebhookAdminService::listEndpoints();

            case 'webhook_create':
                return \SPP\Admin\Services\WebhookAdminService::createEndpoint($params);

            case 'webhook_update':
                return \SPP\Admin\Services\WebhookAdminService::updateEndpoint($params);

            case 'webhook_delete':
                return \SPP\Admin\Services\WebhookAdminService::deleteEndpoint($params);

            case 'webhook_payloads':
                return \SPP\Admin\Services\WebhookAdminService::getPayloads($params);

            case 'webhook_payload':
                return \SPP\Admin\Services\WebhookAdminService::getPayload($params);

            case 'webhook_clear':
                return \SPP\Admin\Services\WebhookAdminService::clearPayloads($params);

            case 'webhook_replay':
                return \SPP\Admin\Services\WebhookAdminService::replayPayload($params);

            case 'webhook_verify':
                return \SPP\Admin\Services\WebhookAdminService::verifySignature($params);

            case 'webhook_ingest':
                $endpointKey = $params['key'] ?? $params['endpoint_key'] ?? '';
                if (empty($endpointKey)) {
                    return ['success' => false, 'error' => 'Endpoint key required.'];
                }
                $headers = function_exists('getallheaders') ? (getallheaders() ?: []) : [];
                $rawBody = file_get_contents('php://input') ?: '';
                $method = $_SERVER['REQUEST_METHOD'] ?? 'POST';
                $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                $queryParams = $_GET;
                unset($queryParams['action'], $queryParams['key']);
                return \SPPMod\SPPIntegrations\WebhookRelayManager::recordPayload(
                    $endpointKey,
                    $method,
                    $headers,
                    $rawBody,
                    $queryParams,
                    $clientIp
                );

            // -------------------------------------------------------------
            // PHASE 4: NATIVE INTERACTIVE API STUDIO & OPENAPI 3.1 WORKBENCH
            // -------------------------------------------------------------
            case 'api_studio_endpoints':
                return \SPP\Admin\Services\ApiStudioService::getEndpoints();

            case 'api_studio_spec':
                return \SPP\Admin\Services\ApiStudioService::getOpenApiSpec(
                    $params['format'] ?? 'json',
                    $params['title'] ?? 'SPP Application API'
                );

            case 'api_studio_send':
                return \SPP\Admin\Services\ApiStudioService::sendRequest($params);

            case 'api_studio_curl':
                return \SPP\Admin\Services\ApiStudioService::generateCurl($params);

            // -------------------------------------------------------------
            // PHASE 4: VISUAL WORKFLOW & SAGA COMPENSATION SIMULATOR
            // -------------------------------------------------------------
            case 'workflow_simulate_list':
                return [
                    'success' => true,
                    'workflows' => \SPPMod\SPPWorkflow\Simulation\WorkflowSagaSimulator::listWorkflows($params['app'] ?? 'default')
                ];

            case 'workflow_simulate_step':
                return \SPPMod\SPPWorkflow\Simulation\WorkflowSagaSimulator::simulateTransition(
                    $params['workflow'] ?? $params['key'] ?? '',
                    $params['state'] ?? 'draft',
                    $params['transition'] ?? '',
                    (array)($params['context'] ?? [])
                );

            case 'workflow_simulate_rollback':
                return \SPPMod\SPPWorkflow\Simulation\WorkflowSagaSimulator::simulateSagaRollback(
                    $params['workflow'] ?? $params['key'] ?? '',
                    (array)($params['history'] ?? []),
                    (array)($params['context'] ?? [])
                );

            case 'workflow_simulate_timeout':
                return \SPPMod\SPPWorkflow\Simulation\WorkflowSagaSimulator::simulateTimeoutEscalation(
                    $params['workflow'] ?? $params['key'] ?? '',
                    $params['state'] ?? ''
                );

            // -------------------------------------------------------------
            // PHASE 4: SOVEREIGN SPY PIXEL NEUTRALIZER & PRIVACY SHIELD
            // -------------------------------------------------------------
            case 'mail_privacy_scan':
                return \SPPMod\SPPMail\Privacy\SpyPixelNeutralizer::neutralize($params['html'] ?? '');

            case 'mail_privacy_proxy_image':
                return \SPPMod\SPPMail\Privacy\SpyPixelNeutralizer::proxyImage(
                    $params['url'] ?? '',
                    $params['sig'] ?? ''
                );

            // -------------------------------------------------------------
            // PHASE 13: SOVEREIGN LIVE THREAT RADAR & PROGRESSIVE AUTO-BAN
            // -------------------------------------------------------------
            case 'shield_threat_radar_feed':
                require_once __DIR__ . '/ShieldAdminService.php';
                return \SPP\Admin\Services\ShieldAdminService::getThreatRadarFeed((int)($params['limit'] ?? 30));

            case 'shield_threat_strike_record':
                require_once __DIR__ . '/ShieldAdminService.php';
                return \SPP\Admin\Services\ShieldAdminService::recordThreatStrike(
                    $params['ip'] ?? '',
                    $params['type'] ?? 'Exploit Attempt',
                    $params['severity'] ?? 'high',
                    $params['details'] ?? null
                );

            case 'shield_threat_auto_ban':
                require_once __DIR__ . '/ShieldAdminService.php';
                return \SPP\Admin\Services\ShieldAdminService::progressiveAutoBan(
                    $params['ip'] ?? '',
                    $params['reason'] ?? '',
                    (int)($params['duration'] ?? 86400)
                );

            case 'shield_threat_quarantine':
                require_once __DIR__ . '/ShieldAdminService.php';
                return \SPP\Admin\Services\ShieldAdminService::autoQuarantineTarget(
                    $params['target'] ?? $params['path'] ?? ''
                );

            case 'shield_threat_clear_strikes':
                require_once __DIR__ . '/ShieldAdminService.php';
                return \SPP\Admin\Services\ShieldAdminService::clearThreatStrikes($params['ip'] ?? '');

            // -------------------------------------------------------------
            // PHASE 14: MULTI-PORTAL SSO & APPLICATION TENANT CONTEXT SWITCHING
            // -------------------------------------------------------------
            case 'auth_sso_generate':
                require_once __DIR__ . '/SsoAuthService.php';
                return \SPP\Admin\Services\SsoAuthService::generateSsoToken(
                    $params['user'] ?? '',
                    $params['target'] ?? 'sppdev',
                    (int)($params['ttl'] ?? 60)
                );

            case 'auth_sso_validate':
                require_once __DIR__ . '/SsoAuthService.php';
                return \SPP\Admin\Services\SsoAuthService::validateSsoToken($params['token'] ?? '');

            case 'apps_switch_context':
                require_once __DIR__ . '/AppsAdminService.php';
                return \SPP\Admin\Services\AppsAdminService::switchAppContext(
                    $params['appname'] ?? $params['app'] ?? ''
                );

            case 'apps_active_context':
                require_once __DIR__ . '/AppsAdminService.php';
                return \SPP\Admin\Services\AppsAdminService::getActiveAppContext();

            default:
                // Check if an exact CLI command matches the action directly
                $candidateCmd = str_replace('_', ':', $action);
                $commands = CommandManager::discover();
                if (isset($commands[$candidateCmd])) {
                    return self::executeCommand($candidateCmd, $params);
                }
                return ['success' => false, 'error' => "Action '{$action}' not recognized by CommandBridge."];
        }
    }
}
