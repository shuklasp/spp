<?php
declare(strict_types=1);

namespace SPP\Core\Server;

/**
 * Class NonSppAppHandler
 *
 * Implements Subdirectory and Root Environment Virtualization for external
 * non-SPP applications (e.g., Drupal, WordPress, Laravel, or static sites).
 *
 * Provides:
 *  - Full physical subdirectory virtualization (SCRIPT_NAME, CONTEXT_PREFIX, DOCUMENT_ROOT)
 *  - Native directory switching (chdir)
 *  - High-performance direct static asset streaming (CSS, JS, images, fonts)
 *  - Clean URL query string normalization (e.g. $_GET['q'] for Drupal)
 *  - Deep path probing and CMS signature detection
 */
class NonSppAppHandler
{
    /**
     * Common MIME type dictionary for high-performance static asset streaming.
     */
    protected static array $mimeTypes = [
        'css'   => 'text/css; charset=utf-8',
        'js'    => 'application/javascript; charset=utf-8',
        'mjs'   => 'application/javascript; charset=utf-8',
        'json'  => 'application/json; charset=utf-8',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'svg'   => 'image/svg+xml',
        'webp'  => 'image/webp',
        'ico'   => 'image/x-icon',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf',
        'otf'   => 'font/otf',
        'eot'   => 'application/vnd.ms-fontobject',
        'pdf'   => 'application/pdf',
        'xml'   => 'application/xml',
        'txt'   => 'text/plain; charset=utf-8',
        'html'  => 'text/html; charset=utf-8',
        'htm'   => 'text/html; charset=utf-8',
        'mp4'   => 'video/mp4',
        'webm'  => 'video/webm',
        'mp3'   => 'audio/mpeg',
        'map'   => 'application/json',
    ];

    /**
     * Dispatch and execute an external application request with full environment virtualization.
     *
     * @param array $appConfig Configuration dictionary containing target_path, index, route, etc.
     * @param string $requestUri The raw incoming REQUEST_URI
     * @return void
     */
    public static function handle(array $appConfig, string $requestUri): void
    {
        $targetPath = rtrim(str_replace('\\', '/', $appConfig['target_path'] ?? ''), '/');
        $indexFile = trim((string)($appConfig['index'] ?? 'index.php'));
        $route = '/' . trim((string)($appConfig['route'] ?? ''), '/');
        if ($route === '/') {
            $route = '';
        }

        if ($targetPath === '' || !is_dir($targetPath)) {
            self::renderError(404, "External application root directory not found: {$targetPath}");
            return;
        }

        // Parse path from Request URI
        $reqPath = parse_url($requestUri, PHP_URL_PATH) ?? '/';

        // Compute relative path within the external application
        $relPath = '/';
        if ($route !== '') {
            if ($reqPath === $route) {
                $relPath = '/';
            } elseif (str_starts_with($reqPath, $route . '/')) {
                $relPath = substr($reqPath, strlen($route));
            } else {
                $relPath = $reqPath;
            }
        } else {
            $relPath = $reqPath;
        }

        $cleanRel = ltrim($relPath, '/');
        $candidateFile = $targetPath . ($cleanRel !== '' ? '/' . $cleanRel : '');

        // Security check: prevent directory traversal
        $realTargetPath = realpath($targetPath);
        if ($realTargetPath !== false && $cleanRel !== '') {
            $realCandidate = realpath($candidateFile);
            if ($realCandidate !== false && !str_starts_with(str_replace('\\', '/', $realCandidate), str_replace('\\', '/', $realTargetPath))) {
                self::renderError(403, "Access denied: Directory traversal detected.");
                return;
            }
        }

        // 1. Direct Static Asset Streaming
        if ($cleanRel !== '' && is_file($candidateFile)) {
            $ext = strtolower(pathinfo($candidateFile, PATHINFO_EXTENSION));
            if ($ext !== 'php') {
                self::serveStatic($candidateFile);
                if (php_sapi_name() !== 'cli') {
                    exit;
                }
                return;
            }
            $targetScript = $candidateFile;
            $scriptRelName = $cleanRel;
        } else {
            // Target entry script
            $targetScript = $targetPath . '/' . $indexFile;
            $scriptRelName = $indexFile;
        }

        if (!file_exists($targetScript)) {
            self::renderError(404, "Application entry script '{$indexFile}' not found in {$targetPath}");
            return;
        }

        // 2. Clean URL & Query Normalization
        // For Drupal 7/8/9/10: if clean URLs are used and $_GET['q'] is empty, inject the path
        if ($cleanRel !== '' && !is_file($candidateFile) && empty($_GET['q'])) {
            $_GET['q'] = $cleanRel;
        }

        // 3. Subdirectory Environment Virtualization
        $virtualScriptName = ($route !== '' ? $route : '') . '/' . $scriptRelName;
        $_SERVER['SCRIPT_NAME'] = $virtualScriptName;
        $_SERVER['PHP_SELF'] = $virtualScriptName;
        $_SERVER['SCRIPT_FILENAME'] = $targetScript;
        $_SERVER['CONTEXT_PREFIX'] = $route;
        $_SERVER['CONTEXT_DOCUMENT_ROOT'] = $targetPath;
        $_SERVER['DOCUMENT_ROOT'] = $targetPath;

        // Inform external apps of SPP presence and active data dir if opted-in
        if (!empty($appConfig['opt_in_sppdata']) && class_exists('\SPP\Core\Router\DomainRouter')) {
            $activeHost = \SPP\Core\Router\DomainRouter::getMatchedHost() ?: ($_SERVER['HTTP_HOST'] ?? 'default');
            $appName = $appConfig['app_name'] ?? 'external_app';
            $sppDataDir = \SPP\Core\Router\DomainRouter::resolveUserDataDir($activeHost, $appName);
            $_SERVER['SPP_DATA_DIR'] = $sppDataDir;
            putenv("SPP_DATA_DIR={$sppDataDir}");
        }

        // 4. Switch working directory to external app root
        $oldCwd = getcwd();
        chdir($targetPath);

        // 5. Execute external application entrypoint
        try {
            require $targetScript;
        } finally {
            if ($oldCwd) {
                @chdir($oldCwd);
            }
        }

        if (php_sapi_name() !== 'cli') {
            exit;
        }
    }

