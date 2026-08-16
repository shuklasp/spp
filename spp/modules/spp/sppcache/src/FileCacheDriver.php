<?php

namespace SPP\Core;

/**
 * Class FileCache
 * High-performance filesystem-based cache driver.
 */
class FileCache implements CacheInterface
{
    protected string $path;

    public function __construct(string $path = null)
    {
        $this->path = $path ?: (defined('SPP_APP_DIR') ? SPP_APP_DIR . '/var/cache' : 'var/cache');
        if (!is_dir($this->path)) {
            mkdir($this->path, 0777, true);
        }
    }

    public function get(string $key)
    {
        $file = $this->getFilePath($key);
        if (!file_exists($file)) {
            return null;
        }

        $content = file_get_contents($file);
        $data = @unserialize($content, ['allowed_classes' => false]);

        if (!is_array($data) || !isset($data['expires']) || !array_key_exists('value', $data)) {
            return null;
        }

        if ($data['expires'] !== 0 && time() > $data['expires']) {
            $this->delete($key);
            return null;
        }

        return $data['value'];
    }

    public function set(string $key, $value, int $ttl = 3600): bool
    {
        $file = $this->getFilePath($key);
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $data = [
            'expires' => ($ttl === 0) ? 0 : time() + $ttl,
            'value'   => $value
        ];

        // Atomic write via temporary file
        $tempFile = $file . '.' . uniqid('', true) . '.tmp';
        if (file_put_contents($tempFile, serialize($data), LOCK_EX) === false) {
            return false;
        }

        return rename($tempFile, $file);
    }

    public function delete(string $key): bool
    {
        $file = $this->getFilePath($key);
        return file_exists($file) ? @unlink($file) : true;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function clear(): bool
    {
        if (!is_dir($this->path)) {
            return true;
        }
        return $this->recursiveRemoveDir($this->path, true);
    }

    protected function getFilePath(string $key): string
    {
        $hash = md5($key);
        // 2-level directory structure for performance
        return $this->path . SPP_DS . substr($hash, 0, 2) . SPP_DS . substr($hash, 2, 2) . SPP_DS . $hash . '.cache';
    }

    private function recursiveRemoveDir($dir, $isRoot = false): bool
    {
        if (!is_dir($dir)) return true;
        $scandir = @scandir($dir);
        if ($scandir === false) return true;
        $files = array_diff($scandir, ['.', '..']);
        foreach ($files as $file) {
            if ($isRoot && $file === 'system') {
                continue; // Preserve system classmap cache
            }
            (is_dir("$dir/$file")) ? $this->recursiveRemoveDir("$dir/$file", false) : @unlink("$dir/$file");
        }
        return $isRoot ? true : @rmdir($dir);
    }

    public function setWithTags(string $key, $value, array $tags, int $ttl = 3600): bool
    {
        $result = $this->set($key, $value, $ttl);
        foreach ($tags as $tag) {
            $tagFile = $this->getTagFilePath($tag);
            $dir = dirname($tagFile);
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }

            $fp = @fopen($tagFile, 'c+');
            if ($fp) {
                if (flock($fp, LOCK_EX)) {
                    $size = filesize($tagFile);
                    $existing = $size > 0 ? json_decode(fread($fp, $size), true) : [];
                    if (!is_array($existing)) $existing = [];
                    if (!in_array($key, $existing)) {
                        $existing[] = $key;
                        ftruncate($fp, 0);
                        rewind($fp);
                        fwrite($fp, json_encode($existing));
                    }
                    flock($fp, LOCK_UN);
                }
                fclose($fp);
            }
        }
        return $result;
    }

    public function invalidateTag(string $tag): bool
    {
        $tagFile = $this->getTagFilePath($tag);
        if (!file_exists($tagFile)) {
            return true;
        }

        $keys = json_decode(file_get_contents($tagFile), true);
        if (is_array($keys)) {
            foreach ($keys as $key) {
                $this->delete($key);
            }
        }
        @unlink($tagFile);
        return true;
    }

    protected function getTagFilePath(string $tag): string
    {
        return $this->path . SPP_DS . '_tags' . SPP_DS . md5($tag) . '.tag';
    }

    public function getWithLock(string $key, int $ttl, callable $callback)
    {
        $value = $this->get($key);
        if ($value !== null) {
            return $value;
        }
        $lockFile = $this->path . SPP_DS . md5($key) . '.lock';
        $fp = @fopen($lockFile, 'w+');
        if ($fp && flock($fp, LOCK_EX)) {
            // Check again in case another process just computed it
            $value = $this->get($key);
            if ($value === null) {
                $value = $callback();
                $this->set($key, $value, $ttl);
            }
            flock($fp, LOCK_UN);
            fclose($fp);
            @unlink($lockFile);
            return $value;
        }
        // Fallback if lock fails
        $value = $callback();
        $this->set($key, $value, $ttl);
        return $value;
    }

    public function prune(): bool
    {
        if (!is_dir($this->path)) return true;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->path, \FilesystemIterator::SKIP_DOTS));
        $now = time();
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'cache') {
                $content = @file_get_contents($file->getPathname());
                if ($content) {
                    $payload = json_decode($content, true);
                    if (isset($payload['expiry']) && $payload['expiry'] < $now) {
                        @unlink($file->getPathname());
                    }
                }
            }
        }
        return true;
    }

    public function stats(): array
    {
        $totalFiles = 0;
        $totalSize = 0;
        if (is_dir($this->path)) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->path, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->isFile() && ($file->getExtension() === 'cache' || $file->getExtension() === 'tag')) {
                    $totalFiles++;
                    $totalSize += $file->getSize();
                }
            }
        }
        return [
            'driver' => 'FileCache',
            'path' => $this->path,
            'total_files' => $totalFiles,
            'total_size_bytes' => $totalSize
        ];
    }
}
