<?php
namespace SPP\CLI\Commands;

use SPP\SPPConfig;

class ConfigKeySetCommand extends \SPP\CLI\Command
{
    public function getName(): string
    {
        return 'config:key:set';
    }

    public function getDescription(): string
    {
        return 'Set or update a configuration key';
    }

    public function execute(array $args): void
    {
        // $args[0] = spp.php, $args[1] = config:key:set, $args[2] = key, $args[3] = val
        $key = $args[2] ?? null;
        $val = $args[3] ?? null;
        if (!$key || $val === null) {
            echo "\033[31m[ERROR]\033[0m Key and value required. Usage: spp config:key:set <key> <value>\n";
            return;
        }
        try {
            SPPConfig::set($key, $val);
            echo "\033[32m[SUCCESS]\033[0m Set {$key} to {$val}\n";
        } catch (\Exception $e) {
            echo "\033[31m[ERROR]\033[0m Validation failed: " . $e->getMessage() . "\n";
        }
    }
}
