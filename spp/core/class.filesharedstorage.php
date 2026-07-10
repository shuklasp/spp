<?php

namespace SPP\Core;

use SPP\Core\Interfaces\SharedStorageInterface;

/**
 * Class FileSharedStorage
 * 
 * Local file-based shared storage adapter for the SPP Registry.
 * Uses atomic locking (flock) to prevent concurrency corruption.
 */
class FileSharedStorage implements SharedStorageInterface, DiskInterface
{
    /** @var string */
    private string $filePath;
    
    /** @var string */
    private string $sharedDir;

    public function __construct()
    {
        if (!defined('SPP_BASE_DIR')) {
            throw new \RuntimeException("SPP_BASE_DIR is not defined.");
        }
        $sharedDir = SPP_BASE_DIR . '/var/shared';
        if (!is_dir($sharedDir)) {
            @mkdir($sharedDir, 0777, true);
        }
        $this->sharedDir = $sharedDir;
        $this->filePath = $sharedDir . '/registry.json';
    }

    /**
     * @inheritDoc
     */
    public function save(array $data): void
    {
        $fp = @fopen($this->filePath, 'c+');
        if ($fp) {
            if (flock($fp, LOCK_EX)) {
                ftruncate($fp, 0);
                rewind($fp);
                fwrite($fp, json_encode($data, JSON_PRETTY_PRINT));
                flock($fp, LOCK_UN);
            }
            fclose($fp);
        }
    }

    /**
     * @inheritDoc
     */
    public function load(): array
    {
        if (file_exists($this->filePath)) {
            $data = json_decode(file_get_contents($this->filePath), true);
            if (is_array($data)) {
                return $data;
            }
        }
        return [];
    }

    protected function getFullPath(string $path): string
    {
        if (strpos($path, '..') !== false) {
            throw new \Exception('Path traversal attempt detected in FileSharedStorage.');
        }
        return $this->sharedDir . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
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
            @mkdir($dir, 0755, true);
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
            @mkdir($dir, 0755, true);
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
}
