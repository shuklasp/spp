<?php
namespace SPPMod\SPPDeploy\Scanner;

class ProjectScanner
{
    private bool $includeConfig = false;

    public function setIncludeConfig(bool $include): self
    {
        $this->includeConfig = $include;
        return $this;
    }

    private array $excludePatterns = [
        '#^/\.git#',
        '#^/vendor#',
        '#^/node_modules#',
        '#^/\.phpdoc#',
        '#(^|/)\.gemini#',
        '#(^|/)\.antigravity#',
        '#(^|/)\.antigravitycli#',
        '#(^|/)brain#',
        '#(^|/)scratch#',
        '#^/\.vs#',
        '#^/\.vscode#',
        '#^/VSCodeCounter#',
        '#^/\.idea#',
        '#^/var#',
        '#^/spp/var#',
        '#^/spp/spp#',
        '#^/tests#',
        '#^/\.sppdeploy\.yml#',
        '#^/\.maintenance#'
    ];

    private array $excludeRules = [
        '*.sqlite-journal',
        '*.sqlite-wal',
        '*.sqlite-shm',
        '*.zip',
        '*.tar',
        '*.gz',
        '*.tgz',
        '*.rar',
        '*.7z',
        '.env.local',
        '.env.testing',
        '.env.dev',
        'docker-compose.override.yml',
        'docker-compose.local.yml',
        '*.local.yml',
        '*.local.yaml',
        '*.local.php',
        '*.local.json'
    ];

    public function addExcludeRule(string $rule): self
    {
        $rule = trim($rule);
        if ($rule !== '' && !str_starts_with($rule, '#')) {
            $this->excludeRules[] = $rule;
        }
        return $this;
    }

    public function scan(string $dir, ?array $scopedPaths = null, ?array $extraExcludes = null): array
    {
        $hashes = [];
        $base = rtrim(str_replace('\\', '/', realpath($dir) ?: $dir), '/');

        $ignoreFile = $base . '/.sppignore';
        if (file_exists($ignoreFile)) {
            $lines = file($ignoreFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#'))
                    continue;
                $this->addExcludeRule($line);
            }
        }

        $confCandidates = array_merge([
            $base . '/.sppdeploy.yml',
            $base . '/spp/.sppdeploy.yml',
            $base . '/.sppdeploy.local.yml',
            $base . '/spp/.sppdeploy.local.yml'
        ], glob($base . '/domains/*/etc/.sppdeploy.yml') ?: []);
        foreach ($confCandidates as $confFile) {
            if (file_exists($confFile)) {
                $conf = class_exists('\\SPPMod\\SPPDeploy\\Deployer\\TargetConnection')
                    ? \SPPMod\SPPDeploy\Deployer\TargetConnection::parseYamlFile($confFile)
                    : (class_exists('\\Symfony\\Component\\Yaml\\Yaml') ? \Symfony\Component\Yaml\Yaml::parseFile($confFile) : []);
                
                // 1. Standard exclude rules
                if (isset($conf['exclude']) && is_array($conf['exclude'])) {
                    foreach ($conf['exclude'] as $ex) {
                        $this->addExcludeRule((string)$ex);
                    }
                }
                // 2. Explicit exclude_files / exclude_paths
                if (isset($conf['exclude_files']) && is_array($conf['exclude_files'])) {
                    foreach ($conf['exclude_files'] as $ex) {
                        $this->addExcludeRule((string)$ex);
                    }
                }
                if (isset($conf['exclude_paths']) && is_array($conf['exclude_paths'])) {
                    foreach ($conf['exclude_paths'] as $ex) {
                        $this->addExcludeRule((string)$ex);
                    }
                }
                // 3. Local testing data exclusion
                if (!empty($conf['exclude_data']) || !empty($conf['no_data'])) {
                    $this->addExcludeRule('*.sqlite');
                    $this->addExcludeRule('*.sqlite3');
                    $this->addExcludeRule('*.db');
                    $this->addExcludeRule('var/data/*');
                    $this->addExcludeRule('data/*');
                }
            }
        }

        if (!empty($extraExcludes)) {
            foreach ($extraExcludes as $ex) {
                $this->addExcludeRule((string)$ex);
            }
        }

        // Clean up scoped paths if provided
        $cleanScopes = [];
        if (!empty($scopedPaths)) {
            foreach ($scopedPaths as $sp) {
                $clean = trim(str_replace('\\', '/', $sp), '/');
                if ($clean !== '') {
                    $cleanScopes[] = $clean;
                }
            }
        }

        if (!empty($cleanScopes)) {
            // High-performance scoped scan: directly scan only requested files/directories
            foreach ($cleanScopes as $cs) {
                $targetPath = $base . '/' . $cs;
                if (!file_exists($targetPath)) {
                    continue;
                }
                if (is_file($targetPath)) {
                    $relPath = '/' . $cs;
                    $entry = basename($cs);
                    if ($this->shouldSkip($relPath, $cs, $entry)) {
                        continue;
                    }
                    $hashes[$cs] = hash_file('md5', $targetPath);
                } elseif (is_dir($targetPath)) {
                    $this->scanDirectory($targetPath, $base, $hashes);
                }
            }
            return $hashes;
        }

        // Full project scan with directory-level pruning
        $this->scanDirectory($base, $base, $hashes);
        return $hashes;
    }

