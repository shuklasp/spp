<?php

namespace SPP\Core;

/**
 * Class LocalDisk
 */
class LocalDisk implements DiskInterface
{
    protected string $basePath;

    public function __construct(string $basePath = '')
    {
        $this->basePath = $basePath ?: (defined('SPP_APP_DIR') ? SPP_APP_DIR . '/var/storage' : '');
        if ($this->basePath && !is_dir($this->basePath)) {
            mkdir($this->basePath, 0755, true);
        }
    }

    public function get(string $path): ?string
    {
        $fullPath = $this->getFullPath($path);
        return file_exists($fullPath) ? file_get_contents($fullPath) : null;
    }

    public function put(string $path, string $contents): bool
    {
        $fullPath = $this->getFullPath($path);
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return file_put_contents($fullPath, $contents) !== false;
    }

    public function exists(string $path): bool
    {
        return file_exists($this->getFullPath($path));
    }

    public function delete(string $path): bool
    {
        return @unlink($this->getFullPath($path));
    }

    public function readStream(string $path)
    {
        $fullPath = $this->getFullPath($path);
        return file_exists($fullPath) ? @fopen($fullPath, 'rb') : null;
    }

    public function writeStream(string $path, $resource): bool
    {
        if (!is_resource($resource)) {
            throw new \InvalidArgumentException('Argument must be a valid resource.');
        }
        $fullPath = $this->getFullPath($path);
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $dest = @fopen($fullPath, 'wb');
        if (!$dest) {
            return false;
        }
        while (!feof($resource)) {
            fwrite($dest, fread($resource, 8192));
        }
        fclose($dest);
        return true;
    }

    protected function getFullPath(string $path): string
    {
        if (strpos($path, '..') !== false) {
            throw new \Exception('Path traversal attempt detected in Storage mechanism.');
        }
        return $this->basePath . SPP_DS . ltrim($path, SPP_DS);
    }
}
