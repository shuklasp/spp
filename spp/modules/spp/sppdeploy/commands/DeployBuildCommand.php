<?php
namespace SPPMod\SPPDeploy\Commands;

use SPP\CLI\Command;

class DeployBuildCommand extends Command
{
    public function execute(array $args): void
    {
        $target = $args[2] ?? null;
        if (!$target) {
            echo "Error: Target connection URI required to calculate diff.\n";
            echo "Usage: php spp.php deploy:build <target_uri> [--key=YOUR_API_KEY] [--no-db] [--no-files]\n";
            return;
        }

        $apiKey = 'default_cli_key';
        $noDb = false;
        $noFiles = false;

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--key=')) {
                $apiKey = substr($arg, 6);
            }
            if ($arg === '--no-db') {
                $noDb = true;
            }
            if ($arg === '--no-files') {
                $noFiles = true;
            }
        }

        $conn = \SPPMod\SPPDeploy\Deployer\TargetConnection::resolve($target, $apiKey);
        $localHashes = [];
        if (!$noFiles) {
            $scanner = new \SPPMod\SPPDeploy\Scanner\ProjectScanner();
            $localHashes = $scanner->scan(SPP_BASE_DIR);
        }

        $localDbHashes = [];
        if (!$noDb) {
            $dbScanner = new \SPPMod\SPPDeploy\Scanner\DbScanner();
            $localDbHashes = $dbScanner->scan();
        }

        try {
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
                echo "✅ Everything is up to date. Nothing to build.\n";
                return;
            }

            echo "\n📦 Preparing deployment artifact...\n";

            $buildId = 'build_' . date('Ymd_His');
            $buildDir = SPP_BASE_DIR . '/var/builds';
            if (!is_dir($buildDir))
                mkdir($buildDir, 0777, true);

            $tempZip = $buildDir . '/' . $buildId . '.zip';
            $manifestPath = $buildDir . '/' . $buildId . '.json';

            $zip = new \ZipArchive();
            if ($zip->open($tempZip, \ZipArchive::CREATE) !== true) {
                throw new \Exception("Cannot create temporary zip archive.");
            }

            foreach (['create', 'update'] as $action) {
                foreach ($diff['files'][$action] as $file) {
                    $zip->addFile(SPP_BASE_DIR . '/' . $file, $file);
                }
            }

            $sqlBuffer = "";
            if ($dbCount > 0) {
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
                                        $sqlBuffer .= "DROP TABLE IF EXISTS `{$table}`;\n";
                                        $sqlBuffer .= $stmt['sql'] . ";\n";
                                    }
                                } elseif ($driver === 'mysql') {
                                    $stmt = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(\PDO::FETCH_ASSOC);
                                    if ($stmt && isset($stmt['Create Table'])) {
                                        $sqlBuffer .= "DROP TABLE IF EXISTS `{$table}`;\n";
                                        $sqlBuffer .= $stmt['Create Table'] . ";\n";
                                    }
                                }
                            }
                        }
                    }
                }
            }

            if ($sqlBuffer !== "") {
                $zip->addFromString('db_snapshot.sql', $sqlBuffer);
            }

            $zip->close();

            $payloadMeta = [
                'filesCount' => $fileCount,
                'dbCount' => $dbCount,
                'diff' => $diff
            ];
            file_put_contents($manifestPath, json_encode($payloadMeta, JSON_PRETTY_PRINT));

            echo "✅ Artifact Built Successfully!\n";
            echo "   Artifact: {$tempZip}\n";
            echo "   Manifest: {$manifestPath}\n";
            echo "\nTo deploy this artifact later, run:\n";
            echo "   php spp.php deploy:push {$target} --artifact=var/builds/{$buildId}.zip\n";

        } catch (\Exception $e) {
            echo "❌ Fatal Error: " . $e->getMessage() . "\n";
        }
    }

    public function getName(): string
    {
        return 'deploy:build';
    }

    public function getDescription(): string
    {
        return 'Create a local deployment artifact bundle without pushing';
    }
}
