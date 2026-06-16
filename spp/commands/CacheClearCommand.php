<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use SPP\Cache;

class CacheClearCommand extends Command
{
    protected string $name = 'cache:clear';
    protected string $description = 'Clear the application file/redis cache';

    public function execute(array $args): void
    {
        $appname = $this->getOption($args, 'app', 'default');

        \SPP\Scheduler::withContext($appname, function() use ($appname) {
            try {
                if (Cache::clear()) {
                    echo "Cache cleared successfully for app: {$appname}\n";
                } else {
                    echo "Failed to clear cache.\n";
                }
            } catch (\Exception $e) {
                echo "Error clearing cache: " . $e->getMessage() . "\n";
            }
        });
    }
}
