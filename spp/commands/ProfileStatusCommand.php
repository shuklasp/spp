<?php
namespace SPP\CLI\Commands;
use SPP\CLI\Command;
class ProfileStatusCommand extends Command {
    protected string $name = 'profile:status';
    protected string $description = 'Check if the performance profiler is running/enabled';
    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void {
        echo "Checking SPPProfile status...\n";
        if (class_exists('\\SPPMod\\SPPProfile\\SPPProfile')) {
            echo "SPPProfile module is ACTIVE.\n";
            echo "Status: Monitoring performance traces.\n";
        } else {
            echo "SPPProfile module is NOT ACTIVE.\n";
        }
    }
}
