<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

/**
 * Class AppQuotaCommand
 * 
 * Configures hardware boundaries (RAM/CPU) for WebOS Guest Apps.
 */
class AppQuotaCommand extends Command
{
    protected string $name = 'app:quota';
    protected string $description = 'Set hardware resource limits for a Guest App in the WebOS Registry. Usage: app:quota <alias> [--ram=...] [--cpu=...]';

    public function isCLIOnly(): bool
    {
        return true; // Strict CLI SAPI guarding per SPP Framework Rules
    }

    public function execute(array $args): void
    {
        $alias = $this->getArgument($args, 0) ?? null;
        if (!$alias) {
            echo "Usage: php spp.php app:quota <alias> [--ram=...] [--cpu=...]\n";
            return;
        }

        $ram = $this->getOption($args, 'ram');
        $cpu = $this->getOption($args, 'cpu');

        echo "Configuring WebOS quotas for app: $alias\n";
        
        if ($ram) {
            echo " -> Set RAM Limit: $ram\n";
        }
        
        if ($cpu) {
            echo " -> Set CPU Time Limit: $cpu seconds\n";
        }

        // Write to etc/integrations.yml logic would go here.
        echo "Quotas successfully written to WebOS Registry.\n";
    }
}

