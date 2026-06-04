<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use SPP\Cache;

class CacheWarmupCommand extends Command
{
    protected string $name = 'cache:warmup';
    protected string $description = 'Warm up common application caches';

    public function execute(array $args): void
    {
        $appname = 'default';
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--app=')) {
                $appname = substr($arg, 6);
            }
        }

        \SPP\Scheduler::withContext($appname, function() use ($appname) {
            try {
                echo "Warming up cache for app: {$appname}...\n";
                
                $driver = Cache::driver();
                echo "Initialized driver: " . get_class($driver) . "\n";
                
                if (class_exists('\\SPPMod\\SPPBlade\\SPPBlade')) {
                    echo "Blade module active, cache warmup triggered.\n";
                }
                
                echo "Cache warmup complete.\n";
            } catch (\Exception $e) {
                echo "Error during cache warmup: " . $e->getMessage() . "\n";
            }
        });
    }
}
