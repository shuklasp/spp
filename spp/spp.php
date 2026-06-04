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

// Auto-enable quiet mode for commands that generate output to suppress discovery noise
if (str_starts_with($command, 'xdb:') || str_starts_with($command, 'man') || $command === 'list') {
    define('SPP_SKIP_DISCOVERY', true);
}

// Load Composer autoloader for Yaml support
require_once __DIR__ . '/sppinit.php';

// Load CLI settings
$cliSettingsPath = __DIR__ . '/etc/cli-settings.yml';
$cliSettings = file_exists($cliSettingsPath) 
    ? \Symfony\Component\Yaml\Yaml::parseFile($cliSettingsPath) 
    : [];
$cliDefaultApp = $cliSettings['default_app'] ?? 'default';

if ($cliDefaultApp !== 'default' && class_exists('\SPP\App')) {
    try {
        // Instantiating the App automatically registers it with the Scheduler
        new \SPP\App($cliDefaultApp);
        \SPP\Scheduler::setContext($cliDefaultApp);
    } catch (\Exception $e) {
        // Fallback silently if the app doesn't exist or loading fails
    }
}

// Function to read interactive input
function prompt($text, $default = '') {
    $extra = ($default !== '') ? " [{$default}]" : "";
    echo $text . $extra . ": ";
    $input = trim(fgets(STDIN));
    return ($input === '') ? $default : $input;
}

// Basic Table Formatter for CLI
function printTable($headers, $rows) {
    if (empty($rows)) {
        echo "(Empty set)\n";
        return;
    }
    $widths = array();
    foreach ($headers as $i => $h) $widths[$i] = strlen($h);
    foreach ($rows as $row) {
        $rValues = array_values($row);
        foreach ($rValues as $i => $v) {
            $widths[$i] = max($widths[$i] ?? 0, strlen((string)$v));
        }
    }

    $line = "+";
    foreach ($widths as $w) $line .= str_repeat("-", $w + 2) . "+";
    echo $line . "\n";

    echo "|";
    foreach ($headers as $i => $h) echo " " . str_pad($h, $widths[$i]) . " |";
    echo "\n" . $line . "\n";

    foreach ($rows as $row) {
        echo "|";
        $rValues = array_values($row);
        foreach ($rValues as $i => $v) echo " " . str_pad((string)substr($v, 0, 50), $widths[$i]) . " |";
        echo "\n";
    }
    echo $line . "\n";
}

/**
 * COMMAND DISCOVERY & EXECUTION (Evolution Phase 3)
 */
$discoveredCommands = \SPP\CLI\CommandManager::discover();

// Execution logic
if (isset($discoveredCommands[$command])) {
    try {
        // Look for --app= explicit flag
        $explicitApp = null;
        foreach ($argv as $arg) {
            if (str_starts_with($arg, '--app=')) {
                $explicitApp = substr($arg, 6);
            }
        }

        if ($explicitApp) {
            $settings = \SPP\App::getGlobalSettings();
            if (isset($settings['apps'][$explicitApp])) {
                try {
                    new \SPP\App($explicitApp);
                    \SPP\Scheduler::setContext($explicitApp);
                    \SPP\Module::loadAllModules();
                } catch (\Exception $e) {
                    echo "[WARNING] Failed to load explicit app context: " . $explicitApp . "\n";
                }
            } else {
                echo "[WARNING] App context '{$explicitApp}' not found in global settings.\n";
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
                    // Fallback silently
                }
            }
        }

        $discoveredCommands[$command]->execute($argv);
        exit(0);
    } catch (\Exception $e) {
        // Look for debug toggle
        $isDebug = \SPP\App::getGlobalSettings('framework.debug') === true;
        echo "\n\033[31m\033[1m[ERROR]\033[0m " . $e->getMessage() . "\n";
        
        if ($isDebug) {
            echo "\033[33m[TRACE]\033[0m in " . $e->getFile() . " on line " . $e->getLine() . "\n";
            echo $e->getTraceAsString() . "\n";
        }
        exit(1);
    }
}


