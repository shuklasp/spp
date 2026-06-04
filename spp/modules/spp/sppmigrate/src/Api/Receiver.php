<?php

namespace SPPMod\SPPMigrate\Api;

class Receiver
{
    public static function handle(string $path): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (str_ends_with($path, '/ping')) {
            echo json_encode(['status' => 'ok', 'message' => 'SPPMigrate Receiver Ready', 'version' => '1.0.0']);
            exit;
        }

        if (str_ends_with($path, '/diff')) {
            self::handleDiff();
        }

        if (str_ends_with($path, '/deploy')) {
            self::handleDeploy();
        }

        echo json_encode(['status' => 'error', 'message' => 'Endpoint not found']);
        exit;
    }

    private static function handleDiff(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !isset($input['hashes'])) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid payload, expected hashes.']);
            exit;
        }

        $localScanner = new \SPPMod\SPPMigrate\Scanner\ProjectScanner();
        $localHashes = $localScanner->scan(SPP_BASE_DIR);

        $localDbScanner = new \SPPMod\SPPMigrate\Scanner\DbScanner();
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

    private static function doAutoBackup(): void
    {
        $backupDir = SPP_BASE_DIR . '/var/backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0777, true);
        }

        $backupFile = $backupDir . '/sppmigrate_backup_' . date('Ymd_His') . '.zip';
        $zip = new \ZipArchive();

        if ($zip->open($backupFile, \ZipArchive::CREATE) === true) {
            $scanner = new \SPPMod\SPPMigrate\Scanner\ProjectScanner();
            $files = $scanner->scan(SPP_BASE_DIR); // Returns path => hash

            foreach (array_keys($files) as $path) {
                $fullPath = SPP_BASE_DIR . '/' . $path;
                if (is_file($fullPath)) {
                    $zip->addFile($fullPath, $path);
                }
            }
            $zip->close();
        }
    }

    private static function handleDeploy(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !isset($input['files'])) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid deploy payload']);
            exit;
        }

        $mode = $input['mode'] ?? 'incremental';

        try {
            // Pre-deployment safety: Create a full state backup
            self::doAutoBackup();

            // 1. Process files payload (base64 or zip)
            if (isset($input['files_content'])) {
                foreach ($input['files_content'] as $path => $b64content) {
                    $fullPath = SPP_BASE_DIR . '/' . $path;
                    $dir = dirname($fullPath);
                    if (!is_dir($dir)) {
                        mkdir($dir, 0777, true);
                    }
                    file_put_contents($fullPath, base64_decode($b64content));
                }
            } elseif (isset($input['files_zip'])) {
                $zipFile = sys_get_temp_dir() . '/sppmigrate_recv_' . time() . '.zip';
                file_put_contents($zipFile, base64_decode($input['files_zip']));
                $zip = new \ZipArchive();
                if ($zip->open($zipFile) === true) {
                    $zip->extractTo(SPP_BASE_DIR);
                    $zip->close();
                }
                unlink($zipFile);
            }

            // 2. Process file deletions (ONLY if mode is 'full')
            if ($mode === 'full') {
                foreach ($input['files']['delete'] ?? [] as $path) {
                    $fullPath = SPP_BASE_DIR . '/' . $path;
                    if (is_file($fullPath)) {
                        unlink($fullPath);
                    }
                }
            }

            // 3. Process DB schema updates
            if (isset($input['db_schema']) || isset($input['db']['delete'])) {
                $db = new \SPPMod\SPPDB\SPPDB();
                $pdo = $db->getPDO();

                foreach ($input['db_schema'] ?? [] as $table => $sql) {
                    $actualTable = \SPPMod\SPPDB\SPPDB::sppTable($table);
                    // For safety, only run if the sql starts with CREATE
                    if (str_starts_with(strtoupper(trim($sql)), 'CREATE TABLE')) {
                        $pdo->exec("DROP TABLE IF EXISTS `{$actualTable}`");
                        $pdo->exec($sql);
                    }
                }

                if ($mode === 'full') {
                    foreach ($input['db']['delete'] ?? [] as $table) {
                        $actualTable = \SPPMod\SPPDB\SPPDB::sppTable($table);
                        $pdo->exec("DROP TABLE IF EXISTS `{$actualTable}`");
                    }
                }
            }

            echo json_encode(['status' => 'ok', 'message' => 'Deployment executed successfully in ' . strtoupper($mode) . ' mode.']);
        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'Deployment failed: ' . $e->getMessage()]);
        }
        exit;
    }
}
