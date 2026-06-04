<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use SPP\Cache;

class CacheStatsCommand extends Command
{
    protected string $name = 'cache:stats';
    protected string $description = 'Display cache driver statistics';

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
                
                if (method_exists($driver, 'stats')) {
                    print_r($driver->stats());
                } else {
                    echo "Driver does not support detailed stats.\n";
                }
            } catch (\Exception $e) {
                echo "Error fetching cache stats: " . $e->getMessage() . "\n";
            }
        });
    }
}
