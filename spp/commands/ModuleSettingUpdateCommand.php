<?php
namespace SPP\CLI\Commands;

use SPP\Module;

class ModuleSettingUpdateCommand extends \SPP\CLI\Command
{
    public function getName(): string
    {
        return 'module:setting:update';
    }

    public function getDescription(): string
    {
        return 'Update a configuration setting for a specific module';
    }

    public function execute(array $args): void
    {
        $modname = $args[2] ?? null;
        $key = $args[3] ?? null;
        $val = $args[4] ?? null;

        if (!$modname || !$key || $val === null) {
            echo "\033[31m[ERROR]\033[0m Module name, key, and value required. Usage: spp module:setting:update <modname> <key> <value>\n";
            return;
        }

        try {
            // value is cast appropriately by validation schema inside setConfig
            Module::setConfig($key, $val, $modname);
            echo "\033[32m[SUCCESS]\033[0m Updated {$key} to {$val} in module {$modname}\n";
        } catch (\Exception $e) {
            echo "\033[31m[ERROR]\033[0m Validation or Save failed: " . $e->getMessage() . "\n";
        }
    }
}
