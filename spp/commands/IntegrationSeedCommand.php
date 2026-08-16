<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use SPPMod\SPPIntegrations\IntegrationFactory;
use SPPMod\SPPInterDB\DBAdapter;

/**
 * Class IntegrationSeedCommand
 * 
 * Automates the mass migration and synchronization of the historical SPP user base
 * into a newly attached external application (e.g., seeding Magento with all SPP users).
 */
class IntegrationSeedCommand extends Command
{
    protected string $name = 'integration:seed';
    protected string $description = 'Bulk seed local SPP users into a specific integration target';

    public function isCLIOnly(): bool 
    { 
        return true; 
    }

    public function execute(array $args): void
    {
        if (empty($this->getArgument($args, 0))) {
            echo "Usage: php spp.php integration:seed <app_name>\n";
            echo "Example: php spp.php integration:seed magento\n";
            return;
        }

        $targetApp = strtolower($this->getArgument($args, 0));

        try {
            $driver = IntegrationFactory::getDriver($targetApp);
            echo "Initializing Data Seeding for Target: {$targetApp}\n";

            // Mocking SPP DB fetch for local users table
            // $db = new DBAdapter('default');
            // $users = $db->query("SELECT id, username, email, first_name as firstname, last_name as lastname FROM spp_users");
            
            // Mock dataset for demonstration
            $users = [
                ['id' => 1, 'username' => 'admin', 'email' => 'admin@spp.local', 'firstname' => 'System', 'lastname' => 'Admin'],
                ['id' => 2, 'username' => 'testuser', 'email' => 'test@spp.local', 'firstname' => 'Test', 'lastname' => 'User']
            ];

            $successCount = 0;
            $failCount = 0;

            echo "Found " . count($users) . " users to sync. Beginning bulk operation...\n";

            foreach ($users as $user) {
                // If DAG Orchestrator is active, we could dispatch jobs here for mass parallelization!
                // For direct seeding feedback, we run synchronously in this command.
                $result = $driver->syncUser($user);
                
                if ($result) {
                    $successCount++;
                    echo " - Synced: {$user['username']} ({$user['email']})\n";
                } else {
                    $failCount++;
                    echo " - FAILED: {$user['username']} ({$user['email']})\n";
                }
            }

            echo "\nSeeding Complete for {$targetApp}.\n";
            echo "Success: {$successCount} | Failed: {$failCount}\n";

        } catch (\Exception $e) {
            echo "[ERROR] Seeding failed: " . $e->getMessage() . "\n";
        }
    }
}
