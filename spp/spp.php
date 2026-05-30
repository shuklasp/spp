#!/usr/bin/env php
<?php
/**
 * SPP CLI Toolkit (Developer Workbench)
 */

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

define('SPP_APP_DIR', dirname(__DIR__, 1));

if ($argc < 2) {
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

// Auto-enable quiet mode for XDB commands to suppress discovery noise
if (str_starts_with($command, 'xdb:')) {
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
        // Auto-switch context if command is app-prefixed (e.g. lekhak:setup)
        if (strpos($command, ':') !== false) {
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
        echo "[UNCAUGHT EXCEPTION] " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "\n";
        exit(1);
    }
}


