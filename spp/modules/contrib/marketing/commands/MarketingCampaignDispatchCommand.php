<?php
namespace SPPMod\Marketing\Commands;
use SPP\CLI\Command;
class MarketingCampaignDispatchCommand extends Command {
    protected string $name = 'marketing:campaign:dispatch';
    protected string $description = 'Dispatch a marketing campaign manually';
    public function execute(array $args): void {
        $campaignId = null;
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--id=')) $campaignId = substr($arg, 5);
        }
        if (!$campaignId) {
            echo "Usage: php spp.php marketing:campaign:dispatch --id=<campaign_id>\n";
            return;
        }
        echo "Dispatching campaign {$campaignId}...\n";
        if (class_exists('\\SPPMod\\Marketing\\Marketing')) {
            echo "Success (Stub).\n";
        } else {
            echo "Marketing module is not active.\n";
        }
    }
}
