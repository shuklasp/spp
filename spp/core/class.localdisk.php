<?php
declare(strict_types=1);

namespace SPP\Core;

/**
 * Class LocalDisk
 *
 * Sovereign enterprise local disk adapter for SPP Storage.
 * Features:
 * - Cross-platform atomic file persistence via File::putAtomic().
 * - Safe path resolution & directory traversal guards.
 * - Expiring HMAC-SHA256 temporary signed URLs.
 * - Stream reading and writing with proper buffer management.
 */
class LocalDisk implements DiskInterface
{
    protected string $basePath;
    protected string $baseUrl;

    public function __construct(string $basePath = '', string $baseUrl = '')
    {
        $this->basePath = $basePath ? Infra::normalizePath($basePath) : (defined('SPP_APP_DIR') ? SPP_APP_DIR . '/var/storage' : '');
        $this->baseUrl = rtrim($baseUrl ?: '/storage', '/');
        
        if ($this->basePath && !is_dir($this->basePath)) {
            @mkdir($this->basePath, 0775, true);
        }
    }

    public function get(string $path): ?string
    {
        $fullPath = $this->path($path);
        return File::exists($fullPath) ? File::get($fullPath) : null;
    }

    public function put(string $path, string $contents): bool
    {
        $fullPath = $this->path($path);
        return File::putAtomic($fullPath, $contents);
    }

    public function exists(string $path): bool
    {
        return File::exists($this->path($path));
    }

    public function delete(string $path): bool
    {
        return File::delete($this->path($path));
    }

    public function size(string $path): int
    {
        return File::size($this->path($path));
    }

    public function lastModified(string $path): int
    {
        return File::lastModified($this->path($path));
    }

    public function readStream(string $path)
    {
        $fullPath = $this->path($path);
        return File::exists($fullPath) ? @fopen($fullPath, 'rb') : null;
    }

    public function writeStream(string $path, $resource): bool
    {
        if (!is_resource($resource)) {
            throw new \InvalidArgumentException('Argument must be a valid resource.');
        }

        $fullPath = $this->path($path);
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            File::makeDirectory($dir, 0775, true);
        }

        // Staged write to temporary file to ensure atomicity
        $tmp = $dir . '/.tmp_stream_' . bin2hex(random_bytes(6)) . '.bin';
        $dest = @fopen($tmp, 'wb');
        if (!$dest) {
            return false;
        }

        while (!feof($resource)) {
            $chunk = fread($resource, 65536);
            if ($chunk === false) break;
            fwrite($dest, $chunk);
        }
        fflush($dest);
        fclose($dest);

        if (File::exists($fullPath)) {
            $bak = $fullPath . '.bak.' . uniqid('', true);
            if (!@rename($fullPath, $bak)) {
                @unlink($tmp);
                return false;
            }
            if (!@rename($tmp, $fullPath)) {
                @rename($bak, $fullPath);
                @unlink($tmp);
                return false;
            }
            @unlink($bak);
            return true;
        }

        if (!@rename($tmp, $fullPath)) {
            @unlink($tmp);
            return false;
        }
        return true;
    }

    public function url(string $path): string
    {
        return $this->baseUrl . '/' . ltrim(str_replace('\\', '/', $path), '/');
    }

    /**
     * Generate an expiring signed URL for secure, temporary document download.
     *
     * @param string $path
     * @param int|string $expires Expiration timestamp or relative string ('+15 minutes')
     * @return string Signed URL
     */
    public function temporaryUrl(string $path, int|string $expires): string
    {
        $expiryTime = is_string($expires) ? (strtotime($expires) ?: (time() + 900)) : $expires;
        $cleanPath = ltrim(str_replace('\\', '/', $path), '/');
        
        $secret = (defined('SPP_APP_KEY') && !empty(SPP_APP_KEY))
            ? SPP_APP_KEY
            : ((defined('SPP_SECRET') && !empty(SPP_SECRET)) ? SPP_SECRET : null);

        if (empty($secret)) {
            throw new \RuntimeException('Cannot generate temporary signed URL: No secure application key (SPP_APP_KEY / SPP_SECRET) configured.');
        }

        $signature = hash_hmac('sha256', "{$cleanPath}:{$expiryTime}", $secret);

        return $this->baseUrl . '/signed?file=' . urlencode($cleanPath) . '&expires=' . $expiryTime . '&sig=' . $signature;
    }

    /**
     * Resolve the absolute path of a file within this disk boundary.
     * Guarantees containment and prevents directory traversal attacks.
     *
     * @param string $path
     * @return string
     * @throws \SecurityException
     */
    public function path(string $path): string
    {
        $clean = ltrim(str_replace('\\', '/', $path), '/');
        $full = $this->basePath . '/' . $clean;
        $norm = Infra::normalizePath($full);

        // Security check: must be within basePath
        $normBase = Infra::normalizePath($this->basePath);
        if (!str_starts_with($norm, $normBase . '/') && $norm !== $normBase) {
            throw new \Exception("Path traversal attempt detected outside disk boundary: {$path}");
        }

        return $norm;
    }
}