    private function shouldSkip(string $relPath, string $cleanRel, string $entry): bool
    {
        // Whitelist public package repositories (var/packages or dist/packages)
        if (str_starts_with($cleanRel, 'var/packages') || str_starts_with($cleanRel, 'dist/packages')) {
            return false;
        }

        // Isolate live effective config files (etc/domains.yml, etc/global-settings.yml, etc.)
        if (!$this->includeConfig) {
            if (!class_exists('\\SPP\\Core\\Config\\ConfigIsolationManager', false)) {
                $cim = dirname(__DIR__, 5) . '/core/Config/ConfigIsolationManager.php';
                if (file_exists($cim)) {
                    require_once $cim;
                }
            }
            if (class_exists('\\SPP\\Core\\Config\\ConfigIsolationManager') && \SPP\Core\Config\ConfigIsolationManager::isProtectedConfigFile($cleanRel)) {
                return true;
            }
        }

        foreach ($this->excludePatterns as $pattern) {
            if (preg_match($pattern, $relPath)) {
                return true;
            }
        }

        foreach ($this->excludeRules as $rule) {
            if (str_contains($rule, '/')) {
                $cleanRule = ltrim($rule, '/');
                if (fnmatch($cleanRule, $cleanRel, FNM_CASEFOLD) || fnmatch($rule, $relPath, FNM_CASEFOLD)) {
                    return true;
                }
                if (str_starts_with($cleanRel, rtrim($cleanRule, '/*') . '/')) {
                    return true;
                }
            } else {
                if (fnmatch($rule, $entry, FNM_CASEFOLD)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function scanDirectory(string $dir, string $base, array &$hashes): void
    {
        if (!is_dir($dir)) return;
        $handle = @opendir($dir);
        if (!$handle) return;

        while (false !== ($entry = readdir($handle))) {
            if ($entry === '.' || $entry === '..') continue;

            $fullPath = $dir . '/' . $entry;
            $relPath = substr(str_replace('\\', '/', $fullPath), strlen($base));
            $cleanRel = ltrim($relPath, '/');

            if (is_dir($fullPath)) {
                // Prune high-cost excluded directories immediately without recursion
                if ($entry === 'var') {
                    $pkgDir = $fullPath . '/packages';
                    if (is_dir($pkgDir)) {
                        $this->scanDirectory($pkgDir, $base, $hashes);
                    }
                    continue;
                }
                if (in_array($entry, ['.git', '.gemini', '.antigravitycli', '.vs', '.vscode', 'VSCodeCounter', '.dist', 'dist', '.idea', '.phpdoc', 'documentation', 'brain', 'scratch', 'node_modules', 'vendor', 'tests', 'cache', 'logs', 'sessions', 'backups'], true)) {
                    continue;
                }
                if ($cleanRel === 'spp/spp' || str_starts_with($cleanRel, 'spp/spp/')) {
                    continue;
                }
                if ($this->shouldSkip($relPath, $cleanRel, $entry)) {
                    continue;
                }
                $this->scanDirectory($fullPath, $base, $hashes);
            } elseif (is_file($fullPath)) {
                if ($this->shouldSkip($relPath, $cleanRel, $entry)) {
                    continue;
                }
                if (str_ends_with($entry, '.log') || $entry === 'error_log' || $entry === '.DS_Store' || $entry === 'Thumbs.db') {
                    continue;
                }
                $hashes[$cleanRel] = hash_file('md5', $fullPath);
            }
        }
        closedir($handle);
    }
}
