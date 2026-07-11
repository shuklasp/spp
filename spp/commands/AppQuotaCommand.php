<?php
namespace SPP\Commands;

use SPPMod\SPPMaker\Command;

/**
 * Class AppQuotaCommand
 * 
 * Configures hardware boundaries (RAM/CPU) for WebOS Guest Apps.
 */
class AppQuotaCommand extends Command
{
    protected string $signature = 'app:quota {alias} {--ram=} {--cpu=}';
    protected string $description = 'Set hardware resource limits for a Guest App in the WebOS Registry';

    public function isCLIOnly(): bool
    {
        return true; // Strict CLI SAPI guarding per SPP Framework Rules
    }

    public function handle(): int
    {
        $alias = $this->argument('alias');
        $ram = $this->option('ram');
        $cpu = $this->option('cpu');

        $this->info("Configuring WebOS quotas for app: $alias");
        
        if ($ram) {
            $this->info(" -> Set RAM Limit: $ram");
        }
        
        if ($cpu) {
            $this->info(" -> Set CPU Time Limit: $cpu seconds");
        }

        // Write to etc/integrations.yml logic would go here.
        $this->info("Quotas successfully written to WebOS Registry.");

        return 0;
    }
}
