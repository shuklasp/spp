<?php
namespace SPPMod\Marketing\Commands;
use SPP\CLI\Command;
class MarketingCampaignSyncCommand extends Command {
    protected string $name = 'marketing:campaign:sync';
    protected string $description = 'Synchronize marketing campaigns/templates with external CRMs';
        public function isCLIOnly(): bool
    {
        return true;
    }

public function execute(array $args): void {
        echo "Synchronizing marketing campaigns...\n";
        if (class_exists('\\SPPMod\\Marketing\\Marketing')) {
            echo "Marketing module loaded. Synchronizing stub data...\n";
            echo "Success.\n";
        } else {
            echo "Marketing module is not active.\n";
        }
    }
}
