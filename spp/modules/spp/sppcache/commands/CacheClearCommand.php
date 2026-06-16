<?php
namespace SPPMod\SppCache\Commands;

use SPP\CLI\Command;
use SPP\Cache;

class CacheClearCommand extends Command {
    protected string $signature = 'cache:clear';
    protected string $description = 'Clear the entire SPP Cache directory';

    public function execute(array $args): void {
        echo "Clearing application cache...\n";
        $cache = \SPP\Cache::getInstance();
        $cache->flush();
        echo "Cache cleared successfully.\n";
    }
}
