<?php
declare(strict_types=1);

namespace SPP\Core\Apps;

use Symfony\Component\Yaml\Yaml;

/**
 * Class ForeignAppRegistry
 *
 * Sovereign Registry & Catalog for multi-language applications hosted under SPP.
 * Manages configuration, route prefix resolution (longest-prefix match),
 * subdomain virtual routing, and ambient service declarations.
 *
 * @package SPP\Core\Apps
 * @author Satya Prakash Shukla
 */
class ForeignAppRegistry
{
    /** @var array<string, array<string, mixed>> */
    private static array $apps = [];

    private static bool $loaded = false;
    private static ?string $configFile = null;

    /**
     * Resets registry state (useful for test isolation).
     */
    public static function reset(): void
    {
        self::$apps = [];
        self::$loaded = false;
        self::$configFile = null;
    }

    /**
     * Loads application catalog from YAML configuration.
     */
    public static function loadConfig(?string $path = null): void
    {
        if ($path !== null) {
            self::$configFile = $path;
        } elseif (self::$configFile === null) {
            $baseDir = defined('SPP_BASE_DIR') ? SPP_BASE_DIR : dirname(__DIR__, 2);
            $appDir = defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname($baseDir);
            
            $candidates = [
                $appDir . '/etc/apps.yml',
                $baseDir . '/etc/apps.yml',
                $appDir . '/etc/apps.yaml',
            ];
            foreach ($candidates as $cand) {
                if (file_exists($cand)) {
                    self::$configFile = $cand;
                    break;
                }
            }
            if (self::$configFile === null) {
                self::$configFile = $appDir . '/etc/apps.yml';
            }
        }

        self::$apps = [];
        if (file_exists(self::$configFile)) {
            $data = Yaml::parseFile(self::$configFile);
            if (is_array($data) && isset($data['apps']) && is_array($data['apps'])) {
                foreach ($data['apps'] as $id => $app) {
                    if (is_array($app)) {
                        self::$apps[(string)$id] = self::normalizeAppConfig((string)$id, $app);
                    }
                }
            }
        }

        self::$loaded = true;
    }

