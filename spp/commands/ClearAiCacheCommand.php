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
        if (in_array('--help', $args, true) || in_array('-h', $args, true)) {
            echo "Usage: php spp.php clear:aicache\n";
            echo "Clears the WebOS AI Decision cache.\n";
            return;
        }

        $this->info("Clearing AI Decision Cache...");

        if (!class_exists(\SPPMod\SPPSwarm\SwarmHub::class)) {
            $path = SPP_APP_DIR . '/spp/modules/optional/sppswarm/class.swarmhub.php';
            if (file_exists($path)) {
                require_once $path;
            }
        }

        if (class_exists(\SPPMod\SPPSwarm\SwarmHub::class)) {
            SwarmHub::clearAiCache();
            $this->info("Cache cleared successfully. The Swarm will now request fresh AI insights.");
        } else {
            $this->info("SwarmHub module is not active; AI decision cache is empty.");
        }
    }
}
