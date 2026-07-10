#!/usr/bin/env php
<?php
/**
 * SPP CLI Toolkit & Polyglot Engine (Developer Workbench)
 */

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

define('SPP_APP_DIR', dirname(__DIR__, 1));

if ($argc < 2) {
    define('SPP_SKIP_DISCOVERY', true);
    // Bootstrap for discovery
    require_once __DIR__ . '/sppinit.php';
    $commands = \SPP\CLI\CommandManager::discover();
    if (isset($commands['list'])) {
        $commands['list']->execute($argv);
    } else {
        echo "SPP CLI: Use 'php spp.php list' to see available commands.\n";
    }
    exit(1);
}

$command = $argv[1];

// Native fast-paths for performance critical commands (like cron)
// Attempt to dynamically resolve the command rather than hardcoding paths
if ($command === 'serve:async') {
    require_once __DIR__ . '/sppinit.php';
    require_once __DIR__ . '/core/Async/AsyncWorker.php';
    $appName = 'default';
    $port = 8080;
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--app=')) $appName = substr($arg, 6);
        if (str_starts_with($arg, '--port=')) $port = (int)substr($arg, 7);
    }
    \SPP\Core\Async\AsyncWorker::serve($appName, $port);
    exit(0);
}

if ($command === 'cron' || str_ends_with($command, ':cron')) {
    require_once __DIR__ . '/sppinit.php';
    // If it's a specific module cron, try to load it safely
    $parts = explode(':', $command);
    if (count($parts) === 2) {
        $module = preg_replace('/[^a-zA-Z0-9_]/', '', $parts[0]);
        $cronPath = __DIR__ . '/modules/spp/' . $module . '/' . $module . '_cron.php';
        if (file_exists($cronPath)) {
            require_once $cronPath;
            exit(0);
        }
    }
}

// Auto-enable quiet mode for commands that generate output to suppress discovery noise
if (str_starts_with($command, 'xdb:') || str_starts_with($command, 'man') || $command === 'list') {
    define('SPP_SKIP_DISCOVERY', true);
}

// Load Composer autoloader for Yaml support
require_once __DIR__ . '/sppinit.php';

// Load CLI settings safely
$cliSettingsPath = __DIR__ . '/etc/cli-settings.yml';
$cliSettings = [];
if (file_exists($cliSettingsPath)) {
    if (class_exists('\Symfony\Component\Yaml\Yaml')) {
        $cliSettings = \Symfony\Component\Yaml\Yaml::parseFile($cliSettingsPath);
    } else {
        error_log("[SPP CLI] Warning: Symfony YAML component not found. CLI settings may not load correctly.");
    }
}
$cliDefaultApp = $cliSettings['default_app'] ?? 'default';

if ($cliDefaultApp !== 'default' && class_exists('\SPP\App')) {
    try {
        // Instantiating the App automatically registers it with the Scheduler
        new \SPP\App($cliDefaultApp);
        \SPP\Scheduler::setContext($cliDefaultApp);
    } catch (\Exception $e) {
        error_log("[SPP CLI] Warning: Failed to boot default app context '{$cliDefaultApp}'. " . $e->getMessage());
    }
}

// Note: prompt() and printTable() functions have been migrated to SPP\CLI\Console.

/**
 * COMMAND DISCOVERY & EXECUTION (Evolution Phase 3)
 */
$discoveredCommands = \SPP\CLI\CommandManager::discover();

// Execution logic
if (isset($discoveredCommands[$command])) {
    // Parse arguments robustly
    require_once __DIR__ . '/core/class.argparser.php';
    $parsedArgs = \SPP\CLI\ArgParser::parse($argv);
    $explicitApp = $parsedArgs['options']['app'] ?? null;

    if ($explicitApp) {
        $settings = \SPP\App::getGlobalSettings();
        if (isset($settings['apps'][$explicitApp])) {
            try {
                new \SPP\App($explicitApp);
                \SPP\Scheduler::setContext($explicitApp);
                \SPP\Module::loadAllModules();
            } catch (\Exception $e) {
                error_log("[SPP CLI] Warning: Failed to boot explicit app context '{$explicitApp}'. " . $e->getMessage());
            }
        } else {
            error_log("[SPP CLI] Warning: App context '{$explicitApp}' not found in global settings.\n");
        }
    } else if (strpos($command, ':') !== false) {
        // Auto-switch context if command is app-prefixed (e.g. lekhak:setup)
        $parts = explode(':', $command);
        $appContext = $parts[0];
        // Verify if it's a valid app before switching
        $settings = \SPP\App::getGlobalSettings();
        if (isset($settings['apps'][$appContext])) {
            try {
                new \SPP\App($appContext);
                \SPP\Scheduler::setContext($appContext);
                // Re-load modules for the new context
                \SPP\Module::loadAllModules();
            } catch (\Exception $e) {
                error_log("[SPP CLI] Warning: Failed to boot app context '{$appContext}'. " . $e->getMessage());
            }
        }
    }

    $discoveredCommands[$command]->execute($argv);
    exit(0);
}

// If we reach here, the command was not found
echo "\n[ERROR] SPP CLI: Command '{$command}' not found.\n";
echo "Use 'php spp.php list' to see available commands.\n";
exit(1);