    /**
     * Checks if a filesystem path belongs to a forbidden system directory.
     */
    public static function isForbiddenSystemPath(string $path): bool
    {
        $real = realpath($path);
        $norm = strtolower(str_replace('\\', '/', $real ?: $path));
        $norm = rtrim($norm, '/');

        // Check root drives
        if ($norm === '' || $norm === '/' || preg_match('/^[a-z]:$/', $norm)) {
            return true;
        }

        // Block system root directories
        $forbiddenPrefixes = [
            '/etc', '/var', '/bin', '/sbin', '/usr', '/boot', '/dev', '/proc', '/sys', '/root',
            'c:/windows', 'c:/program files', 'c:/program files (x86)', 'c:/winnt', 'c:/recovery',
            'c:/etc', 'c:/var', 'c:/bin'
        ];

        $normNoDrive = preg_replace('/^[a-z]:/i', '', $norm);

        foreach ($forbiddenPrefixes as $prefix) {
            if ($norm === $prefix || str_starts_with($norm, $prefix . '/')
                || $normNoDrive === $prefix || str_starts_with($normNoDrive, $prefix . '/')) {
                return true;
            }
        }

        // Block SPP framework core directory itself from being mounted as a foreign app docroot
        $coreDir = defined('SPP_BASE_DIR') ? SPP_BASE_DIR : dirname(__DIR__, 2);
        $realCore = realpath($coreDir);
        if ($realCore) {
            $normCore = strtolower(rtrim(str_replace('\\', '/', $realCore), '/'));
            if ($norm === $normCore) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validates that an incoming subpath remains strictly contained within the application docroot.
     * Prevents directory traversal, null-byte injection, and symlink escapes.
     *
     * @param string $docroot
     * @param string $subpath
     * @return string|null Resolved contained path, or null if traversal/escape detected
     */
    public static function verifySubpathContainment(string $docroot, string $subpath): ?string
    {
        if (str_contains($subpath, "\0") || str_contains($subpath, '..')) {
            return null;
        }

        $realDocroot = realpath($docroot);
        if ($realDocroot === false || !is_dir($realDocroot)) {
            return null;
        }

        $normDocroot = rtrim(str_replace('\\', '/', $realDocroot), '/');
        $cleanSubpath = ltrim(str_replace('\\', '/', $subpath), '/');
        $combined = $normDocroot . '/' . $cleanSubpath;

        // If target file or directory exists, verify its realpath is contained
        if (file_exists($combined)) {
            $realTarget = realpath($combined);
            if ($realTarget === false) {
                return null;
            }
            $normTarget = rtrim(str_replace('\\', '/', $realTarget), '/');
            if ($normTarget !== $normDocroot && !str_starts_with($normTarget, $normDocroot . '/')) {
                return null; // Symlink escaped docroot!
            }
            return $realTarget;
        }

        // Non-existent target: verify parent directory containment
        $parent = dirname($combined);
        $realParent = realpath($parent);
        if ($realParent !== false) {
            $normParent = rtrim(str_replace('\\', '/', $realParent), '/');
            if ($normParent !== $normDocroot && !str_starts_with($normParent, $normDocroot . '/')) {
                return null;
            }
        }

        return $combined;
    }

    /**
     * Validates application configuration.
     *
     * @param string $id
     * @param array<string, mixed> $raw
     * @return array<string> List of validation errors
     */
    public static function validateAppConfig(string $id, array $raw): array
    {
        $errors = [];
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $id)) {
            $errors[] = "Application identifier '{$id}' contains invalid characters (allowed: alphanumeric, _, -).";
        }

        if (isset($raw['working_dir'])) {
            $wDir = (string)$raw['working_dir'];
            if (str_contains($wDir, '..') || str_contains($wDir, "\0")) {
                $errors[] = "Application '{$id}' contains unsafe directory traversal in working_dir.";
            } elseif (self::isForbiddenSystemPath($wDir)) {
                $errors[] = "Application '{$id}' cannot use system directory '{$wDir}' as working_dir.";
            }
        }

        if (isset($raw['docroot'])) {
            $dRoot = (string)$raw['docroot'];
            if (str_contains($dRoot, '..') || str_contains($dRoot, "\0")) {
                $errors[] = "Application '{$id}' contains unsafe directory traversal in docroot.";
            } elseif (self::isForbiddenSystemPath($dRoot)) {
                $errors[] = "Application '{$id}' cannot use system directory '{$dRoot}' as docroot.";
            }
        }

        return $errors;
    }

