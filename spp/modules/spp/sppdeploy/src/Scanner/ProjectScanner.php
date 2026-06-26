<?php
namespace SPPMod\SPPDeploy\Scanner;

class ProjectScanner
{
    private array $excludePatterns = [
        '#^/\.git#',
        '#^/var/cache#',
        '#^/var/logs#',
        '#^/var/sessions#',
        '#^/var/backups#',
        '#^/\.sppdeploy\.yml#',
        '#^/\.maintenance#'
    ];

    public function scan(string $dir): array
    {
        $hashes = [];
        $base = rtrim(realpath($dir), '/');

        $ignoreFile = $base . '/.sppignore';
        if (file_exists($ignoreFile)) {
            $lines = file($ignoreFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#'))
                    continue;
                $pattern = '#^/' . str_replace('\*', '.*', preg_quote($line, '#')) . '#';
                $this->excludePatterns[] = $pattern;
            }
        }

        $confFile = $base . '/spp/.sppdeploy.yml';
        if (file_exists($confFile)) {
            $conf = @yaml_parse_file($confFile);
            if (isset($conf['exclude']) && is_array($conf['exclude'])) {
                foreach ($conf['exclude'] as $ex) {
                    $pattern = '#^/' . str_replace('\*', '.*', preg_quote($ex, '#')) . '#';
                    $this->excludePatterns[] = $pattern;
                }
            }
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile())
                continue;

            $path = str_replace('\\', '/', $file->getPathname());
            $relPath = substr($path, strlen($base));
            if ($relPath === false)
                continue;

            $skip = false;
            foreach ($this->excludePatterns as $pattern) {
                if (preg_match($pattern, $relPath)) {
                    $skip = true;
                    break;
                }
            }
            if ($skip)
                continue;

            $hashes[ltrim($relPath, '/')] = hash_file('md5', $path);
        }

        return $hashes;
    }
}
