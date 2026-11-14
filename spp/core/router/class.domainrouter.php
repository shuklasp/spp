<?php

namespace SPP\Core\Router;

use Symfony\Component\Yaml\Yaml;

if (!class_exists(Yaml::class)) {
    $vendorAutoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
    if (!file_exists($vendorAutoload)) {
        $vendorAutoload = dirname(__DIR__, 3) . '/vendor/autoload.php';
    }
    if (file_exists($vendorAutoload)) {
        require_once $vendorAutoload;
    }
}

/**
 * Class DomainRouter
 *
 * Provides multi-tenant virtual host and domain routing for the SPP framework.
 * Maps incoming HTTP Host headers (or custom domain patterns) to application contexts,
 * allowing single-engine multi-tenancy, per-domain default applications served at root,
 * per-domain application isolation / access controls, domain aliases, canonical 301
 * redirection, HTTPS enforcement, SEO prefix normalization, and domain-scoped settings.
 *
 * Supports:
 * - Default application served at root without route prefix.
 * - Multi-domain mapping: multiple domains pointing to the same SPP root resolve distinct default apps.
 * - Per-domain app permissions: enable or disable secondary apps per domain.
 * - Exact host matching ('school.example.com') and wildcard patterns ('*.school.org').
 * - Domain aliases ('www.school.org' -> 'school.org') with 301 canonical host redirection.
 * - Automatic HTTPS redirection (force_https: true).
 * - SEO 301 prefix normalization (/school/students -> /students on default domain).
 * - Domain-scoped settings dictionary (site_name, theme, logo, contact, etc.).
 * - Seamless backward compatibility for legacy string mappings.
 *
 * @package SPP\Core\Router
 * @author Satya Prakash Shukla
 */
class DomainRouter
{
    // Operational Domain Architecture Modes
    public const DOMAIN_MODE_SINGLE = 'single';
    public const DOMAIN_MODE_MULTI = 'multi';

    public const DOMAIN_MODES = [
        self::DOMAIN_MODE_SINGLE,
        self::DOMAIN_MODE_MULTI,
    ];

    // Operational Virtual Host / Subdomain Modes
    public const MODE_STRICT = 'strict';
    public const MODE_INHERIT = 'inherit';
    public const MODE_REDIRECT = 'redirect';
    public const MODE_DYNAMIC_TENANT = 'dynamic_tenant';
    public const MODE_PERMISSIVE = 'permissive';

    public const MODES = [
        self::MODE_STRICT,
        self::MODE_INHERIT,
        self::MODE_REDIRECT,
        self::MODE_DYNAMIC_TENANT,
        self::MODE_PERMISSIVE,
    ];

    /** @var string Global domain operational mode ('single' or 'multi') */
    private static string $domainMode = self::DOMAIN_MODE_SINGLE;

    /** @var bool Whether the domain mode was explicitly configured */
    private static bool $explicitModeSet = false;

    /** @var array<string,array> Normalized domain pattern to config mapping */
    private static array $domains = [];

    /** @var string|null Global default application when host is unmapped */
    private static ?string $globalDefaultApp = null;

    /** @var string Global virtual host policy mode ('strict' is default) */
    private static string $virtualHostMode = self::MODE_STRICT;

    /** @var string Global unmapped subdomain policy */
    private static string $unmappedSubdomainPolicy = 'strict';

    /** @var string|null Custom 404 domain not found error view path */
    private static ?string $domainNotFoundView = null;

    /** @var array<string> Allowed dev/loopback hosts exempt from strict 404 rejection */
    private static array $allowedDevHosts = ['localhost', '127.0.0.1', '::1'];

    /** @var bool Whether domains config has been loaded from file */
    private static bool $loaded = false;

    /** @var string|null Path to the active domains.yml config file */
    private static ?string $activeConfigFile = null;

    /** @var string|null The host matched during the current request lifecycle */
    private static ?string $matchedHost = null;

    /** @var string|null The domain pattern key that matched the current request */
    private static ?string $matchedPattern = null;

    /** @var array|null The domain configuration matched for the current request */
    private static ?array $matchedDomainConfig = null;

    /** @var string|null The application context resolved from the domain */
    private static ?string $matchedContext = null;

    /** @var array<string, array{domain_only: bool, allowed_domains: array, installed_domain: ?string, is_domain_app: bool, src_path: string}> */
    private static array $domainOnlyApps = [];

    /**
     * Normalize raw domain mapping into a structured array schema.
     *
     * @param mixed $raw
     * @return array{type: string, account: string, subdomain_policy: ?string, default_app: string, enabled_apps: string[], disabled_apps: string[], allow_other_apps: bool, aliases: string[], redirect_to_canonical: bool, force_https: bool, redirect_default_app_prefix: bool, settings: array, error_view: ?string, canonical_host: ?string}
     */
    public static function normalizeDomainConfig(mixed $raw): array
    {
        if (is_string($raw)) {
            return [
                'type' => 'primary',
                'account' => 'default',
                'subdomain_policy' => null,
                'default_app' => trim($raw),
                'enabled_apps' => [],
                'disabled_apps' => [],
                'allow_other_apps' => true,
                'aliases' => [],
                'redirect_to_canonical' => false,
                'force_https' => false,
                'redirect_default_app_prefix' => true,
                'settings' => [],
                'error_view' => null,
                'canonical_host' => null,
            ];
        }

        if (is_array($raw)) {
            $type = isset($raw['type']) && in_array(strtolower(trim((string)$raw['type'])), ['primary', 'addon', 'subdomain', 'alias'], true)
                ? strtolower(trim((string)$raw['type']))
                : 'primary';

            $account = isset($raw['account']) && is_string($raw['account']) && trim($raw['account']) !== ''
                ? trim($raw['account'])
                : 'default';

            $subdomainPolicy = isset($raw['subdomain_policy']) && in_array(strtolower(trim((string)$raw['subdomain_policy'])), self::MODES, true)
                ? strtolower(trim((string)$raw['subdomain_policy']))
                : null;

            $defaultAppRaw = $raw['default_app'] ?? $raw['app'] ?? 'default';
            if (is_array($defaultAppRaw)) {
                $defaultApp = [
                    'type' => $defaultAppRaw['type'] ?? 'non_spp',
                    'app_name' => trim((string)($defaultAppRaw['app_name'] ?? $defaultAppRaw['name'] ?? 'custom_root_app')),
                    'target_path' => rtrim(str_replace('\\', '/', trim((string)($defaultAppRaw['target_path'] ?? ''))), '/'),
                    'index' => trim((string)($defaultAppRaw['index'] ?? 'index.php')),
                    'opt_in_sppdata' => !empty($defaultAppRaw['opt_in_sppdata']),
                ];
            } else {
                $defaultApp = trim((string)$defaultAppRaw);
                if ($defaultApp === '') {
                    $defaultApp = 'default';
                }
            }

            $appDir = isset($raw['app_dir']) && is_string($raw['app_dir']) && trim($raw['app_dir']) !== ''
                ? rtrim(str_replace('\\', '/', trim($raw['app_dir'])), '/')
                : null;

            $dataDir = isset($raw['data_dir']) && is_string($raw['data_dir']) && trim($raw['data_dir']) !== ''
                ? rtrim(str_replace('\\', '/', trim($raw['data_dir'])), '/')
                : null;

            $routingMode = isset($raw['routing_mode']) && in_array(strtolower(trim((string)$raw['routing_mode'])), ['spp_path', 'webserver_vhost'], true)
                ? strtolower(trim((string)$raw['routing_mode']))
                : 'spp_path';

            $subApps = [];
            if (!empty($raw['sub_apps']) && is_array($raw['sub_apps'])) {
                foreach ($raw['sub_apps'] as $rKey => $subCfg) {
                    $cleanRoute = '/' . trim((string)$rKey, '/');
                    if (is_array($subCfg)) {
                        $subApps[$cleanRoute] = [
                            'route' => $cleanRoute,
                            'type' => $subCfg['type'] ?? 'non_spp',
                            'app_name' => trim((string)($subCfg['app_name'] ?? basename($cleanRoute))),
                            'target_path' => rtrim(str_replace('\\', '/', trim((string)($subCfg['target_path'] ?? ''))), '/'),
                            'index' => trim((string)($subCfg['index'] ?? 'index.php')),
                            'opt_in_sppdata' => !empty($subCfg['opt_in_sppdata']),
                        ];
                    }
                }
            }

            $enabled = [];
            if (!empty($raw['enabled_apps']) && is_array($raw['enabled_apps'])) {
                $enabled = array_values(array_unique(array_filter(array_map('trim', array_map('strval', $raw['enabled_apps'])))));
            }

            $disabled = [];
            if (!empty($raw['disabled_apps']) && is_array($raw['disabled_apps'])) {
                $disabled = array_values(array_unique(array_filter(array_map('trim', array_map('strval', $raw['disabled_apps'])))));
            }

            $allowOthers = isset($raw['allow_other_apps']) ? (bool)$raw['allow_other_apps'] : true;

            $aliases = [];
            if (!empty($raw['aliases']) && is_array($raw['aliases'])) {
                $aliases = array_values(array_unique(array_filter(array_map('trim', array_map('strval', $raw['aliases'])))));
            } elseif (!empty($raw['alias']) && is_string($raw['alias'])) {
                $aliases = [trim($raw['alias'])];
            }

            $redirectToCanonical = isset($raw['redirect_to_canonical']) ? (bool)$raw['redirect_to_canonical'] : false;
            $forceHttps = isset($raw['force_https']) ? (bool)$raw['force_https'] : false;
            $redirectDefaultAppPrefix = isset($raw['redirect_default_app_prefix']) ? (bool)$raw['redirect_default_app_prefix'] : true;

            $settings = [];
            if (isset($raw['settings']) && is_array($raw['settings'])) {
                $settings = $raw['settings'];
            }

            $errorView = isset($raw['error_view']) && is_string($raw['error_view']) && trim($raw['error_view']) !== ''
                ? trim($raw['error_view'])
                : null;

            $canonicalHost = isset($raw['canonical_host']) && is_string($raw['canonical_host']) && trim($raw['canonical_host']) !== ''
                ? strtolower(trim($raw['canonical_host']))
                : null;

            $reverseProxy = null;
            if (isset($raw['reverse_proxy']) && is_array($raw['reverse_proxy'])) {
                $reverseProxy = [
                    'enabled' => !empty($raw['reverse_proxy']['enabled']),
                    'target' => trim((string)($raw['reverse_proxy']['target'] ?? '')),
                    'service_type' => in_array($raw['reverse_proxy']['service_type'] ?? '', ['node', 'python', 'go', 'dotnet', 'java', 'generic'], true)
                        ? $raw['reverse_proxy']['service_type']
                        : 'generic',
                    'timeout' => max(1, (int)($raw['reverse_proxy']['timeout'] ?? 30)),
                    'preserve_host' => !isset($raw['reverse_proxy']['preserve_host']) || !empty($raw['reverse_proxy']['preserve_host']),
                    'websocket' => !empty($raw['reverse_proxy']['websocket'])
                ];
            }

            return [
                'type' => $type,
                'account' => $account,
                'subdomain_policy' => $subdomainPolicy,
                'default_app' => $defaultApp,
                'enabled_apps' => $enabled,
                'disabled_apps' => $disabled,
                'allow_other_apps' => $allowOthers,
                'aliases' => $aliases,
                'redirect_to_canonical' => $redirectToCanonical,
                'force_https' => $forceHttps,
                'redirect_default_app_prefix' => $redirectDefaultAppPrefix,
                'settings' => $settings,
                'error_view' => $errorView,
                'canonical_host' => $canonicalHost,
                'reverse_proxy' => $reverseProxy,
                'app_dir' => $appDir,
                'data_dir' => $dataDir,
                'routing_mode' => $routingMode,
                'sub_apps' => $subApps,
            ];
        }

        return [
            'type' => 'primary',
            'account' => 'default',
            'subdomain_policy' => null,
            'default_app' => 'default',
            'enabled_apps' => [],
            'disabled_apps' => [],
            'allow_other_apps' => true,
            'aliases' => [],
            'redirect_to_canonical' => false,
            'force_https' => false,
            'redirect_default_app_prefix' => true,
            'settings' => [],
            'error_view' => null,
            'canonical_host' => null,
        ];
    }

