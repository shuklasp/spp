<?php
namespace SPPMod\SPPDeploy\Commands;

use SPP\CLI\Command;

class DeployPushCommand extends Command
{
    public function isCLIOnly(): bool { return true; }

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
        $noBackup = false;
        $force = false;
        $artifactPath = null;
        $scopedApps = [];
        $scopedDomains = [];
        $globalAppsOnly = false;
        $frameworkOnly = false;
        $includeFramework = true;
        $withConfig = false;
        $silent = false;
        $quiet = false;
        $excludeRules = [];
        $excludeTables = [];

        $rootDir = defined('SPP_APP_DIR') ? SPP_APP_DIR : (defined('SPP_BASE_DIR') && basename(SPP_BASE_DIR) === 'spp' ? dirname(SPP_BASE_DIR) : (defined('SPP_BASE_DIR') ? SPP_BASE_DIR : getcwd()));

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--target=')) {
                $target = substr($arg, 9);
            }
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
            if ($arg === '--no-backup') {
                $noBackup = true;
            }
            if ($arg === '--force' || $arg === '-y') {
                $force = true;
            }
            if ($arg === '--silent') {
                $silent = true;
            }
            if ($arg === '--quiet' || $arg === '-q') {
                $quiet = true;
                $silent = true;
            }
            if (str_starts_with($arg, '--exclude-files=')) {
                $excludeRules = array_merge($excludeRules, explode(',', substr($arg, 16)));
            }
            if (str_starts_with($arg, '--exclude-paths=')) {
                $excludeRules = array_merge($excludeRules, explode(',', substr($arg, 16)));
            }
            if (str_starts_with($arg, '--exclude=')) {
                $excludeRules = array_merge($excludeRules, explode(',', substr($arg, 10)));
            }
            if ($arg === '--exclude-data' || $arg === '--no-data') {
                $noDb = true;
                $excludeRules[] = '*.sqlite';
                $excludeRules[] = '*.sqlite3';
                $excludeRules[] = '*.db';
            }
            if (str_starts_with($arg, '--exclude-tables=')) {
                $excludeTables = array_merge($excludeTables, explode(',', substr($arg, 17)));
            }
            if (str_starts_with($arg, '--artifact=')) {
                $artifactPath = substr($arg, 11);
            }
            if (str_starts_with($arg, '--app=')) {
                $scopedApps[] = substr($arg, 6);
            }
            if (str_starts_with($arg, '--apps=')) {
                $scopedApps = array_merge($scopedApps, explode(',', substr($arg, 7)));
            }
            if (str_starts_with($arg, '--domain=')) {
                $scopedDomains[] = substr($arg, 9);
            }
            if (str_starts_with($arg, '--domains=')) {
                $scopedDomains = array_merge($scopedDomains, explode(',', substr($arg, 10)));
            }
            if ($arg === '--global-apps') {
                $globalAppsOnly = true;
            }
            if ($arg === '--framework-only') {
                $frameworkOnly = true;
            }
            if ($arg === '--no-framework') {
                $includeFramework = false;
            }
            if ($arg === '--with-config') {
                $withConfig = true;
            }
            if ($arg === '--bundle') {
                $manifestPath = $rootDir . '/bundle-manifest.json';
                if (file_exists($manifestPath)) {
                    $m = json_decode((string)file_get_contents($manifestPath), true);
                    if (!empty($m['bundled_apps'])) {
                        $scopedApps = array_merge($scopedApps, array_column($m['bundled_apps'], 'name'));
                    }
                }
            }
        }

        $scopedDomains = array_values(array_unique(array_filter(array_map('trim', $scopedDomains))));
        $scopedApps = array_values(array_unique(array_filter(array_map('trim', $scopedApps))));
        $scope = null;

        if (!empty($scopedDomains) || !empty($scopedApps) || $frameworkOnly || $globalAppsOnly) {
            $scope = [];
            if ($frameworkOnly) {
                $scope = ['spp', 'public', 'index.php', 'spp.php', 'router.php', 'install.php', 'setup.php', 'setup.sh', '.htaccess', 'bundle-manifest.json'];
            } else {
                // 1. Domain Scoping (domains/<domain>/...)
                foreach ($scopedDomains as $sDom) {
                    $scope[] = "domains/{$sDom}";
                }
                if (!empty($scopedDomains)) {
                    $scope[] = "etc/domains.yml";
                }

                // 2. Global Apps Scoping
                if ($globalAppsOnly) {
                    $scope[] = "src";
                    $scope[] = "apps";
                }

                foreach ($scopedApps as $sApp) {
                    // Check if this app exists inside any domain (e.g. domains/example.com/src/app)
                    $domainAppGlob = glob($rootDir . "/domains/*/src/{$sApp}");
                    if (!empty($domainAppGlob)) {
                        foreach ($domainAppGlob as $dAppDir) {
                            $rel = ltrim(substr(str_replace('\\', '/', $dAppDir), strlen(str_replace('\\', '/', $rootDir))), '/');
                            $scope[] = $rel;
                            $domPrefix = dirname(dirname($rel)); // domains/<domain>
                            $scope[] = "{$domPrefix}/etc/apps/{$sApp}";
                        }
                    }
                    $scope[] = "src/{$sApp}";
                    $scope[] = "etc/apps/{$sApp}";
                    $scope[] = "spp/etc/apps/{$sApp}";
                    $scope[] = "resources/{$sApp}";
                    $scope[] = "resources/views/{$sApp}";
                    $scope[] = "public/{$sApp}";
                }

                // 3. Framework Core Inclusion (generic templates in etc.dist, never overwrite effective live configs)
                if ($includeFramework) {
                    $frameworkCore = ['spp', 'public', 'index.php', 'spp.php', 'router.php', 'install.php', 'setup.php', 'setup.sh', '.htaccess', 'bundle-manifest.json', 'etc.dist'];
                    if ($withConfig) {
                        $frameworkCore[] = 'etc/global-settings.yml';
                        $frameworkCore[] = 'etc/domains.yml';
                    }
                    $scope = array_merge($scope, $frameworkCore);
                }
            }
        }

        $rootDir = defined('SPP_APP_DIR') ? SPP_APP_DIR : (defined('SPP_BASE_DIR') && basename(SPP_BASE_DIR) === 'spp' ? dirname(SPP_BASE_DIR) : (defined('SPP_BASE_DIR') ? SPP_BASE_DIR : getcwd()));

        // Execute local pre-deploy hooks if configured
        $confFile = $rootDir . '/.sppdeploy.yml';
        if (file_exists($confFile)) {
            $conf = \SPPMod\SPPDeploy\Deployer\TargetConnection::parseYamlFile($confFile);
            if (isset($conf['pre_deploy']) && is_array($conf['pre_deploy'])) {
                if (!$silent) echo "⚙️  Running local pre-deploy hooks...\n";
                foreach ($conf['pre_deploy'] as $script) {
                    if (!$silent) echo "   > {$script}\n";
                    $cmd = "cd " . escapeshellarg($rootDir) . " && " . $script . " 2>&1";
                    exec($cmd, $output, $returnVar);
                    if ($returnVar !== 0) {
                        echo "❌ Pre-deploy hook failed: {$script}\n";
                        echo "Output:\n" . implode("\n", $output) . "\n";
                        echo "🛑 Deployment aborted to prevent pushing broken code.\n";
                        return;
                    }
                    unset($output); // clear output for next hook
                }
                if (!$silent) echo "✅ All pre-deploy hooks passed.\n";
            }
        }

        $conn = \SPPMod\SPPDeploy\Deployer\TargetConnection::resolve($target, $apiKey);

        try {
            \SPPMod\SPPDeploy\Deployer\TargetConnection::acquireDeploymentLock();

            if (!$silent) echo "🩺 Running pre-flight health checks...\n";
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
                if (!$force) {
                    echo "❌ Target server's 'var' directory is not writable. Deployment will fail (use --force to proceed anyway).\n";
                    return;
                }
                echo "⚠️  Target server reported 'var_writable' as false, but proceeding due to --force.\n";
            }
            if (isset($healthResp['health']['root_writable']) && empty($healthResp['health']['root_writable'])) {
                $pUser = $healthResp['health']['process_user'] ?? 'www-data';
                $pRoot = $healthResp['health']['project_root'] ?? 'target directory';
                echo "❌ Target server workspace root ({$pRoot}) is NOT writable by web server user '{$pUser}'.\n";
                echo "   Permission denied will prevent files from being created, updated, or deleted.\n";
                echo "   Fix this on the remote server by running:\n";
                echo "     sudo chown -R {$pUser}:{$pUser} {$pRoot}\n";
                echo "     sudo chmod -R 775 {$pRoot}\n";
                if (!$force) {
                    echo "🛑 Deployment aborted. Use --force to attempt transfer anyway.\n";
                    return;
                }
                echo "⚠️  Proceeding anyway due to --force flag.\n";
            }

            if ($artifactPath) {
                $fullArtifactPath = $rootDir . '/' . ltrim($artifactPath, '/');
                if (!is_file($fullArtifactPath)) {
                    echo "❌ Artifact not found: {$artifactPath}\n";
                    return;
                }
                $manifestPath = substr($fullArtifactPath, 0, -4) . '.json';
                if (!is_file($manifestPath)) {
                    echo "❌ Manifest not found for artifact: {$manifestPath}\n";
                    return;
                }

                if (!$silent) echo "📦 Loading pre-built artifact...\n";
                $payloadMeta = json_decode(file_get_contents($manifestPath), true);
                $archiveData = file_get_contents($fullArtifactPath);
                self::transmitPayload($conn, $archiveData, $payloadMeta, $target, $silent, $quiet);
                return;
            }

            $localHashes = [];
            if (!$noFiles) {
                if (!$silent) echo "🔍 Scanning local workspace files...\n";
                $scanner = new \SPPMod\SPPDeploy\Scanner\ProjectScanner();
                if ($withConfig) {
                    $scanner->setIncludeConfig(true);
                }
                $localHashes = $scanner->scan($rootDir, $scope, $excludeRules);
                if (!$silent) echo "   > " . count($localHashes) . " local file signatures indexed.\n";
            }

            $localDbHashes = [];
            if (!$noDb) {
                if (!$silent) echo "🔍 Scanning local database schemas...\n";
                $dbScanner = new \SPPMod\SPPDeploy\Scanner\DbScanner();
                $localDbHashes = $dbScanner->scan($excludeTables);
            }

            if (!$silent) echo "🔑 Validating environment variables...\n";
            $envResp = $conn->getEnvKeys();
            if (isset($envResp['status']) && $envResp['status'] === 'ok' && isset($envResp['keys'])) {
                $remoteKeys = $envResp['keys'];
                $localKeys = array_keys($_ENV);
                $missingKeys = array_diff($localKeys, $remoteKeys);

                // Filter out common system vars
                $missingKeys = array_filter($missingKeys, function ($k) {
                    return !str_starts_with($k, 'HTTP_') && !str_starts_with($k, 'SERVER_') && !in_array($k, ['PATH', 'USER', 'HOME']);
                });

                if (!empty($missingKeys) && !$silent) {
                    echo "⚠️  WARNING: The following environment variables exist locally but are missing on the target:\n";
                    echo "   " . implode(", ", $missingKeys) . "\n";
                    echo "   (Make sure they are not required, or add them to the target's .env file)\n\n";
                }
            }

            if (!$silent) echo "📡 Fetching remote diff...\n";
            $diffResp = $conn->getDiff(['files' => $localHashes, 'db' => $localDbHashes], $scope, $excludeRules, $excludeTables);

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
                if (!$quiet) {
                    echo $silent ? "✓ Target {$target} is already up to date.\n" : "✅ Everything is up to date.\n";
                }
                return;
            }

            if (!$silent) {
                echo "\n🚀 Proposed Deployment Summary:\n";
                echo "   Files: " . count($diff['files']['create']) . " to create, " . count($diff['files']['update']) . " to update, " . count($diff['files']['delete']) . " to delete.\n";
                echo "   DB Tables: " . count($diff['db']['create']) . " to create, " . count($diff['db']['update']) . " to update, " . count($diff['db']['delete']) . " to delete.\n";
            }

            if ($dryRun) {
                if (!$quiet) {
                    echo $silent ? "✓ Dry-run diff: {$fileCount} files, {$dbCount} tables.\n" : "\nℹ️  Dry-run complete. No changes were transferred to {$target}.\n";
                }
                return;
            }

            if (!$force && !$silent) {
                echo "\n❓ Proceed with deployment? [Y/n] ";
                $handle = fopen("php://stdin", "r");
                $line = trim(fgets($handle));
                fclose($handle);
                if (strtolower($line) === 'n' || strtolower($line) === 'no') {
                    echo "⛔ Deployment aborted by user.\n";
                    return;
                }
            }

            if (!$silent) echo "\n📦 Preparing payload...\n";

            $tempZip = $rootDir . '/var/cache/deploy_payload.zip';
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
                    $localFile = $rootDir . '/' . $file;
                    if (is_file($localFile)) {
                        $zip->addFile($localFile, $file);
                    }
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
                                $safeTable = $this->escapeIdentifier($table);
                                // $table is implicitly safe here since escapeIdentifier did not throw
                                if ($driver === 'sqlite') {
                                    $stmt = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='{$safeTable}'")->fetch(\PDO::FETCH_ASSOC);
                                    if ($stmt && isset($stmt['sql'])) {
                                        $sqlBuffer .= "DROP TABLE IF EXISTS " . $safeTable . ";\n";
                                        $sqlBuffer .= $stmt['sql'] . ";\n";
                                    }
                                } elseif ($driver === 'mysql') {
                                    $stmt = $pdo->query("SHOW CREATE TABLE " . $safeTable)->fetch(\PDO::FETCH_ASSOC);
                                    if ($stmt && isset($stmt['Create Table'])) {
                                        $sqlBuffer .= "DROP TABLE IF EXISTS " . $safeTable . ";\n";
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
                'diff' => $diff,
                'exclude' => $excludeRules,
                'exclude_tables' => $excludeTables,
                'no_backup' => $noBackup
            ];
            $archiveData = file_get_contents($tempZip);
            self::transmitPayload($conn, $archiveData, $payloadMeta, $target, $silent, $quiet);

        } catch (\Exception $e) {
            echo "❌ Fatal Error: " . $e->getMessage() . "\n";
        } finally {
            \SPPMod\SPPDeploy\Deployer\TargetConnection::releaseDeploymentLock();
        }
    }

    private static function transmitPayload($conn, string $archiveData, array $payloadMeta, string $target, bool $silent = false, bool $quiet = false): void
    {
        $totalSize = strlen($archiveData);
        $chunkSize = 2 * 1024 * 1024; // 2MB
        $sessionId = uniqid('deploy_', true);
        $numChunks = (int) max(1, ceil($totalSize / $chunkSize));

        if (!$silent) {
            echo "🚀 Transmitting payload (" . round($totalSize / 1024 / 1024, 2) . " MB in {$numChunks} chunks) to {$target}...\n";
        }

        $deployResp = null;
        for ($i = 0; $i < $numChunks; $i++) {
            $chunk = substr($archiveData, $i * $chunkSize, $chunkSize);
            $isLast = ($i === $numChunks - 1);

            if (!$silent) {
                echo "   Sending chunk " . ($i + 1) . "/{$numChunks}...\n";
            }
            $resp = $conn->uploadChunk($sessionId, base64_encode($chunk), $i, $isLast, $isLast ? $payloadMeta : []);

            if (!isset($resp['status']) || $resp['status'] !== 'ok') {
                echo "❌ Chunk transfer failed: " . ($resp['message'] ?? 'Unknown error') . "\n";
                if (isset($resp['debug']))
                    echo "DEBUG: " . $resp['debug'] . "\n";
                return;
            }
            if ($isLast) {
                if (($resp['message'] ?? '') === 'Chunk received.') {
                    echo "❌ Deployment execution failed: Target received the final payload chunk but did not trigger execution.\n";
                    return;
                }
                $deployResp = $resp;
            }
        }

        if ($quiet) {
            return;
        }

        if ($silent) {
            $filesCount = $payloadMeta['filesCount'] ?? 0;
            echo "✓ Deployed {$filesCount} file(s) to {$target}.\n";
            return;
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
