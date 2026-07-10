<?php
namespace SPPMod\SPPDeploy\Commands;

use SPP\CLI\Command;

class DeployPushCommand extends Command
{
    public function execute(array $args): void
    {
        $target = $args[2] ?? null;
        if (!$target || str_starts_with($target, '--') || str_starts_with($target, '-')) {
            $target = \SPPMod\SPPDeploy\Deployer\TargetConnection::getDefaultEnvironment();
        }

        $apiKey = getenv('SPP_DEPLOY_TOKEN') ?: 'default_cli_key';
        $dryRun = false;
        $noDb = false;
        $noFiles = false;
        $force = false;
        $artifactPath = null;

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--key=')) {
                $apiKey = substr($arg, 6);
            }
            if ($arg === '--dry-run') {
                $dryRun = true;
            }
            if ($arg === '--no-db') {
                $noDb = true;
            }
            if ($arg === '--no-files') {
                $noFiles = true;
            }
            if ($arg === '--force' || $arg === '-y') {
                $force = true;
            }
            if (str_starts_with($arg, '--artifact=')) {
                $artifactPath = substr($arg, 11);
            }
        }

        // Execute local pre-deploy hooks if configured
        $confFile = SPP_BASE_DIR . '/.sppdeploy.yml';
        if (file_exists($confFile)) {
            $conf = @yaml_parse_file($confFile);
            if (isset($conf['pre_deploy']) && is_array($conf['pre_deploy'])) {
                echo "⚙️  Running local pre-deploy hooks...\n";
                foreach ($conf['pre_deploy'] as $script) {
                    echo "   > {$script}\n";
                    $cmd = "cd " . escapeshellarg(SPP_BASE_DIR) . " && " . $script . " 2>&1";
                    exec($cmd, $output, $returnVar);
                    if ($returnVar !== 0) {
                        echo "❌ Pre-deploy hook failed: {$script}\n";
                        echo "Output:\n" . implode("\n", $output) . "\n";
                        echo "🛑 Deployment aborted to prevent pushing broken code.\n";
                        return;
                    }
                    unset($output); // clear output for next hook
                }
                echo "✅ All pre-deploy hooks passed.\n";
            }
        }

        $conn = \SPPMod\SPPDeploy\Deployer\TargetConnection::resolve($target, $apiKey);

        try {
            \SPPMod\SPPDeploy\Deployer\TargetConnection::acquireDeploymentLock();

            if ($artifactPath) {
                $fullArtifactPath = SPP_BASE_DIR . '/' . ltrim($artifactPath, '/');
                if (!is_file($fullArtifactPath)) {
                    echo "❌ Artifact not found: {$artifactPath}\n";
                    return;
                }
                $manifestPath = substr($fullArtifactPath, 0, -4) . '.json';
                if (!is_file($manifestPath)) {
                    echo "❌ Manifest not found for artifact: {$manifestPath}\n";
                    return;
                }

                echo "📦 Loading pre-built artifact...\n";
                $payloadMeta = json_decode(file_get_contents($manifestPath), true);
                $archiveData = file_get_contents($fullArtifactPath);
                self::transmitPayload($conn, $archiveData, $payloadMeta, $target);
                return;
            }

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

            echo "🩺 Running pre-flight health checks...\n";
            $healthResp = $conn->getHealth();
            if (!isset($healthResp['status']) || $healthResp['status'] !== 'ok') {
                echo "❌ Target server health check failed.\n";
                if (isset($healthResp['message']))
                    echo "Reason: " . $healthResp['message'] . "\n";
                return;
            }
            if (empty($healthResp['health']['zip_extension'])) {
                echo "❌ Target server is missing the 'zip' extension, which is required for extraction.\n";
                return;
            }
            if (empty($healthResp['health']['var_writable'])) {
                echo "❌ Target server's 'spp/var' directory is not writable. Deployment will fail.\n";
                return;
            }

            echo "🔑 Validating environment variables...\n";
            $envResp = $conn->getEnvKeys();
            if (isset($envResp['status']) && $envResp['status'] === 'ok' && isset($envResp['keys'])) {
                $remoteKeys = $envResp['keys'];
                $localKeys = array_keys($_ENV);
                $missingKeys = array_diff($localKeys, $remoteKeys);

                // Filter out common system vars
                $missingKeys = array_filter($missingKeys, function ($k) {
                    return !str_starts_with($k, 'HTTP_') && !str_starts_with($k, 'SERVER_') && !in_array($k, ['PATH', 'USER', 'HOME']);
                });

                if (!empty($missingKeys)) {
                    echo "⚠️  WARNING: The following environment variables exist locally but are missing on the target:\n";
                    echo "   " . implode(", ", $missingKeys) . "\n";
                    echo "   (Make sure they are not required, or add them to the target's .env file)\n\n";
                }
            }

            echo "📡 Fetching remote diff...\n";
            $diffResp = $conn->getDiff(['files' => $localHashes, 'db' => $localDbHashes]);

            if (!isset($diffResp['status']) || $diffResp['status'] !== 'ok') {
                echo "❌ Error computing diff: " . ($diffResp['message'] ?? 'Unknown error') . "\n";
                if (isset($diffResp['debug']))
                    echo "DEBUG: " . $diffResp['debug'] . "\n";
                return;
            }

            $diff = $diffResp['diff'];

            $fileCount = count($diff['files']['create']) + count($diff['files']['update']) + count($diff['files']['delete']);
            $dbCount = count($diff['db']['create']) + count($diff['db']['update']) + count($diff['db']['delete']);

            if ($fileCount === 0 && $dbCount === 0) {
                echo "✅ Everything is up to date.\n";
                return;
            }

            echo "\n🚀 Proposed Deployment Summary:\n";
            echo "   Files: " . count($diff['files']['create']) . " to create, " . count($diff['files']['update']) . " to update, " . count($diff['files']['delete']) . " to delete.\n";
            echo "   DB Tables: " . count($diff['db']['create']) . " to create, " . count($diff['db']['update']) . " to update, " . count($diff['db']['delete']) . " to delete.\n";

            if (!$force && !$dryRun) {
                echo "\n❓ Proceed with deployment? [Y/n] ";
                $handle = fopen("php://stdin", "r");
                $line = trim(fgets($handle));
                fclose($handle);
                if (strtolower($line) === 'n' || strtolower($line) === 'no') {
                    echo "⛔ Deployment aborted by user.\n";
                    return;
                }
            }

            echo "\n📦 Preparing payload...\n";

            $tempZip = SPP_BASE_DIR . '/var/cache/deploy_payload.zip';
            if (is_file($tempZip))
                unlink($tempZip);
            if (!is_dir(dirname($tempZip)))
                mkdir(dirname($tempZip), 0777, true);

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
            $archiveData = file_get_contents($tempZip);
            self::transmitPayload($conn, $archiveData, $payloadMeta, $target);

        } catch (\Exception $e) {
            echo "❌ Fatal Error: " . $e->getMessage() . "\n";
        } finally {
            \SPPMod\SPPDeploy\Deployer\TargetConnection::releaseDeploymentLock();
        }
    }

    private static function transmitPayload($conn, string $archiveData, array $payloadMeta, string $target): void
    {
        $totalSize = strlen($archiveData);
        $chunkSize = 2 * 1024 * 1024; // 2MB
        $sessionId = uniqid('deploy_', true);
        $numChunks = ceil($totalSize / $chunkSize);

        echo "🚀 Transmitting payload (" . round($totalSize / 1024 / 1024, 2) . " MB in {$numChunks} chunks) to {$target}...\n";

        $deployResp = null;
        for ($i = 0; $i < $numChunks; $i++) {
            $chunk = substr($archiveData, $i * $chunkSize, $chunkSize);
            $isLast = ($i === $numChunks - 1);

            echo "   Sending chunk " . ($i + 1) . "/{$numChunks}...\n";
            $resp = $conn->uploadChunk($sessionId, base64_encode($chunk), $i, $isLast, $isLast ? $payloadMeta : []);

            if (!isset($resp['status']) || $resp['status'] !== 'ok') {
                echo "❌ Chunk transfer failed: " . ($resp['message'] ?? 'Unknown error') . "\n";
                if (isset($resp['debug']))
                    echo "DEBUG: " . $resp['debug'] . "\n";
                return;
            }
            if ($isLast) {
                $deployResp = $resp;
            }
        }

        echo "✅ Deployment successful!\n";
        if (isset($deployResp['webhooks']) && !empty($deployResp['webhooks'])) {
            echo "🔔 Webhooks fired: " . implode(', ', $deployResp['webhooks']) . "\n";
        }
    }

    public function getName(): string
    {
        return 'deploy:push';
    }

    public function getDescription(): string
    {
        return 'Push the local project state to a remote SPP target server';
    }
}
