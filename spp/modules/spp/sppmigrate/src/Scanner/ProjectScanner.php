<?php
namespace SPPMod\SPPMigrate\Scanner;

class ProjectScanner {
    
    private array $excludePatterns = [
        '.git/',
        '.gemini/',
        'spp/etc/cache/',
        'node_modules/',
        'vendor/',
        'tmp/',
        'uploads/'
    ];

    public function scan(string $baseDir): array {
        $hashes = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($baseDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $path = $file->getPathname();
                $relativePath = str_replace('\\', '/', str_replace($baseDir, '', $path));
                $relativePath = ltrim($relativePath, '/');

                if ($this->shouldExclude($relativePath)) {
                    continue;
                }

                $hashes[$relativePath] = md5_file($path);
            }
        }

        return $hashes;
    }

    private function shouldExclude(string $path): bool {
        foreach ($this->excludePatterns as $pattern) {
            if (str_contains($path, $pattern)) {
                return true;
            }
        }
        return false;
    }
}
