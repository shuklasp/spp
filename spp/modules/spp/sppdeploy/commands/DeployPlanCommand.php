<?php
namespace SPPMod\Sppdeploy\Commands;

use SPP\CLI\Command;

class DeployPlanCommand extends Command
{
    public function execute(array $args): void
    {
        $target = $args[2] ?? null;
        if (!$target) {
            echo "Error: Target connection URI required.\n";
            echo "Usage: php spp.php deploy:plan <target_uri> [--key=YOUR_API_KEY] [--no-db]\n";
            return;
        }

        $apiKey = 'default_cli_key';
        $noDb = false;

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--key=')) {
                $apiKey = substr($arg, 6);
            }
            if ($arg === '--no-db') {
                $noDb = true;
            }
        }

        $conn = \SPPMod\Sppdeploy\Deployer\TargetConnection::resolve($target, $apiKey);

        echo "🔍 Scanning local application state...\n";
        $fileScanner = new \SPPMod\Sppdeploy\Scanner\FileScanner(SPP_BASE_DIR);
        $localHashes = $fileScanner->scan();

        $localDbHashes = [];
        if (!$noDb) {
            $dbScanner = new \SPPMod\Sppdeploy\Scanner\DbScanner();
            $localDbHashes = $dbScanner->scan();
        }

        echo "📡 Fetching remote diff from {$target}...\n";
        $diffResp = $conn->getDiff(['files' => $localHashes, 'db' => $localDbHashes]);

        if (!isset($diffResp['status']) || $diffResp['status'] !== 'ok') {
            echo "❌ Error computing diff: " . ($diffResp['message'] ?? 'Unknown error') . "\n";
            return;
        }

        $diff = $diffResp['diff'];
        $fileCount = count($diff['files']['create']) + count($diff['files']['update']) + count($diff['files']['delete']);
        $dbCount = count($diff['db']['create']) + count($diff['db']['update']) + count($diff['db']['delete']);

        if ($fileCount === 0 && $dbCount === 0) {
            echo "✅ Everything is up to date. No deployment necessary.\n";
            return;
        }

        echo "\n==================== PRE-FLIGHT PLAN ====================\n";
        echo "FILES:\n";
        echo "  Created : " . count($diff['files']['create']) . "\n";
        echo "  Updated : " . count($diff['files']['update']) . "\n";
        echo "  Deleted : " . count($diff['files']['delete']) . "\n";
        if (count($diff['files']['delete']) > 0) {
            echo "  Files to be removed: \n    - " . implode("\n    - ", $diff['files']['delete']) . "\n";
        }

        echo "\nDATABASE:\n";
        echo "  Created : " . count($diff['db']['create']) . "\n";
        echo "  Updated : " . count($diff['db']['update']) . "\n";
        echo "  Deleted : " . count($diff['db']['delete']) . "\n";

        if ($dbCount > 0) {
            echo "\n⚠️  PROPOSED SQL STATEMENTS (To be executed on target):\n";
            echo str_repeat("-", 50) . "\n";

            if (class_exists('\\SPPMod\\SPPDB\\SPPDB')) {
                $db = new \SPPMod\SPPDB\SPPDB();
                $pdo = $db->getPDO();
                if ($pdo) {
                    $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
                    foreach (['create', 'update'] as $action) {
                        foreach ($diff['db'][$action] as $table) {
                            if ($driver === 'sqlite') {
                                $stmt = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='{$table}'")->fetch(\PDO::FETCH_ASSOC);
                                if ($stmt && isset($stmt['sql'])) {
                                    echo "DROP TABLE IF EXISTS `{$table}`;\n";
                                    echo $stmt['sql'] . ";\n\n";
                                }
                            } elseif ($driver === 'mysql') {
                                $stmt = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(\PDO::FETCH_ASSOC);
                                if ($stmt && isset($stmt['Create Table'])) {
                                    echo "DROP TABLE IF EXISTS `{$table}`;\n";
                                    echo $stmt['Create Table'] . ";\n\n";
                                }
                            }
                        }
                    }
                    foreach ($diff['db']['delete'] as $table) {
                        echo "DROP TABLE IF EXISTS `{$table}`;\n\n";
                    }
                }
            }
            echo str_repeat("-", 50) . "\n";
        }

        echo "\n💡 This is a dry run. Nothing has been deployed.\n";
    }

    public function getName(): string
    {
        return 'deploy:plan';
    }

    public function getDescription(): string
    {
        return 'Perform a dry run to view file changes and raw database SQL diffs before deploying';
    }
}
