<?php
namespace SPPMod\SPPDeploy\Commands;

use SPP\CLI\Command;

class DeployPullCommand extends Command
{
    public function execute(array $args): void
    {
        $target = $args[2] ?? null;
        if (!$target || str_starts_with($target, '--') || str_starts_with($target, '-')) {
            $target = \SPPMod\SPPDeploy\Deployer\TargetConnection::getDefaultEnvironment();
        }

        $apiKey = getenv('SPP_DEPLOY_TOKEN') ?: 'default_cli_key';
        $force = false;

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--key=')) {
                $apiKey = substr($arg, 6);
            }
            if ($arg === '--force' || $arg === '-y') {
                $force = true;
            }
        }

        $conn = \SPPMod\SPPDeploy\Deployer\TargetConnection::resolve($target, $apiKey);

        if (!$force) {
            echo "\n⚠️  CRITICAL WARNING: PULL IS DESTRUCTIVE.\n";
            echo "This will overwrite your local workspace and database with the remote state from {$target}.\n";
            echo "❓ Proceed with pull? [Y/n] ";
            $handle = fopen("php://stdin", "r");
            $line = trim(fgets($handle));
            fclose($handle);
            if (strtolower($line) === 'n' || strtolower($line) === 'no') {
                echo "⛔ Pull aborted.\n";
                return;
            }
        }

        echo "📡 Fetching remote snapshot...\n";
        $resp = $conn->getExport();

        if (!isset($resp['status']) || $resp['status'] !== 'ok' || empty($resp['archive'])) {
            echo "❌ Pull failed: " . ($resp['message'] ?? 'Unknown error') . "\n";
            if (isset($resp['debug']))
                echo "DEBUG: " . $resp['debug'] . "\n";
            return;
        }

        echo "📦 Extracting payload...\n";
        $archiveData = base64_decode($resp['archive']);
        $tempArchive = SPP_BASE_DIR . '/var/cache/deploy_pull.zip';
        if (!is_dir(dirname($tempArchive))) {
            mkdir(dirname($tempArchive), 0777, true);
        }
        file_put_contents($tempArchive, $archiveData);

        try {
            $zip = new \ZipArchive();
            if ($zip->open($tempArchive) === true) {
                $zip->extractTo(dirname(SPP_BASE_DIR));
                $zip->close();
            } else {
                throw new \Exception("Failed to open downloaded payload.");
            }

            $sqlSnapshot = SPP_BASE_DIR . '/db_snapshot.sql';
            if (is_file($sqlSnapshot)) {
                echo "💾 Restoring database...\n";
                if (class_exists('\\SPPMod\\SPPDB\\SPPDB')) {
                    $db = new \SPPMod\SPPDB\SPPDB();
                    $pdo = $db->getPDO();
                    if ($pdo) {
                        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
                        if ($driver === 'mysql') {
                            $pdo->exec("SET FOREIGN_KEY_CHECKS=0;");
                        }
                        $sql = file_get_contents($sqlSnapshot);
                        $pdo->exec($sql);
                        if ($driver === 'mysql') {
                            $pdo->exec("SET FOREIGN_KEY_CHECKS=1;");
                        }
                    }
                }
                unlink($sqlSnapshot);
            }

            echo "✅ Pull successful.\n";
        } catch (\Exception $e) {
            echo "❌ Pull failed during extraction: " . $e->getMessage() . "\n";
        } finally {
            if (is_file($tempArchive))
                unlink($tempArchive);
        }
    }

    public function getName(): string
    {
        return 'deploy:pull';
    }
}
