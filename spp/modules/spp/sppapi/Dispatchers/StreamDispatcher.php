<?php
declare(strict_types=1);

namespace SPPMod\SPPAPI\Dispatchers;

use SPPMod\SppApi\SPPAjax;

class StreamDispatcher
{
    public static function dispatch(string $service): void
    {
        while (ob_get_level()) {
            ob_end_clean();
        }

        http_response_code(200);
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Bypass Nginx/proxy layer buffering

        $emit = function (string $event, array $data) {
            $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            echo "event: {$event}\ndata: {$payload}\n\n";
            @ob_flush();
            @flush();
        };

        // Notify client stream instantiated successfully
        $emit('start', ['message' => 'SSE stream pipeline instantiated successfully.']);

        $svc = SPPAjax::findService($service);
        $serviceFile = null;

        if ($svc) {
            $serviceFile = $svc['script'];
            if (!str_starts_with($serviceFile, '/') && !str_contains($serviceFile, ':')) {
                $serviceFile = SPP_APP_DIR . '/' . ltrim($serviceFile, '/');
            }
            $serviceFile = realpath($serviceFile);
        } else {
            // Fallback Discovery for Streams
            $context = \SPP\Scheduler::getContext();
            $srcPath = \SPP\App::getAppConf('src_path', $context) ?: ('src/' . $context);
            $servicesPath = \SPP\App::getAppConf('services_path', $context) ?: (rtrim($srcPath, '/') . '/services');
            $servDir = SPP_APP_DIR . '/' . ltrim($servicesPath, '/');
            $servFile = $servDir . '/' . preg_replace('/[^a-zA-Z0-9_\-]/', '', $service) . '.php';

            if (file_exists($servFile)) {
                $serviceFile = $servFile;
            }
        }

        if ($serviceFile && file_exists($serviceFile)) {
            try {
                // Pass closure helper to current service inclusion context
                $sseEmit = $emit;
                include $serviceFile;
                $emit('complete', ['status' => 'success']);
            } catch (\Throwable $e) {
                $emit('error', ['message' => 'Stream exception: ' . $e->getMessage()]);
            }
        } else {
            $emit('error', ['message' => "Requested stream target '{$service}' unresolvable."]);
        }

        exit;
    }
}
