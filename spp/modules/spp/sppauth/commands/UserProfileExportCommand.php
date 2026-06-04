<?php
namespace SPPMod\SPPAuth\Commands;
use SPP\CLI\Command;
class UserProfileExportCommand extends Command {
    protected string $name = 'userprofile:export';
    protected string $description = 'Export user profile data for compliance/GDPR';
    public function execute(array $args): void {
        $userId = null;
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--user=')) $userId = substr($arg, 7);
        }
        if (!$userId) {
            echo "Usage: php spp.php userprofile:export --user=<user_id>\n";
            return;
        }
        echo "Exporting extended profile data for user {$userId}...\n";
        echo "Export complete (Stub).\n";
    }
}
