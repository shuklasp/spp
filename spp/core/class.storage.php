<?php

namespace SPP\Core;

use Symfony\Component\Yaml\Yaml;

/**
 * Class Storage
 * Sovereign enterprise facade for accessing storage disks, atomic file operations,
 * directory manipulation, and configuration I/O across platforms.
 */
class Storage
{
    /** @var DiskInterface[] */
    private static array $disks = [];

    public static function disk(string $name = 'local'): DiskInterface
    {
        if (isset(self::$disks[$name])) {
            return self::$disks[$name];
        }

        if ($name === 'file_shared') {
            $disk = new FileSharedStorage();
        } elseif ($name === 'redis_shared') {
            try {
                $disk = new RedisSharedStorage();
            } catch (\Throwable $e) {
                // Fallback to FileSharedStorage if Redis is unavailable in the environment
                $disk = new FileSharedStorage();
            }
        } elseif ($name === 'flysystem') {
            $disk = new FlysystemDisk();
        } elseif ($name === 'memory') {
            $disk = new MemoryDisk();
        } elseif ($name === 's3' || $name === 'r2') {
            $disk = new S3Disk([
                'bucket' => getenv('SPP_S3_BUCKET') ?: (getenv('AWS_BUCKET') ?: 'spp-storage'),
                'region' => getenv('SPP_S3_REGION') ?: (getenv('AWS_DEFAULT_REGION') ?: 'us-east-1'),
                'key' => getenv('SPP_S3_KEY') ?: (getenv('AWS_ACCESS_KEY_ID') ?: ''),
                'secret' => getenv('SPP_S3_SECRET') ?: (getenv('AWS_SECRET_ACCESS_KEY') ?: ''),
                'endpoint' => getenv('SPP_S3_ENDPOINT') ?: null,
            ]);
        } elseif ($name === 'root') {
            $rootBase = defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 2);
            $disk = new LocalDisk($rootBase);
        } elseif ($name === 'backups') {
            $rootBase = defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 2);
            $disk = new LocalDisk($rootBase . '/var/backups');
        } else {
            $basePath = '';
            if (class_exists('\SPP\SPPConfig', false)) {
                $basePath = \SPP\SPPConfig::get("storage.disks.{$name}.base_path", '');
            } elseif (class_exists('\SPP\Env', false)) {
                $basePath = \SPP\Env::get('SPP_STORAGE_' . strtoupper($name) . '_PATH', '');
            }
            $disk = new LocalDisk($basePath);
        }

        self::$disks[$name] = $disk;

        return $disk;
    }

    /**
     * Register a custom storage disk driver or instance.
     *
     * @param string $name
     * @param DiskInterface $disk
     * @return void
     */
    public static function extend(string $name, DiskInterface $disk): void
    {
        self::$disks[$name] = $disk;
    }

    public static function get(string $path): ?string
    {
        return self::disk()->get($path);
    }

    public static function put(string $path, string $contents): bool
    {
        return self::disk()->put($path, $contents);
    }

    public static function exists(string $path): bool
    {
        return self::disk()->exists($path);
    }

    public static function delete(string $path): bool
    {
        return self::disk()->delete($path);
    }

    public static function readStream(string $path)
    {
        return self::disk()->readStream($path);
    }

    public static function writeStream(string $path, $resource): bool
    {
        return self::disk()->writeStream($path, $resource);
    }

    /**
     * Atomically and safely write content to a file with cross-platform fallback strategies.
     * Prevents DrvFs/WSL file locks, Windows sharing violations (STATUS_SHARING_VIOLATION),
     * and guarantees directory existence and proper permissions.
     *
     * @param string $path Target file path
     * @param string $contents File contents to persist
     * @return bool True if written successfully, false otherwise
     */
    public static function writeAtomic(string $path, string $contents): bool
    {
        return File::putAtomic($path, $contents);
    }

    /**
     * Safely dump array data to a YAML file using atomic writes.
     *
     * @param string $path
     * @param array $data
     * @param int $inline
     * @param int $indent
     * @return bool
     */
    public static function writeYaml(string $path, array $data, int $inline = 10, int $indent = 2): bool
    {
        return File::writeYaml($path, $data, $inline, $indent);
    }

    /**
     * Safely parse a YAML file with error handling.
     *
     * @param string $path
     * @return array
     */
    public static function readYaml(string $path): array
    {
        return File::readYaml($path);
    }

    /**
     * Safely write array data to a JSON file using atomic writes.
     *
     * @param string $path
     * @param mixed $data
     * @param int $flags
     * @return bool
     */
    public static function writeJson(string $path, mixed $data, int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES): bool
    {
        return File::writeJson($path, $data, $flags);
    }

    /**
     * Safely parse a JSON file with error handling.
     *
     * @param string $path
     * @param bool $assoc
     * @return mixed
     */
    public static function readJson(string $path, bool $assoc = true): mixed
    {
        return File::readJson($path, $assoc);
    }

    /**
     * Safely delete a file with error handling.
     *
     * @param string $path
     * @return bool
     */
    public static function deleteFile(string $path): bool
    {
        return File::delete($path);
    }

    /**
     * Recursively delete a directory and all its contents.
     *
     * @param string $dir
     * @param bool $preserveRoot
     * @return bool
     */
    public static function deleteDirectory(string $dir, bool $preserveRoot = false): bool
    {
        return File::deleteDirectory($dir, $preserveRoot);
    }

    /**
     * Recursively copy a directory tree to a destination directory.
     *
     * @param string $source
     * @param string $destination
     * @return bool
     */
    public static function copyDirectory(string $source, string $destination): bool
    {
        return File::copyDirectory($source, $destination);
    }

    /**
     * Generate an expiring signed URL for a file across local or cloud S3/R2 storage.
     *
     * @param string $path
     * @param int|string $expires
     * @param string $diskName
     * @return string
     */
    public static function temporaryUrl(string $path, int|string $expires = '+15 minutes', string $diskName = 'local'): string
    {
        $d = self::disk($diskName);
        if (method_exists($d, 'temporaryUrl')) {
            return $d->temporaryUrl($path, $expires);
        }
        return method_exists($d, 'url') ? $d->url($path) : $path;
    }

    /**
     * Stream a file download to the client with full HTTP Range request (206 Partial Content) support,
     * buffer management, and automatic web server acceleration (X-Sendfile / X-Accel-Redirect).
     *
     * @param string $path
     * @param string|null $filename Custom download filename
     * @param array $headers Custom HTTP response headers
     * @param string $diskName
     * @return void
     */
    public static function streamDownload(string $path, ?string $filename = null, array $headers = [], string $diskName = 'local'): void
    {
        $disk = self::disk($diskName);
        if (!$disk->exists($path)) {
            http_response_code(404);
            echo "404 Not Found: Requested resource does not exist.";
            return;
        }

        $cleanFilename = $filename ?: basename($path);
        $cleanFilename = str_replace(['"', "'", "\r", "\n"], '', $cleanFilename);

        $fileSize = method_exists($disk, 'size') ? $disk->size($path) : 0;
        $mime = File::mimeType($path);

        $start = 0;
        $end = ($fileSize > 0) ? ($fileSize - 1) : 0;
        $isRange = false;

        // Parse HTTP_RANGE (e.g. bytes=0-1048575 or bytes=500-)
        if (!empty($_SERVER['HTTP_RANGE']) && $fileSize > 0) {
            $rangeHeader = trim($_SERVER['HTTP_RANGE']);
            if (preg_match('#^bytes=([0-9]*)-([0-9]*)#i', $rangeHeader, $matches)) {
                $isRange = true;
                $rangeStart = $matches[1] !== '' ? (int)$matches[1] : null;
                $rangeEnd = $matches[2] !== '' ? (int)$matches[2] : null;

                if ($rangeStart === null && $rangeEnd !== null) {
                    $start = max(0, $fileSize - $rangeEnd);
                } elseif ($rangeStart !== null && $rangeEnd === null) {
                    $start = $rangeStart;
                } elseif ($rangeStart !== null && $rangeEnd !== null) {
                    $start = $rangeStart;
                    $end = min($rangeEnd, $fileSize - 1);
                }

                if ($start > $end || $start >= $fileSize) {
                    http_response_code(416); // Range Not Satisfiable
                    header("Content-Range: bytes */{$fileSize}");
                    return;
                }
            }
        }

        // Web server acceleration check if using LocalDisk and not in byte-range mode
        if (!$isRange && $disk instanceof LocalDisk && method_exists($disk, 'path')) {
            $fullLocal = $disk->path($path);
            $server = Infra::webServer();

            if ($server === 'apache' || $server === 'iis') {
                header("X-Sendfile: {$fullLocal}");
                header("Content-Type: {$mime}");
                header("Content-Disposition: attachment; filename=\"{$cleanFilename}\"");
                return;
            }
        }

        // Send streaming headers
        if ($isRange) {
            http_response_code(206);
            $length = ($end - $start) + 1;
            header("Content-Range: bytes {$start}-{$end}/{$fileSize}");
            header("Content-Length: {$length}");
        } else {
            http_response_code(200);
            if ($fileSize > 0) {
                header("Content-Length: {$fileSize}");
            }
        }

        header("Content-Type: {$mime}");
        header("Accept-Ranges: bytes");
        header("Content-Disposition: attachment; filename=\"{$cleanFilename}\"");
        header("Cache-Control: private, must-revalidate, max-age=0");
        header("Pragma: public");

        foreach ($headers as $k => $v) {
            header("{$k}: {$v}");
        }

        // Flush output buffers to prevent PHP memory bloat
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        $stream = $disk->readStream($path);
        if (!$stream) {
            echo $disk->get($path);
            return;
        }

        if ($start > 0) {
            fseek($stream, $start);
        }

        $bytesRemaining = ($end - $start) + 1;
        $chunkSize = 65536; // 64 KB slices

        while (!feof($stream) && $bytesRemaining > 0 && !connection_aborted()) {
            $readSize = min($chunkSize, $bytesRemaining);
            $data = fread($stream, $readSize);
            if ($data === false || $data === '') break;
            echo $data;
            flush();
            $bytesRemaining -= strlen($data);
        }

        fclose($stream);
    }
}
