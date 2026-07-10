<?php

namespace SPP\Core;

/**
 * Class FlysystemDisk
 * 
 * Wrapper for League\Flysystem filesystem interoperability.
 */
class FlysystemDisk implements DiskInterface
{
    /** @var \League\Flysystem\FilesystemOperator|null */
    protected $filesystem;

    public function __construct($filesystem = null)
    {
        if ($filesystem !== null) {
            $this->filesystem = $filesystem;
        } elseif (class_exists('\League\Flysystem\Filesystem') && class_exists('\League\Flysystem\Local\LocalFilesystemAdapter')) {
            $adapter = new \League\Flysystem\Local\LocalFilesystemAdapter(defined('SPP_APP_DIR') ? SPP_APP_DIR . '/var/storage/flysystem' : '/tmp/flysystem');
            $this->filesystem = new \League\Flysystem\Filesystem($adapter);
        }
    }

    protected function getFilesystem()
    {
        if (!$this->filesystem) {
            throw new \RuntimeException("League\\Flysystem is not installed or filesystem instance not provided.");
        }
        return $this->filesystem;
    }

    public function get(string $path): ?string
    {
        try {
            return $this->getFilesystem()->read($path);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function put(string $path, string $contents): bool
    {
        try {
            $this->getFilesystem()->write($path, $contents);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function exists(string $path): bool
    {
        try {
            return $this->getFilesystem()->fileExists($path);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function delete(string $path): bool
    {
        try {
            $this->getFilesystem()->delete($path);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function readStream(string $path)
    {
        try {
            return $this->getFilesystem()->readStream($path);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function writeStream(string $path, $resource): bool
    {
        try {
            $this->getFilesystem()->writeStream($path, $resource);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
