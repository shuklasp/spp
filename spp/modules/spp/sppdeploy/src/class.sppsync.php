<?php

namespace SPPMod\Sppdeploy;

/**
 * SPPSync Module - Incremental Mirroring & Differential Sync Engine
 */
class SPPSync
{
    /**
     * Generate a manifest of the current system state.
     * Respects exclusion rules for environment-specific configs.
     */
    public static function generateManifest(array $exclude = []): array
    {
        $manifest = [
            'timestamp' => time(),
            'files' => [],
            'xdb' => [],
            'version' => '1.1.0'
        ];

        // 1. Scan Code & Config
        $scanPaths = [
            'src' => SPP_APP_DIR . '/src',
            'etc' => SPP_APP_DIR . '/spp/etc'
        ];

        foreach ($scanPaths as $key => $path) {
            if (is_dir($path)) {
                $manifest['files'][$key] = self::scanDir($path, $exclude);
            }
        }

        // 2. Scan XDB Data
        $xdbDir = SPP_APP_DIR . '/var/data';
        if (is_dir($xdbDir)) {
            $manifest['xdb'] = self::scanXDB($xdbDir);
        }

        return $manifest;
    }

    /**
     * Compare local manifest with a remote manifest and identify deltas.
     */
    public static function calculateDeltas(array $local, array $remote): array
    {
        $deltas = [
            'files' => ['upload' => [], 'download' => []],
            'xdb' => ['push' => [], 'pull' => []]
        ];

        foreach (['src', 'etc'] as $type) {
            $localFiles = $local['files'][$type] ?? [];
            $remoteFiles = $remote['files'][$type] ?? [];

            foreach ($localFiles as $path => $hash) {
                if (!isset($remoteFiles[$path]) || $remoteFiles[$path] !== $hash) {
                    $deltas['files']['upload'][] = ['type' => $type, 'path' => $path];
                }
            }
        }

        foreach ($local['xdb'] as $dbName => $collections) {
            foreach ($collections as $collName => $meta) {
                $remoteMeta = $remote['xdb'][$dbName][$collName] ?? null;
                if (!$remoteMeta || $remoteMeta['hash'] !== $meta['hash']) {
                    $deltas['xdb']['push'][] = ['db' => $dbName, 'collection' => $collName];
                }
            }
        }

        return $deltas;
    }

    private static function scanDir(string $dir, array $exclude = []): array
    {
        $files = [];
        if (!is_dir($dir)) {
            return [];
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        $rootLen = strlen($dir);

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relativePath = substr($file->getPathname(), $rootLen);
                $normalizedRelative = str_replace('\\', '/', $relativePath);

                // Check exclusion rules
                $isExcluded = false;
                foreach ($exclude as $rule) {
                    if (str_contains($normalizedRelative, $rule)) {
                        $isExcluded = true;
                        break;
                    }
                }

                if (!$isExcluded) {
                    $files[$normalizedRelative] = md5_file($file->getPathname());
                }
            }
        }
        return $files;
    }

    private static function scanXDB(string $baseDir): array
    {
        $databases = [];
        if (!is_dir($baseDir)) {
            return [];
        }
        $dirs = array_diff(scandir($baseDir), ['.', '..']);
        foreach ($dirs as $db) {
            if (is_dir($baseDir . '/' . $db)) {
                $databases[$db] = [];
                $colls = array_diff(scandir($baseDir . '/' . $db), ['.', '..']);
                foreach ($colls as $c) {
                    if (str_ends_with($c, '.xml') || str_ends_with($c, '.json')) {
                        $path = $baseDir . '/' . $db . '/' . $c;
                        $databases[$db][$c] = [
                            'size' => filesize($path),
                            'hash' => md5_file($path)
                        ];
                    }
                }
            }
        }
        return $databases;
    }

    public static function createBackup(string $targetPath): bool
    {
        if (!class_exists('\ZipArchive')) {
            return false;
        }
        $zip = new \ZipArchive();
        if ($zip->open($targetPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        $paths = ['src' => SPP_APP_DIR . '/src', 'etc' => SPP_APP_DIR . '/spp/etc', 'data' => SPP_APP_DIR . '/var/data'];
        foreach ($paths as $prefix => $path) {
            if (is_dir($path)) {
                $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
                foreach ($files as $name => $file) {
                    if (!$file->isDir()) {
                        $filePath = $file->getRealPath();
                        $zip->addFile($filePath, $prefix . '/' . substr($filePath, strlen($path) + 1));
                    }
                }
            }
        }
        $zip->close();
        return true;
    }
}
