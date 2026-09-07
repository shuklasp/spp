<?php

namespace SPPMod\SPPStorage;

use Symfony\Component\Yaml\Yaml;

interface StorageDriverInterface
{
    public function get(string $path): ?string;
    public function put(string $path, string $contents): bool;
    public function delete(string $path): bool;
    public function exists(string $path): bool;
    public function url(string $path): string;
    public function size(string $path): int;
    public function lastModified(string $path): int;
}

class LocalStorageDriver implements StorageDriverInterface
{
    protected string $root;
    protected string $baseUrl;

    public function __construct(array $config = [])
    {
        $baseDir = defined('SPP_BASE_DIR') ? dirname(SPP_BASE_DIR) : dirname(__DIR__, 4);
        $this->root = rtrim($config['root'] ?? ($baseDir . '/public/uploads'), '/\\');
        $this->baseUrl = rtrim($config['base_url'] ?? '/public/uploads', '/\\');
    }

    public function get(string $path): ?string
    {
        $full = $this->root . '/' . ltrim($path, '/\\');
        return file_exists($full) ? file_get_contents($full) : null;
    }

    public function put(string $path, string $contents): bool
    {
        $full = $this->root . '/' . ltrim($path, '/\\');
        $dir = dirname($full);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return file_put_contents($full, $contents, LOCK_EX) !== false;
    }

    public function delete(string $path): bool
    {
        $full = $this->root . '/' . ltrim($path, '/\\');
        return file_exists($full) ? @unlink($full) : true;
    }

    public function exists(string $path): bool
    {
        return file_exists($this->root . '/' . ltrim($path, '/\\'));
    }

    public function url(string $path): string
    {
        return $this->baseUrl . '/' . ltrim($path, '/\\');
    }

    public function size(string $path): int
    {
        $full = $this->root . '/' . ltrim($path, '/\\');
        return file_exists($full) ? filesize($full) : 0;
    }

    public function lastModified(string $path): int
    {
        $full = $this->root . '/' . ltrim($path, '/\\');
        return file_exists($full) ? filemtime($full) : 0;
    }
}

class S3StorageDriver implements StorageDriverInterface
{
    protected string $bucket;
    protected string $region;
    protected string $key;
    protected string $secret;
    protected string $endpoint;

    public function __construct(array $config = [])
    {
        $this->bucket = $config['bucket'] ?? 'spp-storage';
        $this->region = $config['region'] ?? 'us-east-1';
        $this->key = $config['key'] ?? '';
        $this->secret = $config['secret'] ?? '';
        $this->endpoint = rtrim($config['endpoint'] ?? "https://{$this->bucket}.s3.{$this->region}.amazonaws.com", '/');
    }

    public function get(string $path): ?string
    {
        $url = $this->url($path);
        $headers = $this->signRequest('GET', $path);
        
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => $headers,
                'ignore_errors' => true,
                'timeout' => 10,
            ]
        ]);
        
        $data = @file_get_contents($url, false, $ctx);
        return $data !== false ? $data : null;
    }

    public function put(string $path, string $contents): bool
    {
        $url = $this->url($path);
        $headers = $this->signRequest('PUT', $path, $contents);
        
        $ctx = stream_context_create([
            'http' => [
                'method' => 'PUT',
                'header' => $headers,
                'content' => $contents,
                'ignore_errors' => true,
                'timeout' => 30,
            ]
        ]);

        $res = @file_get_contents($url, false, $ctx);
        return $res !== false;
    }

    public function delete(string $path): bool
    {
        $url = $this->url($path);
        $headers = $this->signRequest('DELETE', $path);
        
        $ctx = stream_context_create([
            'http' => [
                'method' => 'DELETE',
                'header' => $headers,
                'ignore_errors' => true,
                'timeout' => 10,
            ]
        ]);

        return @file_get_contents($url, false, $ctx) !== false;
    }

    public function exists(string $path): bool
    {
        $url = $this->url($path);
        $headers = $this->signRequest('HEAD', $path);
        
        $ctx = stream_context_create([
            'http' => [
                'method' => 'HEAD',
                'header' => $headers,
                'ignore_errors' => true,
                'timeout' => 5,
            ]
        ]);

        $fp = @fopen($url, 'r', false, $ctx);
        if ($fp) {
            fclose($fp);
            return true;
        }
        return false;
    }

    public function url(string $path): string
    {
        return $this->endpoint . '/' . ltrim($path, '/');
    }

    public function size(string $path): int
    {
        return 0;
    }

    public function lastModified(string $path): int
    {
        return time();
    }

    protected function signRequest(string $method, string $path, string $payload = ''): array
    {
        $date = gmdate('Ymd\THis\Z');
        $headers = [
            "Host: " . parse_url($this->endpoint, PHP_URL_HOST),
            "x-amz-date: {$date}",
            "x-amz-content-sha256: " . hash('sha256', $payload),
        ];

        // If credentials supplied, attach Authorization header
        if (!empty($this->key) && !empty($this->secret)) {
            $headers[] = "Authorization: AWS4-HMAC-SHA256 Credential={$this->key}/" . substr($date, 0, 8) . "/{$this->region}/s3/aws4_request";
        }

        return $headers;
    }
}

class StorageManager
{
    protected static array $disks = [];
    protected static ?array $config = null;

    protected static function loadConfig(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        $baseDir = defined('SPP_BASE_DIR') ? dirname(SPP_BASE_DIR) : dirname(__DIR__, 4);
        $cfgFile = $baseDir . '/spp/etc/storage.yml';
        if (file_exists($cfgFile)) {
            try {
                self::$config = Yaml::parseFile($cfgFile) ?: [];
            } catch (\Throwable $e) {
                self::$config = [];
            }
        } else {
            self::$config = [
                'default' => 'local',
                'disks' => [
                    'local' => ['driver' => 'local'],
                    's3' => ['driver' => 's3', 'bucket' => 'spp-storage'],
                ]
            ];
        }

        return self::$config;
    }

    public static function disk(?string $name = null): StorageDriverInterface
    {
        $cfg = self::loadConfig();
        $name = $name ?: ($cfg['default'] ?? 'local');

        if (isset(self::$disks[$name])) {
            return self::$disks[$name];
        }

        $diskCfg = $cfg['disks'][$name] ?? ['driver' => 'local'];
        $driver = $diskCfg['driver'] ?? 'local';

        if ($driver === 's3' || $driver === 'r2') {
            self::$disks[$name] = new S3StorageDriver($diskCfg);
        } else {
            self::$disks[$name] = new LocalStorageDriver($diskCfg);
        }

        return self::$disks[$name];
    }
}
