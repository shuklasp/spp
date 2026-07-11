<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use SPPMod\SPPSwarm\SwarmHub;

/**
 * Class ClearAiCacheCommand
 * 
 * Clears the WebOS AI Decision cache.
 */
class ClearAiCacheCommand extends Command
{
    protected string $name = 'clear:aicache';
    protected string $description = 'Clears the WebOS AI Decision cache.';

    public function isCLIOnly(): bool { return true; }

    public function execute(array $args): void
    {
        $this->info("Clearing AI Decision Cache...");
        SwarmHub::clearAiCache();
        $this->info("Cache cleared successfully. The Swarm will now request fresh AI insights.");
    }
}
