<?php
namespace SPPMod\SPPCache\Commands;

use SPP\CLI\Command;
use SPP\Cache;

class CacheClearCommand extends Command
{
    protected string $name = 'cache:clear';
    protected string $description = 'Clear the entire SPP Cache directory';

    public function execute(array $args): void
    {
        echo "Clearing application cache...\n";
        \SPP\Cache::clear();
        echo "Cache cleared successfully.\n";
    }
}
