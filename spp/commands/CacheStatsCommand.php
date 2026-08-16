<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use SPP\Cache;

class CacheStatsCommand extends Command
{
    protected string $name = 'cache:stats';
    protected string $description = 'Display cache driver statistics';

    
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

        \SPP\Scheduler::withContext($appname, function() {
            try {
                $driver = Cache::driver();
                $driverClass = get_class($driver);
                echo "Active Cache Driver: {$driverClass}\n";
                
                echo "--- L1 Memory Cache & Telemetry ---\n";
                print_r(Cache::stats());
                
            } catch (\Exception $e) {
                echo "Error fetching cache stats: " . $e->getMessage() . "\n";
            }
        });
    }
}
