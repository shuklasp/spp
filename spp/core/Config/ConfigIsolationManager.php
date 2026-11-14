<?php
declare(strict_types=1);

namespace SPP\Core\Config;

/**
 * Class ConfigIsolationManager
 *
 * Isolates instance-specific effective runtime configurations from deployment updates.
 * Provides on-demand synthesis from pristine generic distribution templates (etc.dist/)
 * to effective configuration directories (etc/ and spp/etc/).
 *
 * Invariant: Live effective configuration files (e.g. domains.yml, global-settings.yml,
 * dns-domains.yml, cloudflare.yml) must NEVER be overwritten or deleted during deploy updates.
 *
 * @package SPP\Core\Config
 * @author Satya Prakash Shukla
 */
class ConfigIsolationManager
{
    public const DIR_GENERIC = 'etc.dist';
    public const DIR_EFFECTIVE = 'etc';

    /**
     * List of protected configuration basenames that must be isolated on live targets.
     */
    public const PROTECTED_FILENAMES = [
        'domains.yml',
        'domains.local.yml',
        'global-settings.yml',
        'global-config.yml',
        'dns-domains.yml',
        'dns-providers.yml',
        'cloudflare.yml',
        'mail.yml',
        'watchdog.yml',
        'backup_vault.yml',
        'apps.yml',
        'db.yml',
        '.server_secret',
        '.env',
    ];

    /**
     * Resolves the project root directory.
     */
    public static function getProjectRoot(): string
    {
        if (defined('SPP_APP_DIR') && is_dir(SPP_APP_DIR)) {
            return rtrim(str_replace('\\', '/', realpath(SPP_APP_DIR) ?: SPP_APP_DIR), '/');
        }
        if (defined('SPP_BASE_DIR') && is_dir(SPP_BASE_DIR)) {
            $base = rtrim(str_replace('\\', '/', realpath(SPP_BASE_DIR) ?: SPP_BASE_DIR), '/');
            if (basename($base) === 'spp') {
                return dirname($base);
            }
            return $base;
        }
        $cwd = getcwd() ?: '.';
        return rtrim(str_replace('\\', '/', realpath($cwd) ?: $cwd), '/');
    }

    /**
     * Resolves the canonical generic distribution templates directory (etc.dist/).
     */
    public static function getGenericTemplateDir(?string $rootDir = null): string
    {
        $root = $rootDir ? rtrim(str_replace('\\', '/', $rootDir), '/') : self::getProjectRoot();
        $candidates = [
            $root . '/' . self::DIR_GENERIC,
            $root . '/spp/' . self::DIR_GENERIC,
            $root . '/etc/templates',
            $root . '/spp/etc/templates',
        ];

        foreach ($candidates as $dir) {
            if (is_dir($dir)) {
                return $dir;
            }
        }

        return $root . '/' . self::DIR_GENERIC;
    }

    /**
     * Resolves the effective live configuration directory (etc/).
     */
    public static function getEffectiveConfigDir(?string $rootDir = null): string
    {
        if ($rootDir !== null) {
            $root = rtrim(str_replace('\\', '/', $rootDir), '/');
            if (is_dir($root . '/' . self::DIR_EFFECTIVE)) {
                return $root . '/' . self::DIR_EFFECTIVE;
            }
            if (is_dir($root . '/spp/' . self::DIR_EFFECTIVE)) {
                return $root . '/spp/' . self::DIR_EFFECTIVE;
            }
            return $root . '/' . self::DIR_EFFECTIVE;
        }

        if (defined('SPP_ETC_DIR') && is_dir(SPP_ETC_DIR)) {
            return rtrim(str_replace('\\', '/', SPP_ETC_DIR), '/');
        }
        $root = self::getProjectRoot();
        if (is_dir($root . '/' . self::DIR_EFFECTIVE)) {
            return $root . '/' . self::DIR_EFFECTIVE;
        }
        if (is_dir($root . '/spp/' . self::DIR_EFFECTIVE)) {
            return $root . '/spp/' . self::DIR_EFFECTIVE;
        }
        return $root . '/' . self::DIR_EFFECTIVE;
    }

