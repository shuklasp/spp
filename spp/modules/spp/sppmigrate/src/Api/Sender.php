<?php

namespace SPPMod\SPPMigrate\Api;

class Sender
{
    public static function handle(string $path): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (str_ends_with($path, '/sender/ping')) {
            self::handlePing();
        }

        if (str_ends_with($path, '/sender/diff')) {
            self::handleDiff();
        }

        if (str_ends_with($path, '/sender/deploy')) {
            self::handleDeploy();
        }

        echo json_encode(['status' => 'error', 'message' => 'Sender Endpoint not found']);
        exit;
    }

    private static function getTargetConnection(): \SPPMod\SPPMigrate\Deployer\TargetConnection
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $url = $input['target_url'] ?? '';
        $key = $input['api_key'] ?? '';

        if (empty($url)) {
            echo json_encode(['status' => 'error', 'message' => 'Missing target_url']);
            exit;
        }

        return new \SPPMod\SPPMigrate\Deployer\TargetConnection($url, $key);
    }

    private static function handlePing(): void
    {
        try {
            $conn = self::getTargetConnection();
            $resp = $conn->ping();
            echo json_encode($resp);
        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    private static function handleDiff(): void
    {
        try {
            $conn = self::getTargetConnection();

            $scanner = new \SPPMod\SPPMigrate\Scanner\ProjectScanner();
            $fileHashes = $scanner->scan(SPP_BASE_DIR);

            $dbScanner = new \SPPMod\SPPMigrate\Scanner\DbScanner();
            $dbHashes = $dbScanner->scan();

            $localHashes = [
                'files' => $fileHashes,
                'db' => $dbHashes
            ];

            $resp = $conn->getDiff($localHashes);
            echo json_encode($resp);
        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    private static function handleDeploy(): void
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $payload = $input['payload'] ?? null;
            $mode = $input['mode'] ?? 'incremental';
            if (!$payload) {
                throw new \Exception('Missing deployment payload');
            }

            $conn = self::getTargetConnection();

            $filesToProcess = array_merge($payload['files']['create'] ?? [], $payload['files']['update'] ?? []);

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

            $dbSchemas = [];
            $tablesToProcess = array_merge($payload['db']['create'] ?? [], $payload['db']['update'] ?? []);

            $db = new \SPPMod\SPPDB\SPPDB();
            $pdo = $db->getPDO();
            $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

            foreach ($tablesToProcess as $table) {
                $actualTable = \SPPMod\SPPDB\SPPDB::sppTable($table);
                if ($driver === 'sqlite') {
                    $stmt = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='{$actualTable}'");
                    $row = $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : null;
                    if ($row) {
                        $dbSchemas[$table] = $row['sql'];
                    }
                } else {
                    $stmt = $pdo->query("SHOW CREATE TABLE {$actualTable}");
                    $row = $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : null;
                    if ($row) {
                        $dbSchemas[$table] = $row['Create Table'];
                    }
                }
            }
            $payload['db_schema'] = $dbSchemas;
            $payload['mode'] = $mode;

            $resp = $conn->deploy($payload);
            echo json_encode($resp);
        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
}
