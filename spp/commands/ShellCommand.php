<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use SPP\CLI\CommandManager;

/**
 * Class ShellCommand
 * Interactive SPP Shell Mode providing all functions of spp.php and more.
 */
class ShellCommand extends Command
{
    protected string $name = 'shell';
    protected string $description = 'Launch the interactive SPP Shell Mode (run all CLI commands, switch apps, inspect state, tabs, AI, polyglot, etc.).';

    public function isCLIOnly(): bool
    {
        return true;
    }

    /** @var array<string> */
    private static array $commandList = [];

    public function execute(array $args): void
    {
        echo "\n===================================================\n";
        echo "          SPP Interactive Shell Mode\n";
        echo "===================================================\n";
        echo "Welcome to the SPP developer & administrator shell.\n";
        echo "Type 'help' or '?' for shell built-ins, 'list' for SPP commands, or 'exit' to quit.\n\n";

        $activeApp = null;
        $explicitApp = $this->getOption($args, 'app');
        if ($explicitApp) {
            $activeApp = $explicitApp;
        }

        // Virtual Workspaces (Tabs) Initialization
        $tabs = [
            1 => ['name' => 'Tab 1', 'app' => $activeApp, 'env' => $_ENV]
        ];
        $activeTab = 1;

        $commands = CommandManager::discover();
        self::$commandList = array_keys($commands);
        foreach (self::$commandList as $cmdKey) {
            if (($pos = strpos($cmdKey, ':')) !== false) {
                self::$commandList[] = substr($cmdKey, 0, $pos);
            }
        }
        self::$commandList = array_values(array_unique(array_merge(self::$commandList, [
            'help', 'exit', 'quit', 'clear', 'cls', 'history', 'context', 'app', 'use', 'switch', 'eval', 'alias', 'unalias', 'env', 'jobs',
            'tab', 'tab:new', 'tab:list', 'tab:next', 'tab:prev', 'tab:switch', 'tab:close',
            'python>', 'go>', 'node>', 'ruby>', 'bash>'
        ])));

        if (function_exists('readline_completion_function')) {
            readline_completion_function(function(string $input, int $index) {
                $matches = [];
                foreach (self::$commandList as $cmd) {
                    if (str_starts_with($cmd, $input)) {
                        $matches[] = $cmd;
                    }
                }
                return $matches;
            });
        }

        $sessionHistory = [];
        $historyFile = dirname(__DIR__, 2) . '/.spp_shell_history';
        if (function_exists('readline_read_history') && file_exists($historyFile)) {
            readline_read_history($historyFile);
        }

        $aliasFile = dirname(__DIR__, 2) . '/etc/shell-aliases.json';
        $aliases = file_exists($aliasFile) ? json_decode(file_get_contents($aliasFile), true) ?? [] : [];

        $bgJobs = [];
        $lineBuffer = '';
        $ipcFile = dirname(__DIR__, 2) . '/var/ipc/tab_notifications.json';
        if (!is_dir(dirname($ipcFile))) {
            @mkdir(dirname($ipcFile), 0777, true);
        }

        while (true) {
            // Check Cross-Tab IPC Notifications
            if (file_exists($ipcFile)) {
                $ipcData = json_decode(file_get_contents($ipcFile), true) ?? [];
                foreach ($ipcData as $idx => $notification) {
                    if ($notification['tab'] !== $activeTab && !$notification['read']) {
                        echo "\n\033[36m[IPC Notification from Tab {$notification['tab']}]\033[0m {$notification['message']}\n";
                        $ipcData[$idx]['read'] = true;
                    }
                }
                @file_put_contents($ipcFile, json_encode($ipcData));
            }

            $appDisplay = ($activeApp !== null && $activeApp !== '') ? $activeApp : 'global';
            $prompt = ($lineBuffer !== '') ? "spp [tab:{$activeTab}|{$appDisplay}] ..> " : "spp [tab:{$activeTab}|{$appDisplay}]> ";
            
            if (function_exists('readline')) {
                $line = readline($prompt);
                if ($line === false) {
                    echo "\n";
                    break; // EOF / Ctrl+D
                }
                if (trim($line) !== '' && $lineBuffer === '') {
                    readline_add_history($line);
                    if (function_exists('readline_write_history')) {
                        readline_write_history($historyFile);
                    }
                }
            } else {
                echo $prompt;
                $line = fgets(STDIN);
                if ($line === false) {
                    echo "\n";
                    break;
                }
            }

            $trimmed = trim($line);
            if ($trimmed === '' && $lineBuffer === '') {
                continue;
            }

            // Multi-line bracket pairing tracking
            $fullLine = $lineBuffer . ($lineBuffer !== '' ? "\n" : "") . $line;
            $openParens = substr_count($fullLine, '(') - substr_count($fullLine, ')');
            $openBraces = substr_count($fullLine, '{') - substr_count($fullLine, '}');
            $openQuotes = (substr_count(str_replace("\\\"", "", $fullLine), '"') % 2 !== 0) || (substr_count(str_replace("\\\'", "", $fullLine), "'") % 2 !== 0);

            if ($openParens > 0 || $openBraces > 0 || $openQuotes) {
                $lineBuffer = $fullLine;
                continue;
            }

            $line = trim($fullLine);
            $lineBuffer = '';

            $sessionHistory[] = $line;

            // 1. AI Copilot Prompt Integration (@ai / ?ai)
            if (str_starts_with($line, '@ai ') || str_starts_with($line, '?ai ')) {
                $promptText = trim(substr($line, 4));
                $cliArgs = ['spp.php', 'ai:prompt', $promptText];
                if ($activeApp !== null) {
                    $cliArgs[] = "--app={$activeApp}";
                }
                $commands = CommandManager::discover();
                if (isset($commands['ai:prompt'])) {
                    try {
                        $commands['ai:prompt']->execute($cliArgs);
                    } catch (\Throwable $e) {
                        echo "\n[AI Exception] " . get_class($e) . ": " . $e->getMessage() . "\n\n";
                    }
                } else {
                    echo "Error: ai:prompt command not available.\n";
                }
                continue;
            }

            // 2. Background execution check (&)
            if (substr($line, -1) === '&' && !str_starts_with($line, 'eval')) {
                $bgCmd = trim(substr($line, 0, -1));
                $appParam = ($activeApp !== null) ? " --app={$activeApp}" : "";
                $fullCmd = "php spp.php " . $bgCmd . $appParam;
                
                $descriptors = [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w']
                ];
                $proc = proc_open($fullCmd, $descriptors, $pipes);
                if (is_resource($proc)) {
                    stream_set_blocking($pipes[1], false);
                    stream_set_blocking($pipes[2], false);
                    $jobId = count($bgJobs) + 1;
                    $bgJobs[$jobId] = [
                        'id' => $jobId,
                        'cmd' => $bgCmd,
                        'proc' => $proc,
                        'pipes' => $pipes,
                        'status' => 'running',
                        'time' => date('H:i:s')
                    ];
                    echo "[Job spawned] ID: {$jobId} ({$bgCmd})\n";
                    
                    // Push Cross-Tab IPC Notification
                    $ipcData = file_exists($ipcFile) ? json_decode(file_get_contents($ipcFile), true) ?? [] : [];
                    $ipcData[] = ['tab' => $activeTab, 'message' => "Spawned background job {$jobId}: {$bgCmd}", 'read' => false];
                    @file_put_contents($ipcFile, json_encode($ipcData));
                } else {
                    echo "Error: Failed to spawn background job for '{$bgCmd}'.\n";
                }
                continue;
            }

            // 3. Output piping & redirection check
            $isPipe = false;
            $isRedirect = false;
            $redirectAppend = false;
            $pipeCmd = '';
            $redirectFile = '';
            $baseLine = $line;

            if (!str_starts_with($line, 'eval') && !str_starts_with($line, '!')) {
                if (($pos = strpos($line, '|')) !== false) {
                    $isPipe = true;
                    $baseLine = trim(substr($line, 0, $pos));
                    $pipeCmd = trim(substr($line, $pos + 1));
                } elseif (($pos = strpos($line, '>>')) !== false) {
                    $isRedirect = true;
                    $redirectAppend = true;
                    $baseLine = trim(substr($line, 0, $pos));
                    $redirectFile = trim(substr($line, $pos + 2));
                } elseif (($pos = strpos($line, '>')) !== false) {
                    $isRedirect = true;
                    $baseLine = trim(substr($line, 0, $pos));
                    $redirectFile = trim(substr($line, $pos + 1));
                }
            }

            $inputArgs = array_values(array_filter(explode(' ', $baseLine), function($val) {
                return trim($val) !== '';
            }));

            $cmdName = $inputArgs[0] ?? '';

            // Alias expansion
            if (isset($aliases[$cmdName])) {
                $expanded = $aliases[$cmdName] . ' ' . implode(' ', array_slice($inputArgs, 1));
                $inputArgs = array_values(array_filter(explode(' ', $expanded), function($val) {
                    return trim($val) !== '';
                }));
                $cmdName = $inputArgs[0] ?? '';
            }

            // Universal space-to-colon command normalization (e.g. 'cache clear' -> 'cache:clear', 'make app' -> 'make:app')
            $discoveredCommands = CommandManager::discover();
            if (!isset($discoveredCommands[$cmdName]) && isset($inputArgs[1]) && isset($discoveredCommands[$cmdName . ':' . $inputArgs[1]])) {
                $cmdName = $cmdName . ':' . $inputArgs[1];
                $inputArgs[0] = $cmdName;
                array_splice($inputArgs, 1, 1);
            }

            // 4. Polyglot Microservice REPL check
            if (in_array($cmdName, ['python>', 'go>', 'node>', 'ruby>', 'bash>'])) {
                $lang = rtrim($cmdName, '>');
                echo "\n--- SPP Polyglot REPL ({$lang}) ---\n";
                echo "Type 'run' to execute buffer, 'exit' to return to SPP shell.\n";
                
                $extMap = ['python' => 'py', 'go' => 'go', 'node' => 'js', 'ruby' => 'rb', 'bash' => 'sh'];
                $ext = $extMap[$lang] ?? 'txt';
                $codeBuffer = [];
                
                while (true) {
                    $subPrompt = "spp:{$lang}> ";
                    $subLine = function_exists('readline') ? readline($subPrompt) : fgets(STDIN);
                    if ($subLine === false) {
                        echo "\n";
                        break;
                    }
                    $subLine = trim($subLine);
                    if ($subLine === 'exit' || $subLine === 'quit') {
                        echo "Exiting {$lang} REPL.\n\n";
                        break;
                    }
                    if ($subLine === 'run' || $subLine === 'exec') {
                        $baseDir = defined('SPP_BASE_DIR') ? SPP_BASE_DIR : dirname(__DIR__, 2);
                        $scratchDir = $baseDir . '/scratch';
                        if (!is_dir($scratchDir)) {
                            mkdir($scratchDir, 0777, true);
                        }
                        $tmpFile = $scratchDir . '/polyglot_repl.' . $ext;
                        file_put_contents($tmpFile, implode("\n", $codeBuffer));
                        
                        $relPath = 'scratch/polyglot_repl.' . $ext;
                        $cliArgs = ['spp.php', 'polyglot:run', '--path=' . $relPath];
                        if ($activeApp !== null) {
                            $cliArgs[] = "--app={$activeApp}";
                        }
                        
                        $commands = CommandManager::discover();
                        if (isset($commands['polyglot:run'])) {
                            try {
                                $commands['polyglot:run']->execute($cliArgs);
                            } catch (\Throwable $e) {
                                echo "[Polyglot Exception] " . $e->getMessage() . "\n";
                            }
                        } else {
                            echo "Error: polyglot:run command not available.\n";
                        }
                        $codeBuffer = [];
                        continue;
                    }
                    $codeBuffer[] = $subLine;
                    echo "  [Line added. Type 'run' to execute, 'exit' to quit]\n";
                }
                continue;
            }

            // 5. Virtual Workspaces (Tabs) check
            if ($cmdName === 'tab') {
                $subTab = $inputArgs[1] ?? 'list';
                if (is_numeric($subTab)) {
                    $cmdName = 'tab:switch';
                    $inputArgs[1] = $subTab;
                } else {
                    $cmdName = 'tab:' . $subTab;
                    if (isset($inputArgs[2])) {
                        $inputArgs[1] = $inputArgs[2];
                    }
                }
            }

            if ($cmdName === 'tab:new') {
                $tabs[$activeTab]['app'] = $activeApp;
                $tabs[$activeTab]['env'] = $_ENV;
                
                $newId = max(array_keys($tabs)) + 1;
                $tabs[$newId] = ['name' => "Tab {$newId}", 'app' => null, 'env' => $_ENV];
                $activeTab = $newId;
                $activeApp = null;
                echo "Created and switched to new virtual tab: {$newId} (Context: global)\n";
                continue;
            }

            if ($cmdName === 'tab:list') {
                echo "\n--- Virtual Workspaces (Tabs) ---\n";
                foreach ($tabs as $id => $tab) {
                    $current = ($id === $activeTab) ? '-> ' : '   ';
                    $appCtx = $tab['app'] ?? 'global';
                    echo "{$current}[Tab {$id}] App Context: {$appCtx}\n";
                }
                echo "---------------------------------\n\n";
                continue;
            }

            if ($cmdName === 'tab:next' || $cmdName === 'tab:prev') {
                $tabs[$activeTab]['app'] = $activeApp;
                $tabs[$activeTab]['env'] = $_ENV;
                
                $keys = array_keys($tabs);
                $curPos = array_search($activeTab, $keys);
                if ($cmdName === 'tab:next') {
                    $nextPos = ($curPos + 1) % count($keys);
                } else {
                    $nextPos = ($curPos - 1 + count($keys)) % count($keys);
                }
                $activeTab = $keys[$nextPos];
                $activeApp = $tabs[$activeTab]['app'];
                $_ENV = $tabs[$activeTab]['env'];
                
                if ($activeApp !== null && class_exists('\SPP\App')) {
                    try {
                        new \SPP\App($activeApp);
                        \SPP\Scheduler::setContext($activeApp);
                        if (class_exists('\SPP\Module')) \SPP\Module::loadAllModules();
                    } catch (\Throwable $e) {}
                }
                echo "Switched to Tab {$activeTab} (Context: " . ($activeApp ?? 'global') . ")\n";
                continue;
            }

            if ($cmdName === 'tab:switch') {
                if (!isset($inputArgs[1]) || !isset($tabs[(int)$inputArgs[1]])) {
                    echo "Error: Invalid tab ID. Type 'tab:list' to see active tabs.\n";
                    continue;
                }
                $tabs[$activeTab]['app'] = $activeApp;
                $tabs[$activeTab]['env'] = $_ENV;
                
                $activeTab = (int)$inputArgs[1];
                $activeApp = $tabs[$activeTab]['app'];
                $_ENV = $tabs[$activeTab]['env'];
                
                if ($activeApp !== null && class_exists('\SPP\App')) {
                    try {
                        new \SPP\App($activeApp);
                        \SPP\Scheduler::setContext($activeApp);
                        if (class_exists('\SPP\Module')) \SPP\Module::loadAllModules();
                    } catch (\Throwable $e) {}
                }
                echo "Switched to Tab {$activeTab} (Context: " . ($activeApp ?? 'global') . ")\n";
                continue;
            }

            if ($cmdName === 'tab:close') {
                $targetId = isset($inputArgs[1]) ? (int)$inputArgs[1] : $activeTab;
                if (count($tabs) <= 1) {
                    echo "Error: Cannot close the last remaining tab.\n";
                    continue;
                }
                if (!isset($tabs[$targetId])) {
                    echo "Error: Tab {$targetId} does not exist.\n";
                    continue;
                }
                unset($tabs[$targetId]);
                if ($activeTab === $targetId) {
                    $activeTab = array_keys($tabs)[0];
                    $activeApp = $tabs[$activeTab]['app'];
                    $_ENV = $tabs[$activeTab]['env'];
                    if ($activeApp !== null && class_exists('\SPP\App')) {
                        try {
                            new \SPP\App($activeApp);
                            \SPP\Scheduler::setContext($activeApp);
                            if (class_exists('\SPP\Module')) \SPP\Module::loadAllModules();
                        } catch (\Throwable $e) {}
                    }
                }
                echo "Closed Tab {$targetId}. Active Tab is now {$activeTab}.\n";
                continue;
            }

            // 6. Check Shell Built-ins
            if ($cmdName === 'exit' || $cmdName === 'quit') {
                echo "Exiting SPP Shell. Goodbye!\n";
                break;
            }

            if ($cmdName === 'clear' || $cmdName === 'cls') {
                echo "\033[2J\033[;H";
                continue;
            }

            if ($cmdName === 'history') {
                echo "\nCommand History (Current Session):\n";
                foreach ($sessionHistory as $i => $h) {
                    $num = $i + 1;
                    echo "  {$num}: {$h}\n";
                }
                echo "\n";
                continue;
            }

            if ($cmdName === 'jobs') {
                echo "\n--- Background Jobs ---\n";
                if (empty($bgJobs)) {
                    echo "  (No active or recent background jobs)\n";
                } else {
                    foreach ($bgJobs as $jobId => &$job) {
                        if ($job['status'] === 'running') {
                            $status = proc_get_status($job['proc']);
                            if (!$status['running']) {
                                $job['status'] = 'completed (exit ' . $status['exitcode'] . ')';
                                $out = stream_get_contents($job['pipes'][1]);
                                $err = stream_get_contents($job['pipes'][2]);
                                fclose($job['pipes'][0]);
                                fclose($job['pipes'][1]);
                                fclose($job['pipes'][2]);
                                proc_close($job['proc']);
                                $job['output'] = trim($out . "\n" . $err);
                            }
                        }
                        echo "  [{$jobId}] {$job['cmd']} - Status: {$job['status']} (Started: {$job['time']})\n";
                        if (isset($job['output']) && $job['output'] !== '') {
                            echo "    Output:\n    " . str_replace("\n", "\n    ", $job['output']) . "\n";
                        }
                    }
                }
                echo "-----------------------\n\n";
                continue;
            }

            if ($cmdName === 'alias') {
                if (!isset($inputArgs[1])) {
                    echo "\nConfigured Shell Aliases:\n";
                    if (empty($aliases)) {
                        echo "  (No aliases configured)\n";
                    } else {
                        foreach ($aliases as $k => $v) {
                            echo "  alias {$k} = {$v}\n";
                        }
                    }
                    echo "\n";
                } else {
                    $aliasDef = implode(' ', array_slice($inputArgs, 1));
                    if (strpos($aliasDef, '=') !== false) {
                        $parts = explode('=', $aliasDef, 2);
                        $aliasName = trim($parts[0]);
                        $aliasCmd = trim($parts[1]);
                    } else {
                        $aliasName = trim($inputArgs[1]);
                        $aliasCmd = trim(implode(' ', array_slice($inputArgs, 2)));
                    }
                    if ($aliasName !== '' && $aliasCmd !== '') {
                        $aliases[$aliasName] = $aliasCmd;
                        if (!is_dir(dirname($aliasFile))) {
                            mkdir(dirname($aliasFile), 0777, true);
                        }
                        file_put_contents($aliasFile, json_encode($aliases, JSON_PRETTY_PRINT));
                        echo "Alias registered: {$aliasName} = {$aliasCmd}\n";
                    } else {
                        echo "Error: Invalid alias definition. Example: alias c = cache:clear\n";
                    }
                }
                continue;
            }

            if ($cmdName === 'unalias') {
                if (isset($inputArgs[1]) && isset($aliases[$inputArgs[1]])) {
                    unset($aliases[$inputArgs[1]]);
                    file_put_contents($aliasFile, json_encode($aliases, JSON_PRETTY_PRINT));
                    echo "Alias removed: {$inputArgs[1]}\n";
                } else {
                    echo "Error: Alias not found.\n";
                }
                continue;
            }

            if ($cmdName === 'env') {
                if (!isset($inputArgs[1])) {
                    echo "\n--- Current Environment Variables ---\n";
                    foreach ($_ENV as $k => $v) {
                        if (is_string($v) || is_numeric($v)) {
                            echo "  {$k} = {$v}\n";
                        }
                    }
                    echo "-------------------------------------\n\n";
                } else {
                    $envArg = implode(' ', array_slice($inputArgs, 1));
                    if (strpos($envArg, '=') !== false) {
                        $parts = explode('=', $envArg, 2);
                        $key = trim($parts[0]);
                        $val = trim($parts[1]);
                        putenv("{$key}={$val}");
                        $_ENV[$key] = $val;
                        echo "Environment variable {$key} set to {$val}\n";
                    } else {
                        $key = trim($envArg);
                        $val = getenv($key);
                        if ($val !== false) {
                            echo "{$key} = {$val}\n";
                        } else {
                            echo "Environment variable {$key} is not set.\n";
                        }
                    }
                }
                continue;
            }

            if ($cmdName === 'context' || $cmdName === 'app') {
                echo "\n--- SPP Shell Context ---\n";
                echo "Active Tab         : Tab {$activeTab}\n";
                echo "Active App Context : " . ($activeApp ?? '(None - Global)') . "\n";
                echo "PHP Version        : " . PHP_VERSION . "\n";
                echo "Operating System   : " . PHP_OS_FAMILY . "\n";
                if (class_exists('\SPP\App')) {
                    $settings = \SPP\App::getGlobalSettings();
                    $apps = array_keys($settings['apps'] ?? []);
                    echo "Registered Apps    : " . implode(', ', $apps) . "\n";
                }
                echo "Discovered Commands: " . count(CommandManager::discover()) . "\n";
                echo "-------------------------\n\n";
                continue;
            }

            if ($cmdName === 'use' || $cmdName === 'switch') {
                if (!isset($inputArgs[1]) || trim($inputArgs[1]) === '') {
                    $activeApp = null;
                    $tabs[$activeTab]['app'] = null;
                    echo "Deselected all apps. Switched to global context for Tab {$activeTab}.\n";
                } else {
                    $targetApp = $inputArgs[1];
                    if (class_exists('\SPP\App')) {
                        $settings = \SPP\App::getGlobalSettings();
                        if (isset($settings['apps'][$targetApp])) {
                            try {
                                new \SPP\App($targetApp);
                                \SPP\Scheduler::setContext($targetApp);
                                if (class_exists('\SPP\Module')) {
                                    \SPP\Module::loadAllModules();
                                }
                                $activeApp = $targetApp;
                                $tabs[$activeTab]['app'] = $targetApp;
                                echo "Switched to app context: {$targetApp}\n";
                            } catch (\Throwable $e) {
                                echo "Error: Failed to boot app context '{$targetApp}'. " . $e->getMessage() . "\n";
                            }
                        } else {
                            echo "Error: App context '{$targetApp}' not found in global settings.\n";
                            $apps = array_keys($settings['apps'] ?? []);
                            echo "Available apps: " . implode(', ', $apps) . "\n";
                        }
                    } else {
                        $activeApp = $targetApp;
                        $tabs[$activeTab]['app'] = $targetApp;
                        echo "Switched to app context: {$targetApp}\n";
                    }
                }
                continue;
            }

            if ($cmdName === 'help' || $cmdName === '?') {
                if (isset($inputArgs[1])) {
                    $targetCmd = $inputArgs[1];
                    $commands = CommandManager::discover();
                    if (isset($commands[$targetCmd])) {
                        echo "\nHelp for command: {$targetCmd}\n";
                        echo "Description: " . $commands[$targetCmd]->getDescription() . "\n";
                        $helpText = $commands[$targetCmd]->getHelp();
                        if ($helpText) {
                            echo "Usage/Details:\n{$helpText}\n";
                        }
                        echo "\n";
                    } else {
                        echo "Command '{$targetCmd}' not found.\n";
                    }
                } else {
                    echo "\n================================================================================\n";
                    echo "                        SPP Interactive Shell Help\n";
                    echo "================================================================================\n";
                    echo "Virtual Workspaces (Tabs):\n";
                    echo "  tab new / tab:new    Create a new virtual workspace tab\n";
                    echo "  tab list / tab:list  List all active workspace tabs\n";
                    echo "  tab next / tab:prev  Cycle through active workspace tabs\n";
                    echo "  tab switch <id>      Switch directly to a specific tab ID (or just tab <id>)\n";
                    echo "  tab close [id]       Close a tab (defaults to current tab)\n\n";
                    echo "AI & Polyglot REPLs:\n";
                    echo "  @ai <query>          Send a prompt directly to the AI Copilot\n";
                    echo "  python> / go>        Drop into a Polyglot sub-REPL (also node>, ruby>, bash>)\n\n";
                    echo "Shell Built-in Commands:\n";
                    echo "  help [command]       Display this help menu, or detailed help for a command\n";
                    echo "  use [app_name]       Switch active SPP App context (e.g., use school). Use without arg to deselect.\n";
                    echo "  context / app        Display current app context and environment details\n";
                    echo "  history              Display command history for the current shell session\n";
                    echo "  alias [name]=[cmd]   List aliases, or register a new alias (e.g., alias c=cache:clear)\n";
                    echo "  unalias <name>       Remove a configured alias\n";
                    echo "  env [key]=[val]      List env variables, or get/set a specific env variable\n";
                    echo "  jobs                 List active and completed background jobs\n";
                    echo "  clear / cls          Clear the terminal screen\n";
                    echo "  exit / quit          Exit the interactive shell\n\n";
                    echo "Advanced Capabilities:\n";
                    echo "  eval <php_code>      Execute raw PHP code on the fly (e.g., eval echo PHP_OS;)\n";
                    echo "  ! <sys_cmd>          Execute an OS system command (e.g., ! dir or ! ls)\n";
                    echo "  <cmd> | <sys_cmd>    Pipe command output to a system command (e.g., list | grep make)\n";
                    echo "  <cmd> > <file>       Redirect command output to a file (e.g., list > cmds.txt)\n";
                    echo "  <cmd> &              Launch a command asynchronously in the background\n\n";
                    echo "SPP Framework Commands:\n";
                    echo "  You can directly run any SPP CLI command exactly as you would with spp.php.\n";
                    echo "  Example: list\n";
                    echo "  Example: cache:clear\n";
                    echo "  Example: make:app myapp --src=src/myapp\n";
                    echo "  Example: xdb:list-dbs\n";
                    echo "  Example: tinker\n";
                    echo "================================================================================\n\n";
                }
                continue;
            }

            if ($cmdName === 'eval') {
                $code = substr($line, 5);
                if (substr(trim($code), -1) !== ';') {
                    $code .= ';';
                }
                try {
                    ob_start();
                    $result = eval($code);
                    $output = ob_get_clean();
                    if ($output !== '') {
                        echo $output . "\n";
                    }
                    if ($result !== null) {
                        var_dump($result);
                    }
                } catch (\Throwable $e) {
                    ob_end_clean();
                    echo "[eval error] " . get_class($e) . ": " . $e->getMessage() . "\n";
                }
                continue;
            }

            if (str_starts_with($line, '!')) {
                $sysCmd = trim(substr($line, 1));
                if ($sysCmd !== '') {
                    passthru($sysCmd);
                    echo "\n";
                }
                continue;
            }

            // 7. Execute SPP Framework Command with Interactive Wizard Mode
            $commands = CommandManager::discover();
            if (isset($commands[$cmdName])) {
                $cmdObj = $commands[$cmdName];
                
                // Interactive Wizard Mode for missing required arguments
                if (!in_array($cmdName, ['tinker', 'list', 'shell', 'help'])) {
                    $helpText = $cmdObj->getHelp();
                    if (preg_match_all('/<([a-zA-Z0-9_]+)>/', $helpText, $matches)) {
                        $requiredArgs = $matches[1];
                        $providedPositionals = [];
                        for ($i = 1; $i < count($inputArgs); $i++) {
                            if (!str_starts_with($inputArgs[$i], '-')) {
                                $providedPositionals[] = $inputArgs[$i];
                            }
                        }
                        foreach ($requiredArgs as $idx => $reqArg) {
                            if (!isset($providedPositionals[$idx])) {
                                echo "Wizard Mode: Missing required argument '<{$reqArg}>'.\n";
                                echo "Please enter value for {$reqArg}: ";
                                $val = trim(fgets(STDIN));
                                if ($val !== '') {
                                    $inputArgs[] = $val;
                                }
                            }
                        }
                    }
                }

                $cliArgs = ['spp.php'];
                foreach ($inputArgs as $arg) {
                    $cliArgs[] = $arg;
                }
                if ($activeApp !== null) {
                    $cliArgs[] = "--app={$activeApp}";
                }

                if ($isPipe || $isRedirect) {
                    ob_start();
                    try {
                        $commands[$cmdName]->execute($cliArgs);
                    } catch (\Throwable $e) {
                        echo "\n[Command Exception] " . get_class($e) . ": " . $e->getMessage() . "\n";
                    }
                    $output = ob_get_clean();

                    if ($isRedirect) {
                        $flags = $redirectAppend ? FILE_APPEND : 0;
                        file_put_contents($redirectFile, $output, $flags);
                        echo "[Output redirected to {$redirectFile}]\n";
                    } elseif ($isPipe) {
                        $descriptors = [
                            0 => ['pipe', 'r'],
                            1 => ['pipe', 'w'],
                            2 => ['pipe', 'w']
                        ];
                        $proc = proc_open($pipeCmd, $descriptors, $pipes);
                        if (is_resource($proc)) {
                            fwrite($pipes[0], $output);
                            fclose($pipes[0]);
                            echo stream_get_contents($pipes[1]);
                            $err = stream_get_contents($pipes[2]);
                            if ($err !== '') {
                                echo $err;
                            }
                            fclose($pipes[1]);
                            fclose($pipes[2]);
                            proc_close($proc);
                        } else {
                            echo "Error: Failed to open pipe to '{$pipeCmd}'.\n";
                        }
                    }
                } else {
                    try {
                        $commands[$cmdName]->execute($cliArgs);
                    } catch (\Throwable $e) {
                        echo "\n[Command Exception] " . get_class($e) . ": " . $e->getMessage() . "\n";
                        echo "Stack trace:\n" . $e->getTraceAsString() . "\n\n";
                        
                        // Autonomous AI Error Self-Healing
                        if (isset($commands['ai:prompt'])) {
                            echo "\033[33m[Autonomous AI Auto-Healing] Querying @ai for explanation and suggested fix...\033[0m\n";
                            try {
                                $aiArgs = ['spp.php', 'ai:prompt', "Explain this SPP command exception and provide a fix: " . $e->getMessage()];
                                $commands['ai:prompt']->execute($aiArgs);
                            } catch (\Throwable $aiErr) {
                                echo "[AI Fallback Failed] " . $aiErr->getMessage() . "\n";
                            }
                        }
                    }
                }
            } else {
                echo "\n[ERROR] SPP CLI: Command '{$cmdName}' not found.\n";
                echo "Type 'help' for built-ins or 'list' to see available SPP commands.\n\n";
            }
        }
    }
}