    /**
     * Load domain configurations from etc/domains.yml.
     *
     * @param string|null $customPath Optional custom configuration path
     */
    public static function loadConfig(?string $customPath = null): void
    {
        if (self::$loaded && $customPath === null) {
            return;
        }

        // Check environment variable or PHP constant overrides
        if (defined('SPP_DOMAIN_MODE') && in_array(strtolower(trim((string)SPP_DOMAIN_MODE)), self::DOMAIN_MODES, true)) {
            self::$domainMode = strtolower(trim((string)SPP_DOMAIN_MODE));
            self::$explicitModeSet = true;
        } elseif (($envMode = getenv('SPP_DOMAIN_MODE')) !== false && in_array(strtolower(trim((string)$envMode)), self::DOMAIN_MODES, true)) {
            self::$domainMode = strtolower(trim((string)$envMode));
            self::$explicitModeSet = true;
        }

        $searchPaths = [];
        if ($customPath !== null) {
            self::$domains = [];
            self::$domainOnlyApps = [];
            self::$globalDefaultApp = null;
            self::$domainMode = self::DOMAIN_MODE_SINGLE;
            self::$explicitModeSet = false;
            self::$activeConfigFile = null;
            $searchPaths[] = $customPath;
        } else {
            if (defined('SPP_APP_DIR')) {
                $searchPaths[] = SPP_APP_DIR . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'domains.local.yml';
                $searchPaths[] = SPP_APP_DIR . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'domains.yml';
            }
            if (defined('SPP_BASE_DIR')) {
                $searchPaths[] = SPP_BASE_DIR . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'domains.local.yml';
                $searchPaths[] = SPP_BASE_DIR . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'domains.yml';
            }
            // Fallback discovery for CLI, unit tests, and standalone scripts
            $cwd = getcwd() ?: '.';
            $sppRoot = dirname(__DIR__, 2);
            $searchPaths[] = $cwd . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'domains.local.yml';
            $searchPaths[] = $cwd . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'domains.yml';
            $searchPaths[] = $sppRoot . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'domains.local.yml';
            $searchPaths[] = $sppRoot . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'domains.yml';
            if (dirname($sppRoot) !== $sppRoot) {
                $searchPaths[] = dirname($sppRoot) . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'domains.yml';
            }
            // Domain-scoped preserved configurations (e.g. domains/example.com/etc/domains.yml)
            $domConfigs = array_merge(
                glob($cwd . '/domains/*/etc/domains.yml') ?: [],
                glob($sppRoot . '/domains/*/etc/domains.yml') ?: []
            );
            foreach ($domConfigs as $dc) {
                $searchPaths[] = $dc;
            }
        }

        $configFound = false;
        foreach ($searchPaths as $file) {
            if (file_exists($file)) {
                $configFound = true;
                try {
                    $raw = @file_get_contents($file);
                    $parsed = $raw !== false ? (array)Yaml::parse($raw) : [];
                    if (is_array($parsed)) {
                        self::$activeConfigFile = $file;

                        if (!self::$explicitModeSet) {
                            $modeKey = $parsed['mode'] ?? $parsed['domain_mode'] ?? null;
                            if ($modeKey !== null && in_array(strtolower(trim((string)$modeKey)), self::DOMAIN_MODES, true)) {
                                self::$domainMode = strtolower(trim((string)$modeKey));
                                self::$explicitModeSet = true;
                            } elseif (!empty($parsed['domains']) && is_array($parsed['domains'])) {
                                self::$domainMode = self::DOMAIN_MODE_MULTI;
                            } else {
                                self::$domainMode = self::DOMAIN_MODE_SINGLE;
                            }
                        }

                        if (isset($parsed['default_app']) && is_string($parsed['default_app'])) {
                            self::$globalDefaultApp = trim($parsed['default_app']);
                        }

                        if (isset($parsed['virtual_host_mode']) && in_array(strtolower(trim((string)$parsed['virtual_host_mode'])), self::MODES, true)) {
                            self::$virtualHostMode = strtolower(trim((string)$parsed['virtual_host_mode']));
                        }

                        if (isset($parsed['unmapped_subdomain_policy']) && in_array(strtolower(trim((string)$parsed['unmapped_subdomain_policy'])), self::MODES, true)) {
                            self::$unmappedSubdomainPolicy = strtolower(trim((string)$parsed['unmapped_subdomain_policy']));
                        }

                        if (isset($parsed['domain_not_found_view']) && is_string($parsed['domain_not_found_view']) && trim($parsed['domain_not_found_view']) !== '') {
                            self::$domainNotFoundView = trim($parsed['domain_not_found_view']);
                        }

                        if (isset($parsed['allowed_dev_hosts']) && is_array($parsed['allowed_dev_hosts'])) {
                            self::$allowedDevHosts = array_values(array_unique(array_filter(array_map('trim', array_map('strval', $parsed['allowed_dev_hosts'])))));
                        }

                        if (!empty($parsed['domains']) && is_array($parsed['domains'])) {
                            foreach ($parsed['domains'] as $pattern => $rawConfig) {
                                self::$domains[strtolower(trim((string)$pattern))] = self::normalizeDomainConfig($rawConfig);
                            }
                        }
                        if (!empty($parsed['domain_only_apps']) && is_array($parsed['domain_only_apps'])) {
                            self::$domainOnlyApps = $parsed['domain_only_apps'];
                        }
                    }
                } catch (\Throwable $e) {
                    error_log("[DomainRouter] Failed to parse domains configuration ({$file}): " . $e->getMessage());
                }
                break;
            }
        }

        if (!$configFound && !self::$explicitModeSet) {
            // No etc/domains.yml found: automatically operate in Single Domain Mode.
            // Copy-pasting works out of the box with zero configuration on any host/IP.
            self::$domainMode = self::DOMAIN_MODE_SINGLE;
        }

        self::$loaded = true;
    }

