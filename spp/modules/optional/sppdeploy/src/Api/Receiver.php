<?php
namespace SPPMod\SPPDeploy\Api;

class Receiver
{
    public static function getProjectRoot(): string
    {
        if (defined('SPP_APP_DIR') && is_dir(SPP_APP_DIR)) {
            return rtrim(str_replace('\\', '/', realpath(SPP_APP_DIR) ?: SPP_APP_DIR), '/');
        }
        if (defined('SPP_BASE_DIR') && is_dir(SPP_BASE_DIR)) {
            $base = rtrim(str_replace('\\', '/', realpath(SPP_BASE_DIR) ?: SPP_BASE_DIR), '/');
            if (basename($base) === 'spp') {
                return dirname($base);
            }
            return $base;
        }
        return rtrim(str_replace('\\', '/', dirname(__DIR__, 5)), '/');
    }

    public static function handle(string $path): void
    {
        $path = explode('?', $path)[0];

        if ($path === '/diff' || $path === '/_sppdeploy/diff') {
            self::handleDiff();
        }

        if ($path === '/deploy' || $path === '/_sppdeploy/deploy') {
            self::handleDeploy();
        }

        if ($path === '/backups' || $path === '/_sppdeploy/backups') {
            self::handleBackups();
        }

        if ($path === '/rollback' || $path === '/_sppdeploy/rollback') {
            self::handleRollback();
        }

        if ($path === '/health' || $path === '/_sppdeploy/health') {
            self::handleHealth();
        }

        if ($path === '/env-keys' || $path === '/_sppdeploy/env-keys') {
            self::handleEnvKeys();
        }

        if ($path === '/export' || $path === '/_sppdeploy/export') {
            self::handleExport();
        }

        if ($path === '/chunk' || $path === '/_sppdeploy/chunk') {
            self::handleChunk();
        }

        if ($path === '/chunk/status' || $path === '/_sppdeploy/chunk/status') {
            self::handleChunkStatus();
        }

        if (str_starts_with($path, '/telemetry') || str_starts_with($path, '/_sppdeploy/telemetry')) {
            self::handleTelemetry();
        }

        if ($path === '/logs' || $path === '/_sppdeploy/logs') {
            self::handleLogs();
        }

        if ($path === '/history' || $path === '/_sppdeploy/history') {
            self::handleHistory();
        }

        if ($path === '/maintenance' || $path === '/_sppdeploy/maintenance') {
            self::handleMaintenance();
        }

        if ($path === '/env/push' || $path === '/_sppdeploy/env/push') {
            self::handleEnvPush();
        }

        if ($path === '/backups' || $path === '/_sppdeploy/backups') {
            self::handleBackups();
        }

        if ($path === '/cleanup' || $path === '/_sppdeploy/cleanup') {
            self::handleCleanup();
        }

        if ($path === '/run' || $path === '/_sppdeploy/run') {
            self::handleRun();
        }

        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => "Endpoint not found: {$path}"]);
        exit;
    }

    public static function getVarDir(): string
    {
        $projectRoot = self::getProjectRoot();
        if (is_dir($projectRoot . '/var') && is_writable($projectRoot . '/var')) {
            return $projectRoot . '/var';
        }
        if (is_dir($projectRoot . '/spp/var') && is_writable($projectRoot . '/spp/var')) {
            return $projectRoot . '/spp/var';
        }
        if (is_dir($projectRoot . '/var')) {
            return $projectRoot . '/var';
        }
        if (is_dir($projectRoot . '/spp/var')) {
            return $projectRoot . '/spp/var';
        }
        @mkdir($projectRoot . '/var', 0777, true);
        if (is_dir($projectRoot . '/var')) {
            return $projectRoot . '/var';
        }
        @mkdir($projectRoot . '/spp/var', 0777, true);
        return $projectRoot . '/spp/var';
    }

    private static function handleHealth(): void
    {
        \SPPMod\SPPDeploy\SPPDeploy::requireAuth();
        $isDbOk = false;
        try {
            if (class_exists('\\SPPMod\\SPPDB\\SPPDB')) {
                try {
                    $db = new \SPPMod\SPPDB\SPPDB();
                    $pdo = $db->getPDO();
                } catch (\Throwable $e) {
                    $pdo = null;
                }
                if ($pdo) {
                    $pdo->query("SELECT 1");
                    $isDbOk = true;
                }
            } elseif (class_exists('\\SPP\\Core\\DB')) {
                $db = \SPP\Core\DB::getInstance();
                if ($db) {
                    $db->query("SELECT 1")->execute();
                    $isDbOk = true;
                }
            }
        } catch (\Throwable $e) {
        }

        $projectRoot = self::getProjectRoot();
        $varDir = self::getVarDir();
        $varWritable = $varDir ? is_writable($varDir) : false;
        $rootWritable = is_writable($projectRoot);
        $processUser = function_exists('posix_getpwuid') && function_exists('posix_geteuid') 
            ? (posix_getpwuid(posix_geteuid())['name'] ?? get_current_user()) 
            : get_current_user();

        $maintCandidates = array_unique(array_filter([
            $projectRoot . '/.maintenance',
            defined('SPP_BASE_DIR') ? SPP_BASE_DIR . '/.maintenance' : null,
            defined('SPP_APP_DIR') ? SPP_APP_DIR . '/.maintenance' : null,
        ]));
        $isMaintenance = false;
        foreach ($maintCandidates as $mPath) {
            if (is_file($mPath)) {
                $isMaintenance = true;
                break;
            }
        }

        echo json_encode([
            'status' => 'ok',
            'health' => [
                'php_version' => PHP_VERSION,
                'zip_extension' => extension_loaded('zip'),
                'curl_extension' => extension_loaded('curl'),
                'var_writable' => $varWritable,
                'root_writable' => $rootWritable,
                'project_root' => $projectRoot,
                'process_user' => $processUser,
                'db_connected' => $isDbOk,
                'maintenance' => $isMaintenance
            ]
        ]);
        exit;
    }

    private static function handleEnvKeys(): void
    {
        \SPPMod\SPPDeploy\SPPDeploy::requireAuth();
        $keys = array_keys($_ENV);
        echo json_encode([
            'status' => 'ok',
            'keys' => $keys
        ]);
        exit;
    }

    private static function handleExport(): void
    {
        \SPPMod\SPPDeploy\SPPDeploy::requireAuth();
        $projectRoot = self::getProjectRoot();
        $backupDir = self::getVarDir() . '/backups';
        if (!is_dir($backupDir))
            mkdir($backupDir, 0777, true);

        $backupPath = $backupDir . '/sppdeploy_export_' . time() . '.zip';
        $zip = new \ZipArchive();
        if ($zip->open($backupPath, \ZipArchive::CREATE) !== true) {
            echo json_encode(['status' => 'error', 'message' => 'Failed to create export archive.']);
            exit;
        }

        $scanner = new \SPPMod\SPPDeploy\Scanner\ProjectScanner();
        $files = $scanner->scan($projectRoot);
        foreach (array_keys($files) as $path) {
            $fullPath = $projectRoot . '/' . $path;
            if (is_file($fullPath)) {
                $zip->addFile($fullPath, $path);
            }
        }

        if (class_exists('\\SPPMod\\SPPDB\\SPPDB')) {
            try {
                $db = new \SPPMod\SPPDB\SPPDB();
                $pdo = $db->getPDO();
            } catch (\Throwable $e) {
                $pdo = null;
            }
            if ($pdo) {
                $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
                $sqlBuffer = "";

                $anonymize = [];
                $confFile = $projectRoot . '/.sppdeploy.yml';
                if (file_exists($confFile)) {
                    $conf = \SPPMod\SPPDeploy\Deployer\TargetConnection::parseYamlFile($confFile);
                    if (isset($conf['anonymize']) && is_array($conf['anonymize'])) {
                        $anonymize = $conf['anonymize'];
                    }
                }

                if ($driver === 'sqlite') {
                    $stmt = $pdo->query("SELECT name, sql FROM sqlite_master WHERE type='table'");
                    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                        $tableName = $row['name'];
                        $sqlBuffer .= "DROP TABLE IF EXISTS `{$tableName}`;\n" . $row['sql'] . ";\n";
                        $res = $pdo->query("SELECT * FROM `{$tableName}`");
                        while ($data = $res->fetch(\PDO::FETCH_ASSOC)) {
                            if (isset($anonymize[$tableName])) {
                                foreach ($anonymize[$tableName] as $col) {
                                    if (array_key_exists($col, $data)) {
                                        $data[$col] = '***';
                                    }
                                }
                            }
                            $cols = array_keys($data);
                            $vals = array_map(fn($v) => $pdo->quote($v), array_values($data));
                            $sqlBuffer .= "INSERT INTO `{$tableName}` (`" . implode("`,`", $cols) . "`) VALUES (" . implode(",", $vals) . ");\n";
                        }
                    }
                } elseif ($driver === 'mysql') {
                    $stmt = $pdo->query("SHOW TABLES");
                    while ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
                        $tableName = $row[0];
                        $createStmt = $pdo->query("SHOW CREATE TABLE `{$tableName}`")->fetch(\PDO::FETCH_ASSOC);
                        if ($createStmt && isset($createStmt['Create Table'])) {
                            $sqlBuffer .= "DROP TABLE IF EXISTS `{$tableName}`;\n" . $createStmt['Create Table'] . ";\n";
                        }
                        $res = $pdo->query("SELECT * FROM `{$tableName}`");
                        while ($data = $res->fetch(\PDO::FETCH_ASSOC)) {
                            if (isset($anonymize[$tableName])) {
                                foreach ($anonymize[$tableName] as $col) {
                                    if (array_key_exists($col, $data)) {
                                        $data[$col] = '***';
                                    }
                                }
                            }
                            $cols = array_keys($data);
                            $vals = array_map(fn($v) => $pdo->quote($v), array_values($data));
                            $sqlBuffer .= "INSERT INTO `{$tableName}` (`" . implode("`,`", $cols) . "`) VALUES (" . implode(",", $vals) . ");\n";
                        }
                    }
                }
                if ($sqlBuffer !== "") {
                    $zip->addFromString('db_snapshot.sql', $sqlBuffer);
                }
            }
        }
        $zip->close();

        $data = file_get_contents($backupPath);
        unlink($backupPath);

        echo json_encode(['status' => 'ok', 'archive' => base64_encode($data)]);
        exit;
    }

    private static function handleDiff(): void
    {
        \SPPMod\SPPDeploy\SPPDeploy::requireAuth();
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !isset($input['hashes'])) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid payload, expected hashes.']);
            exit;
        }

        $scope = $input['scope'] ?? null;
        $exclude = $input['exclude'] ?? null;
        $projectRoot = self::getProjectRoot();
        $localScanner = new \SPPMod\SPPDeploy\Scanner\ProjectScanner();
        $localHashes = $localScanner->scan($projectRoot, $scope, $exclude);

        $excludeTables = $input['exclude_tables'] ?? null;
        $localDbHashes = [];
        try {
            $localDbScanner = new \SPPMod\SPPDeploy\Scanner\DbScanner();
            $localDbHashes = $localDbScanner->scan($excludeTables);
        } catch (\Throwable $e) {
            $localDbHashes = [];
        }

        $diff = [
            'files' => [
                'create' => [],
                'update' => [],
                'delete' => []
            ],
            'db' => [
                'create' => [],
                'update' => [],
                'delete' => []
            ]
        ];

        // Process file hashes
        $clientFileHashes = $input['hashes']['files'] ?? [];
        foreach ($clientFileHashes as $path => $hash) {
            if (!isset($localHashes[$path])) {
                $diff['files']['create'][] = $path;
            } elseif ($localHashes[$path] !== $hash) {
                $diff['files']['update'][] = $path;
            }
        }
        foreach ($localHashes as $path => $hash) {
            if (!isset($clientFileHashes[$path])) {
                $diff['files']['delete'][] = $path;
            }
        }

        // Process DB hashes
        $clientDbHashes = $input['hashes']['db'] ?? [];
        foreach ($clientDbHashes as $table => $hash) {
            if (!isset($localDbHashes[$table])) {
                $diff['db']['create'][] = $table;
            } elseif ($localDbHashes[$table] !== $hash) {
                $diff['db']['update'][] = $table;
            }
        }
        foreach ($localDbHashes as $table => $hash) {
            if (!isset($clientDbHashes[$table])) {
                $diff['db']['delete'][] = $table;
            }
        }

        echo json_encode(['status' => 'ok', 'diff' => $diff]);
        exit;
    }

    private static function handleChunk(): void
    {
        \SPPMod\SPPDeploy\SPPDeploy::requireAuth();

        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!$data || !isset($data['session_id'], $data['chunk_data'])) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid chunk payload.']);
            exit;
        }

        $sessionId = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$data['session_id']);
        $chunkIndex = (int)($data['index'] ?? 0);
        $chunkData = base64_decode($data['chunk_data']);
        $isLast = filter_var($data['is_last'] ?? false, FILTER_VALIDATE_BOOLEAN) || ($data['is_last'] ?? false) === 1 || ($data['is_last'] ?? false) === '1' || ($data['is_last'] ?? false) === true;

        $tempArchive = self::getVarDir() . '/cache/' . $sessionId . '.zip';
        $metaFile = self::getVarDir() . '/cache/' . $sessionId . '.meta.json';

        if (!is_dir(dirname($tempArchive))) {
            mkdir(dirname($tempArchive), 0777, true);
        }

        $meta = file_exists($metaFile) ? (json_decode((string)file_get_contents($metaFile), true) ?: []) : ['chunks' => []];

        // Resumable check: only append if not already received
        if (!in_array($chunkIndex, $meta['chunks'] ?? [], true)) {
            file_put_contents($tempArchive, $chunkData, FILE_APPEND);
            $meta['chunks'][] = $chunkIndex;
            file_put_contents($metaFile, json_encode($meta));
        }

        if ($isLast) {
            @unlink($metaFile);
            $payloadData = $data['payload_data'] ?? [];
            self::processDeploymentPayload($tempArchive, $payloadData);
        } else {
            echo json_encode([
                'status' => 'ok',
                'message' => 'Chunk received.',
                'chunk_index' => $chunkIndex,
                'bytes_received' => (int)filesize($tempArchive)
            ]);
            exit;
        }
    }

    private static function handleChunkStatus(): void
    {
        \SPPMod\SPPDeploy\SPPDeploy::requireAuth();

        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        $sessionId = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)($data['session_id'] ?? ''));

        if (!$sessionId) {
            echo json_encode(['status' => 'error', 'message' => 'Missing session_id.']);
            exit;
        }

        $tempArchive = self::getVarDir() . '/cache/' . $sessionId . '.zip';
        $metaFile = self::getVarDir() . '/cache/' . $sessionId . '.meta.json';

        $bytes = file_exists($tempArchive) ? (int)filesize($tempArchive) : 0;
        $meta = file_exists($metaFile) ? (json_decode((string)file_get_contents($metaFile), true) ?: []) : [];

        echo json_encode([
            'status' => 'ok',
            'session_id' => $sessionId,
            'bytes_received' => $bytes,
            'chunks_received' => count($meta['chunks'] ?? []),
            'last_chunk_index' => end($meta['chunks'] ?? []) ?: -1
        ]);
        exit;
    }

    private static function handleTelemetry(): void
    {
        \SPPMod\SPPDeploy\SPPDeploy::requireAuth();

        $appName = $_GET['app'] ?? 'default';
        $snapshot = \SPPMod\SPPDeploy\Telemetry\TelemetryBridge::getSnapshot($appName);

        echo json_encode([
            'status' => 'ok',
            'telemetry' => $snapshot
        ]);
        exit;
    }

    private static function handleDeploy(): void
    {
        \SPPMod\SPPDeploy\SPPDeploy::requireAuth();

        $input = file_get_contents('php://input');
        $payloadData = json_decode($input, true);

        if (!$payloadData || !isset($payloadData['archive'])) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid payload format.']);
            exit;
        }

        $tempArchive = self::getVarDir() . '/cache/deploy_incoming.zip';
        if (!is_dir(dirname($tempArchive))) {
            mkdir(dirname($tempArchive), 0777, true);
        }
        file_put_contents($tempArchive, base64_decode($payloadData['archive']));

        self::processDeploymentPayload($tempArchive, $payloadData);
    }

    private static function processDeploymentPayload(string $tempArchive, array $payloadData): void
    {
        $projectRoot = self::getProjectRoot();
        $meta = $payloadData['metadata'] ?? [];
        $diff = $payloadData['diff'] ?? ($meta['diff'] ?? ['files' => ['create' => [], 'update' => [], 'delete' => []], 'db' => ['create' => [], 'update' => [], 'delete' => []]]);
        $filesCount = $payloadData['filesCount'] ?? ($meta['filesCount'] ?? (count($diff['files']['create']) + count($diff['files']['update'])));
        $dbCount = $payloadData['dbCount'] ?? ($meta['dbCount'] ?? (count($diff['db']['create'] ?? []) + count($diff['db']['update'] ?? [])));
        $isDryRun = $payloadData['dry_run'] ?? ($meta['dryRun'] ?? false);

        $webhooks = [];
        $confFile = $projectRoot . '/.sppdeploy.yml';
        if (file_exists($confFile)) {
            $conf = \SPPMod\SPPDeploy\Deployer\TargetConnection::parseYamlFile($confFile);
            if (!$conf) {
                $confStr = file_get_contents($confFile);
                if (preg_match_all('/-\s*"(.*?)"/', $confStr, $matches)) {
                    $webhooks = $matches[1] ?? [];
                }
            } else {
                $webhooks = $conf['webhooks'] ?? [];
            }
        }

        if ($isDryRun) {
            self::logDeployment("DRY RUN: Completed. Simulated $filesCount file(s) and $dbCount table(s) updates.", [
                'status' => 'dry_run',
                'filesCount' => $filesCount,
                'dbCount' => $dbCount
            ]);
            self::sendWebhooks($webhooks, 'dry_run', $filesCount, $dbCount, 'success');
            echo json_encode(['status' => 'ok', 'message' => 'Dry run successful. No files were modified.']);
            if (is_file($tempArchive))
                unlink($tempArchive);
            exit;
        }

        $lockFile = $projectRoot . '/.maintenance';
        @file_put_contents($lockFile, 'Deployment in progress...');

        $affected = array_merge($diff['files']['update'] ?? [], $diff['files']['delete'] ?? []);
        $backupResult = (!empty($payloadData['no_backup']) || ($conf['backup'] ?? true) === false) 
            ? 'Skipped (no-backup requested)' 
            : self::doAutoBackup($affected);

        $protectedRules = array_merge(
            (array)($conf['protected'] ?? []),
            (array)($conf['exclude'] ?? []),
            (array)($payloadData['exclude'] ?? [])
        );

        try {
            $failedDeletions = [];
            foreach ($diff['files']['delete'] as $path) {
                // Safeguard: Never delete protected or excluded files on live target
                foreach ($protectedRules as $rule) {
                    $cleanRule = ltrim((string)$rule, '/');
                    if (fnmatch($cleanRule, $path, FNM_CASEFOLD) || fnmatch($rule, '/' . $path, FNM_CASEFOLD) || (!str_contains($rule, '/') && fnmatch($rule, basename($path), FNM_CASEFOLD))) {
                        continue 2;
                    }
                }
                foreach (self::resolveDestinationPaths($path, $projectRoot, $conf) as $fullPath) {
                    if (is_file($fullPath)) {
                        if (!@unlink($fullPath) && file_exists($fullPath)) {
                            $failedDeletions[] = $path;
                        }
                    }
                }
            }

            $targetDir = $projectRoot;

            // 1. Prefer compiled C-extension \ZipArchive with per-entry directory and permission management
            $unzipSuccess = false;
            $failedWrites = [];
            if (class_exists('\\ZipArchive')) {
                $zip = new \ZipArchive();
                if ($zip->open($tempArchive) === true) {
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $entry = $zip->getNameIndex($i);
                        $destPaths = self::resolveDestinationPaths($entry, $targetDir, $conf);

                        if (str_ends_with($entry, '/')) {
                            foreach ($destPaths as $destDir) {
                                if (!is_dir($destDir) && !@mkdir($destDir, 0777, true) && !is_dir($destDir)) {
                                    $failedWrites[] = "mkdir failed: {$destDir}";
                                }
                            }
                            continue;
                        }

                        $fileBytes = $zip->getFromIndex($i);
                        if ($fileBytes === false) {
                            $stream = $zip->getStream($entry);
                            $fileBytes = $stream ? stream_get_contents($stream) : false;
                            if ($stream) fclose($stream);
                        }

                        foreach ($destPaths as $dest) {
                            $destDir = dirname($dest);
                            if (!is_dir($destDir)) {
                                if (!@mkdir($destDir, 0777, true) && !is_dir($destDir)) {
                                    $failedWrites[] = "mkdir failed: {$destDir}";
                                }
                            }
                            if (file_exists($dest)) {
                                @chmod($dest, 0666);
                            }
                            if ($fileBytes !== false) {
                                $bytesWritten = @file_put_contents($dest, $fileBytes);
                                if ($bytesWritten === false) {
                                    $failedWrites[] = "write failed: {$entry} -> {$dest}";
                                }
                            } else {
                                $extOk = @$zip->extractTo($destDir, $entry);
                                if (!$extOk && !file_exists($dest)) {
                                    $failedWrites[] = "extract failed: {$entry} -> {$dest}";
                                }
                            }
                        }
                    }
                    $zip->close();
                    if (!empty($failedWrites) || !empty($failedDeletions)) {
                        $pUser = function_exists('posix_getpwuid') && function_exists('posix_geteuid') 
                            ? (posix_getpwuid(posix_geteuid())['name'] ?? get_current_user()) 
                            : get_current_user();
                        $errMsg = "Deployment failed due to filesystem permissions for web server user '{$pUser}'.\n";
                        if (!empty($failedWrites)) {
                            $errMsg .= "Failed writes (" . count($failedWrites) . "):\n   - " . implode("\n   - ", array_slice($failedWrites, 0, 8)) . "\n";
                        }
                        if (!empty($failedDeletions)) {
                            $errMsg .= "Failed deletions (" . count($failedDeletions) . "):\n   - " . implode("\n   - ", array_slice($failedDeletions, 0, 8)) . "\n";
                        }
                        $errMsg .= "\nPlease fix ownership on the target server:\n";
                        $errMsg .= "   sudo chown -R {$pUser}:{$pUser} {$projectRoot}\n";
                        $errMsg .= "   sudo chmod -R 775 {$projectRoot}\n";
                        throw new \Exception($errMsg);
                    }
                    $unzipSuccess = true;
                }
            }

            // 2. Fallback to CLI unzip / tar if ZipArchive failed and exec() is available
            if (!$unzipSuccess && function_exists('exec')) {
                $unzipCmd = "unzip -o " . escapeshellarg($tempArchive) . " -d " . escapeshellarg($targetDir) . " 2>&1";
                @exec($unzipCmd, $unzipOutput, $unzipCode);
                if ($unzipCode === 0) {
                    $unzipSuccess = true;
                } else {
                    $tarCmd = "tar -xf " . escapeshellarg($tempArchive) . " -C " . escapeshellarg($targetDir) . " 2>&1";
                    @exec($tarCmd, $tarOutput, $tarCode);
                    if ($tarCode === 0) {
                        $unzipSuccess = true;
                    }
                }
            }

            if (!$unzipSuccess) {
                throw new \Exception("Failed to extract deployment archive (both ZipArchive and CLI unzip failed or were unavailable).");
            }

            $sqlSnapshot = $projectRoot . '/db_snapshot.sql';
            if (is_file($sqlSnapshot)) {
                if (class_exists('\\SPPMod\\SPPDB\\SPPDB')) {
                    try {
                        $db = new \SPPMod\SPPDB\SPPDB();
                        $pdo = $db->getPDO();
                    } catch (\Throwable $e) {
                        $pdo = null;
                    }
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
            $hooksOutput = [];
            if (file_exists($confFile)) {
                $conf = \SPPMod\SPPDeploy\Deployer\TargetConnection::parseYamlFile($confFile);
                if (isset($conf['post_deploy']) && is_array($conf['post_deploy'])) {
                    foreach ($conf['post_deploy'] as $script) {
                        if (!function_exists('exec')) {
                            $hooksOutput[] = ['script' => $script, 'output' => 'Skipped (exec() disabled on host)'];
                            continue;
                        }
                        $cmd = "cd " . escapeshellarg($projectRoot) . " && " . $script . " 2>&1";
                        exec($cmd, $output, $returnVar);
                        $out = implode("\n", $output);
                        $hooksOutput[] = ['script' => $script, 'output' => $out];
                        unset($output); // clear for next iteration
                        if ($returnVar !== 0) {
                            throw new \Exception("Hook failed: {$script}\nOutput: {$out}");
                        }
                    }
                }

                // Update bundle-manifest.json if present
                $manifestPath = $projectRoot . '/bundle-manifest.json';
                if (file_exists($manifestPath)) {
                    $mJson = @file_get_contents($manifestPath);
                    $mData = $mJson ? json_decode($mJson, true) : null;
                    if (is_array($mData)) {
                        $mData['last_sppdeploy_update'] = date('c');
                        $mData['last_sppdeploy_files_count'] = $filesCount;
                        @file_put_contents($manifestPath, json_encode($mData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                    }
                }

                // Automated backup cleanup
                if (isset($conf['keep_backups']) && is_numeric($conf['keep_backups']) && $conf['keep_backups'] > 0) {
                    $keep = (int) $conf['keep_backups'];
                    $deleted = self::executeCleanup($keep);
                    if ($deleted > 0) {
                        self::logDeployment("CLEANUP: Removed {$deleted} old backups, keeping latest {$keep}.");
                    }
                }

                // Remote Builder: Composer
                if (isset($conf['run_composer']) && $conf['run_composer'] === true) {
                    if (!function_exists('exec')) {
                        $hooksOutput[] = ['script' => 'composer install', 'output' => 'Skipped (exec() disabled on host)'];
                    } else {
                        $composerCmd = "cd " . escapeshellarg($projectRoot) . " && composer install --no-dev --optimize-autoloader 2>&1";
                        exec($composerCmd, $compOutput, $compCode);
                        $compOutStr = implode("\n", $compOutput);
                        $hooksOutput[] = ['script' => 'composer install', 'output' => $compOutStr];
                        if ($compCode !== 0) {
                            throw new \Exception("Composer install failed: \nOutput: {$compOutStr}");
                        }
                    }
                }

                // Health Check
                if (isset($conf['health_check_url']) && filter_var($conf['health_check_url'], FILTER_VALIDATE_URL)) {
                    $healthUrl = $conf['health_check_url'];
                    $context = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 10]]);
                    $response = @file_get_contents($healthUrl, false, $context);
                    if ($response === false) {
                        throw new \Exception("Health check failed: Could not connect to {$healthUrl}");
                    }
                    if (isset($http_response_header[0])) {
                        preg_match('#HTTP/\d+\.\d+ (\d+)#', $http_response_header[0], $match);
                        $code = (int) ($match[1] ?? 0);
                        if ($code >= 500) {
                            throw new \Exception("Health check failed: Endpoint returned HTTP {$code}");
                        }
                    }
                }
            }

            // Post-deploy Cache Invalidation: clear compiled route, module, view, and opcode caches
            $cacheDir = self::getVarDir() . '/cache';
            if (is_dir($cacheDir)) {
                $cacheFiles = glob($cacheDir . '/*');
                if ($cacheFiles) {
                    foreach ($cacheFiles as $cf) {
                        if (is_file($cf) && (str_ends_with($cf, '.php') || (str_ends_with($cf, '.json') && !str_ends_with($cf, 'polyglot_runtimes.json')))) {
                            @unlink($cf);
                        }
                    }
                }
                // Recursively invalidate compiled Blade views
                try {
                    $rIt = new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator($cacheDir, \FilesystemIterator::SKIP_DOTS),
                        \RecursiveIteratorIterator::CHILD_FIRST
                    );
                    foreach ($rIt as $f) {
                        if ($f->isFile() && str_ends_with($f->getFilename(), '.bladec')) {
                            @unlink($f->getPathname());
                        }
                    }
                } catch (\Throwable $e) {}
            }
            if (function_exists('opcache_reset')) {
                @opcache_reset();
            }

            self::logDeployment("SUCCESS: Updated $filesCount file(s) and $dbCount table(s). Backup: $backupResult. Hooks: " . count($hooksOutput), [
                'status' => 'success',
                'filesCount' => $filesCount,
                'dbCount' => $dbCount,
                'backup' => $backupResult
            ]);
            self::sendWebhooks($webhooks, 'production', $filesCount, $dbCount, 'success');

            echo json_encode([
                'status' => 'ok',
                'message' => 'Deployment executed successfully.',
                'webhooks' => $webhooks
            ]);
        } catch (\Throwable $e) {
            self::logDeployment("ERROR: " . $e->getMessage(), [
                'status' => 'error',
                'error' => $e->getMessage()
            ]);

            $rollbackMessage = '';
            if (str_ends_with($backupResult, '.zip')) {
                try {
                    self::executeRollbackInternal($backupResult);
                    $rollbackMessage = " ⚠️ Automated rollback was triggered and completed successfully.";
                } catch (\Exception $re) {
                    $rollbackMessage = " 🚨 Automated rollback FAILED: " . $re->getMessage();
                }
            }

            self::sendWebhooks($webhooks, 'production', $filesCount, $dbCount, 'failed', $e->getMessage() . $rollbackMessage);
            echo json_encode(['status' => 'error', 'message' => 'Deployment failed: ' . $e->getMessage() . $rollbackMessage]);
        } finally {
            if (is_file($tempArchive))
                unlink($tempArchive);
            if (is_file($projectRoot . '/.maintenance'))
                unlink($projectRoot . '/.maintenance');
        }
        exit;
    }

    private static function doAutoBackup(?array $affectedFiles = null): string
    {
        $projectRoot = self::getProjectRoot();
        $backupDir = self::getVarDir() . '/backups';
        if (!is_dir($backupDir))
            mkdir($backupDir, 0777, true);

        $backupName = 'sppdeploy_backup_' . date('Ymd_His') . '.zip';
        $backupPath = $backupDir . '/' . $backupName;

        try {
            $zip = new \ZipArchive();
            if ($zip->open($backupPath, \ZipArchive::CREATE) !== true) {
                return 'Failed to create backup archive';
            }

            if ($affectedFiles !== null) {
                $targetFiles = $affectedFiles;
            } else {
                $scanner = new \SPPMod\SPPDeploy\Scanner\ProjectScanner();
                $targetFiles = array_keys($scanner->scan($projectRoot));
            }

            foreach ($targetFiles as $path) {
                $fullPath = $projectRoot . '/' . $path;
                if (is_file($fullPath)) {
                    $zip->addFile($fullPath, $path);
                }
            }

            if (class_exists('\\SPPMod\\SPPDB\\SPPDB')) {
                try {
                    $db = new \SPPMod\SPPDB\SPPDB();
                    $pdo = $db->getPDO();
                } catch (\Throwable $e) {
                    $pdo = null;
                }
                if ($pdo) {
                    $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
                    $sqlBuffer = "";
                    if ($driver === 'sqlite') {
                        $stmt = $pdo->query("SELECT name, sql FROM sqlite_master WHERE type='table'");
                        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                            $sqlBuffer .= "DROP TABLE IF EXISTS `{$row['name']}`;\n";
                            $sqlBuffer .= $row['sql'] . ";\n";

                            $res = $pdo->query("SELECT * FROM `{$row['name']}`");
                            while ($data = $res->fetch(\PDO::FETCH_ASSOC)) {
                                $cols = array_keys($data);
                                $vals = array_map(fn($v) => $pdo->quote($v), array_values($data));
                                $sqlBuffer .= "INSERT INTO `{$row['name']}` (`" . implode("`,`", $cols) . "`) VALUES (" . implode(",", $vals) . ");\n";
                            }
                        }
                    } elseif ($driver === 'mysql') {
                        $stmt = $pdo->query("SHOW TABLES");
                        while ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
                            $tableName = $row[0];
                            $createStmt = $pdo->query("SHOW CREATE TABLE `{$tableName}`")->fetch(\PDO::FETCH_ASSOC);
                            if ($createStmt && isset($createStmt['Create Table'])) {
                                $sqlBuffer .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
                                $sqlBuffer .= $createStmt['Create Table'] . ";\n";
                            }
                            $res = $pdo->query("SELECT * FROM `{$tableName}`");
                            while ($data = $res->fetch(\PDO::FETCH_ASSOC)) {
                                $cols = array_keys($data);
                                $vals = array_map(fn($v) => $pdo->quote($v), array_values($data));
                                $sqlBuffer .= "INSERT INTO `{$tableName}` (`" . implode("`,`", $cols) . "`) VALUES (" . implode(",", $vals) . ");\n";
                            }
                        }
                    }
                    if ($sqlBuffer !== "") {
                        $zip->addFromString('db_snapshot.sql', $sqlBuffer);
                    }
                }
            }
            $zip->close();
            return "Created $backupName";
        } catch (\Exception $e) {
            return "Backup Error: " . $e->getMessage();
        }
    }

    private static function logDeployment(string $message, array $metadata = []): void
    {
        $logDir = self::getVarDir() . '/logs';
        if (!is_dir($logDir))
            @mkdir($logDir, 0777, true);

        $file = $logDir . '/deploy.log';
        $time = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI/Unknown';
        @file_put_contents($file, "[$time] [IP: $ip] $message\n", FILE_APPEND);

        if (!empty($metadata)) {
            $jsonl = $logDir . '/deploy_history.jsonl';
            $metadata['timestamp'] = $time;
            $metadata['ip'] = $ip;
            $metadata['message'] = $message;
            @file_put_contents($jsonl, json_encode($metadata) . "\n", FILE_APPEND);
        }
    }

    private static function sendWebhooks(array $webhooks, string $mode, int $filesCount, int $dbCount, string $status, string $error = null): void
    {
        if (empty($webhooks))
            return;

        $title = $status === 'success' ? "✅ Deployment Successful" : "❌ Deployment Failed";
        $color = $status === 'success' ? 3066993 : 15158332; // Green : Red

        foreach ($webhooks as $url) {
            if (!filter_var($url, FILTER_VALIDATE_URL))
                continue;

            $payload = '';

            if (str_contains($url, 'discord.com/api/webhooks')) {
                // Discord Rich Embed
                $payload = json_encode([
                    'embeds' => [
                        [
                            'title' => $title,
                            'color' => $color,
                            'fields' => [
                                ['name' => 'Environment', 'value' => $mode, 'inline' => true],
                                ['name' => 'Files Changed', 'value' => (string) $filesCount, 'inline' => true],
                                ['name' => 'Tables Updated', 'value' => (string) $dbCount, 'inline' => true],
                                ['name' => 'Error Message', 'value' => $error ?: 'None', 'inline' => false]
                            ],
                            'timestamp' => date('c')
                        ]
                    ]
                ]);
            } elseif (str_contains($url, 'hooks.slack.com')) {
                // Slack Block Kit
                $emoji = $status === 'success' ? ':white_check_mark:' : ':x:';
                $payload = json_encode([
                    'blocks' => [
                        [
                            'type' => 'header',
                            'text' => ['type' => 'plain_text', 'text' => "{$emoji} {$title}"]
                        ],
                        [
                            'type' => 'section',
                            'fields' => [
                                ['type' => 'mrkdwn', 'text' => "*Environment:*\n{$mode}"],
                                ['type' => 'mrkdwn', 'text' => "*Files:*\n{$filesCount}"],
                                ['type' => 'mrkdwn', 'text' => "*Tables:*\n{$dbCount}"],
                                ['type' => 'mrkdwn', 'text' => "*Error:*\n" . ($error ?: 'None')]
                            ]
                        ]
                    ]
                ]);
            } else {
                // Generic JSON
                $payload = json_encode([
                    'text' => "SPPDeploy: {$title}",
                    'deployment_mode' => $mode,
                    'files_changed_count' => $filesCount,
                    'db_changed_count' => $dbCount,
                    'status' => $status,
                    'error_message' => $error,
                    'timestamp' => date('c')
                ]);
            }

            $options = [
                'http' => [
                    'header' => "Content-type: application/json\r\n",
                    'method' => 'POST',
                    'content' => $payload,
                    'timeout' => 5
                ]
            ];
            $context = stream_context_create($options);
            @file_get_contents($url, false, $context);
        }
    }

    private static function handleBackups(): void
    {
        \SPPMod\SPPDeploy\SPPDeploy::requireAuth();
        $projectRoot = self::getProjectRoot();
        $backupDir = self::getVarDir() . '/backups';
        $backups = [];
        if (is_dir($backupDir)) {
            foreach (scandir($backupDir) as $file) {
                if (str_ends_with($file, '.zip')) {
                    $backups[] = [
                        'filename' => $file,
                        'size' => filesize($backupDir . '/' . $file),
                        'date' => date('Y-m-d H:i:s', filemtime($backupDir . '/' . $file))
                    ];
                }
            }
        }
        usort($backups, fn($a, $b) => $b['date'] <=> $a['date']);
        echo json_encode(['status' => 'ok', 'backups' => $backups]);
        exit;
    }

    private static function handleRollback(): void
    {
        \SPPMod\SPPDeploy\SPPDeploy::requireAuth();
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? null;
        if (!$id || !preg_match('/^[a-zA-Z0-9_\-\.]+$/', $id)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid backup ID']);
            exit;
        }

        try {
            self::executeRollbackInternal($id);
            echo json_encode(['status' => 'ok', 'message' => "Successfully rolled back to {$id}"]);
        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => "Rollback failed: " . $e->getMessage()]);
        }
        exit;
    }

    private static function executeRollbackInternal(string $id): void
    {
        $projectRoot = self::getProjectRoot();
        $backupFile = self::getVarDir() . '/backups/' . $id;
        if (!is_file($backupFile)) {
            throw new \Exception('Backup not found');
        }

        file_put_contents($projectRoot . '/.maintenance', 'Rolling back deployment...');
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        try {
            $scanner = new \SPPMod\SPPDeploy\Scanner\ProjectScanner();
            $files = $scanner->scan($projectRoot);

            foreach (array_keys($files) as $path) {
                $fullPath = $projectRoot . '/' . $path;
                if (is_file($fullPath)) {
                    unlink($fullPath);
                }
            }

            $zip = new \ZipArchive();
            if ($zip->open($backupFile) === true) {
                $zip->extractTo($projectRoot);
                $zip->close();
            } else {
                throw new \Exception("Failed to open backup archive.");
            }

            $sqlFile = $projectRoot . '/db_snapshot.sql';
            if (is_file($sqlFile) && class_exists('\\SPPMod\\SPPDB\\SPPDB')) {
                try {
                    $db = new \SPPMod\SPPDB\SPPDB();
                    $pdo = $db->getPDO();
                } catch (\Throwable $e) {
                    $pdo = null;
                }
                if ($pdo) {
                    $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
                    if ($driver === 'mysql') {
                        $pdo->exec("SET FOREIGN_KEY_CHECKS=0;");
                    }
                    $sqlContent = file_get_contents($sqlFile);
                    $pdo->exec($sqlContent);
                    if ($driver === 'mysql') {
                        $pdo->exec("SET FOREIGN_KEY_CHECKS=1;");
                    }
                }
                unlink($sqlFile);
            }
        } finally {
            if (is_file($projectRoot . '/.maintenance')) {
                unlink($projectRoot . '/.maintenance');
            }
        }
    }

    private static function handleLogs(): void
    {
        \SPPMod\SPPDeploy\SPPDeploy::requireAuth();
        $projectRoot = self::getProjectRoot();

        $offset = isset($_GET['offset']) ? (int) $_GET['offset'] : -1;
        $linesToFetch = $_GET['lines'] ?? 100;
        $linesToFetch = max(10, min((int) $linesToFetch, 1000));

        // Find the most recently modified log file in projectRoot/var/logs/
        $logDir = is_dir($projectRoot . '/var/logs') ? $projectRoot . '/var/logs' : $projectRoot . '/spp/var/logs';
        $latestLog = null;
        $latestTime = 0;

        if (is_dir($logDir)) {
            $files = glob($logDir . '/*.log');
            if ($files) {
                foreach ($files as $file) {
                    $mtime = filemtime($file);
                    if ($mtime > $latestTime) {
                        $latestTime = $mtime;
                        $latestLog = $file;
                    }
                }
            }
        }

        if (!$latestLog) {
            echo json_encode(['status' => 'error', 'message' => 'No log files found']);
            exit;
        }

        $content = '';
        $newOffset = 0;

        if ($offset >= 0) {
            // Offset mode (for --tail)
            $fp = fopen($latestLog, 'r');
            if ($fp) {
                fseek($fp, $offset);
                while (!feof($fp)) {
                    $content .= fread($fp, 8192);
                }
                $newOffset = ftell($fp);
                fclose($fp);
            }
        } else {
            // Lines mode (initial load) - seek from end of file safely to avoid memory exhaustion on large logs
            $fileSize = filesize($latestLog);
            $bytesToRead = min($fileSize, 256 * 1024); // Read at most 256KB from end
            $fp = fopen($latestLog, 'r');
            if ($fp) {
                fseek($fp, max(0, $fileSize - $bytesToRead));
                $rawChunk = fread($fp, $bytesToRead);
                fclose($fp);
                $lines = explode("\n", str_replace("\r", "", (string)$rawChunk));
                $tail = array_slice($lines, -$linesToFetch);
                $content = implode("\n", $tail) . "\n";
            }
            $newOffset = $fileSize;
        }

        echo json_encode([
            'status' => 'ok',
            'file' => basename($latestLog),
            'content' => $content,
            'offset' => $newOffset
        ]);
        exit;
    }

    private static function handleRun(): void
    {
        \SPPMod\SPPDeploy\SPPDeploy::requireAuth();

        $input = json_decode(file_get_contents('php://input'), true);
        $command = $input['command'] ?? null;

        if (!$command) {
            echo json_encode(['status' => 'error', 'message' => 'Command not provided']);
            exit;
        }

        if (!function_exists('exec')) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Command execution disabled on host (exec() function disabled in php.ini)',
                'command' => $command,
                'output' => '',
                'exit_code' => 127
            ]);
            exit;
        }

        $baseDir = self::getProjectRoot();
        $fullCmd = "cd " . escapeshellarg($baseDir) . " && " . $command . " 2>&1";

        exec($fullCmd, $output, $returnVar);
        $outStr = implode("\n", $output);

        echo json_encode([
            'status' => 'ok',
            'command' => $command,
            'output' => $outStr,
            'exit_code' => $returnVar
        ]);
        exit;
    }

    private static function handleHistory(): void
    {
        \SPPMod\SPPDeploy\SPPDeploy::requireAuth();
        $projectRoot = self::getProjectRoot();
        $jsonl = is_file($projectRoot . '/var/logs/deploy_history.jsonl')
            ? ($projectRoot . '/var/logs/deploy_history.jsonl')
            : ($projectRoot . '/spp/var/logs/deploy_history.jsonl');
        $history = [];

        if (is_file($jsonl)) {
            $lines = file($jsonl, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines !== false) {
                // Get last 50
                $lines = array_slice($lines, -50);
                foreach ($lines as $line) {
                    $decoded = json_decode($line, true);
                    if ($decoded) {
                        $history[] = $decoded;
                    }
                }
            }
        }

        echo json_encode(['status' => 'ok', 'history' => array_reverse($history)]);
        exit;
    }

    private static function handleMaintenance(): void
    {
        \SPPMod\SPPDeploy\SPPDeploy::requireAuth();

        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $state = $input['state'] ?? ($_POST['state'] ?? ($_GET['state'] ?? 'off'));
        $base = self::getProjectRoot();
        $lockFile = $base . '/.maintenance';

        $candidatePaths = array_unique(array_filter([
            $lockFile,
            dirname($base) . '/.maintenance',
            defined('SPP_BASE_DIR') ? SPP_BASE_DIR . '/.maintenance' : null,
            defined('SPP_APP_DIR') ? SPP_APP_DIR . '/.maintenance' : null,
        ]));

        if ($state === 'on') {
            file_put_contents($lockFile, 'Site is undergoing manual maintenance. Please check back later.');
            if (defined('SPP_BASE_DIR') && SPP_BASE_DIR !== $base) {
                @file_put_contents(SPP_BASE_DIR . '/.maintenance', 'Site is undergoing manual maintenance. Please check back later.');
            }
            self::logDeployment("MAINTENANCE: Mode explicitly enabled.");
            echo json_encode(['status' => 'ok', 'message' => 'Maintenance mode enabled.']);
        } else {
            foreach ($candidatePaths as $p) {
                if (is_file($p)) {
                    @unlink($p);
                }
            }
            self::logDeployment("MAINTENANCE: Mode explicitly disabled.");
            echo json_encode(['status' => 'ok', 'message' => 'Maintenance mode disabled.']);
        }
        exit;
    }

    private static function handleEnvPush(): void
    {
        \SPPMod\SPPDeploy\SPPDeploy::requireAuth();

        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $key = $input['key'] ?? ($_POST['key'] ?? null);
        $value = $input['value'] ?? ($_POST['value'] ?? null);

        if (!$key) {
            echo json_encode(['status' => 'error', 'message' => 'Key is required.']);
            exit;
        }

        $base = self::getProjectRoot();
        $envFile = $base . '/.env';
        if (!file_exists($envFile) && file_exists(dirname($base) . '/.env')) {
            $envFile = dirname($base) . '/.env';
        }
        $envData = file_exists($envFile) ? file_get_contents($envFile) : "";

        $lines = explode("\n", $envData);
        $found = false;

        foreach ($lines as &$line) {
            $lineTrimmed = trim($line);
            if (str_starts_with($lineTrimmed, $key . '=')) {
                $line = $key . '=' . $value;
                $found = true;
                break;
            }
        }

        if (!$found) {
            if (!empty($lines[count($lines) - 1])) {
                $lines[] = ''; // ensure newline
            }
            $lines[] = $key . '=' . $value;
        }

        file_put_contents($envFile, implode("\n", $lines));
        self::logDeployment("ENV: Pushed environment variable `{$key}`.");

        echo json_encode(['status' => 'ok', 'message' => "Environment variable `{$key}` securely saved."]);
        exit;
    }

    private static function executeCleanup(int $keep): int
    {
        $backupDir = self::getVarDir() . '/backups';
        if (!is_dir($backupDir))
            return 0;

        $backups = [];
        foreach (scandir($backupDir) as $file) {
            if (str_ends_with($file, '.zip')) {
                $backups[$file] = filemtime($backupDir . '/' . $file);
            }
        }

        if (count($backups) <= $keep)
            return 0;

        arsort($backups); // Sort descending by time
        $toDelete = array_slice(array_keys($backups), $keep);

        $deleted = 0;
        foreach ($toDelete as $file) {
            if (unlink($backupDir . '/' . $file)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    private static function handleCleanup(): void
    {
        \SPPMod\SPPDeploy\SPPDeploy::requireAuth();

        $input = json_decode(file_get_contents('php://input'), true);
        $keep = isset($input['keep']) ? (int) $input['keep'] : 5;

        $deleted = self::executeCleanup($keep);
        self::logDeployment("CLEANUP: Removed {$deleted} old backups, keeping latest {$keep}.");

        echo json_encode(['status' => 'ok', 'message' => "Cleanup complete. Deleted {$deleted} old backups.", 'deleted' => $deleted]);
        exit;
    }

    /**
     * Resolves target destination paths for an incoming file entry based on multi-directory topology.
     * - Framework files -> Framework directory ($projectRoot)
     * - Domain app files -> $projectRoot/domains/<domain>/ AND external domain directory if configured/discovered
     * - Global apps -> $projectRoot (or designated apps_dir/global_apps_dir if configured)
     *
     * @param string $entry Relative archive path (e.g. 'spp/admin/services/StorageAdminService.php')
     * @param string $projectRoot The SPP framework project root
     * @param array $conf Target server .sppdeploy.yml configuration
     * @return array<string> List of absolute filesystem paths to write this file to
     */
    public static function resolveDestinationPaths(string $entry, string $projectRoot, array $conf = []): array
    {
        $dests = [];
        $cleanEntry = ltrim(str_replace('\\', '/', $entry), '/');

        // Always include the canonical path in the framework workspace
        $canonicalDest = $projectRoot . '/' . $cleanEntry;
        $dests[] = $canonicalDest;

        // 1. Domain Directory Routing (domains/<domain>/...)
        if (str_starts_with($cleanEntry, 'domains/')) {
            $parts = explode('/', $cleanEntry, 3);
            $domain = $parts[1] ?? '';
            $subPath = $parts[2] ?? '';

            if ($domain !== '' && $subPath !== '') {
                // Check explicit domain roots mapping in .sppdeploy.yml
                $domainRoots = (array)($conf['domain_roots'] ?? ($conf['domains'] ?? []));
                $targetDomainRoot = $domainRoots[$domain] ?? null;

                // Check DomainRouter configuration for user home app_dir (e.g. /home/user/apps/<app>)
                if (class_exists('\\SPP\\Core\\Router\\DomainRouter')) {
                    try {
                        \SPP\Core\Router\DomainRouter::loadConfig();
                        $dCfg = \SPP\Core\Router\DomainRouter::resolveDomain($domain);
                        if ($dCfg) {
                            $userAppDir = $dCfg['app_dir'] ?? null;
                            if (!$userAppDir && !empty($dCfg['account']) && $dCfg['account'] !== 'default' && is_dir('/home/' . $dCfg['account'] . '/apps')) {
                                $userAppDir = '/home/' . $dCfg['account'] . '/apps';
                            }
                            if ($userAppDir && str_starts_with($subPath, 'src/')) {
                                $appRel = substr($subPath, 4); // strip 'src/'
                                $userDest = rtrim(str_replace('\\', '/', $userAppDir), '/') . '/' . $appRel;
                                $dests[] = $userDest;
                            }
                        }
                    } catch (\Throwable $e) {}
                }

                // Also check dynamic site root discovery from StorageAdminService / DomainRouter if present
                if (!$targetDomainRoot && class_exists('\\SPP\\Admin\\Services\\StorageAdminService')) {
                    try {
                        $siteRoots = \SPP\Admin\Services\StorageAdminService::getConfiguredSiteRoots();
                        foreach ($siteRoots as $sr) {
                            if (($sr['domain'] ?? '') === $domain || ($sr['pattern'] ?? '') === $domain) {
                                $cand = $sr['home_path'] ?? '';
                                if ($cand && is_dir($cand) && $cand !== ($projectRoot . '/domains/' . $domain)) {
                                    $targetDomainRoot = $cand;
                                    break;
                                }
                            }
                        }
                    } catch (\Throwable $e) {}
                }

                if ($targetDomainRoot && is_dir($targetDomainRoot)) {
                    $cleanDomRoot = rtrim(str_replace('\\', '/', $targetDomainRoot), '/');
                    $dests[] = $cleanDomRoot . '/' . $subPath;
                }
            }
        }

        // 2. Global Apps Routing (src/<app>/... or apps/<app>/...)
        elseif (str_starts_with($cleanEntry, 'src/') || str_starts_with($cleanEntry, 'apps/')) {
            $designatedAppsDir = $conf['global_apps_dir'] ?? ($conf['apps_dir'] ?? null);
            if ($designatedAppsDir) {
                $cleanAppsDir = rtrim(str_replace('\\', '/', $designatedAppsDir), '/');
                if (is_dir($cleanAppsDir) && $cleanAppsDir !== $projectRoot) {
                    $dests[] = $cleanAppsDir . '/' . $cleanEntry;
                }
            }
        }

        // 3. Central Framework Directory Routing (/var/www/spp or configured framework_dir)
        $designatedFrameworkDir = $conf['framework_dir'] ?? ($conf['framework'] ?? null);
        if (!$designatedFrameworkDir && is_dir('/var/www/spp')) {
            $designatedFrameworkDir = '/var/www/spp';
        }
        if ($designatedFrameworkDir) {
            $cleanFwDir = rtrim(str_replace('\\', '/', $designatedFrameworkDir), '/');
            if (is_dir($cleanFwDir) && realpath($cleanFwDir) !== realpath($projectRoot)) {
                if (str_starts_with($cleanEntry, 'spp/') || str_starts_with($cleanEntry, 'public/') || in_array($cleanEntry, ['index.php', 'spp.php', 'router.php'], true)) {
                    $dests[] = $cleanFwDir . '/' . $cleanEntry;
                }
            }
        }

        return array_values(array_unique($dests));
    }
}
