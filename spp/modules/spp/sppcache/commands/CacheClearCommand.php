<?php
namespace SPPMod\SppCache\Commands;

use SPP\Core\Command;
use SPP\Cache;

class CacheClearCommand extends Command {
    protected $signature = 'cache:clear';
    protected $description = 'Flush the application cache.';

    public function handle() {
        $this->info("Clearing application cache...");
        if (Cache::clear()) {
            $this->info("Cache cleared successfully.");
            return 0;
        } else {
            $this->error("Failed to clear cache.");
            return 1;
        }
    }
}