    /**
     * Resolve an incoming host to its matching domain configuration.
     *
     * @param string|null $host Optional host string (defaults to $_SERVER['HTTP_HOST'])
     * @return array|null Normalized domain configuration or null if unmapped
     */
    public static function resolveDomain(?string $host = null): ?array
    {
        self::loadConfig();

        if ($host === null) {
            $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        }

        if ($host === '') {
            return null;
        }

        // Strip port numbers if present (e.g. school.localhost:8080 -> school.localhost)
        $cleanHost = strtolower(trim(explode(':', $host)[0]));

        // 1. Direct exact match check on primary pattern
        if (isset(self::$domains[$cleanHost])) {
            self::$matchedHost = $cleanHost;
            self::$matchedPattern = $cleanHost;
            self::$matchedDomainConfig = self::$domains[$cleanHost];
            self::$matchedContext = self::$domains[$cleanHost]['default_app'];
            return self::$matchedDomainConfig;
        }

        // 2. Wildcard pattern matching (e.g. *.school.org)
        foreach (self::$domains as $pattern => $config) {
            if (str_contains($pattern, '*')) {
                if (fnmatch($pattern, $cleanHost, FNM_CASEFOLD)) {
                    self::$matchedHost = $cleanHost;
                    self::$matchedPattern = $pattern;
                    self::$matchedDomainConfig = $config;
                    self::$matchedContext = $config['default_app'];
                    return $config;
                }
            }
        }

        // 3. Match against declared aliases (exact and wildcard)
        foreach (self::$domains as $pattern => $config) {
            if (!empty($config['aliases'])) {
                foreach ($config['aliases'] as $alias) {
                    $cleanAlias = strtolower(trim(explode(':', $alias)[0]));
                    if ($cleanAlias === $cleanHost) {
                        self::$matchedHost = $cleanHost;
                        self::$matchedPattern = $pattern;
                        self::$matchedDomainConfig = $config;
                        self::$matchedContext = $config['default_app'];
                        return $config;
                    }
                    if (str_contains($cleanAlias, '*') && fnmatch($cleanAlias, $cleanHost, FNM_CASEFOLD)) {
                        self::$matchedHost = $cleanHost;
                        self::$matchedPattern = $pattern;
                        self::$matchedDomainConfig = $config;
                        self::$matchedContext = $config['default_app'];
                        return $config;
                    }
                }
            }
        }

        // 4. Subdomain matching registered application (e.g. app.example.com -> app)
        $parts = explode('.', $cleanHost);
        if (count($parts) >= 2) {
            $candidateApp = $parts[0];
            if (class_exists('\\SPP\\App')) {
                $allApps = \SPP\App::getGlobalSettings('apps') ?: [];
                $matchedAppKey = null;
                foreach (array_keys($allApps) as $appKey) {
                    if (strcasecmp((string)$appKey, $candidateApp) === 0) {
                        $matchedAppKey = (string)$appKey;
                        break;
                    }
                }
                if ($matchedAppKey !== null) {
                    $parent = self::findParentDomain($cleanHost);
                    $allowed = true;
                    if ($parent !== null && isset(self::$domains[$parent])) {
                        $pConfig = self::$domains[$parent];
                        if (!empty($pConfig['enabled_apps']) && !in_array($matchedAppKey, $pConfig['enabled_apps'], true) && empty($pConfig['allow_other_apps'])) {
                            $allowed = false;
                        }
                    }
                    if ($allowed && self::isAppAllowed($matchedAppKey, $cleanHost)) {
                        $synthConfig = [
                            'type' => 'subdomain',
                            'account' => 'default',
                            'subdomain_policy' => null,
                            'default_app' => $matchedAppKey,
                            'enabled_apps' => [$matchedAppKey],
                            'disabled_apps' => [],
                            'allow_other_apps' => true,
                            'aliases' => [],
                            'redirect_to_canonical' => false,
                            'force_https' => false,
                            'redirect_default_app_prefix' => true,
                            'settings' => [],
                            'error_view' => null,
                            'canonical_host' => null,
                        ];
                        self::$matchedHost = $cleanHost;
                        self::$matchedPattern = $cleanHost;
                        self::$matchedDomainConfig = $synthConfig;
                        self::$matchedContext = $matchedAppKey;
                        return $synthConfig;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Get the canonical primary host for the resolved domain.
     *
     * @param string|null $host Optional host string
     * @return string Canonical host name (e.g. 'school.example.com')
     */
    public static function getCanonicalHost(?string $host = null): string
    {
        $domainConfig = self::resolveDomain($host);
        $cleanHost = $host ? strtolower(trim(explode(':', $host)[0])) : self::$matchedHost;

        if ($domainConfig !== null) {
            if (!empty($domainConfig['canonical_host'])) {
                return $domainConfig['canonical_host'];
            }
            if (self::$matchedPattern !== null && !str_contains(self::$matchedPattern, '*')) {
                return self::$matchedPattern;
            }
        }

        return $cleanHost ?: 'localhost';
    }

    /**
     * Check if the incoming host is an alias that should be 301 redirected to canonical.
     *
     * @param string|null $host Optional host string
     * @return string|null The canonical host to redirect to, or null if no redirect needed
     */
    public static function shouldRedirectToCanonical(?string $host = null): ?string
    {
        if (self::isSingleDomainMode()) {
            return null;
        }

        $domainConfig = self::resolveDomain($host);
        if ($domainConfig === null || empty($domainConfig['redirect_to_canonical'])) {
            return null;
        }

        $cleanHost = $host ? strtolower(trim(explode(':', $host)[0])) : self::$matchedHost;
        $canonical = self::getCanonicalHost($host);

        if ($cleanHost !== '' && strcasecmp($cleanHost, $canonical) !== 0) {
            return $canonical;
        }

        return null;
    }

    /**
     * Check if the current request should be redirected to HTTPS.
     *
     * @param string|null $host Optional host string
     * @return bool
     */
    public static function shouldRedirectHttps(?string $host = null): bool
    {
        if (self::isSingleDomainMode()) {
            return false;
        }

        $domainConfig = self::resolveDomain($host);
        if ($domainConfig === null || empty($domainConfig['force_https'])) {
            return false;
        }

        // Check if current connection is not HTTPS
        $isHttps = (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === '1'))
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
            || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

        return !$isHttps;
    }

    /**
     * Get a domain-specific configuration setting.
     *
     * @param string $key Setting key (e.g. 'site_name', 'theme', 'logo')
     * @param mixed $default Default value if setting is not defined
     * @param string|null $host Optional host string
     * @return mixed
     */
    public static function getSetting(string $key, mixed $default = null, ?string $host = null): mixed
    {
        $domainConfig = self::resolveDomain($host);
        if ($domainConfig !== null && isset($domainConfig['settings'][$key])) {
            return $domainConfig['settings'][$key];
        }

        return $default;
    }

    /**
     * Get all domain settings.
     *
     * @param string|null $host Optional host string
     * @return array<string,mixed>
     */
    public static function getAllSettings(?string $host = null): array
    {
        $domainConfig = self::resolveDomain($host);
        return $domainConfig['settings'] ?? [];
    }

    /**
     * Calculate SEO 301 redirection URI if accessing redundant default app prefix.
     * e.g. /school/students -> /students on a domain where school is default app.
     *
     * @param string $uri The incoming request URI
     * @param string|null $host Optional host string
     * @return string|null Target redirect URI (e.g. '/students' or '/') or null if no redirect needed
     */
    public static function getSeoRedirectUri(string $uri, ?string $host = null): ?string
    {
        if (self::isSingleDomainMode()) {
            return null;
        }

        $domainConfig = self::resolveDomain($host);
        if ($domainConfig === null || empty($domainConfig['redirect_default_app_prefix'])) {
            return null;
        }

        $defaultApp = $domainConfig['default_app'];
        if ($defaultApp === '') {
            return null;
        }

        $path = explode('?', $uri)[0];
        $query = parse_url($uri, PHP_URL_QUERY);

        // Normalize path
        $cleanPath = '/' . ltrim($path, '/');
        $prefix = '/' . strtolower($defaultApp);
        $cleanPathLower = strtolower($cleanPath);

        if ($cleanPathLower === $prefix || $cleanPathLower === $prefix . '/') {
            $target = '/';
            return $query ? $target . '?' . $query : $target;
        }

        if (str_starts_with($cleanPathLower, $prefix . '/')) {
            $target = substr($cleanPath, strlen($prefix));
            if ($target === '' || $target[0] !== '/') {
                $target = '/' . $target;
            }
            return $query ? $target . '?' . $query : $target;
        }

        return null;
    }

    /**
     * Get the default application for a specific domain or the global default.
     *
     * @param string|null $host Optional host string
     * @return string
     */
    public static function getDefaultApp(?string $host = null): string
    {
        self::loadConfig();

        if ($host === null || $host === '') {
            $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? null;
        }

        if ($host !== null && $host !== '') {
            $domainConfig = self::resolveDomain($host);
            if ($domainConfig !== null && !empty($domainConfig['default_app'])) {
                if (is_array($domainConfig['default_app'])) {
                    return (string)($domainConfig['default_app']['app_name'] ?? 'default');
                }
                return (string)$domainConfig['default_app'];
            }
        }

        if (self::isSingleDomainMode()) {
            if (self::$globalDefaultApp !== null && self::$globalDefaultApp !== '') {
                return self::$globalDefaultApp;
            }
            if (class_exists('\\SPP\\App')) {
                $baseApp = \SPP\App::getGlobalSettings('base_app');
                if ($baseApp) {
                    return $baseApp;
                }
            }
            return 'default';
        }

        if (self::$globalDefaultApp !== null && self::$globalDefaultApp !== '') {
            return self::$globalDefaultApp;
        }

        if (class_exists('\SPP\App')) {
            $baseApp = \SPP\App::getGlobalSettings('base_app');
            if ($baseApp) {
                return $baseApp;
            }
        }

        return 'default';
    }

    /**
     * Check if a given application is the designated default app for a host.
     *
     * @param string $appName Application name to check
     * @param string|null $host Optional host string
     * @return bool
     */
    public static function isDefaultApp(string $appName, ?string $host = null): bool
    {
        return strcasecmp(trim($appName), self::getDefaultApp($host)) === 0;
    }

    /**
     * Evaluate whether a specific application is allowed to be served on a domain.
     *
     * @param string $appName Application name to verify
     * @param string|null $host Optional host string
     * @return bool True if allowed, false if blocked
     */
    public static function isAppAllowed(string $appName, ?string $host = null, ?array $domainConfig = null): bool
    {
        self::loadConfig();
        if (self::isSingleDomainMode()) {
            return true;
        }

        $cleanApp = trim($appName);

        static $resolving = false;
        if ($domainConfig === null) {
            if ($resolving) {
                $domainConfig = null;
            } else {
                $resolving = true;
                try {
                    $domainConfig = self::resolveDomain($host);
                } finally {
                    $resolving = false;
                }
            }
        }

        if ($host === null) {
            $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        }
        $cleanHost = strtolower(trim(explode(':', (string)$host)[0]));

        // 1. Evaluate Domain-Only and Domain-Installed restrictions
        $restrictions = self::getAppRestrictions($cleanApp);
        if (!empty($restrictions['domain_only'])) {
            $allowed = false;
            foreach ($restrictions['allowed_domains'] as $allowedPattern) {
                $allowedPatternClean = strtolower(trim((string)$allowedPattern));
                if ($cleanHost !== '' && ($cleanHost === $allowedPatternClean || fnmatch($allowedPatternClean, $cleanHost, FNM_CASEFOLD))) {
                    $allowed = true;
                    break;
                }
                if (isset(self::$domains[$allowedPatternClean])) {
                    $domCfg = self::$domains[$allowedPatternClean];
                    if (!empty($domCfg['aliases'])) {
                        foreach ($domCfg['aliases'] as $alias) {
                            $cleanAlias = strtolower(trim(explode(':', (string)$alias)[0]));
                            if ($cleanHost === $cleanAlias || fnmatch($cleanAlias, $cleanHost, FNM_CASEFOLD)) {
                                $allowed = true;
                                break 2;
                            }
                        }
                    }
                }
            }
            if (!$allowed) {
                return false;
            }
        }

        // If domain is unmapped, allow access (domain-only restrictions were already validated above)
        if ($domainConfig === null) {
            return true;
        }

        // The default app for the domain is ALWAYS allowed
        if (strcasecmp($cleanApp, $domainConfig['default_app']) === 0) {
            return true;
        }

        // Check explicit disabled/denied list
        foreach ($domainConfig['disabled_apps'] as $disabled) {
            if (strcasecmp($cleanApp, $disabled) === 0) {
                return false;
            }
        }

        // Check enabled list if defined
        if (!empty($domainConfig['enabled_apps'])) {
            foreach ($domainConfig['enabled_apps'] as $enabled) {
                if (strcasecmp($cleanApp, $enabled) === 0) {
                    return true;
                }
            }
            // Not in explicit enabled list: respect allow_other_apps
            return $domainConfig['allow_other_apps'];
        }

        // If other apps are explicitly denied
        if (!$domainConfig['allow_other_apps']) {
            return false;
        }

        return true;
    }

    /**
     * Resolve an HTTP Host to its corresponding application context (legacy/backward compatibility).
     *
     * @param string|null $host Optional host string
     * @return string|null Resolved application context or null if unmapped
     */
    public static function resolve(?string $host = null): ?string
    {
        $domain = self::resolveDomain($host);
        return $domain ? $domain['default_app'] : null;
    }

    /**
     * Register or update a domain mapping programmatically at runtime.
     *
     * @param string $pattern Domain pattern (e.g. 'school.example.com' or '*.client.com')
     * @param array|string $config Target app name string or full structured config
     */
    public static function registerDomain(string $pattern, array|string $config): void
    {
        self::loadConfig();
        self::$domains[strtolower(trim($pattern))] = self::normalizeDomainConfig($config);
        if (self::$domainMode === self::DOMAIN_MODE_SINGLE && !self::$explicitModeSet) {
            self::$domainMode = self::DOMAIN_MODE_MULTI;
        }
    }

    /**
     * Alias for registerDomain.
     */
    public static function setDomain(string $pattern, array|string $config): void
    {
        self::registerDomain($pattern, $config);
    }

    /**
     * Remove a domain mapping.
     *
     * @param string $pattern Domain pattern to remove
     * @return bool True if removed, false if not found
     */
    public static function removeDomain(string $pattern): bool
    {
        self::loadConfig();
        $key = strtolower(trim($pattern));
        if (isset(self::$domains[$key])) {
            unset(self::$domains[$key]);
            return true;
        }
        return false;
    }

    /**
     * Set the global default application when no domain matches.
     *
     * @param string $appName
     */
    public static function setGlobalDefaultApp(string $appName): void
    {
        self::$globalDefaultApp = trim($appName);
    }

    /**
     * Get the global default application.
     *
     * @return string|null
     */
    public static function getGlobalDefaultApp(): ?string
    {
        return self::$globalDefaultApp;
    }

    /**
     * Check if the current request was resolved via domain virtual host mapping.
     */
    public static function isDomainMatched(): bool
    {
        return self::$matchedContext !== null;
    }

    /**
     * Get the matched host for the current request.
     */
    public static function getMatchedHost(): ?string
    {
        return self::$matchedHost;
    }

    /**
     * Get the pattern that matched the current request.
     */
    public static function getMatchedPattern(): ?string
    {
        return self::$matchedPattern;
    }

    /**
     * Get the matched domain configuration for the current request.
     */
    public static function getMatchedDomainConfig(): ?array
    {
        return self::$matchedDomainConfig;
    }

    /**
     * Get the resolved context for the current request.
     */
    public static function getMatchedContext(): ?string
    {
        return self::$matchedContext;
    }

    /**
     * Get the active domains configuration file path.
     */
    public static function getActiveConfigFile(): ?string
    {
        return self::$activeConfigFile;
    }

    /**
     * Return all registered domain mappings.
     *
     * @return array<string,array>
     */
    public static function getAllDomains(): array
    {
        self::loadConfig();
        return self::$domains;
    }

    /**
     * Persist current domain configurations back to etc/domains.yml.
     *
     * @param string|null $path Optional target file path
     * @return bool
     */
    public static function saveConfig(?string $path = null): bool
    {
        $targetFile = $path ?: (self::$activeConfigFile ?: (defined('SPP_APP_DIR') ? SPP_APP_DIR . '/etc/domains.yml' : null));
        if (!$targetFile) {
            return false;
        }

        $dir = dirname($targetFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $data = [];
        $data['mode'] = self::$domainMode;
        if (self::$globalDefaultApp !== null && self::$globalDefaultApp !== '') {
            $data['default_app'] = self::$globalDefaultApp;
        }
        $data['virtual_host_mode'] = self::$virtualHostMode;
        if (self::$unmappedSubdomainPolicy !== 'strict') {
            $data['unmapped_subdomain_policy'] = self::$unmappedSubdomainPolicy;
        }
        if (self::$domainNotFoundView !== null) {
            $data['domain_not_found_view'] = self::$domainNotFoundView;
        }
        if (self::$allowedDevHosts !== ['localhost', '127.0.0.1', '::1']) {
            $data['allowed_dev_hosts'] = self::$allowedDevHosts;
        }

        if (!empty(self::$domainOnlyApps)) {
            $data['domain_only_apps'] = self::$domainOnlyApps;
        }

        $data['domains'] = [];
        foreach (self::$domains as $pattern => $config) {
            $entry = [
                'type' => $config['type'] ?? 'primary',
                'account' => $config['account'] ?? 'default',
                'default_app' => $config['default_app'],
            ];

            if (!empty($config['subdomain_policy'])) {
                $entry['subdomain_policy'] = $config['subdomain_policy'];
            }
            if (!empty($config['aliases'])) {
                $entry['aliases'] = $config['aliases'];
            }
            if (!empty($config['redirect_to_canonical'])) {
                $entry['redirect_to_canonical'] = true;
            }
            if (!empty($config['force_https'])) {
                $entry['force_https'] = true;
            }
            if ($config['redirect_default_app_prefix'] === false) {
                $entry['redirect_default_app_prefix'] = false;
            }
            if (!empty($config['enabled_apps'])) {
                $entry['enabled_apps'] = $config['enabled_apps'];
            }
            if (!empty($config['disabled_apps'])) {
                $entry['disabled_apps'] = $config['disabled_apps'];
            }
            if ($config['allow_other_apps'] === false) {
                $entry['allow_other_apps'] = false;
            }
            if (!empty($config['settings'])) {
                $entry['settings'] = $config['settings'];
            }
            if (!empty($config['error_view'])) {
                $entry['error_view'] = $config['error_view'];
            }
            if (!empty($config['canonical_host'])) {
                $entry['canonical_host'] = $config['canonical_host'];
            }
            if (!empty($config['app_dir'])) {
                $entry['app_dir'] = $config['app_dir'];
            }
            if (!empty($config['data_dir'])) {
                $entry['data_dir'] = $config['data_dir'];
            }
            if (!empty($config['routing_mode'])) {
                $entry['routing_mode'] = $config['routing_mode'];
            }
            if (!empty($config['sub_apps'])) {
                $entry['sub_apps'] = $config['sub_apps'];
            }
            if (!empty($config['reverse_proxy'])) {
                $entry['reverse_proxy'] = $config['reverse_proxy'];
            }

            $data['domains'][$pattern] = $entry;
        }

        $yaml = "# SPP Virtual Host Domain Mapping Configuration\n";
        $yaml .= "# Generated by SPP DomainRouter\n\n";
        $yaml .= Yaml::dump($data, 4, 2);

        if (class_exists('\\SPP\\File')) {
            return \SPP\File::putAtomic($targetFile, $yaml);
        }
        return (bool)file_put_contents($targetFile, $yaml, LOCK_EX);
    }

    /**
     * Locate the registered parent domain pattern for an unmapped subdomain.
     * e.g. 'biology.school.example.com' -> 'school.example.com' or 'example.com'.
     *
     * @param string $host Subdomain hostname to inspect
     * @return string|null Matching registered parent domain key, or null
     */
    public static function findParentDomain(string $host): ?string
    {
        self::loadConfig();
        $cleanHost = strtolower(trim(explode(':', $host)[0]));
        $parts = explode('.', $cleanHost);

        if (count($parts) < 2) {
            return null;
        }

        // Check progressively shorter domain suffixes: sub.demo.example.com -> demo.example.com -> example.com
        for ($i = 1; $i < count($parts); $i++) {
            $candidate = implode('.', array_slice($parts, $i));
            if (isset(self::$domains[$candidate])) {
                return $candidate;
            }
            // Also check if any registered domain has this candidate as an alias
            foreach (self::$domains as $pattern => $config) {
                if (!empty($config['aliases'])) {
                    foreach ($config['aliases'] as $alias) {
                        $cleanAlias = strtolower(trim(explode(':', $alias)[0]));
                        if ($cleanAlias === $candidate) {
                            return $pattern;
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Determine the active policy for an unmapped subdomain or virtual host.
     *
     * Returns an associative array containing:
     * - 'policy': 'strict', 'inherit', 'redirect', 'dynamic_tenant', or 'permissive'
     * - 'parent_domain': string|null
     * - 'parent_config': array|null
     *
     * @param string $host
     * @return array{policy: string, parent_domain: ?string, parent_config: ?array}
     */
    public static function getUnmappedSubdomainPolicy(string $host): array
    {
        self::loadConfig();
        $cleanHost = strtolower(trim(explode(':', $host)[0]));
        $parent = self::findParentDomain($cleanHost);

        $parentConfig = null;
        $policy = self::$virtualHostMode; // default 'strict'

        if ($parent !== null && isset(self::$domains[$parent])) {
            $parentConfig = self::$domains[$parent];
            if (!empty($parentConfig['subdomain_policy'])) {
                $policy = strtolower(trim($parentConfig['subdomain_policy']));
            } elseif (self::$unmappedSubdomainPolicy !== 'strict') {
                $policy = self::$unmappedSubdomainPolicy;
            }
        }

        if (!in_array($policy, self::MODES, true)) {
            $policy = self::MODE_STRICT;
        }

        return [
            'policy' => $policy,
            'parent_domain' => $parent,
            'parent_config' => $parentConfig,
        ];
    }

    /**
     * Check if the host is a recognized local development/loopback address
     * exempt from strict domain 404 rejection.
     *
     * @param string|null $host Optional host string
     * @return bool
     */
    public static function isAllowedUnmappedDevHost(?string $host = null): bool
    {
        if (self::isSingleDomainMode()) {
            return true;
        }

        if ($host === null) {
            $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        }
        $raw = trim($host);
        if ($raw === '') {
            return false;
        }

        if (preg_match('/^\[([a-fA-F0-9:]+)\](?::[0-9]+)?$/', $raw, $m)) {
            $cleanHost = strtolower($m[1]);
        } elseif (substr_count($raw, ':') === 1) {
            $cleanHost = strtolower(explode(':', $raw)[0]);
        } else {
            $cleanHost = strtolower($raw);
        }

        if (in_array($cleanHost, self::$allowedDevHosts, true)) {
            return true;
        }

        // Allow localhost subdomains or test TLDs in dev: *.localhost, *.local, *.test
        if (str_ends_with($cleanHost, '.localhost') || str_ends_with($cleanHost, '.local') || str_ends_with($cleanHost, '.test')) {
            return true;
        }

        return false;
    }

    /**
     * Resolve the absolute path to the 404 domain not found error view template.
     *
     * @param string|null $host Optional host string
     * @return string|null Absolute path to the view file or null if not found
     */
    public static function getDomainNotFoundView(?string $host = null): ?string
    {
        if (self::$domainNotFoundView && file_exists(self::$domainNotFoundView)) {
            return self::$domainNotFoundView;
        }

        $candidates = [
            defined('SPP_BASE_DIR') ? SPP_BASE_DIR . '/views/errors/domain_not_found.php' : null,
            defined('SPP_APP_DIR') ? SPP_APP_DIR . '/spp/views/errors/domain_not_found.php' : null,
            dirname(__DIR__, 2) . '/views/errors/domain_not_found.php',
        ];

        foreach ($candidates as $file) {
            if ($file && file_exists($file)) {
                return $file;
            }
        }

        return null;
    }

    /**
     * Set custom domain not found error view path.
     */
    public static function setDomainNotFoundView(?string $viewPath): void
    {
        self::$domainNotFoundView = $viewPath;
    }

    /**
     * Check if SPP is operating in Single Domain Mode.
     * In Single Domain Mode, domain routing is not required and not enforced.
     * Copy-pasting the installation to any host/directory works immediately.
     */
    public static function isSingleDomainMode(): bool
    {
        self::loadConfig();
        return self::$domainMode === self::DOMAIN_MODE_SINGLE;
    }

    /**
     * Check if SPP is operating in Multi Domain Mode.
     * In Multi Domain Mode, virtual host routing and domain-level scoping are enforced.
     */
    public static function isMultiDomainMode(): bool
    {
        self::loadConfig();
        return self::$domainMode === self::DOMAIN_MODE_MULTI;
    }

    /**
     * Get the active domain mode ('single' or 'multi').
     */
    public static function getDomainMode(): string
    {
        self::loadConfig();
        return self::$domainMode;
    }

    /**
     * Set the active domain mode ('single' or 'multi').
     */
    public static function setDomainMode(string $mode): void
    {
        self::loadConfig();
        $clean = strtolower(trim($mode));
        if (in_array($clean, self::DOMAIN_MODES, true)) {
            self::$domainMode = $clean;
            self::$explicitModeSet = true;
        }
    }

    /**
     * Check if domain routing enforcement is active.
     */
    public static function isDomainRoutingEnforced(): bool
    {
        return self::isMultiDomainMode();
    }

    /**
     * Get the active virtual host mode.
     */
    public static function getVirtualHostMode(): string
    {
        self::loadConfig();
        return self::$virtualHostMode;
    }

    /**
     * Set the active virtual host mode ('strict', 'inherit', 'redirect', 'dynamic_tenant', 'permissive').
     */
    public static function setVirtualHostMode(string $mode): void
    {
        self::loadConfig();
        $clean = strtolower(trim($mode));
        if (in_array($clean, self::MODES, true)) {
            self::$virtualHostMode = $clean;
        }
    }

    /**
     * Check if strict mode is active.
     */
    public static function isStrictDomainsEnabled(): bool
    {
        if (self::isSingleDomainMode()) {
            return false;
        }
        return self::getVirtualHostMode() === self::MODE_STRICT;
    }

    /**
     * Get unmapped subdomain policy fallback setting.
     */
    public static function getUnmappedSubdomainPolicySetting(): string
    {
        self::loadConfig();
        return self::$unmappedSubdomainPolicy;
    }

    /**
     * Set unmapped subdomain policy fallback setting.
     */
    public static function setUnmappedSubdomainPolicySetting(string $policy): void
    {
        self::loadConfig();
        $clean = strtolower(trim($policy));
        if (in_array($clean, self::MODES, true)) {
            self::$unmappedSubdomainPolicy = $clean;
        }
    }

    /**
     * Get allowed dev hosts list.
     *
     * @return array<string>
     */
    public static function getAllowedDevHosts(): array
    {
        return self::$allowedDevHosts;
    }

    /**
     * Set allowed dev hosts list.
     *
     * @param array<string> $hosts
     */
    public static function setAllowedDevHosts(array $hosts): void
    {
        self::$allowedDevHosts = array_values(array_unique(array_filter(array_map('trim', array_map('strval', $hosts)))));
    }

    /**
     * Register or update an Addon Domain for a specific account.
     *
     * @param string $domain The addon domain hostname (e.g. 'portal.partner.org')
     * @param string $defaultApp Target application context
     * @param string|null $account The hosting account ID (defaults to 'default')
     * @param array $options Additional configuration options (aliases, force_https, etc.)
     */
    public static function registerAddonDomain(string $domain, string $defaultApp, ?string $account = 'default', array $options = []): void
    {
        self::loadConfig();
        $key = strtolower(trim($domain));
        $config = array_merge($options, [
            'type' => 'addon',
            'account' => trim($account ?: 'default'),
            'default_app' => trim($defaultApp),
        ]);
        self::$domains[$key] = self::normalizeDomainConfig($config);
    }

    /**
     * Remove an addon domain mapping.
     *
     * @param string $domain Addon domain to remove
     * @return bool True if removed, false if not found
     */
    public static function removeAddonDomain(string $domain): bool
    {
        self::loadConfig();
        $key = strtolower(trim($domain));
        if (isset(self::$domains[$key]) && (self::$domains[$key]['type'] ?? '') === 'addon') {
            unset(self::$domains[$key]);
            return true;
        }
        return false;
    }

    /**
     * Retrieve all registered Addon Domains, optionally filtered by account.
     *
     * @param string|null $account Optional account filter
     * @return array<string,array> Addon domains indexed by pattern
     */
    public static function getAddonDomains(?string $account = null): array
    {
        self::loadConfig();
        $addons = [];
        foreach (self::$domains as $pattern => $config) {
            if (($config['type'] ?? '') === 'addon') {
                if ($account === null || strcasecmp($config['account'] ?? '', $account) === 0) {
                    $addons[$pattern] = $config;
                }
            }
        }
        return $addons;
    }

    /**
     * Check if a specific host is registered as an Addon Domain.
     *
     * @param string $host Hostname to check
     * @return bool
     */
    public static function isAddonDomain(string $host): bool
    {
        $domainConfig = self::resolveDomain($host);
        return $domainConfig !== null && ($domainConfig['type'] ?? '') === 'addon';
    }

    /**
     * Retrieve all registered Primary Domains, optionally filtered by account.
     *
     * @param string|null $account Optional account filter
     * @return array<string,array>
     */
    public static function getPrimaryDomains(?string $account = null): array
    {
        self::loadConfig();
        $primaries = [];
        foreach (self::$domains as $pattern => $config) {
            if (($config['type'] ?? 'primary') === 'primary') {
                if ($account === null || strcasecmp($config['account'] ?? '', $account) === 0) {
                    $primaries[$pattern] = $config;
                }
            }
        }
        return $primaries;
    }

    /**
     * Reset router state (primarily for testing and worker daemon isolation).
     */
    /**
     * Retrieve domain restrictions and physical installation metadata for an application.
     *
     * @param string $appName Application name
     * @return array{is_domain_app: bool, installed_domain: ?string, domain_only: bool, allowed_domains: string[], src_path: string}
     */
    public static function getAppRestrictions(string $appName): array
    {
        self::loadConfig();
        $cleanApp = trim($appName);

        if (isset(self::$domainOnlyApps[$cleanApp])) {
            $entry = self::$domainOnlyApps[$cleanApp];
            return [
                'is_domain_app' => !empty($entry['is_domain_app']),
                'installed_domain' => $entry['installed_domain'] ?? null,
                'domain_only' => !empty($entry['domain_only']),
                'allowed_domains' => array_values(array_unique(array_filter((array)($entry['allowed_domains'] ?? [])))),
                'src_path' => $entry['src_path'] ?? ''
            ];
        }

        $appConf = [];
        if (class_exists('\\SPP\\App')) {
            $appConf = \SPP\App::getAppConf('', $cleanApp) ?: [];
        }

        $isDomainApp = !empty($appConf['is_domain_app']);
        $installedDomain = $appConf['installed_domain'] ?? null;
        $domainOnly = !empty($appConf['domain_only']) || $isDomainApp;
        $allowedDomains = (array)($appConf['allowed_domains'] ?? []);

        if (!$isDomainApp) {
            $baseDir = defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 4);
            $domainsDir = $baseDir . DIRECTORY_SEPARATOR . 'domains';
            if (is_dir($domainsDir)) {
                foreach (@scandir($domainsDir) ?: [] as $dom) {
                    if ($dom === '.' || $dom === '..' || !is_dir($domainsDir . DIRECTORY_SEPARATOR . $dom)) continue;
                    $domAppPath = $domainsDir . DIRECTORY_SEPARATOR . $dom . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $cleanApp;
                    if (is_dir($domAppPath)) {
                        $isDomainApp = true;
                        $installedDomain = $dom;
                        $domainOnly = true;
                        if (!in_array($dom, $allowedDomains, true)) {
                            $allowedDomains[] = $dom;
                        }
                        break;
                    }
                }
            }
        }

        if ($installedDomain && !in_array($installedDomain, $allowedDomains, true)) {
            $allowedDomains[] = $installedDomain;
        }

        $srcPath = $appConf['src_path'] ?? ($isDomainApp ? "domains/{$installedDomain}/src/{$cleanApp}" : "src/{$cleanApp}");

        return [
            'is_domain_app' => $isDomainApp,
            'installed_domain' => $installedDomain,
            'domain_only' => $domainOnly,
            'allowed_domains' => array_values(array_unique(array_filter($allowedDomains))),
            'src_path' => $srcPath
        ];
    }

    /**
     * Configure domain scope for an application (marking it domain-only or global).
     */
    public static function setAppDomainScope(string $appName, bool $domainOnly, array $allowedDomains = []): bool
    {
        self::loadConfig();
        $cleanApp = trim($appName);
        $restrictions = self::getAppRestrictions($cleanApp);

        $allowed = array_values(array_unique(array_filter(array_map('trim', array_map('strval', $allowedDomains)))));
        if ($restrictions['installed_domain'] && !in_array($restrictions['installed_domain'], $allowed, true)) {
            $allowed[] = $restrictions['installed_domain'];
        }

        self::$domainOnlyApps[$cleanApp] = [
            'is_domain_app' => $restrictions['is_domain_app'],
            'installed_domain' => $restrictions['installed_domain'],
            'domain_only' => $domainOnly,
            'allowed_domains' => $allowed,
            'src_path' => $restrictions['src_path']
        ];

        // Also update app's etc/app.yml if available
        $baseDir = defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 4);
        $appYmlPath = $baseDir . DIRECTORY_SEPARATOR . $restrictions['src_path'] . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'app.yml';
        if (file_exists($appYmlPath) && class_exists('\\Symfony\\Component\\Yaml\\Yaml')) {
            try {
                $rawApp = @file_get_contents($appYmlPath);
                $parsed = $rawApp !== false ? (array)\Symfony\Component\Yaml\Yaml::parse($rawApp) : [];
                if (is_array($parsed)) {
                    $parsed['domain_only'] = $domainOnly;
                    $parsed['allowed_domains'] = $allowed;
                    file_put_contents($appYmlPath, \Symfony\Component\Yaml\Yaml::dump($parsed, 10, 2));
                }
            } catch (\Throwable $e) {}
        }

        self::saveConfig();
        self::clearConfigCaches();
        return true;
    }

    /**
     * Get configuration for a specific domain pattern.
     */
    public static function getDomain(string $pattern): ?array
    {
        self::loadConfig();
        $p = strtolower(trim($pattern));
        return self::$domains[$p] ?? null;
    }

    /**
     * Alias for getDomain() for backward compatibility.
     */
    public static function getDomainConfig(string $pattern): ?array
    {
        return self::getDomain($pattern);
    }

    /**
     * Resolves the user-centric sppdata directory for a domain and application context.
     * Hierarchy:
     * 1. Explicit domain 'data_dir' from etc/domains.yml (e.g. /home/user/sppdata) -> /home/user/sppdata/{domain}/{appName}/
     * 2. Auto-detected user home (/home/{account}/sppdata/{domain}/{appName}/) if account home exists
     * 3. Framework global sppdata (SPP_DATA_DIR/domains/{domain}/{appName}/)
     * 4. Fallback to SPP_DATA_DIR/global/{appName}/ or var/data
     */
    public static function resolveUserDataDir(?string $domain, string $appName, ?string $account = null): string
    {
        $cleanApp = trim($appName);
        if ($cleanApp === '') $cleanApp = 'default';

        if ($domain !== null && $domain !== '') {
            $cfg = self::getDomain($domain);
            if (!$cfg && class_exists('\SPP\Core\Router\DomainRouter')) {
                $cfg = self::resolveDomain($domain);
            }

            // 1. Explicit data_dir
            if (!empty($cfg['data_dir']) && is_dir($cfg['data_dir'])) {
                $dir = rtrim(str_replace('\\', '/', $cfg['data_dir']), '/') . '/' . $domain . '/' . $cleanApp;
                if (!is_dir($dir)) {
                    @mkdir($dir, 0777, true);
                    @chmod($dir, 0777);
                }
                return $dir;
            }

            // 2. Auto-detected /home/{account}/sppdata
            $acc = $account ?: ($cfg['account'] ?? null);
            if ($acc && $acc !== 'default') {
                $userHomeSppData = '/home/' . $acc . '/sppdata';
                if (is_dir('/home/' . $acc)) {
                    $dir = $userHomeSppData . '/' . $domain . '/' . $cleanApp;
                    if (!is_dir($dir)) {
                        @mkdir($dir, 0777, true);
                        @chmod($dir, 0777);
                    }
                    return $dir;
                }
            }

            // 3. Central SPP_DATA_DIR/domains/{domain}/{appName}
            $baseData = defined('SPP_DATA_DIR') ? SPP_DATA_DIR : (defined('SPP_APP_DIR') ? SPP_APP_DIR . '/var/data' : 'var/data');
            $dir = rtrim(str_replace('\\', '/', $baseData), '/') . '/domains/' . $domain . '/' . $cleanApp;
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
                @chmod($dir, 0777);
            }
            return $dir;
        }

        // 4. Global application fallback
        $baseData = defined('SPP_DATA_DIR') ? SPP_DATA_DIR : (defined('SPP_APP_DIR') ? SPP_APP_DIR . '/var/data' : 'var/data');
        $dir = rtrim(str_replace('\\', '/', $baseData), '/') . '/global/' . $cleanApp;
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
            @chmod($dir, 0777);
        }
        return $dir;
    }

    /**
     * Resolves the configured domain application directory (e.g. /home/user/apps).
     */
    public static function getDomainAppDir(string $domain): ?string
    {
        $cfg = self::getDomain($domain);
        if (!$cfg && class_exists('\SPP\Core\Router\DomainRouter')) {
            $cfg = self::resolveDomain($domain);
        }
        if (!empty($cfg['app_dir']) && is_dir($cfg['app_dir'])) {
            return rtrim(str_replace('\\', '/', $cfg['app_dir']), '/');
        }
        $acc = $cfg['account'] ?? null;
        if ($acc && $acc !== 'default' && is_dir('/home/' . $acc . '/apps')) {
            return '/home/' . $acc . '/apps';
        }
        return null;
    }

    /**
     * Finds any matching sub-route application (SPP or Non-SPP) for the incoming URI.
     * Evaluates registered sub_apps by longest prefix first.
     */
    public static function getSubAppForUri(string $uri, ?string $host = null): ?array
    {
        $cleanUri = '/' . ltrim(explode('?', $uri)[0], '/');
        $targetHost = $host ?: (self::$matchedHost ?: ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
        if ($targetHost === '') return null;

        $domainCfg = self::getDomain($targetHost);
        if (!$domainCfg) {
            $domainCfg = self::resolveDomain($targetHost);
        }
        if (!$domainCfg || empty($domainCfg['sub_apps']) || !is_array($domainCfg['sub_apps'])) {
            return null;
        }

        // Sort routes by length descending for longest-prefix match
        $subApps = $domainCfg['sub_apps'];
        uksort($subApps, fn($a, $b) => strlen($b) <=> strlen($a));

        foreach ($subApps as $route => $appMeta) {
            $routePrefix = '/' . trim($route, '/');
            if ($cleanUri === $routePrefix || str_starts_with($cleanUri, $routePrefix . '/')) {
                $res = $appMeta;
                $res['route_prefix'] = $routePrefix;
                $res['domain'] = $targetHost;
                return $res;
            }
        }

        return null;
    }

    /**
     * Retrieve all applications installed directly in domains/<domain>/src/ or assigned as domain-only.
     */
    public static function getInstalledDomainApps(?string $domain = null): array
    {
        $baseDir = defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 4);
        $domainsDir = $baseDir . DIRECTORY_SEPARATOR . 'domains';
        $apps = [];

        // Scan custom app_dir for domains if configured
        self::loadConfig();
        foreach (self::$domains as $domPattern => $dCfg) {
            if ($domain !== null && strcasecmp($domPattern, $domain) !== 0) continue;
            $customAppDir = $dCfg['app_dir'] ?? null;
            if (!$customAppDir && !empty($dCfg['account']) && $dCfg['account'] !== 'default' && is_dir('/home/' . $dCfg['account'] . '/apps')) {
                $customAppDir = '/home/' . $dCfg['account'] . '/apps';
            }
            if ($customAppDir && is_dir($customAppDir)) {
                foreach (@scandir($customAppDir) ?: [] as $appName) {
                    if ($appName === '.' || $appName === '..' || !is_dir($customAppDir . '/' . $appName)) continue;
                    $appYml = $customAppDir . '/' . $appName . '/etc/app.yml';
                    $meta = [];
                    if (file_exists($appYml) && class_exists('\Symfony\Component\Yaml\Yaml')) {
                        try {
                            $rawMeta = @file_get_contents($appYml);
                            $meta = $rawMeta !== false ? (array)\Symfony\Component\Yaml\Yaml::parse($rawMeta) : [];
                        } catch (\Throwable $e) {}
                    }
                    $apps[] = [
                        'name' => $appName,
                        'domain' => $domPattern,
                        'title' => $meta['title'] ?? $meta['name'] ?? ucfirst($appName),
                        'src_path' => $customAppDir . '/' . $appName,
                        'is_domain_app' => true,
                        'domain_only' => true,
                        'allowed_domains' => [$domPattern]
                    ];
                }
            }
        }

        if (is_dir($domainsDir)) {
            foreach (@scandir($domainsDir) ?: [] as $dom) {
                if ($dom === '.' || $dom === '..' || !is_dir($domainsDir . DIRECTORY_SEPARATOR . $dom)) continue;
                if ($domain !== null && strcasecmp($dom, $domain) !== 0) continue;
                $domSrc = $domainsDir . DIRECTORY_SEPARATOR . $dom . DIRECTORY_SEPARATOR . 'src';
                if (is_dir($domSrc)) {
                    foreach (@scandir($domSrc) ?: [] as $appName) {
                        if ($appName === '.' || $appName === '..' || !is_dir($domSrc . DIRECTORY_SEPARATOR . $appName)) continue;
                        $appYml = $domSrc . DIRECTORY_SEPARATOR . $appName . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'app.yml';
                        $meta = [];
                        if (file_exists($appYml) && class_exists('\\Symfony\\Component\\Yaml\\Yaml')) {
                            try {
                                $rawMeta = @file_get_contents($appYml);
                                $meta = $rawMeta !== false ? (array)\Symfony\Component\Yaml\Yaml::parse($rawMeta) : [];
                            } catch (\Throwable $e) {}
                        }
                        $apps[] = [
                            'name' => $appName,
                            'domain' => $dom,
                            'title' => $meta['title'] ?? $meta['name'] ?? ucfirst($appName),
                            'src_path' => "domains/{$dom}/src/{$appName}",
                            'is_domain_app' => true,
                            'domain_only' => true,
                            'allowed_domains' => [$dom]
                        ];
                    }
                }
            }
        }

        return $apps;
    }

    /**
     * Installs and scaffolds a domain application in domains/<domain>/src/<appName>.
     */
    public static function installDomainApp(string $domain, string $appName, array $options = []): array
    {
        $cleanDomain = strtolower(trim($domain));
        $cleanApp = trim($appName);

        if ($cleanDomain === '' || !preg_match('/^[a-z0-9_\-\.]+$/i', $cleanDomain)) {
            return ['success' => false, 'error' => 'Invalid domain name.'];
        }
        if ($cleanApp === '' || !preg_match('/^[a-zA-Z0-9_\-]+$/', $cleanApp)) {
            return ['success' => false, 'error' => 'Invalid application name.'];
        }

        $baseDir = defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 4);
        $targetDir = $baseDir . DIRECTORY_SEPARATOR . 'domains' . DIRECTORY_SEPARATOR . $cleanDomain . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $cleanApp;

        if (is_dir($targetDir)) {
            return ['success' => false, 'error' => "Application '{$cleanApp}' already exists in domains/{$cleanDomain}/src/"];
        }

        // 1. Create directory structure
        $subdirs = ['etc', 'Controllers', 'Models', 'resources/views/layouts', 'tests', 'migrations', 'var/cache', 'var/data', 'var/logs'];
        foreach ($subdirs as $sub) {
            @mkdir($targetDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $sub), 0775, true);
        }

        $title = trim((string)($options['title'] ?? $cleanApp));
        $desc = trim((string)($options['description'] ?? "Domain application for {$cleanDomain}"));

        // 2. etc/app.yml
        $appYmlContent = "name: {$cleanApp}\n" .
            "title: '" . addslashes($title) . "'\n" .
            "description: '" . addslashes($desc) . "'\n" .
            "domain: {$cleanDomain}\n" .
            "domain_only: true\n" .
            "allowed_domains:\n  - {$cleanDomain}\n" .
            "is_domain_app: true\n" .
            "src_path: domains/{$cleanDomain}/src/{$cleanApp}\n" .
            "etc_path: domains/{$cleanDomain}/src/{$cleanApp}/etc\n";
        file_put_contents($targetDir . '/etc/app.yml', $appYmlContent);

        // 3. etc/routes.yml
        $routesYml = "routes:\n" .
            "  - path: /\n" .
            "    methods: [GET]\n" .
            "    target: App\\{$cleanApp}\\Controllers\\HomeController@index\n" .
            "    name: {$cleanApp}.home\n" .
            "  - path: /status\n" .
            "    methods: [GET]\n" .
            "    target: App\\{$cleanApp}\\Controllers\\HomeController@status\n" .
            "    name: {$cleanApp}.status\n";
        file_put_contents($targetDir . '/etc/routes.yml', $routesYml);

        // 4. Controllers/HomeController.php
        $controllerCode = "<?php\ndeclare(strict_types=1);\n\nnamespace App\\{$cleanApp}\\Controllers;\n\nuse SPPMod\\SPPView\\ViewController;\nuse SPPMod\\SPPView\\Attributes\\Route;\n\nclass HomeController extends ViewController\n{\n    #[Route('/', methods: ['GET'], name: '{$cleanApp}.home')]\n    public function index(...\$args)\n    {\n        return \$this->render('home.blade.php', [\n            'appName' => '{$cleanApp}',\n            'domain' => '{$cleanDomain}',\n            'time' => date('Y-m-d H:i:s')\n        ]);\n    }\n\n    #[Route('/status', methods: ['GET'], name: '{$cleanApp}.status')]\n    public function status(...\$args)\n    {\n        return \$this->renderJson([\n            'status' => 'operational',\n            'app' => '{$cleanApp}',\n            'domain' => '{$cleanDomain}',\n            'time' => time()\n        ]);\n    }\n}\n";
        file_put_contents($targetDir . '/Controllers/HomeController.php', $controllerCode);

        // 5. resources/views/layouts/app.blade.php
        $layoutCode = "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n    <meta charset=\"utf-8\">\n    <title>{{ \$title ?? 'Domain App' }} - {{ \$domain }}</title>\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n    <link rel=\"stylesheet\" href=\"{{ APP_BASE_URI }}/sppadmin/css/admin.css\">\n</head>\n<body>\n    @yield('content')\n</body>\n</html>\n";
        file_put_contents($targetDir . '/resources/views/layouts/app.blade.php', $layoutCode);

        // 6. resources/views/home.blade.php
        $viewCode = "@extends('layouts/app.blade.php', ['title' => 'Home', 'domain' => \$domain])\n\n@section('content')\n<div style=\"font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 800px; margin: 40px auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 8px; background: #ffffff; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);\">\n    <div style=\"display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #e2e8f0; padding-bottom: 16px; margin-bottom: 20px;\">\n        <div>\n            <span style=\"background: #0284c7; color: #fff; font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 4px; text-transform: uppercase;\">Domain App</span>\n            <h1 style=\"color: #0f172a; margin: 8px 0 0 0; font-size: 24px;\">🌐 {{ \$appName }}</h1>\n        </div>\n        <span style=\"background: #ecfdf5; color: #059669; font-size: 12px; font-weight: 600; padding: 4px 10px; border-radius: 9999px;\">Active</span>\n    </div>\n    <p style=\"color: #475569; font-size: 15px; line-height: 1.6;\">\n        This application is installed specifically for domain <strong style=\"color: #0284c7;\">{{ \$domain }}</strong>.\n        It is strictly inaccessible from other hostnames.\n    </p>\n    <div style=\"background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 16px; margin: 20px 0; font-size: 13px; color: #334155;\">\n        <strong>Installation Path:</strong> <code>domains/{{ \$domain }}/src/{{ \$appName }}/</code>\n    </div>\n    <div style=\"font-size: 12px; color: #94a3b8; text-align: right;\">\n        SPP Multi-Tenant Domain Isolation • {{ \$time }}\n    </div>\n</div>\n@endsection\n";
        file_put_contents($targetDir . '/resources/views/home.blade.php', $viewCode);

        // 7. tests/test.HomeControllerTest.php
        $testCode = "<?php\ndeclare(strict_types=1);\n\nnamespace Tests\\{$cleanApp};\n\nuse SPPMod\\Parikshak\\SPPTestCase;\nuse SPP\\Core\\Router\\DomainRouter;\n\nclass HomeControllerTest extends SPPTestCase\n{\n    public function testDomainAppAccessAllowed(): void\n    {\n        \$allowed = DomainRouter::isAppAllowed('{$cleanApp}', '{$cleanDomain}');\n        \$this->assertTrue(\$allowed);\n    }\n\n    public function testDomainAppAccessDeniedOnOtherDomain(): void\n    {\n        \$allowed = DomainRouter::isAppAllowed('{$cleanApp}', 'unauthorized-domain.com');\n        \$this->assertFalse(\$allowed);\n    }\n}\n";
        file_put_contents($targetDir . '/tests/test.HomeControllerTest.php', $testCode);

        // 8. Register in domains.yml
        self::loadConfig();
        $makeDefault = !empty($options['make_default']) || !isset(self::$domains[$cleanDomain]);
        if (!isset(self::$domains[$cleanDomain])) {
            self::registerDomain($cleanDomain, [
                'default_app' => $cleanApp,
                'enabled_apps' => [$cleanApp],
                'allow_other_apps' => !empty($options['allow_other_apps'])
            ]);
        } else {
            if ($makeDefault) {
                self::$domains[$cleanDomain]['default_app'] = $cleanApp;
            }
            if (!in_array($cleanApp, self::$domains[$cleanDomain]['enabled_apps'], true)) {
                self::$domains[$cleanDomain]['enabled_apps'][] = $cleanApp;
            }
        }

        // Set domain scope in memory and config
        self::setAppDomainScope($cleanApp, true, [$cleanDomain]);

        return [
            'success' => true,
            'message' => "Application '{$cleanApp}' successfully installed on domain '{$cleanDomain}'!",
            'domain' => $cleanDomain,
            'app' => $cleanApp,
            'path' => "domains/{$cleanDomain}/src/{$cleanApp}",
            'make_default' => $makeDefault
        ];
    }

    /**
     * Unlinks or removes a domain application.
     */
    public static function removeDomainApp(string $domain, string $appName, bool $deleteFiles = false): bool
    {
        self::loadConfig();
        $cleanDomain = strtolower(trim($domain));
        $cleanApp = trim($appName);

        if (isset(self::$domains[$cleanDomain])) {
            self::$domains[$cleanDomain]['enabled_apps'] = array_values(array_filter(self::$domains[$cleanDomain]['enabled_apps'], function($a) use ($cleanApp) {
                return strcasecmp($a, $cleanApp) !== 0;
            }));
            if (strcasecmp(self::$domains[$cleanDomain]['default_app'], $cleanApp) === 0) {
                self::$domains[$cleanDomain]['default_app'] = 'default';
            }
        }

        if (isset(self::$domainOnlyApps[$cleanApp])) {
            unset(self::$domainOnlyApps[$cleanApp]);
        }

        if ($deleteFiles) {
            $baseDir = defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 4);
            $targetDir = $baseDir . DIRECTORY_SEPARATOR . 'domains' . DIRECTORY_SEPARATOR . $cleanDomain . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $cleanApp;
            if (is_dir($targetDir)) {
                $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($targetDir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
                foreach ($it as $item) {
                    $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
                }
                @rmdir($targetDir);
            }
        }

        self::saveConfig();
        self::clearConfigCaches();
        return true;
    }

    /**
     * Clear system and route caches.
     */
    public static function clearConfigCaches(): void
    {
        $baseDir = defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 4);
        $files = [
            $baseDir . '/var/cache/system/config.php',
            $baseDir . '/var/cache/system/classmap_win.php',
            $baseDir . '/var/cache/system/classmap_unix.php',
            $baseDir . '/var/cache/system/classmap.php',
            $baseDir . '/var/cache/routes_manifest.cache.php',
            $baseDir . '/var/cache/routes.json',
        ];
        foreach ($files as $f) {
            if (file_exists($f)) @unlink($f);
        }
        if (class_exists('\\SPP\\App') && method_exists('\\SPP\\App', 'resetGlobalSettings')) {
            \SPP\App::resetGlobalSettings();
        }
    }
    public static function reset(): void
    {
        self::$domains = [];
        self::$domainOnlyApps = [];
        self::$globalDefaultApp = null;
        self::$domainMode = self::DOMAIN_MODE_SINGLE;
        self::$explicitModeSet = false;
        self::$virtualHostMode = self::MODE_STRICT;
        self::$unmappedSubdomainPolicy = 'strict';
        self::$domainNotFoundView = null;
        self::$allowedDevHosts = ['localhost', '127.0.0.1', '::1'];
        self::$loaded = false;
        self::$activeConfigFile = null;
        self::$matchedHost = null;
        self::$matchedPattern = null;
        self::$matchedDomainConfig = null;
        self::$matchedContext = null;
    }
}

// Global namespace alias for seamless access
if (!class_exists('\SPP\DomainRouter', false)) {
    class_alias(\SPP\Core\Router\DomainRouter::class, '\SPP\DomainRouter');
}

