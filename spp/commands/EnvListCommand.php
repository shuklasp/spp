<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use SPP\SPPConfig;

class EnvListCommand extends Command
{
    protected string $name = 'env:list';
    protected string $description = 'List all environment and configuration variables for an app context';

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $appname = 'default';
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--app=')) {
                $appname = substr($arg, 6);
            }
        }

        echo "Environment Config for app: {$appname}\n";
        echo str_repeat("=", 80) . "\n\n";

        \SPP\Scheduler::withContext($appname, function() use ($appname) {
            // Force compile to ensure we have all values
            SPPConfig::compile($appname);

            // Reflection to call the private getCompiledPath method
            $reflector = new \ReflectionClass(SPPConfig::class);
            $method = $reflector->getMethod('getCompiledPath');
            $method->setAccessible(true);
            $cacheFile = $method->invoke(null, $appname);

            if (!file_exists($cacheFile)) {
                echo "Failed to compile configuration.\n";
                return;
            }

            $config = require $cacheFile;

            if (empty($config)) {
                echo "No configuration found.\n";
                return;
            }

            echo str_pad("Key", 45) . "Value\n";
            echo str_repeat("-", 80) . "\n";

            ksort($config);
            foreach ($config as $key => $value) {
                $valStr = is_scalar($value) ? (string) $value : json_encode($value);
                echo str_pad($key, 45) . substr($valStr, 0, 35) . "\n";
            }
            echo "\n";
        });
    }
}
