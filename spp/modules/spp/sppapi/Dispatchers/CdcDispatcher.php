<?php
declare(strict_types=1);

namespace SPPMod\SPPAPI\Dispatchers;

class CdcDispatcher
{
    public static function dispatch(): void
    {
        // Unauthenticated Information Exposure Protection
        if (!\SPPMod\SPPAPI\SPPAPI::checkAuth()) {
            http_response_code(401);
            echo "event: error\ndata: Unauthorized\n\n";
            exit;
        }

        while (ob_get_level()) {
            ob_end_clean();
        }
        http_response_code(200);
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        $island = trim($_REQUEST['island'] ?? 'global');
        $emit = function (string $type, array $data) use ($island) {
            $payload = json_encode(['island' => $island, 'timestamp' => microtime(true), 'mutation' => $data]);
            echo "event: {$type}\ndata: {$payload}\n\n";
            @ob_flush();
            @flush();
        };

        $emit('cdc_init', ['status' => 'listening', 'target_island' => $island]);
        // Simulate initial state tail payload block
        $emit('cdc_update', ['operation' => 'SYNC', 'records_affected' => 1]);
        exit;
    }
}
