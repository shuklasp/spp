<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use SPP\SPPConfig;

class EnvSetCommand extends Command
{
    protected string $name = 'env:set';
    protected string $description = 'Set a specific configuration variable';

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $appname = 'default';
        $key = null;
        $value = null;
        
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--app=')) {
                $appname = substr($arg, 6);
            } elseif (!str_starts_with($arg, '--')) {
                if (!$key) {
                    $key = $arg;
                } elseif ($value === null) {
                    $value = $arg;
                }
            }
        }

        if (!$key || $value === null) {
            echo "Error: Missing configuration key or value.\n";
            echo "Usage: php spp.php env:set <key> <value> [--app=appname]\n";
            echo "Key formats: 'app:key', 'global:key', 'sys:key', etc.\n";
            return;
        }

        \SPP\Scheduler::withContext($appname, function() use ($key, $value) {
            try {
                // Determine if value is a boolean or numeric, to preserve type if needed, 
                // but for simplicity CLI arguments are strings.
                if (strtolower($value) === 'true') $value = true;
                elseif (strtolower($value) === 'false') $value = false;
                elseif (strtolower($value) === 'null') $value = null;
                elseif (is_numeric($value)) $value = $value + 0;

                SPPConfig::set($key, $value);
                echo "Successfully set configuration '{$key}'.\n";
            } catch (\Exception $e) {
                echo "Error setting configuration: " . $e->getMessage() . "\n";
            }
        });
    }
}