    /**
     * Normalizes application configuration.
     *
     * @param string $id
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    public static function normalizeAppConfig(string $id, array $raw): array
    {
        $route = isset($raw['route']) ? '/' . trim((string)$raw['route'], '/') : '/' . $id;
        // Normalize any internal multiple slashes
        $route = preg_replace('#/+#', '/', $route);
        if ($route === '/') {
            // Keep root route for base apps
            $route = '/';
        }

        $type = strtolower((string)($raw['type'] ?? 'node'));
        $enabled = !isset($raw['enabled']) || (bool)$raw['enabled'];

        // Safe path sanitization - prevent escaping root via ../
        $workingDir = isset($raw['working_dir']) ? (string)$raw['working_dir'] : null;
        if ($workingDir !== null && (str_contains($workingDir, '..') || str_contains($workingDir, "\0"))) {
            $workingDir = str_replace(['..', "\0"], '', $workingDir);
        }

        $docroot = isset($raw['docroot']) ? (string)$raw['docroot'] : null;
        if ($docroot !== null && (str_contains($docroot, '..') || str_contains($docroot, "\0"))) {
            $docroot = str_replace(['..', "\0"], '', $docroot);
        }

        return [
            'id'             => $id,
            'name'           => (string)($raw['name'] ?? ucfirst($id)),
            'type'           => $type, // spp | php-fpm | node | python | go | rust | ruby | static
            'route'          => $route,
            'subdomain'      => isset($raw['subdomain']) && $raw['subdomain'] !== '' ? strtolower(trim((string)$raw['subdomain'])) : null,
            'domain'         => isset($raw['domain']) && $raw['domain'] !== '' ? strtolower(trim((string)$raw['domain'])) : null,
            'docroot'        => $docroot,
            'entrypoint'     => isset($raw['entrypoint']) ? (string)$raw['entrypoint'] : 'index.php',
            'working_dir'    => $workingDir,
            'command'        => isset($raw['command']) ? (string)$raw['command'] : null,
            'port'           => $raw['port'] ?? 'auto',
            'healthcheck'    => isset($raw['healthcheck']) ? (string)$raw['healthcheck'] : null,
            'websocket'      => !empty($raw['websocket']),
            'enabled'        => $enabled,
            'env'            => is_array($raw['env'] ?? null) ? $raw['env'] : [],
            'runtime'        => is_array($raw['runtime'] ?? null) ? $raw['runtime'] : [],
            'virtualization' => [
                'rewrite_redirects' => !isset($raw['virtualization']['rewrite_redirects']) || (bool)$raw['virtualization']['rewrite_redirects'],
                'rewrite_html_base' => !isset($raw['virtualization']['rewrite_html_base']) || (bool)$raw['virtualization']['rewrite_html_base'],
                'cookie_path'       => $raw['virtualization']['cookie_path'] ?? ($route !== '/' ? $route : '/'),
            ],
            'spp_integration'=> [
                'ambient_sensing' => !isset($raw['spp_integration']['ambient_sensing']) || (bool)$raw['spp_integration']['ambient_sensing'],
                'service_mesh'    => !empty($raw['spp_integration']['service_mesh']),
                'sso_bridge'      => !empty($raw['spp_integration']['sso_bridge']),
                'expose_services' => is_array($raw['spp_integration']['expose_services'] ?? null) ? $raw['spp_integration']['expose_services'] : [],
            ],
        ];
    }

    /**
     * Returns all registered applications.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getAllApps(): array
    {
        if (!self::$loaded) {
            self::loadConfig();
        }
        return self::$apps;
    }

    /**
     * Gets a specific application by ID.
     */
    public static function getApp(string $id): ?array
    {
        $apps = self::getAllApps();
        return $apps[$id] ?? null;
    }

    /**
     * Checks if a request matches any non-SPP foreign application.
     */
    public static function matchesRoute(string $path, ?string $host = null): bool
    {
        $resolved = self::resolveApp($path, $host);
        return $resolved !== null && $resolved['app']['type'] !== 'spp';
    }

