<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

/**
 * Class ModuleUpdateCommand
 * Triggers the update hook on a module's ServiceProvider.
 */
class ModuleUpdateCommand extends Command
{
    public function getName(): string
    {
        return 'module:update';
    }

    public function getDescription(): string
    {
        return 'Execute the update hook for a specific module';
    }

    public function execute(array $args): void
    {
        $moduleName = $args[2] ?? null;
        if (!$moduleName) {
            echo "Usage: php spp.php module:update <modulename> [--from=1.0] [--to=1.1]\n";
            return;
        }

        $fromVersion = $this->getOption('from', $args) ?? 'unknown';
        $toVersion = $this->getOption('to', $args) ?? 'latest';

        \SPP\Module::loadAllModules();
        $module = \SPP\Module::getModule($moduleName);

        if (!$module) {
            echo "Error: Module '{$moduleName}' not found or is not active.\n";
            return;
        }

        $provider = $module->ServiceProvider ?? null;

        if (!$provider) {
            echo "Module '{$moduleName}' does not have a ServiceProvider registered. Skipping update.\n";
            return;
        }

        if (method_exists($provider, 'update')) {
            echo "Executing update hook for module '{$moduleName}' (from: {$fromVersion}, to: {$toVersion})...\n";
            try {
                $provider->update($fromVersion, $toVersion);
                echo "Success: Module updated.\n";
            } catch (\Exception $e) {
                echo "Error during module update: " . $e->getMessage() . "\n";
            }
        } else {
            echo "Module '{$moduleName}' ServiceProvider does not implement an update() method. No action taken.\n";
        }
    }
}
