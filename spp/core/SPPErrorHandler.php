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
        // Suppress non-breaking deprecation notices so they do not abort execution
        if ($level & (E_DEPRECATED | E_USER_DEPRECATED)) {
            return;
        }

        if (error_reporting() & $level) {
            throw new \ErrorException($message, 0, $level, $file, $line);
        }
    }

    public static function handleException(\Throwable $exception): void {
        $isDebug = \SPP\SPPConfig::get('app.debug', false);

        if (php_sapi_name() === 'cli') {
            echo "\n\033[31m\033[1m[ERROR]\033[0m " . $exception->getMessage() . "\n";
            if ($isDebug) {
                echo "\033[33m[TRACE]\033[0m in " . $exception->getFile() . " on line " . $exception->getLine() . "\n";
                echo $exception->getTraceAsString() . "\n";
            }
            exit(1);
        }

        if (!headers_sent()) {
            http_response_code($exception->getCode() ?: 500);
        }
        
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $isApi = str_contains($uri, '/api/') 
            || str_contains($uri, 'api.php')
            || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
            || !empty($_SERVER['HTTP_X_SPP_AJAX'])
            || !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            || (headers_sent() && str_contains(implode(',', headers_list()), 'application/json'));

        if ($isApi) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode([
                'status' => 'error',
                'success' => false,
                'message' => $isDebug ? $exception->getMessage() : 'Internal Server Error',
                'file' => $isDebug ? $exception->getFile() : null,
                'line' => $isDebug ? $exception->getLine() : null,
                'trace' => $isDebug ? explode("\n", $exception->getTraceAsString()) : null,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } else {
            // Render Ignition-style HTML page
            echo self::renderHtmlError($exception, $isDebug);
        }
        exit;
    }

    public static function handleShutdown(): void {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            // If output was already emitted and headers sent for an API/JSON response, avoid corrupting it
            if (headers_sent() && !ob_get_level()) {
                exit;
            }
            self::handleException(new \ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']));
        }
    }

    private static function renderHtmlError(\Throwable $exception, bool $isDebug): string {
        $baseDir = defined('SPP_BASE_DIR') ? SPP_BASE_DIR : dirname(__DIR__);
        $viewFile = $isDebug ? ($baseDir . '/views/errors/debug.php') : ($baseDir . '/views/errors/production.php');

        if (file_exists($viewFile)) {
            $class = get_class($exception);
            $message = $exception->getMessage();
            $file = $exception->getFile();
            $line = $exception->getLine();
            $trace = $exception->getTraceAsString();
            $codeSnippet = self::getCodeSnippet($file, $line);
            $suggestion = self::inferSuggestion($exception);

            ob_start();
            include $viewFile;
            return (string) ob_get_clean();
        }

        return "Service Unavailable";
    }

    private static function getCodeSnippet(string $file, int $line, int $radius = 6): ?array {
        if (!file_exists($file) || !is_readable($file)) {
            return null;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return null;
        }

        $totalLines = count($lines);
        $start = max(1, $line - $radius);
        $end = min($totalLines, $line + $radius);

        $snippetLines = [];
        for ($i = $start; $i <= $end; $i++) {
            $snippetLines[$i] = $lines[$i - 1] ?? '';
        }

        return [
            'start' => $start,
            'end' => $end,
            'lines' => $snippetLines
        ];
    }

    private static function inferSuggestion(\Throwable $e): ?string {
        $msg = $e->getMessage();
        if (stripos($msg, 'no such table') !== false || stripos($msg, 'Base table or view not found') !== false) {
            return "A database table is missing. Run 'php spp.php sys:upgrade' to synchronize your schema from db.yml definitions.";
        }
        if (stripos($msg, 'Class') !== false && stripos($msg, 'not found') !== false) {
            return "Class resolution failed. Check the namespace in src/ or run 'php spp.php sys:status' to inspect autoloader bindings.";
        }
        if (stripos($msg, 'View template') !== false || stripos($msg, 'Template not found') !== false) {
            return "Blade view could not be located. Ensure the .blade.php file exists in resources/views/ or use @spppartial.";
        }
        if (stripos($msg, 'Call to undefined method') !== false) {
            return "Method does not exist on the targeted instance. Verify interface contracts or active record model inheritance.";
        }
        return null;
    }
}
