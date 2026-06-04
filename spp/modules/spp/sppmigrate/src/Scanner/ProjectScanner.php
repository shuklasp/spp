<?php

namespace SPPMod\SPPMigrate\Scanner;

class ProjectScanner
{
    private array $excludePatterns = [
        '.git/',
        '.gemini/',
        'spp/etc/cache/',
        'node_modules/',
        'vendor/',
        'tmp/',
        'uploads/'
    ];

    public function scan(string $baseDir): array
    {
        $hashes = [];
        $this->scanDir($baseDir, $baseDir, $hashes);
        return $hashes;
    }

    private function scanDir(string $currentDir, string $baseDir, array &$hashes): void
    {
        if (!is_dir($currentDir)) {
            return;
        }

        $files = scandir($currentDir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $currentDir . '/' . $file;
            if (is_link($path)) {
                continue;
            } // skip symlinks

            $normalizedPath = str_replace('\\', '/', $path);
            $normalizedBaseDir = str_replace('\\', '/', $baseDir);

            $relativePath = ltrim(str_replace($normalizedBaseDir, '', $normalizedPath), '/');

            if ($this->shouldExclude($relativePath)) {
                continue;
            }

            if (is_dir($path)) {
                $this->scanDir($path, $baseDir, $hashes);
            } else {
                $hashes[$relativePath] = hash_file('xxh3', $path);
            }
        }
    }

    private function shouldExclude(string $path): bool
    {
        // Exclude completely matches directory names or paths
        foreach ($this->excludePatterns as $pattern) {
            $normalizedPattern = trim(str_replace('\\', '/', $pattern), '/');
            if (str_contains('/' . $path . '/', '/' . $normalizedPattern . '/')) {
                return true;
            }
            if (str_starts_with($path, $normalizedPattern . '/')) {
                return true;
            }
        }
        return false;
    }
}
