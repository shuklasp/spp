<?php
namespace SPPMod\SPPDeploy\Api;

class Receiver
{
    public static function handle(string $path): void
    {
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

    private static function handleHealth(): void
    {
        \SPPMod\SPPDeploy\SPPDeploy::requireAuth();
        $isDbOk = false;
        try {
            $db = \SPP\Core\DB::getInstance();
            if ($db) {
                $db->query("SELECT 1")->execute();
                $isDbOk = true;
            }
        } catch (\Exception $e) {
        }

        echo json_encode([
            'status' => 'ok',
            'health' => [
                'php_version' => PHP_VERSION,
                'zip_extension' => extension_loaded('zip'),
                'curl_extension' => extension_loaded('curl'),
                'var_writable' => is_writable(SPP_BASE_DIR . '/spp/var'),
                'db_connected' => $isDbOk
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
        $backupDir = SPP_BASE_DIR . '/var/backups';
        if (!is_dir($backupDir))
            mkdir($backupDir, 0777, true);

        $backupPath = $backupDir . '/sppdeploy_export_' . time() . '.zip';
        $zip = new \ZipArchive();
        if ($zip->open($backupPath, \ZipArchive::CREATE) !== true) {
            echo json_encode(['status' => 'error', 'message' => 'Failed to create export archive.']);
            exit;
        }

        $scanner = new \SPPMod\SPPDeploy\Scanner\ProjectScanner();
        $files = $scanner->scan(SPP_BASE_DIR);
        foreach (array_keys($files) as $path) {
            $fullPath = dirname(SPP_BASE_DIR) . '/' . $path;
            if (is_file($fullPath)) {
                $zip->addFile($fullPath, $path);
            }
        }

        if (class_exists('\\SPPMod\\SPPDB\\SPPDB')) {
            $db = new \SPPMod\SPPDB\SPPDB();
            $pdo = $db->getPDO();
            if ($pdo) {
                $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
                $sqlBuffer = "";

                $anonymize = [];
                $confFile = dirname(SPP_BASE_DIR) . '/.sppdeploy.yml';
                if (file_exists($confFile)) {
                    $conf = @yaml_parse_file($confFile);
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

        $localScanner = new \SPPMod\SPPDeploy\Scanner\ProjectScanner();
        $localHashes = $localScanner->scan(SPP_BASE_DIR);

        $localDbScanner = new \SPPMod\SPPDeploy\Scanner\DbScanner();
        $localDbHashes = $localDbScanner->scan();

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

        $sessionId = $data['session_id'];
        $chunkData = base64_decode($data['chunk_data']);
        $isLast = $data['is_last'] ?? false;

        $tempArchive = SPP_BASE_DIR . '/var/cache/' . $sessionId . '.zip';
        if (!is_dir(dirname($tempArchive))) {
            mkdir(dirname($tempArchive), 0777, true);
        }

        file_put_contents($tempArchive, $chunkData, FILE_APPEND);

        if ($isLast) {
            $payloadData = $data['payload_data'] ?? [];
            self::processDeploymentPayload($tempArchive, $payloadData);
        } else {
            echo json_encode(['status' => 'ok', 'message' => 'Chunk received.']);
            exit;
        }
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

        $tempArchive = SPP_BASE_DIR . '/var/cache/deploy_incoming.zip';
        if (!is_dir(dirname($tempArchive))) {
            mkdir(dirname($tempArchive), 0777, true);
        }
        file_put_contents($tempArchive, base64_decode($payloadData['archive']));

        self::processDeploymentPayload($tempArchive, $payloadData);
    }

    private static function processDeploymentPayload(string $tempArchive, array $payloadData): void
    {
        $diff = $payloadData['diff'] ?? ['files' => ['create' => [], 'update' => [], 'delete' => []], 'db' => ['create' => [], 'update' => [], 'delete' => []]];
        $filesCount = $payloadData['filesCount'] ?? 0;
        $dbCount = $payloadData['dbCount'] ?? 0;
        $isDryRun = $payloadData['dry_run'] ?? false;

        $webhooks = [];
        $confFile = dirname(SPP_BASE_DIR) . '/.sppdeploy.yml';
        if (file_exists($confFile)) {
            $conf = @yaml_parse_file($confFile);
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

        $lockFile = dirname(SPP_BASE_DIR) . '/.maintenance';
        file_put_contents($lockFile, 'Deployment in progress...');

        $backupResult = self::doAutoBackup();

        try {
            foreach ($diff['files']['delete'] as $path) {
                $fullPath = dirname(SPP_BASE_DIR) . '/' . $path;
                if (is_file($fullPath)) {
                    unlink($fullPath);
                }
            }

            $targetDir = dirname(SPP_BASE_DIR);

            // 1. Try native unzip (Fastest, lowest memory)
            $unzipSuccess = false;
            $unzipCmd = "unzip -o " . escapeshellarg($tempArchive) . " -d " . escapeshellarg($targetDir) . " 2>&1";
            @exec($unzipCmd, $unzipOutput, $unzipCode);
            if ($unzipCode === 0) {
                $unzipSuccess = true;
            } else {
                // 2. Try native tar (Windows 10+ / Linux fallback)
                $tarCmd = "tar -xf " . escapeshellarg($tempArchive) . " -C " . escapeshellarg($targetDir) . " 2>&1";
                @exec($tarCmd, $tarOutput, $tarCode);
                if ($tarCode === 0) {
                    $unzipSuccess = true;
                }
            }

            // 3. Fallback to PHP ZipArchive (Slowest, high memory)
            if (!$unzipSuccess) {
                $zip = new \ZipArchive();
                if ($zip->open($tempArchive) === true) {
                    $zip->extractTo($targetDir);
                    $zip->close();
                } else {
                    throw new \Exception("Failed to open extracted zip payload (Native unzips also failed).");
                }
            }

            $sqlSnapshot = SPP_BASE_DIR . '/db_snapshot.sql';
            if (is_file($sqlSnapshot)) {
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
            $hooksOutput = [];
            if (file_exists($confFile)) {
                $conf = @yaml_parse_file($confFile);
                if (isset($conf['post_deploy']) && is_array($conf['post_deploy'])) {
                    foreach ($conf['post_deploy'] as $script) {
                        $cmd = "cd " . escapeshellarg(dirname(SPP_BASE_DIR)) . " && " . $script . " 2>&1";
                        exec($cmd, $output, $returnVar);
                        $out = implode("\n", $output);
                        $hooksOutput[] = ['script' => $script, 'output' => $out];
                        unset($output); // clear for next iteration
                        if ($returnVar !== 0) {
                            throw new \Exception("Hook failed: {$script}\nOutput: {$out}");
                        }
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
                    $composerCmd = "cd " . escapeshellarg(dirname(SPP_BASE_DIR)) . " && composer install --no-dev --optimize-autoloader 2>&1";
                    exec($composerCmd, $compOutput, $compCode);
                    $compOutStr = implode("\n", $compOutput);
                    $hooksOutput[] = ['script' => 'composer install', 'output' => $compOutStr];
                    if ($compCode !== 0) {
                        throw new \Exception("Composer install failed: \nOutput: {$compOutStr}");
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
        } catch (\Exception $e) {
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
            if (is_file(dirname(SPP_BASE_DIR) . '/.maintenance'))
                unlink(dirname(SPP_BASE_DIR) . '/.maintenance');
        }
        exit;
    }

    private static function doAutoBackup(): string
    {
        $backupDir = SPP_BASE_DIR . '/var/backups';
        if (!is_dir($backupDir))
            mkdir($backupDir, 0777, true);

        $backupName = 'sppdeploy_backup_' . date('Ymd_His') . '.zip';
        $backupPath = $backupDir . '/' . $backupName;

        try {
            $zip = new \ZipArchive();
            if ($zip->open($backupPath, \ZipArchive::CREATE) !== true) {
                return 'Failed to create backup archive';
            }

            $scanner = new \SPPMod\SPPDeploy\Scanner\ProjectScanner();
            $files = $scanner->scan(SPP_BASE_DIR);

            foreach (array_keys($files) as $path) {
                $fullPath = SPP_BASE_DIR . '/' . $path;
                if (is_file($fullPath)) {
                    $zip->addFile($fullPath, $path);
                }
            }

            if (class_exists('\\SPPMod\\SPPDB\\SPPDB')) {
                $db = new \SPPMod\SPPDB\SPPDB();
                $pdo = $db->getPDO();
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
        $logDir = SPP_BASE_DIR . '/spp/var/logs';
        if (!is_dir($logDir))
            mkdir($logDir, 0777, true);

        $file = $logDir . '/deploy.log';
        $time = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI/Unknown';
        file_put_contents($file, "[$time] [IP: $ip] $message\n", FILE_APPEND);

        if (!empty($metadata)) {
            $jsonl = $logDir . '/deploy_history.jsonl';
            $metadata['timestamp'] = $time;
            $metadata['ip'] = $ip;
            $metadata['message'] = $message;
            file_put_contents($jsonl, json_encode($metadata) . "\n", FILE_APPEND);
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
        $backupDir = SPP_BASE_DIR . '/var/backups';
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
        $backupFile = SPP_BASE_DIR . '/var/backups/' . $id;
        if (!is_file($backupFile)) {
            throw new \Exception('Backup not found');
        }

        file_put_contents(SPP_BASE_DIR . '/.maintenance', 'Rolling back deployment...');
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        try {
            $scanner = new \SPPMod\SPPDeploy\Scanner\ProjectScanner();
            $files = $scanner->scan(SPP_BASE_DIR);

            foreach (array_keys($files) as $path) {
                $fullPath = SPP_BASE_DIR . '/' . $path;
                if (is_file($fullPath)) {
                    unlink($fullPath);
                }
            }

            $zip = new \ZipArchive();
            if ($zip->open($backupFile) === true) {
                $zip->extractTo(SPP_BASE_DIR);
                $zip->close();
            } else {
                throw new \Exception("Failed to open backup archive.");
            }

            $sqlFile = SPP_BASE_DIR . '/db_snapshot.sql';
            if (is_file($sqlFile) && class_exists('\\SPPMod\\SPPDB\\SPPDB')) {
                $db = new \SPPMod\SPPDB\SPPDB();
                $pdo = $db->getPDO();
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
            if (is_file(dirname(SPP_BASE_DIR) . '/.maintenance')) {
                unlink(dirname(SPP_BASE_DIR) . '/.maintenance');
            }
        }
    }

    private static function handleLogs(): void
    {
        \SPPMod\SPPDeploy\SPPDeploy::requireAuth();

        $offset = isset($_GET['offset']) ? (int) $_GET['offset'] : -1;
        $linesToFetch = $_GET['lines'] ?? 100;
        $linesToFetch = max(10, min((int) $linesToFetch, 1000));

        // Find the most recently modified log file in SPP_BASE_DIR/var/logs/
        $logDir = SPP_BASE_DIR . '/var/logs';
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
            // Lines mode (initial load)
            $lines = file($latestLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines !== false) {
                $tail = array_slice($lines, -$linesToFetch);
                $content = implode("\n", $tail) . "\n";
            }
            $newOffset = filesize($latestLog);
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

        $baseDir = dirname(SPP_BASE_DIR);
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

        $jsonl = SPP_BASE_DIR . '/spp/var/logs/deploy_history.jsonl';
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

        $state = $_POST['state'] ?? $_GET['state'] ?? 'off';
        $lockFile = dirname(SPP_BASE_DIR) . '/.maintenance';

        if ($state === 'on') {
            file_put_contents($lockFile, 'Site is undergoing manual maintenance. Please check back later.');
            self::logDeployment("MAINTENANCE: Mode explicitly enabled.");
            echo json_encode(['status' => 'ok', 'message' => 'Maintenance mode enabled.']);
        } else {
            if (is_file($lockFile))
                unlink($lockFile);
            self::logDeployment("MAINTENANCE: Mode explicitly disabled.");
            echo json_encode(['status' => 'ok', 'message' => 'Maintenance mode disabled.']);
        }
        exit;
    }

    private static function handleEnvPush(): void
    {
        \SPPMod\SPPDeploy\SPPDeploy::requireAuth();

        $key = $_POST['key'] ?? null;
        $value = $_POST['value'] ?? null;

        if (!$key) {
            echo json_encode(['status' => 'error', 'message' => 'Key is required.']);
            exit;
        }

        $envFile = dirname(SPP_BASE_DIR) . '/.env';
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
        $backupDir = SPP_BASE_DIR . '/var/backups';
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
}
