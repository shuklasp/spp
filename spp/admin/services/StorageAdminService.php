<?php
declare(strict_types=1);

namespace SPP\Admin\Services;

use ZipArchive;
use Throwable;
use Symfony\Component\Yaml\Yaml;

/**
 * Class StorageAdminService
 *
 * File Manager and Storage Quota service for SPP Admin.
 * Provides secure directory browsing, embedded code editor file I/O,
 * zip archive unpacking, and live storage quota analytics.
 */
class StorageAdminService
{
    /**
     * Returns the host operating system root path ('/' on Linux, drive root e.g. 'C:/' on Windows).
     */
    public static function getSystemRoot(): string
    {
        if (DIRECTORY_SEPARATOR === '/') {
            return '/';
        }
        $baseDir = realpath(defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 3));
        if ($baseDir && preg_match('/^[a-zA-Z]:/', $baseDir, $m)) {
            return strtoupper($m[0]) . '/';
        }
        return 'C:/';
    }

    /**
     * Returns the active SPP workspace root directory.
     */
    public static function getWorkspaceRoot(): string
    {
        $base = realpath(defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 3));
        return str_replace('\\', '/', $base ?: dirname(__DIR__, 3));
    }

    /**
     * Returns curated quick-navigation system roots available on this server.
     */
    public static function getAvailableRoots(): array
    {
        $baseDir = self::getWorkspaceRoot();
        $sysRoot = self::getSystemRoot();
        $isLinux = DIRECTORY_SEPARATOR === '/';

        $roots = [
            [
                'id' => 'system_root',
                'name' => $isLinux ? 'System Root (/)' : 'System Drive (' . $sysRoot . ')',
                'path' => $sysRoot,
                'icon' => '🖥️',
                'exists' => true,
                'description' => 'Top-level operating system filesystem root'
            ],
            [
                'id' => 'workspace',
                'name' => 'SPP Workspace',
                'path' => $baseDir,
                'icon' => '🚀',
                'exists' => true,
                'description' => 'Current SPP application and framework root'
            ]
        ];

        if ($isLinux) {
            $candidates = [
                ['name' => 'Web Roots (/var/www)', 'path' => '/var/www', 'icon' => '🌐', 'description' => 'Virtual hosts and website document roots'],
                ['name' => 'User Homes (/home)', 'path' => '/home', 'icon' => '👤', 'description' => 'Server user home directories'],
                ['name' => 'System Config (/etc)', 'path' => '/etc', 'icon' => '⚙️', 'description' => 'System configuration, Apache, Nginx, and BIND configs'],
                ['name' => 'System Logs (/var/log)', 'path' => '/var/log', 'icon' => '📋', 'description' => 'Server and application access/error logs'],
                ['name' => 'Temp Files (/tmp)', 'path' => '/tmp', 'icon' => '📁', 'description' => 'Temporary system cache and scratch files'],
            ];
            foreach ($candidates as $c) {
                if (is_dir($c['path'])) {
                    $c['id'] = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $c['name']));
                    $c['exists'] = true;
                    $roots[] = $c;
                }
            }
        } else {
            $userDir = 'C:/Users';
            if (is_dir($userDir)) {
                $roots[] = [
                    'id' => 'users',
                    'name' => 'Users (' . $userDir . ')',
                    'path' => $userDir,
                    'icon' => '👤',
                    'exists' => true,
                    'description' => 'Windows user accounts and documents'
                ];
            }
        }

        return $roots;
    }

    /**
     * Discovers all configured site document roots and tenant home directories.
     * Provides 1-click shortcuts for users to jump directly to any website's files.
     */
    public static function getConfiguredSiteRoots(): array
    {
        $sites = [];
        $baseDir = self::getWorkspaceRoot();
        $isLinux = DIRECTORY_SEPARATOR === '/';

        // 1. Load domain configuration from DomainRouter or etc/domains.yml
        $domains = [];
        if (class_exists('\\SPP\\Core\\Router\\DomainRouter')) {
            try {
                \SPP\Core\Router\DomainRouter::loadConfig();
                $domains = \SPP\Core\Router\DomainRouter::getAllDomains();
            } catch (\Throwable $e) {}
        }
        if (empty($domains)) {
            $domainsFile = defined('SPP_BASE_DIR') ? (SPP_BASE_DIR . '/etc/domains.yml') : (dirname(__DIR__, 2) . '/etc/domains.yml');
            if (file_exists($domainsFile) && function_exists('yaml_parse_file')) {
                $yaml = @yaml_parse_file($domainsFile);
                if (!empty($yaml['domains'])) {
                    $domains = $yaml['domains'];
                }
            }
        }

        if (empty($domains)) {
            $domains = [
                'localhost' => [
                    'type' => 'primary',
                    'default_app' => 'default',
                    'account' => 'default'
                ]
            ];
        }

        foreach ($domains as $pattern => $cfg) {
            $cleanDomain = strtolower(trim((string)$pattern));
            if ($cleanDomain === '') continue;

            $account = $cfg['account'] ?? 'default';
            $defaultApp = $cfg['default_app'] ?? 'default';
            $explicitDocroot = $cfg['docroot'] ?? ($cfg['document_root'] ?? ($cfg['settings']['document_root'] ?? ($cfg['settings']['home_dir'] ?? null)));

            $resolvedPath = null;
            $source = 'default_standard';

            // 1. Explicit docroot in domain config
            if (!empty($explicitDocroot)) {
                $candidate = str_replace('\\', '/', (string)$explicitDocroot);
                if (is_dir($candidate)) {
                    $resolvedPath = realpath($candidate) ?: $candidate;
                    $source = 'domain_config';
                } else {
                    $resolvedPath = $candidate;
                    $source = 'domain_config';
                }
            }

            // 2. Webserver Vhost Configuration (Apache / Nginx) on Linux
            if (!$resolvedPath && $isLinux) {
                $vhostFiles = [
                    "/etc/apache2/sites-available/{$cleanDomain}.conf",
                    "/etc/apache2/sites-available/{$cleanDomain}-le-ssl.conf",
                    "/etc/apache2/sites-enabled/{$cleanDomain}.conf",
                    "/etc/apache2/sites-enabled/{$cleanDomain}-le-ssl.conf",
                    "/etc/httpd/conf.d/{$cleanDomain}.conf",
                    "/etc/nginx/sites-available/{$cleanDomain}",
                    "/etc/nginx/conf.d/{$cleanDomain}.conf"
                ];
                foreach ($vhostFiles as $vf) {
                    if (file_exists($vf)) {
                        $content = @file_get_contents($vf);
                        if ($content) {
                            // Apache DocumentRoot
                            if (preg_match('/DocumentRoot\s+["\']?([^"\'\r\n\s]+)/i', $content, $m)) {
                                $doc = trim($m[1]);
                                if (is_dir($doc)) {
                                    $resolvedPath = str_replace('\\', '/', realpath($doc) ?: $doc);
                                    $source = 'vhost_config';
                                    break;
                                }
                            }
                            // Nginx root
                            if (preg_match('/root\s+["\']?([^;"\'\r\n\s]+)/i', $content, $m)) {
                                $doc = trim($m[1]);
                                if (is_dir($doc)) {
                                    $resolvedPath = str_replace('\\', '/', realpath($doc) ?: $doc);
                                    $source = 'vhost_config';
                                    break;
                                }
                            }
                        }
                    }
                }
            }

            // 3. Filesystem heuristics: user home, webroot, or workspace app
            if (!$resolvedPath) {
                $candidates = [
                    "/var/www/{$cleanDomain}/public_html",
                    "/var/www/{$cleanDomain}/public",
                    "/var/www/{$cleanDomain}",
                    "/var/www/html/{$cleanDomain}",
                    "/home/{$account}/domains/{$cleanDomain}/public_html",
                    "/home/{$account}/{$cleanDomain}/public_html",
                    "/home/{$account}/{$cleanDomain}/public",
                    "/home/{$account}/{$cleanDomain}",
                    "/home/{$account}/public_html",
                    $baseDir . "/domains/{$cleanDomain}/public",
                    $baseDir . "/domains/{$cleanDomain}",
                    $baseDir . "/src/{$defaultApp}/public",
                    $baseDir . "/src/{$defaultApp}",
                    $baseDir . "/apps/{$defaultApp}/public",
                    $baseDir . "/apps/{$defaultApp}"
                ];

                foreach ($candidates as $cand) {
                    if (is_dir($cand)) {
                        $resolvedPath = str_replace('\\', '/', realpath($cand) ?: $cand);
                        $source = 'filesystem_discovery';
                        break;
                    }
                }
            }

            // 4. Fallback canonical path
            if (!$resolvedPath) {
                if ($isLinux) {
                    if ($account && $account !== 'default' && @is_dir("/home/{$account}")) {
                        $resolvedPath = "/home/{$account}/public_html";
                    } else {
                        $resolvedPath = "/var/www/{$cleanDomain}";
                    }
                } else {
                    $sysDrive = self::getSystemRoot();
                    $resolvedPath = $sysDrive . "inetpub/wwwroot/{$cleanDomain}";
                }
                $source = 'default_standard';
            }

            $resolvedPath = str_replace('\\', '/', $resolvedPath);
            $exists = is_dir($resolvedPath);

            $short = strlen($resolvedPath) > 38 ? ('...' . substr($resolvedPath, -35)) : $resolvedPath;

            $sites[] = [
                'domain' => $cleanDomain,
                'pattern' => $pattern,
                'home_path' => $resolvedPath,
                'short_path' => $short,
                'exists' => $exists,
                'type' => $cfg['type'] ?? 'primary',
                'account' => $account,
                'default_app' => $defaultApp,
                'source' => $source,
                'icon' => ($cfg['type'] ?? '') === 'subdomain' ? '🌿' : (($cfg['type'] ?? '') === 'addon' ? '📦' : '🌐')
            ];
        }

        return $sites;
    }

    /**
     * Get list of file manager favorites (default system favorites + custom saved favorites).
     */
    public static function getFavorites(?string $domain = null): array
    {
        $baseDir = self::getWorkspaceRoot();
        $sysRoot = self::getSystemRoot();
        $isLinux = DIRECTORY_SEPARATOR === '/';

        // Standard system & workspace favorites
        $favorites = [
            [
                'id' => 'fav_workspace',
                'label' => 'SPP Workspace',
                'path' => $baseDir,
                'icon' => '📁',
                'type' => 'system',
                'description' => 'Current application workspace root',
                'exists' => true,
                'is_pinned' => true
            ],
            [
                'id' => 'fav_system_root',
                'label' => $isLinux ? 'System Root (/)' : 'System Drive (' . $sysRoot . ')',
                'path' => $sysRoot,
                'icon' => '🖥️',
                'type' => 'system',
                'description' => 'Operating system root filesystem',
                'exists' => true,
                'is_pinned' => true
            ]
        ];

        // Useful workspace directories if they exist
        $commonDirs = [
            ['id' => 'fav_apps', 'label' => 'Applications', 'path' => $baseDir . '/apps', 'icon' => '📦', 'description' => 'SPP Applications directory'],
            ['id' => 'fav_modules', 'label' => 'Framework Modules', 'path' => $baseDir . '/spp/modules', 'icon' => '🧩', 'description' => 'Core & optional modules'],
            ['id' => 'fav_etc', 'label' => 'Configuration (etc)', 'path' => $baseDir . '/etc', 'icon' => '⚙️', 'description' => 'Configuration and settings'],
            ['id' => 'fav_var', 'label' => 'Data & Storage (var)', 'path' => $baseDir . '/var', 'icon' => '💾', 'description' => 'Local database, cache and runtime data'],
        ];
        foreach ($commonDirs as $cd) {
            if (is_dir($cd['path'])) {
                $cd['type'] = 'workspace';
                $cd['exists'] = true;
                $cd['is_pinned'] = false;
                $favorites[] = $cd;
            }
        }

        // Common server directories on Linux
        if ($isLinux) {
            $serverDirs = [
                ['id' => 'fav_var_www', 'label' => 'Web Roots (/var/www)', 'path' => '/var/www', 'icon' => '🌐', 'description' => 'Virtual hosts & web roots'],
                ['id' => 'fav_home', 'label' => 'User Homes (/home)', 'path' => '/home', 'icon' => '👤', 'description' => 'User accounts and files'],
                ['id' => 'fav_etc_sys', 'label' => 'System Config (/etc)', 'path' => '/etc', 'icon' => '⚙️', 'description' => 'System configuration'],
            ];
            foreach ($serverDirs as $sd) {
                if (is_dir($sd['path'])) {
                    $sd['type'] = 'server';
                    $sd['exists'] = true;
                    $sd['is_pinned'] = false;
                    $favorites[] = $sd;
                }
            }
        }

        // Active domain root if requested/available
        if ($domain && $domain !== 'default') {
            $siteRoots = self::getConfiguredSiteRoots();
            foreach ($siteRoots as $sr) {
                if (($sr['domain'] ?? '') === $domain && !empty($sr['path'])) {
                    $favorites[] = [
                        'id' => 'fav_domain_' . md5($domain),
                        'label' => 'Domain: ' . $domain,
                        'path' => $sr['path'],
                        'icon' => '🎯',
                        'type' => 'domain',
                        'description' => 'Document root for ' . $domain,
                        'exists' => is_dir($sr['path']),
                        'is_pinned' => true
                    ];
                    break;
                }
            }
        }

        // Load custom user favorites from JSON file
        $storageDir = defined('SPP_BASE_DIR') ? (SPP_BASE_DIR . '/var/data') : (dirname(__DIR__, 2) . '/var/data');
        $storageFile = $storageDir . '/storage_favorites.json';
        if (file_exists($storageFile)) {
            $json = @json_decode(file_get_contents($storageFile), true);
            if (is_array($json)) {
                foreach ($json as $item) {
                    if (is_array($item) && !empty($item['path'])) {
                        $resolved = self::resolveSafePath($item['path']);
                        if ($resolved) {
                            $item['path'] = str_replace('\\', '/', $resolved);
                            $item['exists'] = is_dir($resolved);
                            $favorites[] = $item;
                        }
                    }
                }
            }
        }

        return $favorites;
    }

    /**
     * Save custom user favorites to persistent storage.
     */
    public static function saveFavorites(array $customFavorites, ?string $domain = null): array
    {
        $storageDir = defined('SPP_BASE_DIR') ? (SPP_BASE_DIR . '/var/data') : (dirname(__DIR__, 2) . '/var/data');
        if (!is_dir($storageDir)) {
            @mkdir($storageDir, 0777, true);
        }
        $storageFile = $storageDir . '/storage_favorites.json';

        $cleanItems = [];
        foreach ($customFavorites as $item) {
            if (is_array($item) && !empty($item['path']) && ($item['type'] ?? '') === 'custom') {
                $resolved = self::resolveSafePath($item['path']);
                if ($resolved && is_dir($resolved)) {
                    $cleanItems[] = [
                        'id' => $item['id'] ?? ('fav_' . uniqid()),
                        'label' => trim($item['label'] ?? basename($resolved)) ?: basename($resolved),
                        'path' => str_replace('\\', '/', $resolved),
                        'icon' => $item['icon'] ?? '⭐',
                        'type' => 'custom',
                        'is_pinned' => !empty($item['is_pinned']),
                        'created_at' => $item['created_at'] ?? time()
                    ];
                }
            }
        }

        file_put_contents($storageFile, json_encode($cleanItems, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return self::getFavorites($domain);
    }

    /**
     * Add a single custom favorite.
     */
    public static function addFavorite(string $label, string $path, string $icon = '⭐', ?string $domain = null): array
    {
        $resolved = self::resolveSafePath($path);
        if (!$resolved || !is_dir($resolved)) {
            throw new \InvalidArgumentException("Directory path does not exist or is inaccessible: " . $path);
        }

        $allFavs = self::getFavorites($domain);
        $customs = array_values(array_filter($allFavs, fn($f) => ($f['type'] ?? '') === 'custom'));

        // Check if path is already favorited
        $normPath = str_replace('\\', '/', $resolved);
        foreach ($customs as $c) {
            if ($c['path'] === $normPath) {
                return $allFavs; // Already favorited
            }
        }

        $customs[] = [
            'id' => 'fav_' . uniqid(),
            'label' => trim($label) ?: basename($normPath),
            'path' => $normPath,
            'icon' => $icon ?: '⭐',
            'type' => 'custom',
            'is_pinned' => true,
            'created_at' => time()
        ];

        return self::saveFavorites($customs, $domain);
    }

    /**
     * Remove a custom favorite by id or path.
     */
    public static function removeFavorite(string $idOrPath, ?string $domain = null): array
    {
        $allFavs = self::getFavorites($domain);
        $customs = array_values(array_filter($allFavs, fn($f) => ($f['type'] ?? '') === 'custom'));
        $normTarget = str_replace('\\', '/', $idOrPath);

        $filtered = array_values(array_filter($customs, function($c) use ($idOrPath, $normTarget) {
            return ($c['id'] ?? '') !== $idOrPath && ($c['path'] ?? '') !== $normTarget;
        }));

        return self::saveFavorites($filtered, $domain);
    }

    /**
     * Resolves the authoritative set of authorized roots for filesystem access.
     * Enforces containment within workspace root, SPP_BASE_DIR, active domain document roots,
     * configured site roots, and system temp directory.
     *
     * @param string|null $domain Optional domain scope for tenant isolation
     * @return string[] Array of canonical normalized root paths
     */
    public static function getAuthorizedRoots(?string $domain = null): array
    {
        $roots = [];

        // 1. SPP Workspace Root & Base Directory
        $workspace = self::getWorkspaceRoot();
        if ($workspace) {
            $realWs = realpath($workspace);
            $roots[] = str_replace('\\', '/', $realWs ?: $workspace);
        }

        if (defined('SPP_BASE_DIR')) {
            $baseDir = SPP_BASE_DIR;
            $realBase = realpath($baseDir);
            $roots[] = str_replace('\\', '/', $realBase ?: $baseDir);
        }

        if (defined('SPP_APP_DIR')) {
            $appDir = SPP_APP_DIR;
            $realApp = realpath($appDir);
            $roots[] = str_replace('\\', '/', $realApp ?: $appDir);
        }

        // 2. Active Tenant / Domain Roots via DomainContextResolver
        $domTarget = $domain;
        if ($domTarget === null && class_exists('\\SPP\\Core\\Domain\\DomainContextResolver')) {
            if (\SPP\Core\Domain\DomainContextResolver::has()) {
                $domTarget = \SPP\Core\Domain\DomainContextResolver::current()->getDomain();
            }
        }

        $tenantIsolationActive = ($domTarget !== null && $domTarget !== '' && $domTarget !== 'default');

        if ($tenantIsolationActive && class_exists('\\SPP\\Core\\Domain\\DomainContextResolver')) {
            try {
                $ctx = \SPP\Core\Domain\DomainContextResolver::resolve($domTarget);
                $dPath = $ctx->getDomainPath();
                if ($dPath) {
                    $realD = realpath($dPath);
                    $roots[] = str_replace('\\', '/', $realD ?: $dPath);
                }
                $pPath = $ctx->getPublicPath();
                if ($pPath) {
                    $realP = realpath($pPath);
                    $roots[] = str_replace('\\', '/', $realP ?: $pPath);
                }
                $dataPath = $ctx->getDataPath();
                if ($dataPath) {
                    $realData = realpath($dataPath);
                    $roots[] = str_replace('\\', '/', $realData ?: $dataPath);
                }
            } catch (\Throwable $e) {}
        }

        // 3. Configured Site Roots (Tenant Document Roots & User Homes)
        try {
            $siteRoots = self::getConfiguredSiteRoots();
            foreach ($siteRoots as $site) {
                if (!empty($site['home_path'])) {
                    $siteReal = realpath($site['home_path']);
                    $roots[] = str_replace('\\', '/', $siteReal ?: $site['home_path']);
                }
            }
        } catch (\Throwable $e) {}

        // 4. System Temp Directory (for temporary file lifecycles and scratch uploads)
        $sysTmp = sys_get_temp_dir();
        if ($sysTmp) {
            $realTmp = realpath($sysTmp);
            $roots[] = str_replace('\\', '/', $realTmp ?: $sysTmp);
        }

        // 5. System Root (Allowed ONLY in sovereign host admin mode when tenant isolation is NOT active)
        if (!$tenantIsolationActive) {
            $sysRoot = self::getSystemRoot();
            $realSys = realpath($sysRoot);
            $roots[] = str_replace('\\', '/', $realSys ?: $sysRoot);
        }

        // Deduplicate and filter empty
        $canonical = [];
        foreach ($roots as $r) {
            $norm = rtrim(str_replace('\\', '/', $r), '/');
            if ($norm === '' && DIRECTORY_SEPARATOR === '/') {
                $norm = '/';
            }
            if (!empty($norm) && !in_array($norm, $canonical, true)) {
                $canonical[] = $norm;
            }
        }

        return $canonical;
    }

    /**
     * Checks if a target path is strictly contained within the set of authorized roots.
     */
    public static function isContainedInAuthorizedRoots(string $targetPath, ?string $domain = null): bool
    {
        $targetNorm = str_replace('\\', '/', rtrim($targetPath, '/\\'));
        if ($targetNorm === '' && DIRECTORY_SEPARATOR === '/') {
            $targetNorm = '/';
        }

        $realTarget = realpath($targetNorm);
        $checkPath = $realTarget !== false ? str_replace('\\', '/', $realTarget) : $targetNorm;
        if ($checkPath === '' && DIRECTORY_SEPARATOR === '/') {
            $checkPath = '/';
        }

        $isWindows = (DIRECTORY_SEPARATOR !== '/' || stripos(PHP_OS, 'WIN') === 0);
        $authorizedRoots = self::getAuthorizedRoots($domain);

        foreach ($authorizedRoots as $root) {
            $rootNorm = rtrim(str_replace('\\', '/', $root), '/');
            if ($rootNorm === '' && DIRECTORY_SEPARATOR === '/') {
                $rootNorm = '/';
            }

            if ($rootNorm === '/') {
                return true; // System root matches everything on Unix
            }

            if ($isWindows) {
                // Windows drive root check: e.g. "C:" or "C:/"
                if (preg_match('/^[a-zA-Z]:\/?$/', $rootNorm)) {
                    $driveLetter = strtoupper(substr($rootNorm, 0, 1));
                    if (str_starts_with(strtoupper($checkPath), $driveLetter . ':')) {
                        return true;
                    }
                }

                if (strcasecmp($checkPath, $rootNorm) === 0) {
                    return true;
                }
                if (str_starts_with(strtolower($checkPath), strtolower($rootNorm) . '/')) {
                    return true;
                }
            } else {
                if ($checkPath === $rootNorm) {
                    return true;
                }
                if (str_starts_with($checkPath, $rootNorm . '/')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Resolves and validates an absolute filesystem path.
     * Supports full system root navigation (e.g. '/' on Linux or 'C:/' on Windows)
     * as well as relative paths within the SPP workspace.
     */
    public static function resolveSafePath(string $relativePath, ?string $domain = null): ?string
    {
        $baseDir = realpath(defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 3));
        if ($baseDir === false) {
            $baseDir = dirname(__DIR__, 3);
        }

        $clean = trim($relativePath);

        // Security check: Reject null bytes
        if (str_contains($clean, "\0")) {
            return null;
        }

        // Empty path or workspace keyword defaults to SPP workspace root
        if ($clean === '' || $clean === '.' || $clean === 'workspace' || $clean === 'spp') {
            return self::isContainedInAuthorizedRoots($baseDir, $domain) ? $baseDir : null;
        }

        // System root keyword or '/'
        if ($clean === '/' || $clean === 'ROOT' || $clean === 'SYSTEM_ROOT') {
            $sys = self::getSystemRoot();
            $realSys = realpath($sys);
            $target = $realSys !== false ? $realSys : $sys;
            return self::isContainedInAuthorizedRoots($target, $domain) ? $target : null;
        }

        // Windows drive without trailing slash e.g. 'C:' -> 'C:/'
        if (preg_match('/^[a-zA-Z]:$/', $clean)) {
            $clean .= '/';
        }

        // Security check: Reject relative directory traversal attempts (e.g. ../../)
        if (preg_match('/(^|[\\\\\/])\.\.([\\\\\/]|$)/', $clean)) {
            return null;
        }

        // 1. Resolve PHP FQCN (e.g. App\ptable\Rules\DoeemployeeidRule)
        if (str_starts_with($clean, 'App\\') || str_starts_with($clean, 'App/') || str_starts_with($clean, '\\App\\')) {
            $parts = explode('/', str_replace('\\', '/', ltrim($clean, '\\/')));
            if (count($parts) >= 3 && $parts[0] === 'App') {
                $clean = 'src/' . $parts[1] . '/' . implode('/', array_slice($parts, 2));
                if (!str_ends_with($clean, '.php')) {
                    $clean .= '.php';
                }
            }
        }

        // 2. Absolute Path Resolution (Linux: '/path', Windows: 'C:/path' or 'C:\path')
        $isAbsolute = str_starts_with($clean, '/') || preg_match('/^[a-zA-Z]:[\\\\\/]/', $clean);
        if ($isAbsolute) {
            $real = realpath($clean);
            if ($real !== false) {
                return self::isContainedInAuthorizedRoots($real, $domain) ? $real : null;
            }

            // If file/folder does not exist yet (e.g. createFile, createDirectory), check parent directory
            $parent = dirname($clean);
            $realParent = realpath($parent);
            if ($realParent !== false && is_dir($realParent)) {
                $candidate = $realParent . DIRECTORY_SEPARATOR . basename($clean);
                return self::isContainedInAuthorizedRoots($candidate, $domain) ? $candidate : null;
            }

            // Cross-platform normalization fallback (e.g. absolute Windows path sent to Linux or vice versa)
            $normalized = str_replace('\\', '/', $clean);
            foreach (['src/', 'etc/', 'spp/', 'resources/', 'tests/', 'themes/', 'var/', 'public/', 'modules/', 'apps/'] as $prefix) {
                $pos = strpos($normalized, '/' . $prefix);
                if ($pos !== false) {
                    $clean = substr($normalized, $pos + 1);
                    $isAbsolute = false;
                    break;
                }
                if (str_starts_with($normalized, $prefix)) {
                    $clean = $normalized;
                    $isAbsolute = false;
                    break;
                }
            }
        }

        // 3. Relative Path within SPP Workspace
        if (!$isAbsolute) {
            $cleanRel = trim(str_replace(['\\'], ['/'], $clean), '/');
            $target = empty($cleanRel) ? $baseDir : $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $cleanRel);

            if (file_exists($target)) {
                $res = realpath($target) ?: $target;
                return self::isContainedInAuthorizedRoots($res, $domain) ? $res : null;
            }

            // Auto-append .php if file exists with .php extension
            if (file_exists($target . '.php')) {
                $res = realpath($target . '.php') ?: ($target . '.php');
                return self::isContainedInAuthorizedRoots($res, $domain) ? $res : null;
            }

            // If parent directory exists, allow target for new file creation
            $parent = dirname($target);
            if (is_dir($parent)) {
                $res = (realpath($parent) ?: $parent) . DIRECTORY_SEPARATOR . basename($target);
                return self::isContainedInAuthorizedRoots($res, $domain) ? $res : null;
            }

            return self::isContainedInAuthorizedRoots($target, $domain) ? $target : null;
        }

        return null;
    }

    /**
     * Lists files and folders in any directory path (supports system root, web roots, user homes, or workspace).
     */
    public static function listFiles(string $relativePath = ''): array
    {
        $targetDir = self::resolveSafePath($relativePath);
        if (!$targetDir || !is_dir($targetDir)) {
            return ['success' => false, 'error' => 'Directory not found or access denied.'];
        }

        $items = [];
        $entries = @scandir($targetDir);
        if ($entries === false) {
            return ['success' => false, 'error' => 'Permission denied: Cannot read directory ' . $targetDir];
        }

        $baseDir = realpath(defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 3));
        $sysRoot = self::getSystemRoot();
        $normalizedTarget = str_replace('\\', '/', $targetDir);
        $normalizedBase = str_replace('\\', '/', $baseDir ?: '');

        // Standard Linux directory descriptions for novice-first DX
        $systemDirDescriptions = [
            '/' => 'Operating System Root Filesystem',
            '/bin' => 'Essential system user binaries & commands',
            '/boot' => 'Static files of the boot loader and Linux kernel',
            '/dev' => 'Device nodes and hardware interfaces',
            '/etc' => 'Host-specific system configuration files (Apache, Nginx, BIND)',
            '/home' => 'User home directories and domain document roots',
            '/lib' => 'Essential shared system libraries and kernel modules',
            '/lib64' => '64-bit essential system libraries',
            '/media' => 'Mount points for removable media (CD-ROMs, USB)',
            '/mnt' => 'Mount points for temporarily mounted filesystems',
            '/opt' => 'Add-on application software packages',
            '/proc' => 'Virtual filesystem providing process and kernel information',
            '/root' => 'Home directory for the root superuser',
            '/run' => 'Run-time variable data since last boot',
            '/sbin' => 'Essential system administration binaries',
            '/srv' => 'Site-specific data served by this system',
            '/sys' => 'Modern virtual filesystem for device and driver inspection',
            '/tmp' => 'Temporary files preserved between reboots',
            '/usr' => 'Secondary hierarchy for user utilities and applications',
            '/var' => 'Variable files: logs, spools, caches, and web roots',
            '/var/www' => 'Web server virtual host document roots and website files',
            '/var/log' => 'System, daemon, and web server log files'
        ];

        $manifestFile = $targetDir . DIRECTORY_SEPARATOR . '__spp_file_props.yml';
        $propsData = [];
        if (file_exists($manifestFile)) {
            if (!class_exists('\SPPMod\SPPStorage\FilePropsManager')) {
                require_once dirname(__DIR__, 2) . '/modules/spp/sppstorage/src/FilePropsManager.php';
            }
            $propsData = \SPPMod\SPPStorage\FilePropsManager::loadManifest($targetDir);
        }

        $isInsideBase = $normalizedBase !== '' && ($normalizedTarget === $normalizedBase || str_starts_with($normalizedTarget . '/', $normalizedBase . '/'));
        $wasAbsolute = str_starts_with(trim($relativePath), '/') || preg_match('/^[a-zA-Z]:/', trim($relativePath));

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $full = rtrim($targetDir, '\\/') . DIRECTORY_SEPARATOR . $entry;
            $normFull = preg_replace('#(?<!:)/+#', '/', str_replace('\\', '/', $full));
            $isDir = is_dir($full);

            // Compute path representation: always use canonical normalized full path
            $rel = $normFull;

            $ext = $isDir ? 'dir' : strtolower(pathinfo($entry, PATHINFO_EXTENSION));

            $propEntry = null;
            if ($isDir) {
                $propEntry = $propsData['directories'][$entry] ?? ($propsData[$entry] ?? null);
                if (empty($propEntry) && count($entries) <= 30) {
                    $subManifestFile = $full . DIRECTORY_SEPARATOR . '__spp_file_props.yml';
                    if (file_exists($subManifestFile)) {
                        $subManifest = \SPPMod\SPPStorage\FilePropsManager::loadManifest($full);
                        if (!empty($subManifest['directory']) && (is_array($subManifest['directory']) || is_string($subManifest['directory']))) {
                            $propEntry = $subManifest['directory'];
                        }
                    }
                }
                // Fallback to system directory description if browsing system root
                if (empty($propEntry) && isset($systemDirDescriptions[$normFull])) {
                    $propEntry = ['description' => $systemDirDescriptions[$normFull]];
                }
            } else {
                $propEntry = $propsData['files'][$entry] ?? ($propsData[$entry] ?? null);
            }

            $comment = null;
            $usage = null;
            $attributes = [];

            if (is_string($propEntry)) {
                $comment = $propEntry;
            } elseif (is_array($propEntry)) {
                $comment = $propEntry['description'] ?? ($propEntry['comment'] ?? null);
                $usage = $propEntry['usage'] ?? null;
                $attributes = $propEntry['attributes'] ?? [];
                if (empty($attributes['tags']) && !empty($propEntry['tags'])) {
                    $attributes['tags'] = is_array($propEntry['tags']) ? $propEntry['tags'] : array_values(array_filter(array_map('trim', explode(',', (string)$propEntry['tags']))));
                }
                if (empty($attributes['stability']) && !empty($propEntry['stability'])) {
                    $attributes['stability'] = $propEntry['stability'];
                }
                if (empty($attributes['author']) && !empty($propEntry['author'])) {
                    $attributes['author'] = $propEntry['author'];
                }
                if (empty($attributes['layer']) && !empty($propEntry['layer'])) {
                    $attributes['layer'] = $propEntry['layer'];
                }
            }

            $tags = $attributes['tags'] ?? [];
            $isDnsZone = ($ext === 'zone' || str_ends_with($entry, '.zone') || in_array('dns', $tags, true) || in_array('zone', $tags, true));
            $isAppDir = $isDir && (!empty($attributes['is_app']) || file_exists($full . '/etc/app.yml') || file_exists($full . '/etc/manifest.yml'));
            $isSymlink = is_link($full);

            $items[] = [
                'name' => $entry,
                'path' => $rel,
                'is_dir' => $isDir,
                'is_symlink' => $isSymlink,
                'is_props_manifest' => ($entry === '__spp_file_props.yml'),
                'is_dns_zone' => $isDnsZone,
                'is_app_dir' => $isAppDir,
                'has_props' => ($propEntry !== null),
                'size' => $isDir ? 0 : (@filesize($full) ?: 0),
                'mtime' => @filemtime($full) ?: 0,
                'modified' => date('Y-m-d H:i:s', @filemtime($full) ?: time()),
                'extension' => $ext,
                'permissions' => substr(sprintf('%o', @fileperms($full) ?: 0), -4),
                'comment' => $comment,
                'description' => $comment,
                'usage' => $usage,
                'author' => $attributes['author'] ?? ($propEntry['author'] ?? null),
                'stability' => $attributes['stability'] ?? ($propEntry['stability'] ?? 'stable'),
                'review_date' => $attributes['review_date'] ?? ($propEntry['review_date'] ?? null),
                'checksum' => $propEntry['checksum'] ?? ($attributes['checksum'] ?? null),
                'security' => $propEntry['security'] ?? ($attributes['security'] ?? null),
                'tags' => $tags,
                'attributes' => $attributes
            ];
        }

        // Sort: directories first, then alphabetical
        usort($items, function ($a, $b) {
            if ($a['is_dir'] !== $b['is_dir']) {
                return $a['is_dir'] ? -1 : 1;
            }
            return strcasecmp($a['name'], $b['name']);
        });

        $cleanCurrentPath = preg_replace('#(?<!:)/+#', '/', $normalizedTarget);

        $dirMeta = $propsData['directory'] ?? [];

        return [
            'success' => true,
            'current_path' => $cleanCurrentPath,
            'system_root' => $sysRoot,
            'workspace_root' => self::getWorkspaceRoot(),
            'available_roots' => self::getAvailableRoots(),
            'site_roots' => self::getConfiguredSiteRoots(),
            'total_items' => count($items),
            'directory_description' => $dirMeta['description'] ?? ($propsData['description'] ?? ($systemDirDescriptions[$normalizedTarget] ?? null)),
            'has_props_manifest' => file_exists($manifestFile),
            'directory_manifest' => [
                'exists' => file_exists($manifestFile),
                'has_manifest' => file_exists($manifestFile),
                'valid' => !empty($propsData),
                'manifest_valid' => !empty($propsData),
                'manifest_file' => $manifestFile,
                'directory' => $dirMeta,
                'directory_props' => $dirMeta,
                'description' => $dirMeta['description'] ?? ($propsData['description'] ?? ($systemDirDescriptions[$normalizedTarget] ?? '')),
                'usage' => $dirMeta['usage'] ?? ($propsData['usage'] ?? ''),
                'author' => $dirMeta['author'] ?? ($dirMeta['attributes']['author'] ?? ''),
                'stability' => $dirMeta['stability'] ?? ($dirMeta['attributes']['stability'] ?? 'stable'),
                'review_date' => $dirMeta['review_date'] ?? ($dirMeta['attributes']['review_date'] ?? ''),
                'tags' => $dirMeta['attributes']['tags'] ?? ($dirMeta['tags'] ?? []),
                'attributes' => $dirMeta['attributes'] ?? ($propsData['attributes'] ?? []),
                'security' => $dirMeta['security'] ?? null,
                'tracked_files_count' => count($propsData['files'] ?? []),
                'tracked_dirs_count' => count($propsData['directories'] ?? [])
            ],
            'items' => $items
        ];
    }

    /**
     * Reads contents of a file safely.
     */
    public static function readFile(string $relativePath): array
    {
        $filePath = self::resolveSafePath($relativePath);
        if (!$filePath || !is_file($filePath)) {
            return ['success' => false, 'error' => 'File not found or access denied.'];
        }

        // Max file read size: 10MB to prevent memory exhaustion
        $size = filesize($filePath);
        if ($size > 10 * 1024 * 1024) {
            return ['success' => false, 'error' => 'File exceeds maximum viewable size of 10MB.'];
        }

        $content = @file_get_contents($filePath);
        if ($content === false) {
            return ['success' => false, 'error' => 'Failed to read file content.'];
        }

        return [
            'success' => true,
            'path' => str_replace('\\', '/', $relativePath),
            'size' => $size,
            'extension' => strtolower(pathinfo($filePath, PATHINFO_EXTENSION)),
            'content' => $content
        ];
    }

    /**
     * Saves content to a file safely.
     */
    public static function writeFile(string $relativePath, string $content): array
    {
        $filePath = self::resolveSafePath($relativePath);
        if (!$filePath) {
            return ['success' => false, 'error' => 'Invalid path destination.'];
        }

        // Security check: Guard protected system security files
        $normalized = str_replace('\\', '/', $filePath);
        $blockedPatterns = [
            '/etc/shadow', '/etc/gshadow', '/etc/sudoers', '/etc/sudoers.d',
            'master.key', 'agent.token'
        ];
        foreach ($blockedPatterns as $pattern) {
            if (str_contains($normalized, $pattern)) {
                return ['success' => false, 'error' => 'Security Exception: Direct modification of system authentication and security credentials is strictly forbidden.'];
            }
        }

        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        // Atomic write with safe staged replacement & rollback protection
        $tmpFile = $dir . DIRECTORY_SEPARATOR . '.__tmp_write_' . bin2hex(random_bytes(6)) . '.tmp';
        if (@file_put_contents($tmpFile, $content, LOCK_EX) === false) {
            return ['success' => false, 'error' => 'Failed to write temporary file. Check directory permissions.'];
        }
        @chmod($tmpFile, 0664);

        if (file_exists($filePath)) {
            $bak = $filePath . '.bak.' . uniqid('', true);
            if (!@rename($filePath, $bak)) {
                $res = @file_put_contents($filePath, $content, LOCK_EX);
                @unlink($tmpFile);
                if ($res === false) {
                    return ['success' => false, 'error' => 'Failed to overwrite file destination.'];
                }
            } else {
                if (!@rename($tmpFile, $filePath)) {
                    @rename($bak, $filePath);
                    @unlink($tmpFile);
                    return ['success' => false, 'error' => 'Failed to atomically replace destination file.'];
                }
                @unlink($bak);
            }
        } else {
            if (!@rename($tmpFile, $filePath)) {
                $res = @file_put_contents($filePath, $content, LOCK_EX);
                @unlink($tmpFile);
                if ($res === false) {
                    return ['success' => false, 'error' => 'Failed to create destination file.'];
                }
            }
        }

        $bytes = strlen($content);

        // Update or recalculate integrity checksum in __spp_file_props.yml
        if (class_exists('\SPPMod\SPPStorage\FilePropsManager')) {
            try {
                $newChecksum = hash('sha256', $content);
                \SPPMod\SPPStorage\FilePropsManager::saveFileProps($relativePath, [
                    'checksum' => $newChecksum,
                    'review_date' => date('Y-m-d')
                ]);
            } catch (\Throwable $e) {}
        }

        return [
            'success' => true,
            'message' => 'File saved successfully.',
            'path' => str_replace('\\', '/', $relativePath),
            'bytes_written' => $bytes
        ];
    }

    /**
     * Creates an empty file and automatically records properties in __spp_file_props.yml.
     */
    public static function createFile(string $relativePath, array|string $properties = []): array
    {
        $filePath = self::resolveSafePath($relativePath);
        if (!$filePath) {
            return ['success' => false, 'error' => 'Invalid file destination.'];
        }

        if (file_exists($filePath)) {
            return ['success' => false, 'error' => 'File or folder already exists.'];
        }

        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $res = @touch($filePath);
        if (!$res) {
            return ['success' => false, 'error' => 'Failed to create file.'];
        }

        // Register in parent directory's __spp_file_props.yml
        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
        $name = basename($filePath);
        $defaultProps = [
            'description' => "File: {$name}",
            'usage' => "Application component file",
            'stability' => 'stable',
            'author' => 'SPP Administrator',
            'review_date' => date('Y-m-d', strtotime('+1 year')),
            'checksum' => hash_file('sha256', $filePath) ?: null,
            'attributes' => [
                'tags' => array_values(array_filter([$ext, 'created']))
            ]
        ];

        if (is_array($properties)) {
            $defaultProps = array_merge($defaultProps, $properties);
        } elseif (is_string($properties) && trim($properties) !== '') {
            $defaultProps['description'] = trim($properties);
        }

        self::saveFileProperties($relativePath, $defaultProps);

        return ['success' => true, 'message' => 'File created successfully.'];
    }

    /**
     * Creates a new directory and automatically records properties in __spp_file_props.yml.
     */
    public static function createDirectory(string $relativePath, array|string $properties = []): array
    {
        $dirPath = self::resolveSafePath($relativePath);
        if (!$dirPath) {
            return ['success' => false, 'error' => 'Invalid directory destination.'];
        }

        if (is_dir($dirPath)) {
            return ['success' => false, 'error' => 'Directory already exists.'];
        }

        $res = @mkdir($dirPath, 0775, true);
        if (!$res) {
            return ['success' => false, 'error' => 'Failed to create directory.'];
        }

        $name = basename($dirPath);
        $dirProps = [
            'description' => "Directory: {$name}",
            'usage' => "Application component directory",
            'stability' => 'stable',
            'author' => 'SPP Administrator',
            'review_date' => date('Y-m-d', strtotime('+1 year')),
            'attributes' => [
                'tags' => ['directory', 'created']
            ]
        ];

        if (is_array($properties)) {
            $dirProps = array_merge($dirProps, $properties);
        } elseif (is_string($properties) && trim($properties) !== '') {
            $dirProps['description'] = trim($properties);
        }

        if (class_exists('\SPPMod\SPPStorage\FilePropsManager')) {
            try {
                \SPPMod\SPPStorage\FilePropsManager::saveDirectoryProps($relativePath, $dirProps);
            } catch (\Throwable $e) {}
        }

        return ['success' => true, 'message' => 'Directory created successfully.'];
    }

    /**
     * Deletes a file or directory recursively and removes its metadata.
     */
    public static function deleteItem(string $relativePath): array
    {
        $target = self::resolveSafePath($relativePath);
        if (!$target || !file_exists($target)) {
            return ['success' => false, 'error' => 'Target not found or access denied.'];
        }

        // Safety: Do not allow deleting system or workspace root directory
        $baseDir = realpath(defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 3));
        $sysRoot = self::getSystemRoot();
        if ($target === $baseDir || $target === $sysRoot || $target === '/' || $target === 'C:\\' || $target === 'C:/') {
            return ['success' => false, 'error' => 'Cannot delete system or workspace root directory.'];
        }

        $protected = [
            '/bin', '/boot', '/dev', '/etc', '/lib', '/lib64', '/proc', '/root', '/run', '/sbin', '/sys', '/usr',
            'c:/windows', 'c:/program files', 'c:/program files (x86)', 'c:/programdata'
        ];
        $cleanTarget = strtolower(str_replace('\\', '/', rtrim($target, '/\\')));
        if (in_array($cleanTarget, $protected, true)) {
            return ['success' => false, 'error' => "Protected System Directory: Cannot delete critical operating system path '{$cleanTarget}'."];
        }

        // Clean up metadata from parent __spp_file_props.yml
        self::removeFileProperties($relativePath);

        try {
            if (is_dir($target)) {
                self::deleteDirectoryRecursive($target);
            } else {
                @unlink($target);
            }

            return ['success' => true, 'message' => 'Item deleted successfully.'];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => 'Failed to delete item: ' . $e->getMessage()];
        }
    }

    private static function deleteDirectoryRecursive(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? self::deleteDirectoryRecursive($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    /**
     * Renames or moves a file or directory and migrates properties in __spp_file_props.yml.
     */
    public static function renameItem(string $oldPath, string $newPath): array
    {
        $src = self::resolveSafePath($oldPath);
        $dst = self::resolveSafePath($newPath);

        if (!$src || !file_exists($src)) {
            return ['success' => false, 'error' => 'Source item not found.'];
        }
        if (!$dst) {
            return ['success' => false, 'error' => 'Invalid destination path.'];
        }
        if (file_exists($dst)) {
            return ['success' => false, 'error' => 'Destination item already exists.'];
        }

        $res = @rename($src, $dst);
        if (!$res) {
            return ['success' => false, 'error' => 'Failed to rename item.'];
        }

        // Migrate property entry in __spp_file_props.yml
        self::renameFileProperties($oldPath, $newPath);

        return ['success' => true, 'message' => 'Item renamed successfully.'];
    }

    /**
     * Retrieves properties, comments, and attributes for a file or directory from __spp_file_props.yml.
     */
    public static function getFileProperties(string $relativePath): array
    {
        if (!class_exists('\SPPMod\SPPStorage\FilePropsManager')) {
            require_once dirname(__DIR__, 2) . '/modules/spp/sppstorage/src/FilePropsManager.php';
        }
        $props = \SPPMod\SPPStorage\FilePropsManager::getFileProps($relativePath);
        $abs = self::resolveSafePath($relativePath);
        if ($abs && is_file($abs)) {
            if (empty($props['checksum']) && empty($props['attributes']['checksum'])) {
                $chk = @hash_file('sha256', $abs);
                if ($chk) {
                    $props['checksum'] = $chk;
                }
            }
        }
        return $props;
    }

    /**
     * Saves properties, comments, and attributes for a file or directory into __spp_file_props.yml.
     */
    public static function saveFileProperties(string $relativePath, array|string $propsData): array
    {
        if (!class_exists('\SPPMod\SPPStorage\FilePropsManager')) {
            require_once dirname(__DIR__, 2) . '/modules/spp/sppstorage/src/FilePropsManager.php';
        }

        // Validate payload structure
        if (is_array($propsData)) {
            $validStabilities = ['stable', 'beta', 'alpha', 'experimental', 'deprecated', 'internal'];
            if (!empty($propsData['stability']) && !in_array(strtolower($propsData['stability']), $validStabilities, true)) {
                return ['success' => false, 'error' => "Invalid stability value: '{$propsData['stability']}'."];
            }

            $validLayers = ['domain', 'application', 'presentation', 'infrastructure', 'core', 'utility', 'view', 'config', 'unspecified'];
            if (!empty($propsData['layer']) && !in_array(strtolower($propsData['layer']), $validLayers, true)) {
                return ['success' => false, 'error' => "Invalid architectural layer: '{$propsData['layer']}'."];
            }

            // If routes attribute is provided, ensure it's a valid list of objects with 'path'
            if (!empty($propsData['attributes']['routes']) && is_array($propsData['attributes']['routes'])) {
                foreach ($propsData['attributes']['routes'] as $idx => $route) {
                    if (!is_array($route) || empty($route['path'])) {
                        return ['success' => false, 'error' => "Route definition at index {$idx} missing mandatory 'path' attribute."];
                    }
                }
            }

            // Validate feature_flag
            if (!empty($propsData['attributes']['feature_flag'])) {
                $ff = $propsData['attributes']['feature_flag'];
                if (!is_array($ff) || empty($ff['flag'])) {
                    return ['success' => false, 'error' => "Feature flag definition missing mandatory 'flag' name."];
                }
                if (isset($ff['rollout_percentage']) && (!is_numeric($ff['rollout_percentage']) || $ff['rollout_percentage'] < 0 || $ff['rollout_percentage'] > 100)) {
                    return ['success' => false, 'error' => "Feature flag 'rollout_percentage' must be a number between 0 and 100."];
                }
            }

            // Validate slo_budget
            if (!empty($propsData['attributes']['slo_budget'])) {
                $slo = $propsData['attributes']['slo_budget'];
                if (!is_array($slo)) {
                    return ['success' => false, 'error' => "SLO budget must be an object."];
                }
                foreach (['max_duration_ms', 'max_memory_mb', 'max_db_queries'] as $numField) {
                    if (isset($slo[$numField]) && (!is_numeric($slo[$numField]) || $slo[$numField] < 0)) {
                        return ['success' => false, 'error' => "SLO budget field '{$numField}' must be a non-negative number."];
                    }
                }
            }

            // Validate telemetry
            if (!empty($propsData['attributes']['telemetry'])) {
                $tel = $propsData['attributes']['telemetry'];
                if (!is_array($tel)) {
                    return ['success' => false, 'error' => "Telemetry definition must be an object."];
                }
                if (isset($tel['sample_rate']) && (!is_numeric($tel['sample_rate']) || $tel['sample_rate'] < 0 || $tel['sample_rate'] > 1)) {
                    return ['success' => false, 'error' => "Telemetry 'sample_rate' must be between 0.0 and 1.0."];
                }
            }

            // Validate subscribes
            if (!empty($propsData['attributes']['subscribes'])) {
                $sub = $propsData['attributes']['subscribes'];
                if (!is_array($sub) || empty($sub['event'])) {
                    return ['success' => false, 'error' => "Event subscription missing mandatory 'event' identifier."];
                }
            }

            // Validate compliance
            if (!empty($propsData['attributes']['compliance'])) {
                $comp = $propsData['attributes']['compliance'];
                if (!is_array($comp)) {
                    return ['success' => false, 'error' => "Compliance declaration must be an object."];
                }
                $validClassifications = ['PUBLIC', 'INTERNAL', 'RESTRICTED', 'PII_CONFIDENTIAL'];
                if (!empty($comp['data_classification']) && !in_array(strtoupper($comp['data_classification']), $validClassifications, true)) {
                    return ['success' => false, 'error' => "Invalid data classification: '{$comp['data_classification']}'. Must be PUBLIC, INTERNAL, RESTRICTED, or PII_CONFIDENTIAL."];
                }
            }
        }

        $safe = self::resolveSafePath($relativePath) ?: $relativePath;
        if (is_array($propsData) && empty($propsData['checksum']) && is_file($safe)) {
            $propsData['checksum'] = @hash_file('sha256', $safe) ?: null;
        }

        return \SPPMod\SPPStorage\FilePropsManager::saveFileProps($safe, $propsData);
    }

    /**
     * Migrates property key in __spp_file_props.yml when an item is renamed.
     */
    public static function renameFileProperties(string $oldRelativePath, string $newRelativePath): bool
    {
        if (!class_exists('\SPPMod\SPPStorage\FilePropsManager')) {
            require_once dirname(__DIR__, 2) . '/modules/spp/sppstorage/src/FilePropsManager.php';
        }
        return \SPPMod\SPPStorage\FilePropsManager::renameFileProps($oldRelativePath, $newRelativePath);
    }

    /**
     * Retrieves directory-level properties from its __spp_file_props.yml.
     */
    public static function getDirectoryProperties(string $relativePath): array
    {
        if (!class_exists('\SPPMod\SPPStorage\FilePropsManager')) {
            require_once dirname(__DIR__, 2) . '/modules/spp/sppstorage/src/FilePropsManager.php';
        }
        $safe = self::resolveSafePath($relativePath) ?: $relativePath;
        $dir = is_dir($safe) ? $safe : dirname($safe);
        $manifest = \SPPMod\SPPStorage\FilePropsManager::loadManifest($dir);
        return [
            'success' => true,
            'path' => \SPPMod\SPPStorage\FilePropsManager::getRelativePath($dir),
            'directory' => $manifest['directory'] ?? [],
            'has_manifest' => file_exists($dir . DIRECTORY_SEPARATOR . \SPPMod\SPPStorage\FilePropsManager::MANIFEST_FILENAME)
        ];
    }

    /**
     * Saves directory-level properties to that directory's __spp_file_props.yml.
     */
    public static function saveDirectoryProperties(string $relativePath, array $propsData): array
    {
        if (!class_exists('\SPPMod\SPPStorage\FilePropsManager')) {
            require_once dirname(__DIR__, 2) . '/modules/spp/sppstorage/src/FilePropsManager.php';
        }
        return \SPPMod\SPPStorage\FilePropsManager::saveDirectoryProps($relativePath, $propsData);
    }

    /**
     * Retrieves raw manifest YAML for direct inspection or raw code editor viewing.
     */
    public static function getManifestRaw(string $relativePath): array
    {
        if (!class_exists('\SPPMod\SPPStorage\FilePropsManager')) {
            require_once dirname(__DIR__, 2) . '/modules/spp/sppstorage/src/FilePropsManager.php';
        }
        $target = self::resolveSafePath($relativePath) ?: $relativePath;
        return \SPPMod\SPPStorage\FilePropsManager::getManifestRaw($target);
    }

    /**
     * Validates raw manifest YAML syntax and schema.
     */
    public static function validateManifestYaml(string $rawYaml): array
    {
        if (!class_exists('\SPPMod\SPPStorage\FilePropsManager')) {
            require_once dirname(__DIR__, 2) . '/modules/spp/sppstorage/src/FilePropsManager.php';
        }
        return \SPPMod\SPPStorage\FilePropsManager::validateManifestYaml($rawYaml);
    }

    /**
     * Atomically saves validated raw manifest YAML text.
     */
    public static function saveManifestRaw(string $relativePath, string $rawYaml): array
    {
        if (!class_exists('\SPPMod\SPPStorage\FilePropsManager')) {
            require_once dirname(__DIR__, 2) . '/modules/spp/sppstorage/src/FilePropsManager.php';
        }
        $target = self::resolveSafePath($relativePath) ?: $relativePath;
        return \SPPMod\SPPStorage\FilePropsManager::saveManifestRaw($target, $rawYaml);
    }

    /**
     * Computes a structured line-by-line diff between disk manifest and proposed YAML text.
     */
    public static function diffManifest(string $relativePath, string $newYaml): array
    {
        if (!class_exists('\SPPMod\SPPStorage\FilePropsManager')) {
            require_once dirname(__DIR__, 2) . '/modules/spp/sppstorage/src/FilePropsManager.php';
        }
        $target = self::resolveSafePath($relativePath) ?: $relativePath;
        return \SPPMod\SPPStorage\FilePropsManager::diffManifest($target, $newYaml);
    }

    /**
     * Evaluates comprehensive Manifest OS health score and issues for a directory tree.
     */
    public static function getManifestHealth(string $relativePath = '', bool $recursive = false, bool $verifyIntegrity = false): array
    {
        if (!class_exists('\SPPMod\SPPStorage\FilePropsManager')) {
            require_once dirname(__DIR__, 2) . '/modules/spp/sppstorage/src/FilePropsManager.php';
        }
        $target = !empty($relativePath) ? (self::resolveSafePath($relativePath) ?: $relativePath) : self::getWorkspaceRoot();
        return \SPPMod\SPPStorage\FilePropsManager::getManifestHealth($target, $recursive, $verifyIntegrity);
    }

    /**
     * Automatically heals manifests by pruning orphaned entries and registering untracked items.
     */
    public static function healManifest(string $relativePath = '', bool $recursive = true): array
    {
        if (!class_exists('\SPPMod\SPPStorage\FilePropsManager')) {
            require_once dirname(__DIR__, 2) . '/modules/spp/sppstorage/src/FilePropsManager.php';
        }
        $target = !empty($relativePath) ? (self::resolveSafePath($relativePath) ?: $relativePath) : self::getWorkspaceRoot();
        return \SPPMod\SPPStorage\FilePropsManager::healDirectory($target, $recursive);
    }

    /**
     * Compiles manifests across the workspace into array cache and SQLite index.
     */
    public static function compileManifests(?string $rootDir = null): array
    {
        if (!class_exists('\SPPMod\SPPStorage\FilePropsManager')) {
            require_once dirname(__DIR__, 2) . '/modules/spp/sppstorage/src/FilePropsManager.php';
        }
        $target = $rootDir ? (self::resolveSafePath($rootDir) ?: $rootDir) : self::getWorkspaceRoot();
        return \SPPMod\SPPStorage\FilePropsManager::compileWorkspace($target);
    }

    /**
     * Verifies cryptographic file integrity and detects tampering against __spp_file_props.yml.
     */
    public static function verifyFileIntegrity(string $relativePath, ?string $secretKey = null): array
    {
        if (!class_exists('\SPPMod\SPPStorage\FileIntegritySigner')) {
            require_once dirname(__DIR__, 2) . '/modules/spp/sppstorage/src/FileIntegritySigner.php';
        }
        $safe = self::resolveSafePath($relativePath) ?: $relativePath;
        $res = \SPPMod\SPPStorage\FileIntegritySigner::verifyFile($safe, $secretKey);
        $res['success'] = true;
        if (isset($res['actual']) && !isset($res['computed'])) {
            $res['computed'] = $res['actual'];
        }
        return $res;
    }

    /**
     * Signs a file and registers SHA-256 checksum and HMAC signature in its manifest.
     */
    public static function signFile(string $relativePath, ?string $secretKey = null): array
    {
        if (!class_exists('\SPPMod\SPPStorage\FileIntegritySigner')) {
            require_once dirname(__DIR__, 2) . '/modules/spp/sppstorage/src/FileIntegritySigner.php';
        }
        $safe = self::resolveSafePath($relativePath) ?: $relativePath;
        $ok = \SPPMod\SPPStorage\FileIntegritySigner::signFile($safe, $secretKey);
        $checksum = (is_string($safe) && file_exists($safe)) ? @hash_file('sha256', $safe) : null;
        return [
            'success' => $ok,
            'message' => $ok ? 'File signed and manifest updated successfully.' : 'Failed to sign file.',
            'path' => $relativePath,
            'checksum' => $checksum
        ];
    }

    /**
     * Signs all files in a directory tree, updating their manifests.
     */
    public static function signDirectory(string $relativePath, ?string $secretKey = null, bool $recursive = true): array
    {
        if (!class_exists('\SPPMod\SPPStorage\FileIntegritySigner')) {
            require_once dirname(__DIR__, 2) . '/modules/spp/sppstorage/src/FileIntegritySigner.php';
        }
        $safe = self::resolveSafePath($relativePath) ?: $relativePath;
        $res = \SPPMod\SPPStorage\FileIntegritySigner::signDirectory($safe, $secretKey, $recursive);
        $res['success'] = true;
        $res['signed_count'] = $res['signed'] ?? 0;
        return $res;
    }

    /**
     * Inspects active capability and security policy declared for a file or directory.
     */
    public static function checkCapabilities(string $relativePath, ?string $capability = null): array
    {
        if (!class_exists('\SPPMod\SPPStorage\ManifestSecurityGuard')) {
            require_once dirname(__DIR__, 2) . '/modules/spp/sppstorage/src/ManifestSecurityGuard.php';
        }
        $safe = self::resolveSafePath($relativePath) ?: $relativePath;
        $policy = \SPPMod\SPPStorage\ManifestSecurityGuard::resolveSecurityPolicy($safe);
        $allowed = false;
        if ($capability !== null) {
            $allowed = !empty($policy[$capability]);
        }
        return [
            'success' => true,
            'path' => $relativePath,
            'has_policy' => $policy !== null,
            'capability' => $capability,
            'allowed' => $allowed,
            'policy' => $policy ?? [],
            'capabilities' => [
                'allow_network' => $policy['allow_network'] ?? null,
                'allow_env' => $policy['allow_env'] ?? null,
                'allow_filesystem' => $policy['allow_filesystem'] ?? null,
                'allow_shell' => $policy['allow_shell'] ?? false,
                'roles' => $policy['roles'] ?? [],
                'rate_limit' => $policy['rate_limit'] ?? null,
                'ip_allowlist' => $policy['ip_allowlist'] ?? []
            ]
        ];
    }

    /**
     * Changes POSIX/file system permissions of a file or directory.
     */
    public static function chmodItem(string $relativePath, string $mode, bool $recursive = false): array
    {
        $target = self::resolveSafePath($relativePath);
        if (!$target || !file_exists($target)) {
            return ['success' => false, 'error' => 'Target path not found.'];
        }
        $cleanMode = preg_replace('/[^0-7]/', '', $mode);
        if (strlen($cleanMode) === 3) {
            $cleanMode = '0' . $cleanMode;
        }
        $octal = octdec($cleanMode);

        if ($recursive && is_dir($target)) {
            try {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($target, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::SELF_FIRST
                );
                @chmod($target, $octal);
                foreach ($iterator as $item) {
                    @chmod($item->getPathname(), $octal);
                }
                return [
                    'success' => true,
                    'path' => $relativePath,
                    'permissions' => substr(sprintf('%o', @fileperms($target) ?: 0), -4),
                    'recursive' => true
                ];
            } catch (\Throwable $e) {
                return ['success' => false, 'error' => 'Failed to recursively apply permissions: ' . $e->getMessage()];
            }
        }

        if (@chmod($target, $octal)) {
            return [
                'success' => true,
                'path' => $relativePath,
                'permissions' => substr(sprintf('%o', @fileperms($target) ?: 0), -4)
            ];
        }
        return ['success' => false, 'error' => 'Failed to apply permissions.'];
    }

    /**
     * Removes an entry from __spp_file_props.yml when an item is deleted.
     */
    public static function removeFileProperties(string $relativePath): bool
    {
        if (!class_exists('\SPPMod\SPPStorage\FilePropsManager')) {
            require_once dirname(__DIR__, 2) . '/modules/spp/sppstorage/src/FilePropsManager.php';
        }
        return \SPPMod\SPPStorage\FilePropsManager::removeFileProps($relativePath);
    }

    /**
     * Unpacks a ZIP archive into a destination directory.
     */
    public static function extractZip(string $zipPath, ?string $destDir = null): array
    {
        return ArchiveAdminService::extractArchive($zipPath, $destDir);
    }

    /**
     * Inspects disk quota and workspace storage breakdown.
     */
    public static function getDiskUsage(): array
    {
        $baseDir = defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 2);

        $freeBytes = @disk_free_space($baseDir) ?: 0;
        $totalBytes = @disk_total_space($baseDir) ?: 0;
        $usedBytes = $totalBytes > $freeBytes ? ($totalBytes - $freeBytes) : 0;
        $percent = $totalBytes > 0 ? round(($usedBytes / $totalBytes) * 100, 1) : 0;

        // Breakdown top folders
        $breakdown = [];
        $folders = ['src', 'var', 'data', 'spp', 'vendor', 'etc', 'public', 'tests'];
        foreach ($folders as $f) {
            $path = $baseDir . DIRECTORY_SEPARATOR . $f;
            if (is_dir($path)) {
                $size = self::getDirectorySize($path);
                $breakdown[] = [
                    'folder' => $f,
                    'size_bytes' => $size,
                    'size_formatted' => self::formatBytes($size)
                ];
            }
        }

        return [
            'success' => true,
            'total_bytes' => $totalBytes,
            'total_formatted' => self::formatBytes($totalBytes),
            'used_bytes' => $usedBytes,
            'used_formatted' => self::formatBytes($usedBytes),
            'free_bytes' => $freeBytes,
            'free_formatted' => self::formatBytes($freeBytes),
            'used_percentage' => $percent,
            'breakdown' => $breakdown
        ];
    }

    private static function getDirectorySize(string $dir): int
    {
        $size = 0;
        try {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                }
            }
        } catch (Throwable $e) {
        }
        return $size;
    }

    public static function formatBytes(int|float $bytes): string
    {
        $bytes = (float)$bytes;
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }

    /**
     * Uploads a file into a destination directory.
     * Supports multipart $_FILES structure, raw content, or base64 payloads.
     */
    public static function uploadFile(string $targetDir, array $fileData, ?string $filename = null): array
    {
        $dirPath = self::resolveSafePath($targetDir);
        if (!$dirPath || !is_dir($dirPath)) {
            return ['success' => false, 'error' => 'Target directory does not exist or access denied.'];
        }

        $cleanName = $filename ?: ($fileData['name'] ?? ($fileData['filename'] ?? ''));
        $cleanName = basename(trim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $cleanName)));
        if (empty($cleanName)) {
            return ['success' => false, 'error' => 'Invalid or missing file name for upload.'];
        }

        $destination = $dirPath . DIRECTORY_SEPARATOR . $cleanName;
        $baseDir = realpath(defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 3));

        // 1. Multipart PHP Upload
        if (!empty($fileData['tmp_name']) && file_exists($fileData['tmp_name'])) {
            $moved = is_uploaded_file($fileData['tmp_name'])
                ? @move_uploaded_file($fileData['tmp_name'], $destination)
                : @copy($fileData['tmp_name'], $destination);

            if (!$moved) {
                return ['success' => false, 'error' => 'Failed to move uploaded file into destination directory.'];
            }
        }
        // 2. Base64 Upload
        elseif (!empty($fileData['base64'])) {
            $raw = $fileData['base64'];
            if (str_contains($raw, ';base64,')) {
                $raw = explode(';base64,', $raw)[1];
            }
            $decoded = base64_decode($raw, true);
            if ($decoded === false) {
                return ['success' => false, 'error' => 'Invalid base64 payload.'];
            }
            if (@file_put_contents($destination, $decoded) === false) {
                return ['success' => false, 'error' => 'Failed to write base64 file data.'];
            }
        }
        // 3. Raw text content upload
        elseif (isset($fileData['content'])) {
            if (@file_put_contents($destination, $fileData['content']) === false) {
                return ['success' => false, 'error' => 'Failed to write uploaded file content.'];
            }
        } else {
            return ['success' => false, 'error' => 'No file data received.'];
        }

        @chmod($destination, 0664);
        $normDest = str_replace('\\', '/', $destination);
        $normBase = str_replace('\\', '/', $baseDir ?: '');
        if ($normBase !== '' && str_starts_with($normDest, $normBase)) {
            $relPath = ltrim(substr($normDest, strlen($normBase)), '/');
        } else {
            $relPath = $normDest;
        }

        // Auto-register uploaded file in directory __spp_file_props.yml with SHA-256 checksum
        if (class_exists('\SPPMod\SPPStorage\FilePropsManager')) {
            try {
                $ext = pathinfo($destination, PATHINFO_EXTENSION);
                \SPPMod\SPPStorage\FilePropsManager::saveFileProps($relPath, [
                    'description' => "Uploaded file: {$cleanName}",
                    'usage' => "User uploaded asset",
                    'stability' => 'stable',
                    'author' => 'SPP Administrator',
                    'review_date' => date('Y-m-d', strtotime('+1 year')),
                    'checksum' => hash_file('sha256', $destination) ?: null,
                    'attributes' => [
                        'tags' => array_values(array_filter([$ext, 'upload']))
                    ]
                ]);
            } catch (\Throwable $e) {}
        }

        return [
            'success' => true,
            'message' => "File '{$cleanName}' uploaded successfully.",
            'filename' => $cleanName,
            'path' => $relPath,
            'size' => @filesize($destination) ?: 0
        ];
    }

    /**
     * Handles chunked file upload with pre-write byte limit enforcement.
     * Prevents resource exhaustion, enforces max file size before writing chunks,
     * sanitizes upload tokens against traversal, and reassembles final file atomically.
     *
     * @param array $params Contains 'targetDir', 'filename', 'chunkIndex', 'totalChunks', 'chunkSize', 'totalSize', 'uploadId'
     * @param array $fileData Contains 'tmp_name', 'content', or 'base64'
     * @return array
     */
    public static function handleChunkUpload(array $params, array $fileData): array
    {
        $maxUploadSize = defined('SPP_MAX_UPLOAD_SIZE') ? (int)SPP_MAX_UPLOAD_SIZE : 104857600; // 100MB default
        $maxChunkSize = 10485760; // 10MB per chunk limit

        $rawUploadId = (string)($params['uploadId'] ?? '');
        if ($rawUploadId === '' || !preg_match('/^[a-zA-Z0-9_\-]+$/', $rawUploadId)) {
            return ['success' => false, 'error' => 'Missing or invalid uploadId token.'];
        }
        $uploadId = $rawUploadId;

        $targetDirRaw = (string)($params['targetDir'] ?? $params['path'] ?? '');
        $targetDir = self::resolveSafePath($targetDirRaw);
        if (!$targetDir || !is_dir($targetDir)) {
            return ['success' => false, 'error' => 'Target directory does not exist or access denied.'];
        }

        $filename = basename(trim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, (string)($params['filename'] ?? ''))));
        if (empty($filename)) {
            return ['success' => false, 'error' => 'Missing or invalid upload filename.'];
        }

        $chunkIndex = (int)($params['chunkIndex'] ?? 0);
        $totalChunks = (int)($params['totalChunks'] ?? 1);
        $totalSize = (int)($params['totalSize'] ?? 0);
        $chunkSize = (int)($params['chunkSize'] ?? 0);

        if ($totalSize > $maxUploadSize) {
            return [
                'success' => false,
                'error' => "Total upload size ({$totalSize} bytes) exceeds maximum allowable limit ({$maxUploadSize} bytes)."
            ];
        }

        if ($chunkSize > $maxChunkSize) {
            return [
                'success' => false,
                'error' => "Chunk size ({$chunkSize} bytes) exceeds maximum chunk size ({$maxChunkSize} bytes)."
            ];
        }

        // Extract chunk content
        $chunkContent = null;
        if (!empty($fileData['tmp_name']) && file_exists($fileData['tmp_name'])) {
            $actualSize = filesize($fileData['tmp_name']);
            if ($actualSize > $maxChunkSize) {
                return ['success' => false, 'error' => 'Chunk temp file exceeds maximum chunk size limit.'];
            }
            $chunkContent = file_get_contents($fileData['tmp_name']);
        } elseif (isset($fileData['content'])) {
            $chunkContent = $fileData['content'];
        } elseif (!empty($fileData['base64'])) {
            $raw = $fileData['base64'];
            if (str_contains($raw, ';base64,')) {
                $raw = explode(';base64,', $raw)[1];
            }
            $chunkContent = base64_decode($raw, true);
        }

        if ($chunkContent === null || $chunkContent === false) {
            return ['success' => false, 'error' => 'Failed to retrieve chunk content payload.'];
        }

        $contentLen = strlen($chunkContent);
        if ($contentLen > $maxChunkSize) {
            return ['success' => false, 'error' => 'Chunk payload exceeds maximum allowable chunk size.'];
        }

        // Setup scratch chunk directory
        $chunkBase = defined('SPP_BASE_DIR') ? SPP_BASE_DIR . '/var/tmp/spp_chunks' : sys_get_temp_dir() . '/spp_chunks';
        $uploadDir = $chunkBase . DIRECTORY_SEPARATOR . $uploadId;
        if (!is_dir($uploadDir)) {
            if (!@mkdir($uploadDir, 0770, true)) {
                return ['success' => false, 'error' => 'Failed to initialize temporary chunk directory.'];
            }
        }

        $chunkFile = $uploadDir . DIRECTORY_SEPARATOR . sprintf('chunk_%06d.part', $chunkIndex);
        if (@file_put_contents($chunkFile, $chunkContent) === false) {
            return ['success' => false, 'error' => "Failed to write chunk #{$chunkIndex} to disk."];
        }

        // Verify total size of received chunks does not exceed maxUploadSize
        $chunkFiles = glob($uploadDir . DIRECTORY_SEPARATOR . 'chunk_*.part') ?: [];
        $accumulatedBytes = 0;
        foreach ($chunkFiles as $cf) {
            $accumulatedBytes += filesize($cf);
        }

        if ($accumulatedBytes > $maxUploadSize) {
            // Clean up chunks and abort
            self::deleteDirectoryRecursive($uploadDir);
            return [
                'success' => false,
                'error' => "Accumulated chunk payload ({$accumulatedBytes} bytes) exceeded maximum upload size limit."
            ];
        }

        // If not all chunks have arrived, acknowledge receipt
        if (count($chunkFiles) < $totalChunks) {
            return [
                'success' => true,
                'uploadId' => $uploadId,
                'chunkIndex' => $chunkIndex,
                'received_chunks' => count($chunkFiles),
                'totalChunks' => $totalChunks,
                'completed' => false,
                'message' => "Chunk {$chunkIndex} uploaded successfully."
            ];
        }

        // All chunks received -> assemble final file atomically
        sort($chunkFiles);
        $finalDestination = $targetDir . DIRECTORY_SEPARATOR . $filename;
        $tempDestination = $finalDestination . '.tmp_' . bin2hex(random_bytes(6));

        $outHandle = @fopen($tempDestination, 'wb');
        if (!$outHandle) {
            self::deleteDirectoryRecursive($uploadDir);
            return ['success' => false, 'error' => 'Failed to open output handle for final file assembly.'];
        }

        foreach ($chunkFiles as $cf) {
            $inHandle = fopen($cf, 'rb');
            if ($inHandle) {
                stream_copy_to_stream($inHandle, $outHandle);
                fclose($inHandle);
            }
        }
        fclose($outHandle);

        // Verify assembled size
        $assembledSize = filesize($tempDestination);
        if ($assembledSize > $maxUploadSize) {
            @unlink($tempDestination);
            self::deleteDirectoryRecursive($uploadDir);
            return ['success' => false, 'error' => "Assembled file exceeds maximum allowable size limit."];
        }

        // Atomic publish to destination
        if (!@rename($tempDestination, $finalDestination)) {
            @unlink($tempDestination);
            self::deleteDirectoryRecursive($uploadDir);
            return ['success' => false, 'error' => 'Failed to finalize assembled file into target directory.'];
        }

        @chmod($finalDestination, 0664);

        // Clean up scratch chunks
        self::deleteDirectoryRecursive($uploadDir);

        // Auto-register in __spp_file_props.yml
        $baseDir = realpath(defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 3));
        $normDest = str_replace('\\', '/', $finalDestination);
        $normBase = str_replace('\\', '/', $baseDir ?: '');
        $relPath = ($normBase !== '' && str_starts_with($normDest, $normBase))
            ? ltrim(substr($normDest, strlen($normBase)), '/')
            : $normDest;

        if (class_exists('\SPPMod\SPPStorage\FilePropsManager')) {
            try {
                $ext = pathinfo($finalDestination, PATHINFO_EXTENSION);
                \SPPMod\SPPStorage\FilePropsManager::saveFileProps($relPath, [
                    'description' => "Uploaded file (chunked): {$filename}",
                    'usage' => "User uploaded asset",
                    'stability' => 'stable',
                    'author' => 'SPP Administrator',
                    'review_date' => date('Y-m-d', strtotime('+1 year')),
                    'checksum' => hash_file('sha256', $finalDestination) ?: null,
                    'attributes' => [
                        'tags' => array_values(array_filter([$ext, 'chunk_upload']))
                    ]
                ]);
            } catch (\Throwable $e) {}
        }

        return [
            'success' => true,
            'completed' => true,
            'uploadId' => $uploadId,
            'filename' => $filename,
            'path' => $relPath,
            'size' => $assembledSize,
            'message' => "File '{$filename}' successfully assembled from {$totalChunks} chunks."
        ];
    }

    /**
     * Moves a file or directory safely into a target folder or specific destination path.
     */
    public static function moveItem(string $sourcePath, string $destinationDir): array
    {
        $src = self::resolveSafePath($sourcePath);
        $dstResolved = self::resolveSafePath($destinationDir);

        if (!$src || !file_exists($src)) {
            return ['success' => false, 'error' => 'Source item not found.'];
        }

        if ($dstResolved && is_dir($dstResolved)) {
            $dstDir = $dstResolved;
            $itemName = basename($src);
            $targetFile = $dstDir . DIRECTORY_SEPARATOR . $itemName;
        } else {
            $parent = dirname($dstResolved ?: $destinationDir);
            $realParent = realpath($parent);
            if ($realParent && is_dir($realParent)) {
                $dstDir = $realParent;
                $targetFile = $dstResolved ?: ($realParent . DIRECTORY_SEPARATOR . basename($destinationDir));
                $itemName = basename($targetFile);
            } else {
                return ['success' => false, 'error' => 'Destination directory does not exist.'];
            }
        }

        $baseDir = realpath(defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 3));
        if ($src === $baseDir) {
            return ['success' => false, 'error' => 'Cannot move workspace root.'];
        }

        // Prevent moving directory into itself or into one of its subdirectories
        $srcReal = realpath($src);
        $dstReal = realpath($dstDir);
        if (is_dir($srcReal) && ($dstReal === $srcReal || str_starts_with($dstReal . DIRECTORY_SEPARATOR, $srcReal . DIRECTORY_SEPARATOR))) {
            return ['success' => false, 'error' => 'Cannot move a directory into itself or its own subfolder.'];
        }

        if (file_exists($targetFile)) {
            return ['success' => false, 'error' => "An item named '{$itemName}' already exists in destination folder."];
        }

        if (!@rename($src, $targetFile)) {
            return ['success' => false, 'error' => "Failed to move '{$itemName}' to destination."];
        }

        $normTarget = str_replace('\\', '/', $targetFile);
        $normBase = str_replace('\\', '/', $baseDir ?: '');
        if ($normBase !== '' && str_starts_with($normTarget, $normBase)) {
            $relDst = ltrim(substr($normTarget, strlen($normBase)), '/');
        } else {
            $relDst = $normTarget;
        }

        // Migrate property entry in parent and child manifests
        self::renameFileProperties($sourcePath, $relDst);

        return [
            'success' => true,
            'message' => "Item '{$itemName}' moved successfully.",
            'source' => str_replace('\\', '/', $sourcePath),
            'destination' => $relDst
        ];
    }

    /**
     * Copies a file or directory safely into a target folder or specific destination path.
     */
    public static function copyItem(string $sourcePath, string $destinationDir): array
    {
        $src = self::resolveSafePath($sourcePath);
        $dstResolved = self::resolveSafePath($destinationDir);

        if (!$src || !file_exists($src)) {
            return ['success' => false, 'error' => 'Source item not found.'];
        }

        if ($dstResolved && is_dir($dstResolved)) {
            $dstDir = $dstResolved;
            $itemName = basename($src);
            $targetPath = $dstDir . DIRECTORY_SEPARATOR . $itemName;
            if (file_exists($targetPath)) {
                $info = pathinfo($itemName);
                $ext = !empty($info['extension']) ? '.' . $info['extension'] : '';
                $targetPath = $dstDir . DIRECTORY_SEPARATOR . $info['filename'] . '_copy_' . date('His') . $ext;
            }
        } else {
            $parent = dirname($dstResolved ?: $destinationDir);
            $realParent = realpath($parent);
            if ($realParent && is_dir($realParent)) {
                $dstDir = $realParent;
                $targetPath = $dstResolved ?: ($realParent . DIRECTORY_SEPARATOR . basename($destinationDir));
                $itemName = basename($targetPath);
            } else {
                return ['success' => false, 'error' => 'Destination directory does not exist.'];
            }
        }

        if (is_file($src)) {
            if (!@copy($src, $targetPath)) {
                return ['success' => false, 'error' => "Failed to copy file to '{$targetPath}'."];
            }
        } else {
            if (!self::copyDirectoryRecursive($src, $targetPath)) {
                return ['success' => false, 'error' => "Failed to copy directory to '{$targetPath}'."];
            }
        }

        // Replicate metadata into target destination manifest without copying stale identity/integrity claims
        if (class_exists('\SPPMod\SPPStorage\FilePropsManager')) {
            try {
                $srcProps = \SPPMod\SPPStorage\FilePropsManager::getFileProps($sourcePath);
                if (!empty($srcProps['has_props'])) {
                    $newProps = $srcProps;
                    unset($newProps['has_props'], $newProps['signature'], $newProps['path'], $newProps['name']);
                    if (is_file($targetPath)) {
                        $newProps['checksum'] = hash_file('sha256', $targetPath) ?: null;
                    }
                    \SPPMod\SPPStorage\FilePropsManager::saveFileProps($targetPath, $newProps);
                } elseif (is_dir($targetPath)) {
                    \SPPMod\SPPStorage\FilePropsManager::syncDirectory($targetPath, true, false, false);
                }
            } catch (\Throwable $e) {}
        }

        return [
            'success' => true,
            'message' => "Item copied successfully.",
            'source' => str_replace('\\', '/', $sourcePath),
            'target' => str_replace('\\', '/', $targetPath)
        ];
    }

    private static function copyDirectoryRecursive(string $source, string $destination): bool
    {
        if (!is_dir($destination)) {
            if (!@mkdir($destination, 0775, true)) {
                return false;
            }
        }

        $dir = opendir($source);
        if (!$dir) return false;

        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') continue;
            $srcFile = $source . DIRECTORY_SEPARATOR . $file;
            $dstFile = $destination . DIRECTORY_SEPARATOR . $file;
            if (is_dir($srcFile)) {
                if (!self::copyDirectoryRecursive($srcFile, $dstFile)) {
                    closedir($dir);
                    return false;
                }
            } else {
                if (!@copy($srcFile, $dstFile)) {
                    closedir($dir);
                    return false;
                }
            }
        }
        closedir($dir);
        return true;
    }

    /**
     * Bulk deletes an array of files or directories.
     */
    public static function bulkDelete(array $paths): array
    {
        $deleted = 0;
        $errors = [];
        foreach ($paths as $path) {
            $res = self::deleteItem($path);
            if (!empty($res['success'])) {
                $deleted++;
            } else {
                $errors[] = basename($path) . ': ' . ($res['error'] ?? 'Unknown error');
            }
        }
        return [
            'success' => empty($errors) || $deleted > 0,
            'deleted_count' => $deleted,
            'total' => count($paths),
            'errors' => $errors,
            'message' => "Deleted {$deleted} of " . count($paths) . " item(s)." . (!empty($errors) ? " (" . count($errors) . " failed)" : '')
        ];
    }

    /**
     * Bulk moves an array of files or directories into a target folder.
     */
    public static function bulkMove(array $paths, string $destinationDir): array
    {
        $moved = 0;
        $errors = [];
        foreach ($paths as $path) {
            $res = self::moveItem($path, $destinationDir);
            if (!empty($res['success'])) {
                $moved++;
            } else {
                $errors[] = basename($path) . ': ' . ($res['error'] ?? 'Unknown error');
            }
        }
        return [
            'success' => empty($errors) || $moved > 0,
            'moved_count' => $moved,
            'total' => count($paths),
            'errors' => $errors,
            'message' => "Moved {$moved} of " . count($paths) . " item(s)."
        ];
    }

    /**
     * Bulk copies an array of files or directories into a target folder.
     */
    public static function bulkCopy(array $paths, string $destinationDir): array
    {
        $copied = 0;
        $errors = [];
        foreach ($paths as $path) {
            $res = self::copyItem($path, $destinationDir);
            if (!empty($res['success'])) {
                $copied++;
            } else {
                $errors[] = basename($path) . ': ' . ($res['error'] ?? 'Unknown error');
            }
        }
        return [
            'success' => empty($errors) || $copied > 0,
            'copied_count' => $copied,
            'total' => count($paths),
            'errors' => $errors,
            'message' => "Copied {$copied} of " . count($paths) . " item(s)."
        ];
    }

    /**
     * Bulk updates permissions of an array of files or directories.
     */
    public static function bulkChmod(array $paths, string $mode, bool $recursive = false): array
    {
        $updated = 0;
        $errors = [];
        foreach ($paths as $path) {
            $res = self::chmodItem($path, $mode, $recursive);
            if (!empty($res['success'])) {
                $updated++;
            } else {
                $errors[] = basename($path) . ': ' . ($res['error'] ?? 'Failed');
            }
        }
        return [
            'success' => empty($errors) || $updated > 0,
            'updated_count' => $updated,
            'total' => count($paths),
            'errors' => $errors,
            'message' => "Permissions updated for {$updated} of " . count($paths) . " item(s)."
        ];
    }

    /**
     * Compresses multiple selected files and directories into a ZIP archive.
     */
    public static function compressItems(array $paths, ?string $outputPath = null): array
    {
        if (empty($paths)) {
            return ['success' => false, 'error' => 'No items selected for compression.'];
        }
        if (!class_exists('ZipArchive')) {
            return ['success' => false, 'error' => 'PHP ZipArchive extension is required.'];
        }

        $firstItem = self::resolveSafePath($paths[0]);
        $dir = $firstItem ? (is_dir($firstItem) ? $firstItem : dirname($firstItem)) : sys_get_temp_dir();

        if ($outputPath !== null && trim($outputPath) !== '') {
            $destFile = self::resolveSafePath($outputPath);
        } else {
            $destFile = $dir . DIRECTORY_SEPARATOR . 'bundle_' . date('Ymd_His') . '.zip';
        }

        $zip = new ZipArchive();
        $status = $zip->open($destFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($status !== true) {
            return ['success' => false, 'error' => "Failed to create ZIP archive (Code: {$status})."];
        }

        $count = 0;
        foreach ($paths as $p) {
            $real = self::resolveSafePath($p);
            if (!$real || !file_exists($real)) continue;
            $name = basename($real);
            if (is_file($real)) {
                $zip->addFile($real, $name);
                $count++;
            } elseif (is_dir($real)) {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($real, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::SELF_FIRST
                );
                $zip->addEmptyDir($name);
                foreach ($iterator as $item) {
                    $subPath = $name . '/' . substr($item->getPathname(), strlen($real) + 1);
                    $subPath = str_replace('\\', '/', $subPath);
                    if ($item->isDir()) {
                        $zip->addEmptyDir($subPath);
                    } elseif ($item->isFile()) {
                        $zip->addFile($item->getPathname(), $subPath);
                        $count++;
                    }
                }
            }
        }
        $zip->close();

        return [
            'success' => true,
            'message' => "Archive created with {$count} item(s).",
            'output_file' => str_replace('\\', '/', $destFile),
            'filename' => basename($destFile),
            'size' => @filesize($destFile) ?: 0
        ];
    }

    /**
     * Downloads an entire folder zipped on-the-fly.
     */
    public static function downloadFolderAsZip(string $folderPath): void
    {
        $dir = self::resolveSafePath($folderPath);
        if (!$dir || !is_dir($dir)) {
            if (!headers_sent()) {
                http_response_code(404);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Directory not found.']);
            }
            exit;
        }

        $folderName = basename($dir) ?: 'archive';
        $tempZip = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'spp_' . $folderName . '_' . time() . '.zip';

        if (!class_exists('ZipArchive')) {
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'ZipArchive extension missing.']);
            }
            exit;
        }

        $zip = new ZipArchive();
        if ($zip->open($tempZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            exit("Failed to create temporary zip.");
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        $baseLen = strlen($dir) + 1;
        foreach ($iterator as $item) {
            $subPath = substr($item->getPathname(), $baseLen);
            $subPath = str_replace('\\', '/', $subPath);
            if ($item->isDir()) {
                $zip->addEmptyDir($subPath);
            } elseif ($item->isFile()) {
                $zip->addFile($item->getPathname(), $subPath);
            }
        }
        $zip->close();

        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $folderName . '_' . date('Ymd_His') . '.zip"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($tempZip));
        readfile($tempZip);
        @unlink($tempZip);
        exit;
    }

    /**
     * Downloads multiple selected files/directories zipped together.
     */
    public static function downloadSelectedAsZip(array $paths, ?string $zipName = null): void
    {
        if (empty($paths)) {
            if (!headers_sent()) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'No paths provided.']);
            }
            exit;
        }

        $outName = $zipName ?: ('download_' . date('Ymd_His') . '.zip');
        $tempZip = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'spp_sel_' . time() . '_' . mt_rand(100, 999) . '.zip';

        if (!class_exists('ZipArchive')) {
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'ZipArchive extension missing.']);
            }
            exit;
        }

        $zip = new ZipArchive();
        if ($zip->open($tempZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            exit("Failed to create temporary zip.");
        }

        foreach ($paths as $p) {
            $real = self::resolveSafePath($p);
            if (!$real || !file_exists($real)) continue;
            $name = basename($real);
            if (is_file($real)) {
                $zip->addFile($real, $name);
            } elseif (is_dir($real)) {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($real, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::SELF_FIRST
                );
                $zip->addEmptyDir($name);
                foreach ($iterator as $item) {
                    $subPath = $name . '/' . substr($item->getPathname(), strlen($real) + 1);
                    $subPath = str_replace('\\', '/', $subPath);
                    if ($item->isDir()) {
                        $zip->addEmptyDir($subPath);
                    } elseif ($item->isFile()) {
                        $zip->addFile($item->getPathname(), $subPath);
                    }
                }
            }
        }
        $zip->close();

        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $outName . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($tempZip));
        readfile($tempZip);
        @unlink($tempZip);
        exit;
    }

    /**
     * Downloads a file directly.
     * Streams file with attachment headers in web context, or returns file info in CLI context.
     */
    public static function downloadFile(string $relativePath): array
    {
        $filePath = self::resolveSafePath($relativePath);
        if (!$filePath || !is_file($filePath)) {
            return ['success' => false, 'error' => 'File not found or access denied.'];
        }

        $filename = basename($filePath);
        $size = @filesize($filePath) ?: 0;

        // In web SAPI, stream file directly as attachment
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            if (ob_get_level()) {
                ob_end_clean();
            }
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . $size);
            readfile($filePath);
            exit;
        }

        return [
            'success' => true,
            'path' => str_replace('\\', '/', $relativePath),
            'filename' => $filename,
            'size' => $size
        ];
    }

    /**
     * Recursively builds a folder tree hierarchy for the left pane navigation.
     * Supports full system root navigation or workspace root scoping.
     */
    public static function getDirectoryTree(string $root = '', int $maxDepth = 1): array
    {
        $targetDir = self::resolveSafePath($root);
        if (!$targetDir || !is_dir($targetDir)) {
            return ['success' => false, 'error' => 'Root directory not found.'];
        }

        $baseDir = realpath(defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 3));
        $sysRoot = self::getSystemRoot();
        $normTarget = rtrim(str_replace('\\', '/', $targetDir), '/');
        $normSysRoot = rtrim(str_replace('\\', '/', $sysRoot), '/');
        $isSystemRoot = ($normTarget === $normSysRoot || $normTarget === '' || $normTarget === '/');

        $ignored = [
            '.git', '.svn', '.hg', 'vendor', 'node_modules', 'var', 'spp/var', 'var/cache',
            'storage/framework', '.dist', 'dist', '.gemini', '.agents', '.vs', '.vscode', '.idea',
            'bin', 'obj', 'coverage', '.tempmediaStorage', 'scratch', 'brain',
            'Windows', 'Program Files', 'Program Files (x86)', '$Recycle.Bin', 'System Volume Information', 'Recovery', 'AppData'
        ];

        // High responsiveness: default depth 1 for sub-20ms returns; system root is always capped at 1
        $depth = $isSystemRoot ? 1 : max(1, min(2, $maxDepth));
        $tree = self::scanFolderRecursive($targetDir, $baseDir, $ignored, 0, $depth);

        return [
            'success' => true,
            'tree' => $tree,
            'system_root' => $sysRoot,
            'workspace_root' => self::getWorkspaceRoot(),
            'available_roots' => self::getAvailableRoots(),
            'site_roots' => self::getConfiguredSiteRoots()
        ];
    }

    private static function scanFolderRecursive(string $dir, string $baseDir, array $ignored, int $currentDepth, int $maxDepth): array
    {
        $normDir = preg_replace('#(?<!:)/+#', '/', str_replace('\\', '/', $dir));
        $normBase = preg_replace('#(?<!:)/+#', '/', str_replace('\\', '/', $baseDir ?: ''));
        $sysRoot = preg_replace('#(?<!:)/+#', '/', str_replace('\\', '/', self::getSystemRoot()));

        $isSysRoot = ($normDir === '/' || $normDir === rtrim($sysRoot, '/') || $normDir === $sysRoot);
        $isBaseRoot = ($normBase !== '' && $normDir === $normBase);

        // Path representation and node naming
        if ($isSysRoot) {
            $nodePath = $sysRoot;
            $name = 'Root (/)';
        } elseif ($isBaseRoot && $currentDepth === 0) {
            $nodePath = $normDir;
            $name = 'root';
        } else {
            $nodePath = $normDir;
            $name = basename($dir);
        }

        $node = [
            'name' => $name,
            'path' => $nodePath,
            'is_dir' => true,
            'children' => [],
            'has_children' => false
        ];

        if ($currentDepth >= $maxDepth) {
            $hasSubs = false;
            $handle = @opendir($dir);
            if ($handle) {
                while (($entry = readdir($handle)) !== false) {
                    if ($entry !== '.' && $entry !== '..' && !in_array($entry, $ignored, true)) {
                        if (is_dir(rtrim($dir, '\\/') . DIRECTORY_SEPARATOR . $entry)) {
                            $hasSubs = true;
                            break;
                        }
                    }
                }
                closedir($handle);
            }
            $node['has_children'] = $hasSubs;
            return $node;
        }

        $handle = @opendir($dir);
        if (!$handle) {
            return $node;
        }

        while (($entry = readdir($handle)) !== false) {
            if ($entry === '.' || $entry === '..' || in_array($entry, $ignored, true) || str_starts_with($entry, '.')) {
                continue;
            }

            $full = rtrim($dir, '\\/') . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($full)) {
                $node['children'][] = self::scanFolderRecursive($full, $baseDir, $ignored, $currentDepth + 1, $maxDepth);
            }
        }
        closedir($handle);

        // Sort subdirectories alphabetically
        usort($node['children'], fn($a, $b) => strcasecmp($a['name'], $b['name']));
        $node['has_children'] = !empty($node['children']);

        return $node;
    }

    /**
     * Lists custom error page templates (400, 401, 403, 404, 500).
     */
    public static function getErrorPages(?string $docroot = null): array
    {
        $baseDir = realpath(defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 3));
        $targetDocroot = !empty($docroot) ? (self::resolveSafePath($docroot) ?? $baseDir) : $baseDir;
        $errorDir = $targetDocroot . DIRECTORY_SEPARATOR . '.spp' . DIRECTORY_SEPARATOR . 'error_pages';

        $codes = [
            400 => 'Bad Request',
            401 => 'Authorization Required',
            403 => 'Forbidden / Access Denied',
            404 => 'Page Not Found',
            500 => 'Internal Server Error'
        ];

        $pages = [];
        foreach ($codes as $code => $title) {
            $file = $errorDir . DIRECTORY_SEPARATOR . "{$code}.html";
            $exists = file_exists($file);
            $content = $exists ? (file_get_contents($file) ?: '') : self::getDefaultErrorHtml($code);
            $pages[] = [
                'code' => $code,
                'title' => $title,
                'exists' => $exists,
                'content' => $content,
                'path' => str_replace('\\', '/', ltrim(substr($file, strlen($baseDir)), DIRECTORY_SEPARATOR))
            ];
        }

        return [
            'success' => true,
            'docroot' => str_replace('\\', '/', $targetDocroot),
            'error_dir' => str_replace('\\', '/', $errorDir),
            'pages' => $pages
        ];
    }

    /**
     * Saves or creates a custom error page template and configures .htaccess.
     */
    public static function saveErrorPage(int $code, string $content, ?string $docroot = null): array
    {
        $allowedCodes = [400, 401, 403, 404, 500];
        if (!in_array($code, $allowedCodes, true)) {
            return ['success' => false, 'error' => "Invalid HTTP error code: {$code}"];
        }

        $baseDir = realpath(defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 3));
        $targetDocroot = !empty($docroot) ? (self::resolveSafePath($docroot) ?? $baseDir) : $baseDir;
        $errorDir = $targetDocroot . DIRECTORY_SEPARATOR . '.spp' . DIRECTORY_SEPARATOR . 'error_pages';

        if (!is_dir($errorDir)) {
            @mkdir($errorDir, 0775, true);
        }

        $file = $errorDir . DIRECTORY_SEPARATOR . "{$code}.html";
        if (file_put_contents($file, $content) === false) {
            return ['success' => false, 'error' => "Failed to write error page template: {$file}"];
        }

        // Configure .htaccess ErrorDocument directive if applicable
        $htaccess = $targetDocroot . DIRECTORY_SEPARATOR . '.htaccess';
        $directive = "ErrorDocument {$code} /.spp/error_pages/{$code}.html";
        $existing = file_exists($htaccess) ? (file_get_contents($htaccess) ?: '') : '';

        if (!str_contains($existing, "ErrorDocument {$code}")) {
            file_put_contents($htaccess, rtrim($existing) . "\n" . $directive . "\n");
        }

        return [
            'success' => true,
            'message' => "Custom error page {$code} saved successfully.",
            'file' => str_replace('\\', '/', $file)
        ];
    }

    /**
     * Inspects directory indexing status (Options +Indexes / -Indexes) in target path.
     */
    public static function getDirectoryIndexing(string $path = ''): array
    {
        $targetDir = self::resolveSafePath($path);
        if (!$targetDir || !is_dir($targetDir)) {
            return ['success' => false, 'error' => 'Target directory not found.'];
        }

        $htaccess = $targetDir . DIRECTORY_SEPARATOR . '.htaccess';
        $content = file_exists($htaccess) ? (file_get_contents($htaccess) ?: '') : '';

        $mode = 'default';
        if (preg_match('/^\s*Options\s+.*\+Indexes/mi', $content)) {
            $mode = 'enabled';
        } elseif (preg_match('/^\s*Options\s+.*-Indexes/mi', $content)) {
            $mode = 'disabled';
        }

        return [
            'success' => true,
            'path' => str_replace('\\', '/', $path),
            'mode' => $mode,
            'indexed' => ($mode === 'enabled')
        ];
    }

    /**
     * Toggles directory indexing on or off for a given directory via .htaccess.
     */
    public static function toggleDirectoryIndexing(string $path, bool $enable): array
    {
        $targetDir = self::resolveSafePath($path);
        if (!$targetDir || !is_dir($targetDir)) {
            return ['success' => false, 'error' => 'Target directory not found.'];
        }

        $htaccess = $targetDir . DIRECTORY_SEPARATOR . '.htaccess';
        $content = file_exists($htaccess) ? (file_get_contents($htaccess) ?: '') : '';

        // Strip existing Options ±Indexes directives
        $cleaned = preg_replace('/^\s*Options\s+[+-]?Indexes\s*$/mi', '', $content);
        $newDirective = $enable ? 'Options +Indexes' : 'Options -Indexes';
        $final = rtrim((string)$cleaned) . "\n" . $newDirective . "\n";

        file_put_contents($htaccess, ltrim($final));

        return [
            'success' => true,
            'message' => 'Directory indexing has been ' . ($enable ? 'enabled' : 'disabled') . '.',
            'mode' => $enable ? 'enabled' : 'disabled',
            'indexed' => $enable
        ];
    }

    /**
     * Lists custom MIME type mappings configured in .htaccess alongside standard mappings.
     */
    public static function listMimeTypes(?string $docroot = null): array
    {
        $baseDir = realpath(defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 3));
        $targetDocroot = !empty($docroot) ? (self::resolveSafePath($docroot) ?? $baseDir) : $baseDir;
        $htaccess = $targetDocroot . DIRECTORY_SEPARATOR . '.htaccess';

        $custom = [];
        if (file_exists($htaccess)) {
            $lines = file($htaccess, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $line) {
                if (preg_match('/^\s*AddType\s+([^\s]+)\s+\.?([a-zA-Z0-9_-]+)/i', $line, $m)) {
                    $custom[strtolower($m[2])] = [
                        'extension' => strtolower($m[2]),
                        'mime_type' => trim($m[1]),
                        'custom' => true
                    ];
                }
            }
        }

        $standard = [
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            'svg' => 'image/svg+xml',
            'wasm' => 'application/wasm',
            'json' => 'application/json',
            'woff2' => 'font/woff2',
            'mp4' => 'video/mp4'
        ];

        $all = [];
        foreach ($standard as $ext => $type) {
            $all[] = $custom[$ext] ?? [
                'extension' => $ext,
                'mime_type' => $type,
                'custom' => false
            ];
        }

        foreach ($custom as $ext => $data) {
            if (!isset($standard[$ext])) {
                $all[] = $data;
            }
        }

        return [
            'success' => true,
            'custom' => array_values($custom),
            'types' => array_values($all),
            'htaccess_path' => str_replace('\\', '/', $htaccess)
        ];
    }

    /**
     * Saves or appends an AddType MIME mapping in .htaccess.
     */
    public static function saveMimeType(string $extension, string $mimeType, ?string $docroot = null): array
    {
        $ext = strtolower(ltrim(trim($extension), '.'));
        $mime = trim($mimeType);

        if (empty($ext) || empty($mime)) {
            return ['success' => false, 'error' => 'Extension and MIME type cannot be empty.'];
        }

        $baseDir = realpath(defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 3));
        $targetDocroot = !empty($docroot) ? (self::resolveSafePath($docroot) ?? $baseDir) : $baseDir;
        $htaccess = $targetDocroot . DIRECTORY_SEPARATOR . '.htaccess';
        $content = file_exists($htaccess) ? (file_get_contents($htaccess) ?: '') : '';

        // Remove any existing directive for this extension
        $pattern = '/^\s*AddType\s+[^\s]+\s+\.?' . preg_quote($ext, '/') . '\s*$/mi';
        $cleaned = preg_replace($pattern, '', $content);

        $directive = "AddType {$mime} .{$ext}";
        $final = rtrim((string)$cleaned) . "\n" . $directive . "\n";

        file_put_contents($htaccess, ltrim($final));

        return [
            'success' => true,
            'message' => "MIME mapping for .{$ext} => {$mime} saved.",
            'extension' => $ext,
            'mime_type' => $mime
        ];
    }

    /**
     * Removes an AddType MIME mapping from .htaccess.
     */
    public static function removeMimeType(string $extension, ?string $docroot = null): array
    {
        $ext = strtolower(ltrim(trim($extension), '.'));
        $baseDir = realpath(defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 3));
        $targetDocroot = !empty($docroot) ? (self::resolveSafePath($docroot) ?? $baseDir) : $baseDir;
        $htaccess = $targetDocroot . DIRECTORY_SEPARATOR . '.htaccess';

        if (!file_exists($htaccess)) {
            return ['success' => true, 'message' => 'No .htaccess found.'];
        }

        $content = file_get_contents($htaccess) ?: '';
        $pattern = '/^\s*AddType\s+[^\s]+\s+\.?' . preg_quote($ext, '/') . '\s*$/mi';
        $cleaned = preg_replace($pattern, '', $content);

        file_put_contents($htaccess, trim((string)$cleaned) . "\n");

        return [
            'success' => true,
            'message' => "MIME mapping for .{$ext} removed."
        ];
    }

    /**
     * Computes a structured hierarchical treemap of disk consumption for interactive visualization.
     */
    public static function getDiskUsageTreemap(string $root = '', int $maxDepth = 3): array
    {
        $targetDir = self::resolveSafePath($root);
        if (!$targetDir || !is_dir($targetDir)) {
            return ['success' => false, 'error' => 'Root directory not found.'];
        }

        $baseDir = realpath(defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 3));
        $ignored = [
            '.git', 'vendor', 'node_modules', 'cache', '.tempmediaStorage', 'coverage',
            '.gemini', 'var', 'tmp', 'dist', '.vs', '.phpdoc', 'logs', '.idea'
        ];

        $tree = self::scanFolderForTreemap($targetDir, $baseDir, $ignored, 0, $maxDepth);

        return [
            'success' => true,
            'treemap' => $tree
        ];
    }

    /**
     * Alias for getDiskUsageTreemap returning tree key for compatibility.
     */
    public static function getDiskUsageTree(string $root = '', int $maxDepth = 3): array
    {
        $res = self::getDiskUsageTreemap($root, $maxDepth);
        if (!$res['success']) {
            return $res;
        }
        return [
            'success' => true,
            'path' => $root,
            'tree' => $res['treemap']
        ];
    }

    /**
     * Alias for saveMimeType.
     */
    public static function addMimeType(string $extension, string $mimeType, ?string $docroot = null): array
    {
        return self::saveMimeType($extension, $mimeType, $docroot);
    }

    /**
     * Returns fallback default HTML for standard HTTP error codes.
     */
    public static function getDefaultErrorHtml(int $code): string
    {
        $msg = match ($code) {
            400 => 'Bad Request',
            401 => 'Unauthorized Access',
            403 => 'Forbidden - Directory or Resource Protected',
            404 => 'Page or Resource Not Found',
            500 => 'Internal Server Error',
            default => 'HTTP Error'
        };

        return "<!DOCTYPE html>\n<html>\n<head><title>{$code} {$msg}</title></head>\n<body style=\"font-family:sans-serif; text-align:center; padding:4rem;\">\n  <h1>{$code} - {$msg}</h1>\n  <p>The requested resource could not be served by the web server.</p>\n  <hr/>\n  <small>SPP Enterprise Server</small>\n</body>\n</html>";
    }

    private static function scanFolderForTreemap(string $dir, string $baseDir, array $ignored, int $currentDepth, int $maxDepth): array
    {
        $name = basename($dir);
        $rel = ltrim(substr($dir, strlen($baseDir)), DIRECTORY_SEPARATOR);
        $rel = str_replace('\\', '/', $rel);

        $node = [
            'name' => empty($rel) ? 'root' : $name,
            'path' => $rel,
            'type' => 'dir',
            'size' => 0,
            'formatted_size' => '0 B',
            'children' => []
        ];

        $entries = @scandir($dir);
        if ($entries === false) {
            return $node;
        }

        $totalSize = 0;
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || in_array($entry, $ignored, true)) {
                continue;
            }

            $full = $dir . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($full)) {
                if ($currentDepth < $maxDepth) {
                    $childNode = self::scanFolderForTreemap($full, $baseDir, $ignored, $currentDepth + 1, $maxDepth);
                    $totalSize += $childNode['size'];
                    $node['children'][] = $childNode;
                }
            } else {
                $fSize = (int)(@filesize($full) ?: 0);
                $totalSize += $fSize;
                if ($currentDepth < $maxDepth) {
                    $node['children'][] = [
                        'name' => $entry,
                        'path' => str_replace('\\', '/', ltrim(substr($full, strlen($baseDir)), DIRECTORY_SEPARATOR)),
                        'type' => 'file',
                        'size' => $fSize,
                        'formatted_size' => self::formatBytes($fSize)
                    ];
                }
            }
        }

        $node['size'] = $totalSize;
        $node['formatted_size'] = self::formatBytes($totalSize);

        // Sort children descending by size
        usort($node['children'], fn($a, $b) => $b['size'] <=> $a['size']);

        return $node;
    }

    /**
     * Discovers active access log files on the host system.
     */
    public static function getAccessLogInfo(): array
    {
        $baseDir = realpath(defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 3));
        $candidates = [
            $baseDir . '/var/logs/access.log',
            $baseDir . '/logs/access.log',
            'C:/Apache24/logs/access.log',
            'C:/xampp/apache/logs/access.log',
            '/var/log/apache2/access.log',
            '/var/log/httpd/access_log',
            '/var/log/nginx/access.log'
        ];

        $found = [];
        foreach ($candidates as $cand) {
            if (file_exists($cand) && is_file($cand)) {
                $size = (int)(@filesize($cand) ?: 0);
                $found[] = [
                    'path' => str_replace('\\', '/', $cand),
                    'size' => $size,
                    'formatted_size' => self::formatBytes($size),
                    'modified_at' => date('Y-m-d H:i:s', (int)(@filemtime($cand) ?: time())),
                    'readable' => is_readable($cand)
                ];
            }
        }

        return [
            'success' => true,
            'logs' => $found,
            'count' => count($found)
        ];
    }

    /**
     * Reads the tail of an access log file.
     */
    public static function readAccessLog(string $logPath = '', int $lines = 100): array
    {
        $info = self::getAccessLogInfo();
        $target = null;

        if (!empty($logPath)) {
            foreach ($info['logs'] as $log) {
                if (strcasecmp($log['path'], str_replace('\\', '/', $logPath)) === 0) {
                    $target = $log['path'];
                    break;
                }
            }
        }

        if (!$target && !empty($info['logs'])) {
            $target = $info['logs'][0]['path'];
        }

        if (!$target || !file_exists($target)) {
            return ['success' => false, 'error' => 'No readable access log file found.'];
        }

        $fileLines = @file($target, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($fileLines === false) {
            return ['success' => false, 'error' => 'Could not read log file.'];
        }

        $slice = array_slice($fileLines, -$lines);

        return [
            'success' => true,
            'log_path' => $target,
            'total_lines' => count($fileLines),
            'returned_lines' => count($slice),
            'lines' => $slice
        ];
    }

    // ==========================================
    // File Manager v2: FTP Account Management
    // ==========================================

    public static function listFtpAccounts(): array
    {
        if (!class_exists('\SPPMod\SPPServer\FTPAccountManager')) {
            require_once dirname(__DIR__, 2) . '/modules/optional/sppserver/src/FTPAccountManager.php';
        }
        return [
            'success'  => true,
            'accounts' => \SPPMod\SPPServer\FTPAccountManager::listAccounts()
        ];
    }

    public static function createFtpAccount(string $username, string $password, string $dir, int $quotaMb = 1024): array
    {
        if (!class_exists('\SPPMod\SPPServer\FTPAccountManager')) {
            require_once dirname(__DIR__, 2) . '/modules/optional/sppserver/src/FTPAccountManager.php';
        }
        return \SPPMod\SPPServer\FTPAccountManager::createAccount($username, $password, $dir, $quotaMb);
    }

    public static function deleteFtpAccount(string $username): array
    {
        if (!class_exists('\SPPMod\SPPServer\FTPAccountManager')) {
            require_once dirname(__DIR__, 2) . '/modules/optional/sppserver/src/FTPAccountManager.php';
        }
        return \SPPMod\SPPServer\FTPAccountManager::deleteAccount($username);
    }

    // ==========================================
    // File Manager v2: Trash Bin Management
    // ==========================================

    public static function moveToTrash(string $path, string $docroot = ''): array
    {
        $safePath = self::resolveSafePath($path);
        if (!$safePath || !file_exists($safePath)) {
            return ['success' => false, 'error' => "File or directory not found: {$path}"];
        }

        $root = !empty($docroot) ? $docroot : (defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(SPP_BASE_DIR));
        $trashDir = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . '.spp' . DIRECTORY_SEPARATOR . 'trash';
        if (!is_dir($trashDir)) {
            @mkdir($trashDir, 0755, true);
        }

        $id = time() . '_' . bin2hex(random_bytes(3));
        $meta = [
            'id'            => $id,
            'original_path' => $safePath,
            'basename'      => basename($safePath),
            'deleted_at'    => date('Y-m-d H:i:s'),
            'size'          => is_file($safePath) ? filesize($safePath) : 0,
            'is_dir'        => is_dir($safePath)
        ];

        $targetDest = $trashDir . DIRECTORY_SEPARATOR . $id . '_' . basename($safePath);
        if (!@rename($safePath, $targetDest)) {
            return ['success' => false, 'error' => 'Failed to move item to trash.'];
        }

        @file_put_contents($trashDir . DIRECTORY_SEPARATOR . $id . '.json', json_encode($meta, JSON_PRETTY_PRINT));

        return [
            'success'  => true,
            'message'  => "Moved to trash.",
            'trash_id' => $id
        ];
    }

    public static function listTrash(string $docroot = ''): array
    {
        $root = !empty($docroot) ? $docroot : (defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(SPP_BASE_DIR));
        $trashDir = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . '.spp' . DIRECTORY_SEPARATOR . 'trash';
        if (!is_dir($trashDir)) {
            return ['success' => true, 'items' => []];
        }

        $items = [];
        $files = @scandir($trashDir) ?: [];
        foreach ($files as $f) {
            if (str_ends_with($f, '.json')) {
                $raw = @file_get_contents($trashDir . DIRECTORY_SEPARATOR . $f);
                if ($raw) {
                    $m = json_decode($raw, true);
                    if ($m) $items[] = $m;
                }
            }
        }

        return ['success' => true, 'items' => $items];
    }

    public static function restoreFromTrash(string $trashId, string $docroot = ''): array
    {
        $root = !empty($docroot) ? $docroot : (defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(SPP_BASE_DIR));
        $trashDir = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . '.spp' . DIRECTORY_SEPARATOR . 'trash';
        $metaFile = $trashDir . DIRECTORY_SEPARATOR . $trashId . '.json';
        if (!file_exists($metaFile)) {
            return ['success' => false, 'error' => "Trash item '{$trashId}' not found."];
        }

        $meta = json_decode((string)file_get_contents($metaFile), true);
        if (!$meta || empty($meta['original_path'])) {
            return ['success' => false, 'error' => 'Corrupt trash metadata.'];
        }

        $trashedFile = $trashDir . DIRECTORY_SEPARATOR . $trashId . '_' . $meta['basename'];
        if (!file_exists($trashedFile)) {
            return ['success' => false, 'error' => 'Trashed file payload missing.'];
        }

        $origDir = dirname($meta['original_path']);
        if (!is_dir($origDir)) {
            @mkdir($origDir, 0755, true);
        }

        if (!@rename($trashedFile, $meta['original_path'])) {
            return ['success' => false, 'error' => 'Failed to restore file to original path.'];
        }

        @unlink($metaFile);

        return [
            'success'       => true,
            'message'       => "Restored to {$meta['original_path']}.",
            'original_path' => $meta['original_path']
        ];
    }

    public static function emptyTrash(string $docroot = ''): array
    {
        $root = !empty($docroot) ? $docroot : (defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(SPP_BASE_DIR));
        $trashDir = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . '.spp' . DIRECTORY_SEPARATOR . 'trash';
        if (!is_dir($trashDir)) {
            return ['success' => true, 'purged_count' => 0];
        }

        $count = 0;
        $files = @scandir($trashDir) ?: [];
        foreach ($files as $f) {
            if ($f === '.' || $f === '..') continue;
            $p = $trashDir . DIRECTORY_SEPARATOR . $f;
            if (is_file($p)) {
                @unlink($p);
                $count++;
            }
        }

        return ['success' => true, 'purged_count' => $count];
    }

    // ==========================================
    // File Manager v2: Image Processor
    // ==========================================

    public static function processImage(string $path, string $operation, array $params = []): array
    {
        if (!class_exists('\SPPMod\SPPServer\ImageProcessor')) {
            require_once dirname(__DIR__, 2) . '/modules/optional/sppserver/src/ImageProcessor.php';
        }

        $safePath = self::resolveSafePath($path);
        if (!$safePath || !file_exists($safePath)) {
            return ['success' => false, 'error' => "Image not found: {$path}"];
        }

        $target = $params['target_path'] ?? $safePath;

        return match ($operation) {
            'info'    => \SPPMod\SPPServer\ImageProcessor::getInfo($safePath),
            'resize'  => \SPPMod\SPPServer\ImageProcessor::resize(
                $safePath,
                $target,
                (int)($params['width'] ?? 800),
                (int)($params['height'] ?? 600),
                !empty($params['preserve_aspect'])
            ),
            'crop'    => \SPPMod\SPPServer\ImageProcessor::crop(
                $safePath,
                $target,
                (int)($params['x'] ?? 0),
                (int)($params['y'] ?? 0),
                (int)($params['width'] ?? 100),
                (int)($params['height'] ?? 100)
            ),
            'rotate'  => \SPPMod\SPPServer\ImageProcessor::rotate(
                $safePath,
                $target,
                (float)($params['degrees'] ?? 90.0)
            ),
            'convert' => \SPPMod\SPPServer\ImageProcessor::convertFormat(
                $safePath,
                $target,
                (string)($params['format'] ?? 'webp')
            ),
            default   => ['success' => false, 'error' => "Unknown operation '{$operation}'."]
        };
    }

    // ==========================================
    // File Manager v2: Git Workstation
    // ==========================================

    public static function gitStatus(string $path): array
    {
        if (!class_exists('\SPPMod\SPPServer\GitRepoManager')) {
            require_once dirname(__DIR__, 2) . '/modules/optional/sppserver/src/GitRepoManager.php';
        }
        $safe = self::resolveSafePath($path);
        return \SPPMod\SPPServer\GitRepoManager::status($safe ?: $path);
    }

    public static function gitPull(string $path, string $branch = 'main'): array
    {
        if (!class_exists('\SPPMod\SPPServer\GitRepoManager')) {
            require_once dirname(__DIR__, 2) . '/modules/optional/sppserver/src/GitRepoManager.php';
        }
        $safe = self::resolveSafePath($path);
        return \SPPMod\SPPServer\GitRepoManager::pull($safe ?: $path, 'origin', $branch);
    }

    public static function gitLog(string $path, int $limit = 10): array
    {
        if (!class_exists('\SPPMod\SPPServer\GitRepoManager')) {
            require_once dirname(__DIR__, 2) . '/modules/optional/sppserver/src/GitRepoManager.php';
        }
        $safe = self::resolveSafePath($path);
        return \SPPMod\SPPServer\GitRepoManager::log($safe ?: $path, $limit);
    }
}