    /**
     * Checks if a given relative or absolute path represents an effective configuration file
     * that must be isolated and protected from deployment overwrites or deletion.
     *
     * @param string $path File path relative to project root or absolute path
     * @return bool True if protected; false otherwise
     */
    public static function isProtectedConfigFile(string $path): bool
    {
        $norm = str_replace('\\', '/', trim($path));
        $clean = ltrim($norm, '/');
        $basename = basename($clean);

        // Explicitly allow generic distribution templates to be updated via deploy
        if (str_starts_with($clean, 'etc.dist/') || str_starts_with($clean, 'spp/etc.dist/') || str_contains($clean, '/etc.dist/')) {
            return false;
        }

        // Environment variables
        if ($basename === '.env' || str_starts_with($basename, '.env.')) {
            return true;
        }

        // Server secrets
        if ($basename === '.server_secret') {
            return true;
        }

        // Domain-scoped configurations (domains/<domain>/etc/**)
        if (preg_match('#(^|/)domains/[^/]+/etc(/|$)#i', $clean)) {
            return true;
        }

        // App-scoped configurations (etc/apps/** or spp/etc/apps/**)
        if (preg_match('#(^|/)(spp/)?etc/apps/.+\.(yml|yaml|json|php)$#i', $clean)) {
            return true;
        }

        // Direct effective config files in etc/ or spp/etc/
        if (preg_match('#(^|/)(spp/)?etc/[^/]+$#i', $clean)) {
            if (in_array(strtolower($basename), self::PROTECTED_FILENAMES, true)) {
                return true;
            }
            if (str_ends_with(strtolower($basename), '.local.yml') || str_ends_with(strtolower($basename), '.local.yaml')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ensures an effective configuration file exists in the effective configuration directory.
     * If the effective configuration file already exists, it is NEVER modified or overwritten.
     * If it does not exist, it is automatically copied from the generic template (etc.dist/<filename>).
     *
     * @param string $filename Config file basename (e.g. 'domains.yml', 'global-settings.yml')
     * @param string|null $targetDir Target effective directory (defaults to effective config dir)
     * @return string Absolute or canonical path to the effective configuration file
     */
    public static function ensureEffectiveConfig(string $filename, ?string $targetDir = null, ?string $rootDir = null): string
    {
        $cleanFile = ltrim(str_replace('\\', '/', $filename), '/');
        $effectiveDir = $targetDir ? rtrim(str_replace('\\', '/', $targetDir), '/') : self::getEffectiveConfigDir($rootDir);
        $effectivePath = $effectiveDir . '/' . $cleanFile;

        // 1. If effective configuration file already exists, preserve it completely
        if (file_exists($effectivePath)) {
            return $effectivePath;
        }

        // Check fallback location in spp/etc only if targetDir was not explicitly specified
        if ($targetDir === null) {
            $root = $rootDir ? rtrim(str_replace('\\', '/', $rootDir), '/') : self::getProjectRoot();
            $fallbackEffective = $root . '/spp/etc/' . $cleanFile;
            if (file_exists($fallbackEffective)) {
                return $fallbackEffective;
            }
        }

        // 2. Locate generic template
        $templateDir = self::getGenericTemplateDir($rootDir);
        $templatePath = $templateDir . '/' . $cleanFile;

        if (!file_exists($templatePath)) {
            // Check secondary template locations
            $root = $rootDir ? rtrim(str_replace('\\', '/', $rootDir), '/') : self::getProjectRoot();
            $altTemplates = [
                $root . '/spp/' . self::DIR_GENERIC . '/' . $cleanFile,
                $root . '/etc/templates/' . $cleanFile,
                $root . '/spp/etc/templates/' . $cleanFile,
            ];
            foreach ($altTemplates as $alt) {
                if (file_exists($alt)) {
                    $templatePath = $alt;
                    break;
                }
            }
        }

        // 3. Ensure target directory exists
        $dir = dirname($effectivePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        // 4. Copy generic template or synthesize minimal valid fallback
        if (file_exists($templatePath)) {
            @copy($templatePath, $effectivePath);
            @chmod($effectivePath, 0664);
        } else {
            $fallbackContent = self::generateMinimalFallback($cleanFile);
            @file_put_contents($effectivePath, $fallbackContent);
            @chmod($effectivePath, 0664);
        }

        return $effectivePath;
    }

    /**
     * Seeds all missing effective configuration files from generic templates (etc.dist/).
     * Existing effective configuration files are NEVER touched or overwritten.
     *
     * @param string|null $rootDir
     * @return array<string, string> Map of seeded relative paths to destination paths
     */
    public static function ensureAllEffectiveConfigs(?string $rootDir = null): array
    {
        $templateDir = self::getGenericTemplateDir($rootDir);
        $effectiveDir = self::getEffectiveConfigDir($rootDir);
        $seeded = [];

        if (!is_dir($templateDir)) {
            return $seeded;
        }

        $files = scandir($templateDir) ?: [];
        foreach ($files as $f) {
            if ($f === '.' || $f === '..' || $f === 'README.md' || is_dir($templateDir . '/' . $f)) {
                continue;
            }

            $effectivePath = $effectiveDir . '/' . $f;
            if (!file_exists($effectivePath)) {
                self::ensureEffectiveConfig($f, $effectiveDir, $rootDir);
                $seeded[$f] = $effectivePath;
            }
        }

        return $seeded;
    }

    /**
     * Copies a generic template to effective config.
     *
     * @param string $filename Basename of config file
     * @param bool $force If true, overwrites existing effective config
     * @param string|null $rootDir
     * @return bool True if copied; false if skipped or failed
     */
    public static function copyGenericToEffective(string $filename, bool $force = false, ?string $rootDir = null): bool
    {
        $cleanFile = ltrim(str_replace('\\', '/', $filename), '/');
        $effectiveDir = self::getEffectiveConfigDir($rootDir);
        $effectivePath = $effectiveDir . '/' . $cleanFile;

        if (file_exists($effectivePath) && !$force) {
            return false;
        }

        $templateDir = self::getGenericTemplateDir($rootDir);
        $templatePath = $templateDir . '/' . $cleanFile;

        if (!file_exists($templatePath)) {
            return false;
        }

        $dir = dirname($effectivePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $res = @copy($templatePath, $effectivePath);
        if ($res) {
            @chmod($effectivePath, 0664);
        }
        return (bool)$res;
    }

    /**
     * Generates a minimal valid YAML fallback for a standard config filename.
     */
    private static function generateMinimalFallback(string $filename): string
    {
        return match (strtolower(basename($filename))) {
            'domains.yml' => "# SPP Domain Configuration\nmode: single\ndefault_app: default\nvirtual_host_mode: strict\nunmapped_subdomain_policy: block\ndomain_only_apps: {}\ndomains: {}\n",
            'dns-domains.yml' => "domains: {}\n",
            'dns-providers.yml' => "version: 2\naccounts: {}\n",
            'cloudflare.yml' => "enabled: false\napi_token: ''\nzone_id: ''\naccount_id: ''\ndefault_proxied: true\n",
            'mail.yml' => "driver: smtp\nhost: localhost\nport: 587\nencryption: tls\nusername: ''\npassword: ''\nfrom_address: admin@example.com\nfrom_name: 'SPP System'\n",
            'apps.yml' => "apps: []\n",
            'backup_vault.yml' => "enabled: true\ndestinations:\n  local:\n    driver: local\n    path: var/backups\n",
            'watchdog.yml' => "enabled: true\ncheck_interval: 60\nservices:\n  mysql: true\n  redis: false\n",
            'db.yml' => "default:\n  driver: sqlite\n  database: var/db/default.sqlite\n",
            default => "# SPP Effective Configuration\n# Initialized on demand\n",
        };
    }
}
