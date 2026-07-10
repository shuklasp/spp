<?php

namespace SPP\Core;

class AssetRouter
{
    /**
     * Handle incoming asset requests.
     * URI pattern: sppasset/<modname>/<dirAlias>/<filepath>
     * 
     * @param string $qPath
     */
    public static function handle(string $qPath): void
    {
        // Strip the prefix
        $qPath = preg_replace('#^sppasset/#', '', $qPath);
        $parts = explode('/', $qPath, 3);
        
        if (count($parts) < 3) {
            self::sendError(400, "Invalid asset route");
            return;
        }

        $modName = $parts[0];
        $dirAlias = $parts[1];
        $filePath = $parts[2];

        // Ensure file path does not attempt traversal
        if (strpos($filePath, '../') !== false || strpos($filePath, '..\\') !== false) {
            self::sendError(403, "Directory traversal denied");
            return;
        }

        $baseDir = null;

        if ($modName === 'core') {
            $assets = \SPP\App::getGlobalSettings('assets') ?? [];
            if (isset($assets[$dirAlias])) {
                $baseDir = SPP_BASE_DIR . '/../' . $assets[$dirAlias];
            }
        } else {
            // It's a module
            if (!\SPP\Module::isEnabled($modName)) {
                self::sendError(404, "Module not found or disabled");
                return;
            }
            $config = \SPP\Module::getConfig('assets', $modName);
            if ($config && isset($config[$dirAlias])) {
                $moduleDir = \SPP\Module::getModuleDir($modName);
                if ($moduleDir) {
                    $baseDir = $moduleDir . '/' . ltrim($config[$dirAlias], '/');
                }
            }
        }

        if (!$baseDir) {
            self::sendError(404, "Asset alias not found");
            return;
        }

        $fullPath = realpath($baseDir . '/' . $filePath);

        // Security check: Ensure the resolved path actually lives inside the base directory
        $realBaseDir = realpath($baseDir);
        if (!$fullPath || !$realBaseDir || !str_starts_with($fullPath, $realBaseDir)) {
            self::sendError(404, "File not found or access denied");
            return;
        }

        if (!is_file($fullPath) || !is_readable($fullPath)) {
            self::sendError(404, "File not found or unreadable");
            return;
        }

        self::serveFile($fullPath);
    }

    private static function serveFile(string $fullPath): void
    {
        $mime = self::getMimeType($fullPath);
        
        // Cache headers
        $lastModified = filemtime($fullPath);
        $etag = md5_file($fullPath);

        header("Content-Type: $mime");
        header("Cache-Control: public, max-age=31536000"); // Cache for 1 year
        header("Last-Modified: " . gmdate("D, d M Y H:i:s", $lastModified) . " GMT");
        header("ETag: \"$etag\"");

        // Check if browser sent If-None-Match or If-Modified-Since
        if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) == "\"$etag\"") {
            http_response_code(304);
            exit;
        }
        if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) == $lastModified) {
            http_response_code(304);
            exit;
        }

        header("Content-Length: " . filesize($fullPath));
        readfile($fullPath);
        exit;
    }

    private static function getMimeType(string $file): string
    {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mimes = [
            'css'   => 'text/css',
            'js'    => 'application/javascript',
            'json'  => 'application/json',
            'html'  => 'text/html',
            'png'   => 'image/png',
            'jpg'   => 'image/jpeg',
            'jpeg'  => 'image/jpeg',
            'gif'   => 'image/gif',
            'svg'   => 'image/svg+xml',
            'ico'   => 'image/x-icon',
            'woff'  => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf'   => 'font/ttf',
            'eot'   => 'application/vnd.ms-fontobject',
            'otf'   => 'font/otf',
            'pdf'   => 'application/pdf',
            'zip'   => 'application/zip'
        ];

        return $mimes[$ext] ?? 'application/octet-stream';
    }

    private static function sendError(int $code, string $message): void
    {
        http_response_code($code);
        header("Content-Type: text/plain");
        echo $message;
        exit;
    }
}
