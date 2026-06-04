<?php
namespace SPP\Core;

/**
 * Class SPPErrorHandler
 * Modern, ignition-style error handler for the framework.
 */
class SPPErrorHandler {

    public static function register(): void {
        error_reporting(E_ALL);
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handleError(int $level, string $message, string $file = '', int $line = 0): void {
        if (error_reporting() & $level) {
            throw new \ErrorException($message, 0, $level, $file, $line);
        }
    }

    public static function handleException(\Throwable $exception): void {
        http_response_code($exception->getCode() ?: 500);
        
        $isApi = str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/');
        $isDebug = \SPP\SPPConfig::get('app.debug', false);

        if ($isApi) {
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'error',
                'message' => $isDebug ? $exception->getMessage() : 'Internal Server Error',
                'file' => $isDebug ? $exception->getFile() : null,
                'line' => $isDebug ? $exception->getLine() : null,
                'trace' => $isDebug ? explode("\n", $exception->getTraceAsString()) : null,
            ]);
        } else {
            // Render Ignition-style HTML page
            echo self::renderHtmlError($exception, $isDebug);
        }
        exit;
    }

    public static function handleShutdown(): void {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            self::handleException(new \ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']));
        }
    }

    private static function renderHtmlError(\Throwable $exception, bool $isDebug): string {
        if (!$isDebug) {
            return "<html><head><title>Error</title></head><body><h1>Server Error</h1><p>Something went wrong.</p></body></html>";
        }
        
        $class = get_class($exception);
        $message = htmlspecialchars($exception->getMessage());
        $file = htmlspecialchars($exception->getFile());
        $line = $exception->getLine();
        $trace = htmlspecialchars($exception->getTraceAsString());

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Exception: {$class}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: #f3f4f6; color: #1f2937; padding: 2rem; margin: 0; }
        .container { max-width: 1200px; margin: 0 auto; background: #ffffff; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-top: 4px solid #ef4444; }
        h1 { color: #dc2626; font-size: 1.5rem; margin-top: 0; }
        .message { font-size: 1.25rem; font-weight: 500; margin-bottom: 1rem; }
        .file-info { background: #f9fafb; padding: 1rem; border-radius: 4px; font-family: monospace; color: #4b5563; border: 1px solid #e5e7eb; }
        .trace { background: #1f2937; color: #f3f4f6; padding: 1.5rem; border-radius: 4px; overflow-x: auto; font-family: monospace; font-size: 0.875rem; line-height: 1.5; margin-top: 1.5rem; }
    </style>
</head>
<body>
    <div class="container">
        <h1>{$class}</h1>
        <div class="message">{$message}</div>
        <div class="file-info">
            In <strong>{$file}</strong> on line <strong>{$line}</strong>
        </div>
        <div class="trace">
<pre>{$trace}</pre>
        </div>
    </div>
</body>
</html>
HTML;
    }
}
