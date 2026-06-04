<?php
namespace SPPMod\SPPMigrate\Commands;

use SPP\CLI\Command;

class MigrateDeployCommand extends Command
{
    public function execute(array $args): void
    {
        echo "🚀 Initializing SPPMigrate Deployment Protocol...\n";
        $target = $args[2] ?? null;
        if (!$target) {
            echo "Error: Target connection URI required.\n";
            echo "Usage: php spp.php migrate:deploy <target_uri> [--full] [--key=YOUR_API_KEY]\n";
            return;
        }
        
        $apiKey = 'default_cli_key'; // Can be parsed from args
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--key=')) {
                $apiKey = substr($arg, 6);
            }
        }
        
        $isFull = in_array('--full', $args);
        $mode = $isFull ? 'full' : 'incremental';
        
        echo "📡 Target: {$target}\n";
        echo "🔄 Mode: " . ($isFull ? "Full State Mirror (Destructive)" : "Incremental Delta Sync") . "\n";
        
        $conn = new \SPPMod\SPPMigrate\Deployer\TargetConnection($target, $apiKey);
        
        try {
            echo "✅ Initiating handshake with {$target}...\n";
            $conn->ping();
            
            echo "📦 Analyzing local delta map...\n";
            $scanner = new \SPPMod\SPPMigrate\Scanner\ProjectScanner();
            $fileHashes = $scanner->scan(SPP_BASE_DIR);
            
            $dbScanner = new \SPPMod\SPPMigrate\Scanner\DbScanner();
            $dbHashes = $dbScanner->scan();

            $localHashes = [
                'files' => $fileHashes,
                'db' => $dbHashes
            ];

            $resp = $conn->getDiff($localHashes);
            if ($resp['status'] !== 'ok') {
                echo "❌ Error getting diff: " . ($resp['message'] ?? 'Unknown') . "\n";
                return;
            }
            
            $diff = $resp['diff'];
            $total = count($diff['files']['create']) + count($diff['files']['update']) + count($diff['files']['delete']);
            echo "📊 Found {$total} file changes.\n";
            
            if ($total === 0) {
                echo "✅ Target is fully up to date!\n";
                return;
            }
            
            $delCount = count($diff['files']['delete']);
            if ($isFull || $delCount > 50) {
                echo "\n⚠️  CRITICAL WARNING: Full State Mirror will overwrite remote structures.\n";
                echo "Files on the remote server that do not exist locally will be PERMANENTLY DELETED.\n";
                echo "Type 'CONFIRM' to proceed: ";
                $confirm = trim(fgets(STDIN));
                if ($confirm !== 'CONFIRM') {
                    echo "Deployment aborted.\n";
                    return;
                }
            }
            
            echo "⚙️  Packaging payload...\n";
            $payload = ['files' => $diff['files'], 'db' => $diff['db'], 'mode' => $mode];
            $filesToProcess = array_merge($diff['files']['create'], $diff['files']['update']);
            
            if (count($filesToProcess) <= 10) {
                $filesContent = [];
                foreach ($filesToProcess as $path) {
                    $fullPath = SPP_BASE_DIR . '/' . $path;
                    if (is_file($fullPath)) {
                        $filesContent[$path] = base64_encode(file_get_contents($fullPath));
                    }
                }
                $payload['files_content'] = $filesContent;
            } else {
                $zipFile = sys_get_temp_dir() . '/sppmigrate_' . time() . '.zip';
                $zip = new \ZipArchive();
                if ($zip->open($zipFile, \ZipArchive::CREATE) === true) {
                    foreach ($filesToProcess as $path) {
                        $fullPath = SPP_BASE_DIR . '/' . $path;
                        if (is_file($fullPath)) {
                            $zip->addFile($fullPath, $path);
                        }
                    }
                    $zip->close();
                    $payload['files_zip'] = base64_encode(file_get_contents($zipFile));
                    unlink($zipFile);
                }
            }
            
            // DB Schema extraction
            $dbSchemas = [];
            $tablesToProcess = array_merge($diff['db']['create'] ?? [], $diff['db']['update'] ?? []);
            $db = new \SPPMod\SPPDB\SPPDB();
            $pdo = $db->getPDO();
            $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

            foreach ($tablesToProcess as $table) {
                $actualTable = \SPPMod\SPPDB\SPPDB::sppTable($table);
                if ($driver === 'sqlite') {
                    $stmt = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='{$actualTable}'");
                    $row = $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : null;
                    if ($row) $dbSchemas[$table] = $row['sql'];
                } else {
                    $stmt = $pdo->query("SHOW CREATE TABLE {$actualTable}");
                    $row = $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : null;
                    if ($row) $dbSchemas[$table] = $row['Create Table'];
                }
            }
            $payload['db_schema'] = $dbSchemas;
            
            echo "🚀 Transmitting payload to {$target}...\n";
            $deployResp = $conn->deploy($payload);
            
            if ($deployResp['status'] === 'ok') {
                echo "✅ Deployment successful: " . $deployResp['message'] . "\n";
            } else {
                echo "❌ Deployment failed: " . ($deployResp['message'] ?? 'Unknown error') . "\n";
            }
            
        } catch (\Exception $e) {
            echo "❌ Fatal Error: " . $e->getMessage() . "\n";
        }
    }

    public function getName(): string
    {
        return 'migrate:deploy';
    }

    public function getDescription(): string
    {
        return 'Pushes local app state and configurations to a remote SPPMigrate instance';
    }
}
