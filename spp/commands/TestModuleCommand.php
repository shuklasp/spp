<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

/**
 * Class TestModuleCommand
 * Executes PHPUnit test suite for a specific module.
 */
class TestModuleCommand extends Command
{
    public function getName(): string
    {
        return 'test:module';
    }

    public function getDescription(): string
    {
        return 'Run PHPUnit tests for an isolated module';
    }

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $moduleName = $this->getArgument($args, 0) ?? null;
        if (!$moduleName) {
            echo "Usage: php spp.php test:module <modulename>\n";
            return;
        }

        \SPP\Module::loadAllModules();
        $module = \SPP\Module::getModule($moduleName);

        if (!$module) {
            echo "Error: Module '{$moduleName}' not found or is not active.\n";
            return;
        }

        $testDir = $module->ModPath . '/tests';

        if (!is_dir($testDir)) {
            echo "Error: Test directory not found in module '{$moduleName}' ({$testDir})\n";
            return;
        }

        // Locate phpunit executable
        $phpunitBin = SPP_BASE_DIR . '/vendor/bin/phpunit';
        if (!file_exists($phpunitBin)) {
            // Check Windows extension
            $phpunitBin .= '.bat';
            if (!file_exists($phpunitBin)) {
                echo "Error: PHPUnit not found at vendor/bin/phpunit.\n";
                echo "Please run `composer require --dev phpunit/phpunit`.\n";
                return;
            }
        }

        echo "Running tests for module '{$moduleName}'...\n\n";

        $command = escapeshellcmd($phpunitBin) . ' ' . escapeshellarg($testDir);
        
        // Execute and pass output directly to STDOUT
        passthru($command, $exitCode);

        if ($exitCode === 0) {
            echo "\nSuccess: All tests passed for '{$moduleName}'.\n";
        } else {
            echo "\nError: Tests failed for '{$moduleName}' (Exit Code: {$exitCode}).\n";
        }
    }
}
