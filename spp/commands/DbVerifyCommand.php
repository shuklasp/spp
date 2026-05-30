<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class DbVerifyCommand extends Command
{
    public function execute(array $args): void
    {
        echo "🗄️ Initializing SPP XDB MySQL Compatibility Verification Suite...\n";
        $testScript = SPP_APP_DIR . '/spp/modules/spp/sppxdb/test_mysql_compatibility.php';
        if (file_exists($testScript)) {
            passthru("php " . escapeshellarg($testScript));
        } else {
            echo "  ❌ Verification script not found at {$testScript}\n";
        }
    }

    public function getName(): string
    {
        return 'db:verify';
    }

    public function getDescription(): string
    {
        return 'Runs the SPP XDB MySQL Compatibility Verification Suite';
    }
}