    /**
     * Resolves incoming HTTP request against registered applications.
     * Evaluates subdomains first, followed by longest route prefix matching.
     *
     * @param string $path Clean URI path without query string (e.g. 'ai/api/predict' or '/blog/post-1')
     * @param string|null $host Optional HTTP Host header (e.g. 'ai.myserver.com' or 'localhost')
     * @return array{app: array<string, mixed>, app_id: string, match_type: string, matched_route: string, sub_path: string}|null
     */
    public static function resolveApp(string $path, ?string $host = null): ?array
    {
        $apps = self::getAllApps();
        $normalizedPath = preg_replace('#/+#', '/', '/' . ltrim($path, '/'));

        // 1. Check Subdomain / Exact Domain Matching
        if ($host !== null && $host !== '') {
            $hostParts = explode(':', $host)[0]; // strip port
            $hostLower = strtolower($hostParts);

            foreach ($apps as $id => $app) {
                if (!$app['enabled']) {
                    continue;
                }

                // Match exact domain if configured (e.g. 'blog.mysite.com')
                if (!empty($app['domain']) && $app['domain'] === $hostLower) {
                    return [
                        'app'           => $app,
                        'app_id'        => $id,
                        'match_type'    => 'domain',
                        'matched_route' => $app['domain'],
                        'sub_path'      => $normalizedPath,
                    ];
                }

                // Match subdomain if configured (e.g. subdomain 'ai' matches 'ai.mysite.com' or 'ai.localhost')
                if (!empty($app['subdomain'])) {
                    $prefix = $app['subdomain'] . '.';
                    if (str_starts_with($hostLower, $prefix)) {
                        return [
                            'app'           => $app,
                            'app_id'        => $id,
                            'match_type'    => 'subdomain',
                            'matched_route' => $app['subdomain'],
                            'sub_path'      => $normalizedPath,
                        ];
                    }
                }
            }
        }

        // 2. Check Route Directory Matching (Longest Prefix Match)
        $candidates = [];
        foreach ($apps as $id => $app) {
            if (!$app['enabled']) {
                continue;
            }
            $route = $app['route'];
            if ($route === '/') {
                continue; // Root route is reserved as fallback
            }

            // Check if normalized path starts with route
            if ($normalizedPath === $route || str_starts_with($normalizedPath, $route . '/')) {
                $candidates[] = [
                    'app'           => $app,
                    'app_id'        => $id,
                    'route_length'  => strlen($route),
                    'matched_route' => $route,
                    'sub_path'      => substr($normalizedPath, strlen($route)) ?: '/',
                ];
            }
        }

        if (!empty($candidates)) {
            // Sort by longest route length descending
            usort($candidates, fn($a, $b) => $b['route_length'] <=> $a['route_length']);
            $best = $candidates[0];
            return [
                'app'           => $best['app'],
                'app_id'        => $best['app_id'],
                'match_type'    => 'route',
                'matched_route' => $best['matched_route'],
                'sub_path'      => $best['sub_path'] === '' ? '/' : $best['sub_path'],
            ];
        }

        return null;
    }

    /**
     * Registers or updates an application.
     *
     * @param string $id
     * @param array<string, mixed> $config
     * @param bool $persist
     */
    public static function registerApp(string $id, array $config, bool $persist = true): void
    {
        if (!self::$loaded) {
            self::loadConfig();
        }

        self::$apps[$id] = self::normalizeAppConfig($id, $config);

        if ($persist) {
            self::saveConfig();
        }
    }

    /**
     * Unregisters an application.
     */
    public static function unregisterApp(string $id, bool $persist = true): bool
    {
        if (!self::$loaded) {
            self::loadConfig();
        }

        if (isset(self::$apps[$id])) {
            unset(self::$apps[$id]);
            if ($persist) {
                self::saveConfig();
            }
            return true;
        }

        return false;
    }

    /**
     * Persists registered applications back to YAML.
     */
    public static function saveConfig(): void
    {
        if (self::$configFile === null) {
            return;
        }

        $dir = dirname(self::$configFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $export = [
            'version' => '2.0',
            'apps'    => []
        ];

        foreach (self::$apps as $id => $app) {
            // Clean up normalized data for export
            $copy = $app;
            unset($copy['id']);
            $export['apps'][$id] = $copy;
        }

        file_put_contents(self::$configFile, Yaml::dump($export, 6, 2));
    }

    /**
     * Lists all services exposed across all registered applications.
     *
     * @return array<array<string, mixed>>
     */
    public static function listExposedServices(): array
    {
        $apps = self::getAllApps();
        $services = [];

        foreach ($apps as $id => $app) {
            if (!$app['enabled']) continue;
            $exposed = $app['spp_integration']['expose_services'] ?? [];
            foreach ($exposed as $svc) {
                if (is_array($svc) && !empty($svc['name'])) {
                    $services[] = [
                        'app_id'      => $id,
                        'app_name'    => $app['name'],
                        'service'     => $svc['name'],
                        'endpoint'    => $svc['endpoint'] ?? '/',
                        'method'      => strtoupper($svc['method'] ?? 'POST'),
                        'description' => $svc['description'] ?? '',
                    ];
                }
            }
        }

        return $services;
    }
}
