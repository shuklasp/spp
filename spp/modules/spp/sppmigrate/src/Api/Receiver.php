<?php
namespace SPPMod\SPPMigrate\Api;

class Receiver {
    public static function handle(string $path): void {
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

    private static function handleDiff(): void {
        // Receives client's file hashes and compares with local file hashes
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !isset($input['hashes'])) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid payload, expected hashes.']);
            exit;
        }

        $localScanner = new \SPPMod\SPPMigrate\Scanner\ProjectScanner();
        $localHashes = $localScanner->scan(SPP_APP_DIR);

        $diff = [
            'create' => [],
            'update' => [],
            'delete' => []
        ];

        $clientHashes = $input['hashes'];

        foreach ($clientHashes as $file => $hash) {
            if (!isset($localHashes[$file])) {
                $diff['create'][] = $file;
            } elseif ($localHashes[$file] !== $hash) {
                $diff['update'][] = $file;
            }
        }

        foreach ($localHashes as $file => $hash) {
            if (!isset($clientHashes[$file])) {
                $diff['delete'][] = $file;
            }
        }

        echo json_encode(['status' => 'ok', 'diff' => $diff]);
        exit;
    }

    private static function handleDeploy(): void {
        // Handles JSON payload or ZIP file payload
        echo json_encode(['status' => 'ok', 'message' => 'Deploy endpoint not yet fully implemented.']);
        exit;
    }
}
