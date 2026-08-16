<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use SPP\Cache;

class CachePruneCommand extends Command
{
    protected string $name = 'cache:prune';
    protected string $description = 'Prune expired cache items from storage';

    
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

        \SPP\Scheduler::withContext($appname, function() use ($appname) {
            try {
                echo "Pruning expired cache items for app: {$appname}...\n";
                $start = microtime(true);
                Cache::prune();
                $elapsed = round((microtime(true) - $start) * 1000, 2);
                echo "Cache pruning complete in {$elapsed} ms.\n";
            } catch (\Exception $e) {
                echo "Error during cache pruning: " . $e->getMessage() . "\n";
            }
        });
    }
}
