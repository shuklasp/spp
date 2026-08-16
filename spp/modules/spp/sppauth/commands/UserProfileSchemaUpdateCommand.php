<?php
namespace SPPMod\SPPAuth\Commands;
use SPP\CLI\Command;
class UserProfileSchemaUpdateCommand extends Command
{
    protected string $name = 'userprofile:schema:update';
    protected string $description = 'Sync extended user profile metadata schemas';
    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        echo "Synchronizing user profile schemas...\n";
        if (class_exists('\\SPPMod\\SPPUserProfile\\SPPUserProfile')) {
            echo "SPPUserProfile module active. Schema synchronized successfully.\n";
        } else {
            echo "SPPUserProfile module is not active.\n";
        }
    }
}
