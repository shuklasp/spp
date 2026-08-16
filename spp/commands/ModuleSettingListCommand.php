<?php
namespace SPP\CLI\Commands;

use SPP\Module;

class ModuleSettingListCommand extends \SPP\CLI\Command
{
    public function getName(): string
    {
        return 'module:setting:list';
    }

    public function getDescription(): string
    {
        return 'List all settings for a given module';
    }

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $modname = $this->getArgument($args, 0) ?? null;
        if (!$modname) {
            echo "\033[31m[ERROR]\033[0m Module name required. Usage: spp module:setting:list <modname>\n";
            return;
        }

        try {
            $mod = Module::getModule($modname);
            $settingsDef = $mod->getSettingsDefinition();
            
            if (empty($settingsDef)) {
                echo "Module '{$modname}' has no registered settings in its manifest.\n";
                return;
            }

            $rows = [];
            foreach ($settingsDef as $key => $def) {
                $val = Module::getConfig($key, $modname);
                $rows[] = [
                    'Key' => $key,
                    'Type' => $def['type'] ?? 'string',
                    'Current Value' => is_scalar($val) ? (string)$val : json_encode($val),
                    'Default' => is_scalar($def['default'] ?? '') ? (string)($def['default'] ?? '') : json_encode($def['default'] ?? '')
                ];
            }
            
            echo "\nSettings for module: \033[1m{$modname}\033[0m\n";
            printTable(['Key', 'Type', 'Current Value', 'Default'], $rows);

        } catch (\Exception $e) {
            echo "\033[31m[ERROR]\033[0m " . $e->getMessage() . "\n";
        }
    }
}
