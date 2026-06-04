<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use SPP\SPPConfig;

class EnvGetCommand extends Command
{
    protected string $name = 'env:get';
    protected string $description = 'Get a specific configuration variable';

    public function execute(array $args): void
    {
        $appname = 'default';
        $key = null;
        
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--app=')) {
                $appname = substr($arg, 6);
            } elseif (!str_starts_with($arg, '--')) {
                $key = $arg;
            }
        }

        if (!$key) {
            echo "Error: Missing configuration key.\n";
            echo "Usage: php spp.php env:get <key> [--app=appname]\n";
            return;
        }

        \SPP\Scheduler::withContext($appname, function() use ($key) {
            $value = SPPConfig::get($key);
            
            if ($value === null) {
                echo "Configuration key '{$key}' not found or is null.\n";
                return;
            }

            $valStr = is_scalar($value) ? (string) $value : json_encode($value, JSON_PRETTY_PRINT);
            echo $valStr . "\n";
        });
    }
}