    /**
     * Stream a physical static file with appropriate headers and caching.
     *
     * @param string $filePath Absolute file path
     * @return bool True if served successfully
     */
    public static function serveStatic(string $filePath): bool
    {
        if (!is_file($filePath)) {
            return false;
        }

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mime = self::$mimeTypes[$ext] ?? 'application/octet-stream';
        $mtime = filemtime($filePath);
        $size = filesize($filePath);
        $etag = '"' . md5($filePath . $mtime . $size) . '"';

        if (php_sapi_name() !== 'cli' && !headers_sent()) {
            // Check If-None-Match (ETag)
            if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
                http_response_code(304);
                return true;
            }

            // Check If-Modified-Since
            if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
                $ifModified = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
                if ($ifModified !== false && $ifModified >= $mtime) {
                    http_response_code(304);
                    return true;
                }
            }

            header("Content-Type: {$mime}");
            header("Content-Length: {$size}");
            header("ETag: {$etag}");
            header("Last-Modified: " . gmdate('D, d M Y H:i:s GMT', $mtime));
            header("Cache-Control: public, max-age=86400");
        }

        readfile($filePath);
        return true;
    }

    /**
     * Probe a target path to check existence, permissions, and detect CMS framework.
     *
     * @param string $targetPath Absolute or relative filesystem path
     * @return array
     */
    public static function probeApp(string $targetPath): array
    {
        $targetPath = rtrim(str_replace('\\', '/', trim($targetPath)), '/');

        if ($targetPath === '') {
            return [
                'success' => false,
                'valid' => false,
                'exists' => false,
                'readable' => false,
                'writable' => false,
                'type' => 'unknown',
                'framework' => 'Unknown',
                'version' => null,
                'index_file' => 'index.php',
                'clean_path' => '',
                'error' => 'Path cannot be empty.'
            ];
        }

        // Try resolving relative paths against SPP_BASE_DIR, DOCUMENT_ROOT, or SPP_APP_DIR
        $resolvedPath = $targetPath;
        if (!file_exists($resolvedPath)) {
            if (defined('SPP_BASE_DIR') && file_exists(SPP_BASE_DIR . '/' . ltrim($targetPath, '/'))) {
                $resolvedPath = SPP_BASE_DIR . '/' . ltrim($targetPath, '/');
            } elseif (!empty($_SERVER['DOCUMENT_ROOT']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($targetPath, '/'))) {
                $resolvedPath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($targetPath, '/');
            } elseif (defined('SPP_APP_DIR') && file_exists(SPP_APP_DIR . '/' . ltrim($targetPath, '/'))) {
                $resolvedPath = SPP_APP_DIR . '/' . ltrim($targetPath, '/');
            }
        }
        $targetPath = $resolvedPath;

        if (!file_exists($targetPath)) {
            return [
                'success' => false,
                'valid' => false,
                'exists' => false,
                'readable' => false,
                'writable' => false,
                'type' => 'not_found',
                'framework' => 'Missing Directory',
                'version' => null,
                'index_file' => 'index.php',
                'clean_path' => $targetPath,
                'error' => "Directory does not exist: {$targetPath}"
            ];
        }

        if (!is_dir($targetPath)) {
            return [
                'success' => false,
                'valid' => false,
                'exists' => true,
                'readable' => is_readable($targetPath),
                'writable' => is_writable($targetPath),
                'type' => 'file',
                'framework' => 'Single File',
                'version' => null,
                'index_file' => basename($targetPath),
                'clean_path' => $targetPath,
                'error' => "Path is a file, not a directory: {$targetPath}"
            ];
        }

        $readable = is_readable($targetPath);
        $writable = is_writable($targetPath);

        // Detect Framework/CMS
        $type = 'php';
        $framework = 'Generic PHP Application';
        $version = null;
        $indexFile = 'index.php';

        if (file_exists($targetPath . '/core/lib/Drupal.php')) {
            $type = 'drupal';
            $framework = 'Drupal 8/9/10/11';
            $content = @file_get_contents($targetPath . '/core/lib/Drupal.php');
            if ($content && preg_match("/const VERSION = '([^']+)';/", $content, $m)) {
                $version = $m[1];
                $framework = "Drupal {$version}";
            }
        } elseif (file_exists($targetPath . '/includes/bootstrap.inc')) {
            $type = 'drupal';
            $framework = 'Drupal 7';
            $content = @file_get_contents($targetPath . '/includes/bootstrap.inc');
            if ($content && preg_match("/define\('VERSION', '([^']+)'\);/", $content, $m)) {
                $version = $m[1];
                $framework = "Drupal {$version}";
            }
        } elseif (file_exists($targetPath . '/wp-config.php') || file_exists($targetPath . '/wp-includes/version.php')) {
            $type = 'wordpress';
            $framework = 'WordPress';
            $vFile = $targetPath . '/wp-includes/version.php';
            if (file_exists($vFile)) {
                $content = @file_get_contents($vFile);
                if ($content && preg_match("/\\\$wp_version\\s*=\\s*'([^']+)';/", $content, $m)) {
                    $version = $m[1];
                    $framework = "WordPress {$version}";
                }
            }
        } elseif (file_exists($targetPath . '/artisan') && is_dir($targetPath . '/app')) {
            $type = 'laravel';
            $framework = 'Laravel Framework';
        } elseif (!file_exists($targetPath . '/index.php') && (file_exists($targetPath . '/index.html') || file_exists($targetPath . '/index.htm'))) {
            $type = 'static';
            $framework = 'Static HTML Website';
            $indexFile = file_exists($targetPath . '/index.html') ? 'index.html' : 'index.htm';
        }

        if (!file_exists($targetPath . '/' . $indexFile) && file_exists($targetPath . '/index.html')) {
            $indexFile = 'index.html';
        }

        return [
            'success' => true,
            'valid' => true,
            'exists' => true,
            'readable' => $readable,
            'writable' => $writable,
            'type' => $type,
            'framework' => $framework,
            'version' => $version,
            'index_file' => $indexFile,
            'clean_path' => $targetPath,
            'error' => null
        ];
    }

    /**
     * Render a standard HTTP error response.
     *
     * @param int $code HTTP response code
     * @param string $message Diagnostic message
     */
    protected static function renderError(int $code, string $message): void
    {
        if (php_sapi_name() !== 'cli' && !headers_sent()) {
            http_response_code($code);
            header('Content-Type: text/html; charset=utf-8');
        }

        $safeMsg = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        echo "<!DOCTYPE html>\n<html><head><title>HTTP {$code} - SPP Sovereign Gateway</title>";
        echo "<style>body{font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;background:#0f172a;color:#f8fafc;padding:3rem;line-height:1.6;}";
        echo ".card{max-width:650px;margin:2rem auto;background:#1e293b;border:1px solid #334155;border-radius:12px;padding:2rem;box-shadow:0 10px 25px rgba(0,0,0,0.5);}";
        echo "h1{color:#f43f5e;margin-top:0;font-size:1.5rem;}p{color:#cbd5e1;font-size:1rem;}</style></head>";
        echo "<body><div class=\"card\"><h1>HTTP {$code} — External Application Error</h1>";
        echo "<p>{$safeMsg}</p>";
        echo "<p style=\"font-size:0.85rem;color:#64748b;margin-top:2rem;\">SPP Sovereign Cloud Manager &bull; Subdirectory Virtualization</p></div></body></html>";
    }
}
