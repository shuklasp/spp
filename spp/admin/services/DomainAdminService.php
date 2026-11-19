<?php
declare(strict_types=1);

namespace SPP\Admin\Services;

use SPP\Core\Router\DomainRouter;
use SPP\Core\Infra;
use Symfony\Component\Yaml\Yaml;

if (!class_exists(DomainRouter::class)) {
    require_once dirname(__DIR__, 2) . '/core/router/class.domainrouter.php';
}
if (!class_exists(Infra::class)) {
    require_once dirname(__DIR__, 2) . '/core/class.infra.php';
}
require_once __DIR__ . '/DnsZoneManager.php';
require_once __DIR__ . '/HostProvisionService.php';
require_once __DIR__ . '/CloudflareBridge.php';
require_once dirname(__DIR__, 2) . '/core/Dns/DnsProviderInterface.php';
require_once dirname(__DIR__, 2) . '/core/Dns/DnsRecord.php';
require_once dirname(__DIR__, 2) . '/core/Dns/DnsRecordValidator.php';
require_once dirname(__DIR__, 2) . '/core/Dns/DnsCredentialVault.php';
require_once dirname(__DIR__, 2) . '/core/Dns/Providers/AbstractDnsProvider.php';
require_once dirname(__DIR__, 2) . '/core/Dns/Providers/Bind9Provider.php';
require_once dirname(__DIR__, 2) . '/core/Dns/Providers/CloudflareProvider.php';
require_once dirname(__DIR__, 2) . '/core/Dns/Providers/Route53Provider.php';
require_once dirname(__DIR__, 2) . '/core/Dns/Providers/DigitalOceanProvider.php';
require_once dirname(__DIR__, 2) . '/core/Dns/Providers/GoogleCloudDnsProvider.php';
require_once dirname(__DIR__, 2) . '/core/Dns/Providers/CustomDnsProvider.php';
require_once dirname(__DIR__, 2) . '/core/Dns/DnsProviderManager.php';
require_once dirname(__DIR__, 2) . '/core/Dns/DnsRegistrarInterface.php';
require_once dirname(__DIR__, 2) . '/core/Dns/Registrars/AbstractRegistrar.php';
require_once dirname(__DIR__, 2) . '/core/Dns/Registrars/WhoisRegistrar.php';
require_once dirname(__DIR__, 2) . '/core/Dns/Registrars/NamecheapRegistrar.php';
require_once dirname(__DIR__, 2) . '/core/Dns/Registrars/GoDaddyRegistrar.php';
require_once dirname(__DIR__, 2) . '/core/Dns/Registrars/CloudflareRegistrar.php';
require_once dirname(__DIR__, 2) . '/core/Dns/DnsRegistrarManager.php';
require_once dirname(__DIR__, 2) . '/core/Dns/DnsSyncService.php';
require_once dirname(__DIR__, 2) . '/core/Dns/DnsPropagationEngine.php';
require_once dirname(__DIR__, 2) . '/core/Dns/DnssecDiagnostics.php';
require_once dirname(__DIR__, 2) . '/core/Dns/DnsSslMailBridge.php';
require_once __DIR__ . '/StorageAdminService.php';
require_once dirname(__DIR__, 2) . '/core/server/drivers/WebserverDriverInterface.php';
require_once dirname(__DIR__, 2) . '/core/server/drivers/NginxDriver.php';
require_once dirname(__DIR__, 2) . '/core/server/drivers/ApacheDriver.php';
require_once dirname(__DIR__, 2) . '/core/server/drivers/HybridNginxApacheDriver.php';
require_once dirname(__DIR__, 2) . '/core/server/drivers/ApplicationRouterDriver.php';
require_once dirname(__DIR__, 2) . '/core/server/drivers/WebserverDriverResolver.php';

use SPP\Core\Server\Drivers\WebserverDriverResolver;

/**
 * Class DomainAdminService
 *
 * Backend management service for Multi-Domain Virtual Host Routing,
 * SSL certificate inspection, canonical aliases, and domain routing simulations.
 */
class DomainAdminService
{
    public static ?string $scaffoldRoot = null;

    public static function setScaffoldRoot(?string $root): void
    {
        self::$scaffoldRoot = $root;
    }

    private static function validHostname(string $host, bool $allowWildcard = true): bool
    {
        $host = strtolower(trim($host));
        if ($allowWildcard && str_starts_with($host, '*.')) {
            $host = substr($host, 2);
        }
        if ($host === 'localhost') {
            return true;
        }
        return (bool) preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\\.)+[a-z0-9][a-z0-9-]{0,62}$/', $host);
    }

    private static function validEntryFile(string $file): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $file)
            && basename($file) === $file
            && !str_contains($file, '..');
    }

    private static function validRoute(string $route): bool
    {
        return (bool) preg_match('#^/(?:[A-Za-z0-9][A-Za-z0-9._~-]*/?)*$#', $route)
            && !str_contains($route, '..');
    }

    private static function safeConfigValue(string $value): bool
    {
        return $value !== '' && !preg_match('/[\r\n\x00"\'`;{}]/', $value);
    }

    private static function validManagedPath(string $path, array $roots): bool
    {
        $path = str_replace('\\', '/', rtrim(trim($path), '/'));
        if ($path === '' || str_contains($path, "\0")) {
            return false;
        }
        $parent = realpath(is_dir($path) ? $path : dirname($path));
        if ($parent === false) {
            return false;
        }
        foreach ($roots as $root) {
            $resolvedRoot = realpath($root);
            if ($resolvedRoot !== false && ($parent === $resolvedRoot || str_starts_with($parent . DIRECTORY_SEPARATOR, $resolvedRoot . DIRECTORY_SEPARATOR))) {
                return true;
            }
        }
        return false;
    }
    /**
     * Resolves and returns the document root / home directory for all configured sites.
     */
    public static function getSiteHomeDirectories(): array
    {
        return StorageAdminService::getConfiguredSiteRoots();
    }

    /**
     * Canonical source of truth for all active hosted domains across the entire SPP cluster.
     * Synchronizes Virtual Hosts, Addon Domains, BIND DNS Zones, eVaarta Mail Server, and App Scopes.
     * Strictly filters out non-existent scaffold and dev domains.
     *
     * @return array<string>
     */
    public static function getSynchronizedDomains(): array
    {
        DomainRouter::loadConfig();
        $domains = DomainRouter::getAllDomains();

        $excluded = [
            'localhost', 'spp.local', 'school.local', 'school1.local',
            'testscho.edu.in', 'evaltest.school.edu', 'autotest.sovereign.local',
            'cli.test.local', 'parikshak-deploy.local', 'testportal.local',
            'scopedschool.org', 'campus.edu'
        ];

        $clean = [];
        foreach ($domains as $pattern => $cfg) {
            $p = strtolower(trim((string)$pattern));
            if ($p === '' || str_starts_with($p, '*.')) {
                continue;
            }
            if (in_array($p, $excluded, true) || str_ends_with($p, '.local') || str_ends_with($p, '.localhost') || str_ends_with($p, '.internal')) {
                continue;
            }
            $clean[] = $p;
        }

        // Direct disk check if DomainRouter had empty paths
        if (empty($clean) && class_exists('\\Symfony\\Component\\Yaml\\Yaml')) {
            $baseDir = defined('SPP_BASE_DIR') ? SPP_BASE_DIR : dirname(__DIR__, 2);
            $appDir = defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname($baseDir);
            $candidates = [
                $appDir . '/etc/domains.yml',
                $baseDir . '/etc/domains.yml',
                getcwd() . '/etc/domains.yml',
                dirname($baseDir) . '/etc/domains.yml'
            ];
            foreach ($candidates as $f) {
                if (file_exists($f)) {
                    try {
                        $parsed = (array)\Symfony\Component\Yaml\Yaml::parseFile($f);
                        if (!empty($parsed['domains']) && is_array($parsed['domains'])) {
                            foreach ($parsed['domains'] as $dom => $cfg) {
                                $p = strtolower(trim((string)$dom));
                                if ($p !== '' && !str_starts_with($p, '*.')) {
                                    if (!in_array($p, $excluded, true) && !str_ends_with($p, '.local')) {
                                        $clean[] = $p;
                                    }
                                }
                            }
                        }
                    } catch (\Throwable $e) {}
                }
            }
        }

        // Also incorporate any BIND zone files in /etc/bind/zones/
        if (is_dir('/etc/bind/zones')) {
            foreach (glob('/etc/bind/zones/db.*') as $bf) {
                $base = substr(basename($bf), 3);
                if ($base !== '' && !str_ends_with($base, '.bak') && !in_array($base, $excluded, true) && !str_ends_with($base, '.local')) {
                    $clean[] = $base;
                }
            }
        }

        // Also incorporate valid JSON zones in var/dns
        $dnsDir = DnsZoneManager::getStorageDir();
        if (is_dir($dnsDir)) {
            foreach (glob($dnsDir . '/*.json') as $jf) {
                $base = basename($jf, '.json');
                if ($base !== '' && !str_starts_with($base, '*.') && !in_array($base, $excluded, true) && !str_ends_with($base, '.local')) {
                    $clean[] = $base;
                }
            }
        }

        $clean = array_values(array_unique(array_filter($clean)));
        // Place primary domain first if present
        $primary = DomainRouter::getPrimaryDomains();
        $pDom = !empty($primary) ? reset($primary) : null;
        usort($clean, function($a, $b) use ($pDom) {
            if ($pDom) {
                if ($a === $pDom) return -1;
                if ($b === $pDom) return 1;
            }
            return strcmp($a, $b);
        });

        return $clean;
    }

    /**
     * Lists all registered domains with resolved settings and live metadata.
     */
    public static function listDomains(): array
    {
        DomainRouter::loadConfig();
        $domains = DomainRouter::getAllDomains();
        $globalDefault = DomainRouter::getGlobalDefaultApp();
        $configFile = DomainRouter::getActiveConfigFile() ?: (defined('SPP_APP_DIR') ? SPP_APP_DIR . '/etc/domains.yml' : (defined('SPP_BASE_DIR') ? dirname(__DIR__, 2) . '/etc/domains.yml' : 'etc/domains.yml'));

        $excluded = [
            'localhost', 'spp.local', 'school.local', 'school1.local',
            'testscho.edu.in', 'evaltest.school.edu', 'autotest.sovereign.local',
            'cli.test.local', 'parikshak-deploy.local', 'testportal.local',
            'scopedschool.org', 'campus.edu'
        ];

        $list = [];
        foreach ($domains as $pattern => $cfg) {
            $p = strtolower(trim((string)$pattern));
            if (in_array($p, $excluded, true) || str_ends_with($p, '.local') || str_ends_with($p, '.localhost') || str_ends_with($p, '.internal')) {
                continue;
            }
            $list[] = [
                'pattern' => $pattern,
                'type' => $cfg['type'] ?? 'primary',
                'account' => $cfg['account'] ?? 'default',
                'subdomain_policy' => $cfg['subdomain_policy'] ?? null,
                'default_app' => $cfg['default_app'] ?? 'default',
                'aliases' => $cfg['aliases'] ?? [],
                'redirect_to_canonical' => !empty($cfg['redirect_to_canonical']),
                'force_https' => !empty($cfg['force_https']),
                'redirect_default_app_prefix' => $cfg['redirect_default_app_prefix'] ?? true,
                'allow_other_apps' => $cfg['allow_other_apps'] ?? true,
                'enabled_apps' => $cfg['enabled_apps'] ?? [],
                'disabled_apps' => $cfg['disabled_apps'] ?? [],
                'settings' => $cfg['settings'] ?? [],
                'error_view' => $cfg['error_view'] ?? null,
                'canonical_host' => $cfg['canonical_host'] ?? $pattern,
                'is_wildcard' => str_contains($pattern, '*'),
                'app_dir' => $cfg['app_dir'] ?? null,
                'data_dir' => $cfg['data_dir'] ?? null,
                'routing_mode' => $cfg['routing_mode'] ?? 'spp_path',
                'sub_apps' => $cfg['sub_apps'] ?? [],
                'reverse_proxy' => $cfg['reverse_proxy'] ?? null,
            ];
        }

        return [
            'success' => true,
            'config_file' => $configFile,
            'global_default_app' => $globalDefault,
            'domain_mode' => DomainRouter::getDomainMode(),
            'is_single_domain_mode' => DomainRouter::isSingleDomainMode(),
            'is_enforced' => DomainRouter::isDomainRoutingEnforced(),
            'virtual_host_mode' => DomainRouter::getVirtualHostMode(),
            'unmapped_subdomain_policy' => DomainRouter::getUnmappedSubdomainPolicySetting(),
            'total_domains' => count($list),
            'domains' => $list
        ];
    }

    /**
     * Saves or updates a domain mapping in etc/domains.yml.
     */
    public static function saveDomain(string $pattern, array $data): array
    {
        $pattern = strtolower(trim($pattern));
        if (!self::validHostname($pattern)) {
            return ['success' => false, 'error' => 'A valid hostname or wildcard hostname is required.'];
        }

        DomainRouter::loadConfig();

        $existing = DomainRouter::getAllDomains()[$pattern] ?? [];
        $aliases = is_array($data['aliases'] ?? null) ? array_values(array_filter(array_map('trim', $data['aliases']))) : [];
        foreach ($aliases as $alias) {
            if (!self::validHostname($alias, false)) {
                return ['success' => false, 'error' => "Invalid domain alias '{$alias}'."];
            }
        }
        $config = array_merge($existing, [
            'type' => isset($data['type']) && in_array(strtolower(trim((string)$data['type'])), ['primary', 'addon', 'subdomain', 'alias'], true)
                ? strtolower(trim((string)$data['type']))
                : ($existing['type'] ?? 'primary'),
            'account' => isset($data['account']) && is_string($data['account']) && trim($data['account']) !== ''
                ? trim($data['account'])
                : 'default',
            'subdomain_policy' => isset($data['subdomain_policy']) && in_array(strtolower(trim((string)$data['subdomain_policy'])), DomainRouter::MODES, true)
                ? strtolower(trim((string)$data['subdomain_policy']))
                : null,
            'default_app' => is_array($data['default_app'] ?? null)
                ? [
                    'type' => $data['default_app']['type'] ?? 'non_spp',
                    'app_name' => trim((string)($data['default_app']['app_name'] ?? 'default')),
                    'target_path' => rtrim(str_replace('\\', '/', trim((string)($data['default_app']['target_path'] ?? ''))), '/'),
                    'index' => trim((string)($data['default_app']['index'] ?? 'index.php')),
                    'opt_in_sppdata' => !empty($data['default_app']['opt_in_sppdata']),
                ]
                : trim((string)($data['default_app'] ?? 'default')),
            'app_dir' => isset($data['app_dir']) && is_string($data['app_dir']) && trim($data['app_dir']) !== ''
                ? rtrim(str_replace('\\', '/', trim($data['app_dir'])), '/')
                : null,
            'data_dir' => isset($data['data_dir']) && is_string($data['data_dir']) && trim($data['data_dir']) !== ''
                ? rtrim(str_replace('\\', '/', trim($data['data_dir'])), '/')
                : null,
            'routing_mode' => isset($data['routing_mode']) && in_array(strtolower(trim((string)$data['routing_mode'])), ['spp_path', 'webserver_vhost'], true)
                ? strtolower(trim((string)$data['routing_mode']))
                : 'spp_path',
            'sub_apps' => is_array($data['sub_apps'] ?? null) ? $data['sub_apps'] : [],
            'aliases' => $aliases,
            'redirect_to_canonical' => !empty($data['redirect_to_canonical']),
            'force_https' => !empty($data['force_https']),
            'redirect_default_app_prefix' => isset($data['redirect_default_app_prefix']) ? (bool)$data['redirect_default_app_prefix'] : true,
            'allow_other_apps' => isset($data['allow_other_apps']) ? (bool)$data['allow_other_apps'] : true,
            'enabled_apps' => is_array($data['enabled_apps'] ?? null) ? array_values(array_filter(array_map('trim', $data['enabled_apps']))) : [],
            'disabled_apps' => is_array($data['disabled_apps'] ?? null) ? array_values(array_filter(array_map('trim', $data['disabled_apps']))) : [],
            'settings' => is_array($data['settings'] ?? null) ? $data['settings'] : [],
            'error_view' => !empty($data['error_view']) ? trim($data['error_view']) : null,
            'canonical_host' => !empty($data['canonical_host']) ? trim($data['canonical_host']) : $pattern,
            'reverse_proxy' => is_array($data['reverse_proxy'] ?? null) ? $data['reverse_proxy'] : ($existing['reverse_proxy'] ?? null),
        ]);

        foreach ([$config['app_dir'], $config['data_dir']] as $path) {
            if ($path !== null && !self::safeConfigValue((string)$path)) {
                return ['success' => false, 'error' => 'Directory paths may not contain quotes, control characters, or configuration syntax.'];
            }
        }
        if (is_array($config['default_app']) && (!self::safeConfigValue((string)$config['default_app']['target_path']) || !self::validEntryFile((string)$config['default_app']['index']))) {
            return ['success' => false, 'error' => 'External application path or entry file is invalid.'];
        }
        foreach ($config['sub_apps'] as $route => $subApp) {
            $isNonSppSub = ($subApp['type'] ?? '') === 'non_spp';
            $targetPath = (string)($subApp['target_path'] ?? '');
            if (!self::validRoute('/' . trim((string)$route, '/'))) {
                return ['success' => false, 'error' => "Invalid route syntax for sub-route '{$route}'."];
            }
            if ($isNonSppSub && (!self::safeConfigValue($targetPath) || !self::validEntryFile((string)($subApp['index'] ?? 'index.php')))) {
                return ['success' => false, 'error' => 'A sub-route contains an invalid target path or entry file.'];
            }
        }

        // If renaming an existing domain mapping, remove the original pattern
        $origPattern = !empty($data['original_pattern']) ? strtolower(trim((string)$data['original_pattern'])) : null;
        if ($origPattern !== null && $origPattern !== '' && $origPattern !== $pattern) {
            DomainRouter::removeDomain($origPattern);
        }

        DomainRouter::setDomain($pattern, $config);

        if (isset($data['set_as_global_default']) && $data['set_as_global_default']) {
            $globalApp = is_array($config['default_app']) ? ($config['default_app']['app_name'] ?? 'default') : $config['default_app'];
            DomainRouter::setGlobalDefaultApp($globalApp);
        }

        $saved = DomainRouter::saveConfig();
        if (!$saved) {
            return ['success' => false, 'error' => 'Failed to write configuration to domains.yml.'];
        }

        // Scaffold domain application and persistent data directories if configured
        if (!empty($config['app_dir']) || !empty($config['data_dir'])) {
            self::scaffoldDomainPaths($pattern, $config['account'] ?? 'default', $config['app_dir'], $config['data_dir'], true);
        }

        // Web Server Native Virtual Host Provisioning via Pluggable Driver
        $vhostResult = null;
        if (($config['routing_mode'] ?? 'spp_path') === 'webserver_vhost' && (!isset($data['auto_install_vhost']) || !empty($data['auto_install_vhost']))) {
            try {
                $driver = WebserverDriverResolver::resolve();
                $vhostContent = $driver->generateVhost($pattern, array_merge($data, $config));
                $vhostResult = $driver->installVhost($pattern, $vhostContent);
            } catch (\Throwable $ve) {
                $vhostResult = ['success' => false, 'error' => $ve->getMessage()];
            }
        }

        if ($vhostResult && empty($vhostResult['success'])) {
            if ($existing) {
                DomainRouter::setDomain($pattern, $existing);
            } else {
                DomainRouter::removeDomain($pattern);
            }
            DomainRouter::saveConfig();
            return ['success' => false, 'error' => 'Domain configuration was rolled back because virtual-host installation failed: ' . ($vhostResult['error'] ?? 'unknown error')];
        }

        $cfResult = null;
        $cfSettings = CloudflareBridge::getSettings();
        $shouldProvisionCf = !empty($data['auto_provision_cf']) || (!empty($cfSettings['enabled']) && !empty($cfSettings['auto_provision_subdomains']));
        if ($shouldProvisionCf && !str_contains($pattern, '*') && !str_contains($pattern, 'localhost') && str_contains($pattern, '.')) {
            $proxied = isset($data['cf_proxied']) ? (bool)$data['cf_proxied'] : ($cfSettings['default_proxied'] ?? true);
            $cfResult = CloudflareBridge::autoProvisionSubdomain($pattern, $data['server_ip'] ?? null, $proxied);
        }

        $msg = "Domain '{$pattern}' saved successfully.";
        if ($vhostResult && !empty($vhostResult['success'])) {
            $msg .= " Web server virtual host updated.";
        }
        if ($cfResult && !empty($cfResult['success'])) {
            $msg .= " Published to Cloudflare Edge DNS automatically.";
        } elseif ($cfResult && !empty($cfResult['error'])) {
            $msg .= " (Cloudflare notice: " . $cfResult['error'] . ")";
        }

        return [
            'success' => true,
            'message' => $msg,
            'vhost' => $vhostResult,
            'cloudflare' => $cfResult,
            'domain' => array_merge(['pattern' => $pattern], $config)
        ];
    }

    /**
     * Deletes a domain mapping from etc/domains.yml.
     */
    public static function deleteDomain(string $pattern): array
    {
        $pattern = strtolower(trim($pattern));
        DomainRouter::loadConfig();

        $domains = DomainRouter::getAllDomains();
        if (!isset($domains[$pattern])) {
            return ['success' => false, 'error' => "Domain '{$pattern}' does not exist."];
        }

        DomainRouter::removeDomain($pattern);
        $saved = DomainRouter::saveConfig();
        if (!$saved) {
            return ['success' => false, 'error' => 'Failed to persist changes to domains.yml.'];
        }

        // Remove native web server virtual host before committing the routing change.
        try {
            $vhostResult = WebserverDriverResolver::resolve()->removeVhost($pattern);
            if (empty($vhostResult['success'])) {
                DomainRouter::setDomain($pattern, $domains[$pattern]);
                DomainRouter::saveConfig();
                return ['success' => false, 'error' => 'Could not remove native virtual host: ' . ($vhostResult['error'] ?? 'unknown error')];
            }
        } catch (\Throwable $e) {
            DomainRouter::setDomain($pattern, $domains[$pattern]);
            DomainRouter::saveConfig();
            return ['success' => false, 'error' => 'Could not remove native virtual host: ' . $e->getMessage()];
        }

        return [
            'success' => true,
            'message' => "Domain '{$pattern}' removed successfully."
        ];
    }

    /**
     * Probes an external application directory for CMS detection, permissions, and index script.
     */
    public static function probeSubAppPath(string $targetPath): array
    {
        $cleanPath = rtrim(str_replace('\\', '/', trim($targetPath)), '/');
        $probe = [];
        if (class_exists('\\SPP\\Core\\Server\\NonSppAppHandler')) {
            $probe = \SPP\Core\Server\NonSppAppHandler::probeApp($cleanPath);
        } else {
            $exists = @file_exists($cleanPath);
            $probe = [
                'success' => $exists && @is_dir($cleanPath),
                'valid' => $exists && @is_dir($cleanPath),
                'exists' => $exists,
                'readable' => $exists && @is_readable($cleanPath),
                'writable' => $exists && @is_writable($cleanPath),
                'type' => $exists ? 'php' : 'not_found',
                'framework' => $exists ? 'Generic PHP' : 'Missing Directory',
                'version' => null,
                'index_file' => 'index.php',
                'clean_path' => $cleanPath,
                'error' => $exists ? null : "Directory does not exist: {$cleanPath}"
            ];
        }
        $probe['clean_path'] = $probe['clean_path'] ?? $cleanPath;
        $probe['has_props'] = @file_exists($cleanPath . '/__spp_file_props.yml');
        $probe['valid'] = !empty($probe['valid']);
        $probe['success'] = !empty($probe['valid']);
        if (empty($probe['valid']) && empty($probe['error'])) {
            $probe['error'] = 'Directory does not exist or is not readable: ' . $cleanPath;
        }
        return $probe;
    }

    /**
     * Saves or updates a sub-route on a domain.
     */
    public static function saveSubAppRoute(string $domain, string $route, array $config): array
    {
        $domain = strtolower(trim($domain));
        $route = '/' . trim($route, '/');
        if ($domain === '' || $route === '/') {
            return ['success' => false, 'error' => 'Domain and sub-route path must be valid.'];
        }

        DomainRouter::loadConfig();
        $domainCfg = DomainRouter::getDomain($domain);
        if (!$domainCfg) {
            return ['success' => false, 'error' => "Domain '{$domain}' does not exist."];
        }

        if (!isset($domainCfg['sub_apps']) || !is_array($domainCfg['sub_apps'])) {
            $domainCfg['sub_apps'] = [];
        }

        $targetPath = rtrim(str_replace('\\', '/', trim((string)($config['target_path'] ?? ''))), '/');
        $sName = trim((string)($config['app_name'] ?? basename($route)));
        $dataDir = rtrim(str_replace('\\', '/', trim((string)($config['data_dir'] ?? ''))), '/');
        if ($dataDir === '' && !empty($domainCfg['account']) && $domainCfg['account'] !== 'default') {
            $dataDir = "/home/{$domainCfg['account']}/sppdata/{$domain}/{$sName}";
        }

        $domainCfg['sub_apps'][$route] = [
            'route' => $route,
            'type' => $config['type'] ?? 'non_spp',
            'app_name' => $sName,
            'target_path' => $targetPath,
            'data_dir' => $dataDir,
            'index' => trim((string)($config['index'] ?? 'index.php')),
            'opt_in_sppdata' => !empty($config['opt_in_sppdata']),
        ];

        // Ensure manifest (__spp_file_props.yml) is recorded
        if ($targetPath && is_dir($targetPath)) {
            $pPath = $targetPath . DIRECTORY_SEPARATOR . '__spp_file_props.yml';
            $pData = [
                'version' => '1.0',
                'directory' => [
                    'is_app' => true,
                    'app_name' => $sName,
                    'app_type' => $config['type'] ?? 'non_spp',
                    'domain' => $domain,
                    'data_dir' => $dataDir,
                    'index' => trim((string)($config['index'] ?? 'index.php')),
                    'opt_in_sppdata' => !empty($config['opt_in_sppdata']),
                    'attributes' => ['is_app' => true, 'stability' => 'stable']
                ]
            ];
            @file_put_contents($pPath, Yaml::dump($pData, 4, 2));
        }

        DomainRouter::setDomain($domain, $domainCfg);
        $saved = DomainRouter::saveConfig();
        if (!$saved) {
            return ['success' => false, 'error' => 'Failed to save sub-route configuration to domains.yml.'];
        }

        return [
            'success' => true,
            'message' => "Sub-route '{$route}' saved for domain '{$domain}'.",
            'sub_app' => $domainCfg['sub_apps'][$route]
        ];
    }

    /**
     * Deletes a sub-route from a domain.
     */
    public static function deleteSubAppRoute(string $domain, string $route): array
    {
        $domain = strtolower(trim($domain));
        $route = '/' . trim($route, '/');
        if ($domain === '' || $route === '/') {
            return ['success' => false, 'error' => 'Domain and sub-route path must be valid.'];
        }

        DomainRouter::loadConfig();
        $domainCfg = DomainRouter::getDomain($domain);
        if (!$domainCfg) {
            return ['success' => false, 'error' => "Domain '{$domain}' does not exist."];
        }

        if (isset($domainCfg['sub_apps'][$route])) {
            unset($domainCfg['sub_apps'][$route]);
            DomainRouter::setDomain($domain, $domainCfg);
            $saved = DomainRouter::saveConfig();
            if (!$saved) {
                return ['success' => false, 'error' => 'Failed to save configuration after deleting sub-route.'];
            }
        }

        return [
            'success' => true,
            'message' => "Sub-route '{$route}' removed from domain '{$domain}'."
        ];
    }

    /**
     * Retrieves all Addon Domains, optionally filtered by hosting account.
     */
    public static function listAddonDomains(?string $account = null): array
    {
        DomainRouter::loadConfig();
        $addons = DomainRouter::getAddonDomains($account);

        $excluded = [
            'localhost', 'spp.local', 'school.local', 'school1.local',
            'testscho.edu.in', 'evaltest.school.edu', 'autotest.sovereign.local',
            'cli.test.local', 'parikshak-deploy.local', 'testportal.local',
            'scopedschool.org', 'campus.edu'
        ];

        $list = [];
        foreach ($addons as $pattern => $cfg) {
            $p = strtolower(trim((string)$pattern));
            if (in_array($p, $excluded, true) || str_ends_with($p, '.local') || str_ends_with($p, '.localhost') || str_ends_with($p, '.internal')) {
                continue;
            }
            $list[] = [
                'domain' => $pattern,
                'account' => $cfg['account'] ?? 'default',
                'default_app' => $cfg['default_app'] ?? 'default',
                'aliases' => $cfg['aliases'] ?? [],
                'force_https' => !empty($cfg['force_https']),
                'redirect_to_canonical' => !empty($cfg['redirect_to_canonical']),
                'subdomain_policy' => $cfg['subdomain_policy'] ?? null,
                'canonical_host' => $cfg['canonical_host'] ?? $pattern,
            ];
        }

        return [
            'success' => true,
            'account' => $account,
            'total_addons' => count($list),
            'addons' => $list
        ];
    }

    /**
     * Registers a new Addon Domain under a specific hosting account.
     */
    public static function addAddonDomain(string $domain, string $defaultApp, ?string $account = 'default', array $data = []): array
    {
        $domain = strtolower(trim($domain));
        if (empty($domain)) {
            return ['success' => false, 'error' => 'Addon domain cannot be empty.'];
        }

        $defaultApp = trim($defaultApp);
        if (empty($defaultApp)) {
            return ['success' => false, 'error' => 'Target default application cannot be empty.'];
        }

        DomainRouter::loadConfig();
        $all = DomainRouter::getAllDomains();
        if (isset($all[$domain])) {
            return ['success' => false, 'error' => "Domain '{$domain}' already exists."];
        }

        $options = [
            'aliases' => is_array($data['aliases'] ?? null) ? array_values(array_filter(array_map('trim', $data['aliases']))) : [],
            'force_https' => !empty($data['force_https']),
            'redirect_to_canonical' => !empty($data['redirect_to_canonical']),
            'allow_other_apps' => isset($data['allow_other_apps']) ? (bool)$data['allow_other_apps'] : true,
            'enabled_apps' => is_array($data['enabled_apps'] ?? null) ? array_values(array_filter(array_map('trim', $data['enabled_apps']))) : [],
            'disabled_apps' => is_array($data['disabled_apps'] ?? null) ? array_values(array_filter(array_map('trim', $data['disabled_apps']))) : [],
            'settings' => is_array($data['settings'] ?? null) ? $data['settings'] : [],
            'subdomain_policy' => isset($data['subdomain_policy']) && in_array(strtolower(trim((string)$data['subdomain_policy'])), DomainRouter::MODES, true)
                ? strtolower(trim((string)$data['subdomain_policy']))
                : null,
            'canonical_host' => !empty($data['canonical_host']) ? trim($data['canonical_host']) : $domain,
        ];

        DomainRouter::registerAddonDomain($domain, $defaultApp, $account, $options);
        $saved = DomainRouter::saveConfig();
        if (!$saved) {
            return ['success' => false, 'error' => 'Failed to persist addon domain to domains.yml.'];
        }

        return [
            'success' => true,
            'message' => "Addon domain '{$domain}' added successfully for account '{$account}'.",
            'addon' => array_merge([
                'domain' => $domain,
                'default_app' => $defaultApp,
                'account' => $account ?: 'default',
            ], $options)
        ];
    }

    /**
     * Removes an Addon Domain from the configuration.
     */
    public static function removeAddonDomain(string $domain): array
    {
        $domain = strtolower(trim($domain));
        DomainRouter::loadConfig();

        if (!DomainRouter::isAddonDomain($domain)) {
            $all = DomainRouter::getAllDomains();
            if (!isset($all[$domain])) {
                return ['success' => false, 'error' => "Domain '{$domain}' not found."];
            }
            return ['success' => false, 'error' => "Domain '{$domain}' is not an Addon Domain (type is '{$all[$domain]['type']}')."];
        }

        $removed = DomainRouter::removeAddonDomain($domain);
        if (!$removed) {
            return ['success' => false, 'error' => "Failed to remove addon domain '{$domain}'."];
        }

        $saved = DomainRouter::saveConfig();
        if (!$saved) {
            return ['success' => false, 'error' => 'Failed to persist removal to domains.yml.'];
        }

        return [
            'success' => true,
            'message' => "Addon domain '{$domain}' removed successfully."
        ];
    }

    /**
     * Sets domain architecture mode ('single' or 'multi').
     */
    public static function setDomainMode(string $mode): array
    {
        $clean = strtolower(trim($mode));
        if (!in_array($clean, DomainRouter::DOMAIN_MODES, true)) {
            return ['success' => false, 'error' => "Invalid mode '{$mode}'. Supported modes: single, multi"];
        }

        DomainRouter::loadConfig();
        DomainRouter::setDomainMode($clean);
        $saved = DomainRouter::saveConfig();

        if (!$saved) {
            return ['success' => false, 'error' => 'Failed to persist mode to domains.yml.'];
        }

        return [
            'success' => true,
            'message' => "Domain architecture mode set to '{$clean}'.",
            'domain_mode' => DomainRouter::getDomainMode(),
            'is_single_domain_mode' => DomainRouter::isSingleDomainMode(),
            'is_enforced' => DomainRouter::isDomainRoutingEnforced()
        ];
    }

    /**
     * Configures the global Virtual Host policy mode and unmapped subdomain policy.
     */
    public static function setVirtualHostMode(string $mode, ?string $unmappedSubdomainPolicy = null): array
    {
        $mode = strtolower(trim($mode));
        if (!in_array($mode, DomainRouter::MODES, true)) {
            return [
                'success' => false,
                'error' => "Invalid mode '{$mode}'. Valid modes: " . implode(', ', DomainRouter::MODES)
            ];
        }

        DomainRouter::loadConfig();
        DomainRouter::setVirtualHostMode($mode);

        if ($unmappedSubdomainPolicy !== null) {
            $subPolicy = strtolower(trim($unmappedSubdomainPolicy));
            if (in_array($subPolicy, DomainRouter::MODES, true)) {
                DomainRouter::setUnmappedSubdomainPolicySetting($subPolicy);
            }
        } else {
            DomainRouter::setUnmappedSubdomainPolicySetting($mode);
        }

        $saved = DomainRouter::saveConfig();
        if (!$saved) {
            return ['success' => false, 'error' => 'Failed to persist virtual host mode to domains.yml.'];
        }

        return [
            'success' => true,
            'message' => "Virtual host mode set to '{$mode}' successfully.",
            'virtual_host_mode' => DomainRouter::getVirtualHostMode(),
            'unmapped_subdomain_policy' => DomainRouter::getUnmappedSubdomainPolicySetting()
        ];
    }

    /**
     * Retrieves high-level domain engine settings and diagnostics.
     */
    public static function getDomainSettings(): array
    {
        DomainRouter::loadConfig();
        $all = DomainRouter::getAllDomains();
        $addons = DomainRouter::getAddonDomains();
        $primaries = DomainRouter::getPrimaryDomains();

        return [
            'success' => true,
            'virtual_host_mode' => DomainRouter::getVirtualHostMode(),
            'unmapped_subdomain_policy' => DomainRouter::getUnmappedSubdomainPolicySetting(),
            'global_default_app' => DomainRouter::getGlobalDefaultApp(),
            'allowed_dev_hosts' => DomainRouter::getAllowedDevHosts(),
            'total_domains' => count($all),
            'primary_domains_count' => count($primaries),
            'addon_domains_count' => count($addons),
            'supported_modes' => DomainRouter::MODES,
        ];
    }

    /**
     * Tests a domain routing resolution and returns comprehensive simulation results.
     */
    public static function testDomain(string $host, string $path = '/', string $method = 'GET'): array
    {
        DomainRouter::loadConfig();
        $cleanHost = strtolower(trim(explode(':', $host)[0]));
        $domainConfig = DomainRouter::resolveDomain($cleanHost);
        $isDevHost = DomainRouter::isAllowedUnmappedDevHost($cleanHost);

        $policyInfo = DomainRouter::getUnmappedSubdomainPolicy($cleanHost);
        $policy = $policyInfo['policy'];
        $parentDomain = $policyInfo['parent_domain'];
        $parentConfig = $policyInfo['parent_config'];

        $defaultApp = DomainRouter::getDefaultApp($cleanHost);
        $cleanPath = '/' . ltrim($path, '/');
        $pathParts = explode('/', ltrim($cleanPath, '/'));
        $firstSegment = $pathParts[0] ?? '';

        $resolvedApp = $defaultApp;
        $rewrittenPath = $cleanPath;
        $isDefaultApp = true;
        $isAllowed = true;
        $rejectionReason = null;
        $httpStatus = 200;
        $resolutionMode = $domainConfig !== null ? 'exact_or_wildcard' : ($isDevHost ? 'dev_bypass' : $policy);

        if ($domainConfig === null && !$isDevHost) {
            if ($policy === DomainRouter::MODE_STRICT) {
                $isAllowed = false;
                $httpStatus = 404;
                $rejectionReason = "Subdomain or virtual host '{$cleanHost}' is not configured (Rejected under Strict 404 Mode).";
            } elseif ($policy === DomainRouter::MODE_REDIRECT) {
                $canonicalRedirect = $parentDomain ? DomainRouter::getCanonicalHost($parentDomain) : null;
                $httpStatus = 302;
            } elseif ($policy === DomainRouter::MODE_INHERIT) {
                if ($parentDomain && !empty($parentConfig['default_app'])) {
                    $resolvedApp = $parentConfig['default_app'];
                } else {
                    $isAllowed = false;
                    $httpStatus = 404;
                    $rejectionReason = "Parent domain for '{$cleanHost}' has no default app configured.";
                }
            } elseif ($policy === DomainRouter::MODE_DYNAMIC_TENANT) {
                $resolvedApp = 'tenant_' . (explode('.', $cleanHost)[0] ?? 'unknown');
            }
        }

        $appExists = is_dir(SPP_APP_DIR . '/src/' . $firstSegment) || isset((\SPP\App::getGlobalSettings()['apps'] ?? [])[$firstSegment]);
        if ($isAllowed && !empty($firstSegment) && $firstSegment !== $defaultApp && $appExists) {
            $isAllowed = DomainRouter::isAppAllowed($firstSegment, $cleanHost);
            if ($isAllowed) {
                $resolvedApp = $firstSegment;
                $isDefaultApp = false;
                $subPath = implode('/', array_slice($pathParts, 1));
                $rewrittenPath = '/' . $subPath;
            } else {
                $httpStatus = 403;
                $rejectionReason = "Access to app '{$firstSegment}' is forbidden on domain '{$cleanHost}'.";
            }
        }

        $canonicalRedirect = $canonicalRedirect ?? DomainRouter::shouldRedirectToCanonical($cleanHost);
        $httpsRequired = DomainRouter::shouldRedirectHttps($cleanHost);

        return [
            'success' => true,
            'host' => $cleanHost,
            'path' => $path,
            'method' => $method,
            'matched_pattern' => DomainRouter::getMatchedPattern(),
            'resolution_mode' => $resolutionMode,
            'parent_domain' => $parentDomain,
            'http_status' => $httpStatus,
            'resolved_app' => $resolvedApp,
            'rewritten_path' => $rewrittenPath,
            'is_default_app' => $isDefaultApp,
            'is_allowed' => $isAllowed,
            'rejection_reason' => $rejectionReason,
            'canonical_redirect' => $canonicalRedirect,
            'https_required' => $httpsRequired,
            'settings' => DomainRouter::getAllSettings($cleanHost)
        ];
    }

    /**
     * Inspects SSL certificate status for a given domain/host.
     */
    public static function checkSsl(string $host): array
    {
        $cleanHost = preg_replace('/:[0-9]+$/', '', trim($host));
        $isLocal = in_array($cleanHost, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($cleanHost, '.local') || str_ends_with($cleanHost, '.test');

        if ($isLocal) {
            return [
                'success' => true,
                'status' => 'local_dev',
                'valid' => true,
                'issuer' => 'Local Development Self-Signed / Loopback',
                'expires_at' => 'N/A (Localhost)',
                'days_remaining' => 9999
            ];
        }

        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
                'timeout' => 5
            ]
        ]);

        $client = @stream_socket_client("ssl://{$cleanHost}:443", $errno, $errstr, 5, STREAM_CLIENT_CONNECT, $context);
        if (!$client) {
            return [
                'success' => true,
                'status' => 'no_ssl',
                'valid' => false,
                'message' => "Could not establish SSL connection to {$cleanHost}:443 ({$errstr})"
            ];
        }

        $params = stream_context_get_params($client);
        fclose($client);

        if (empty($params['options']['ssl']['peer_certificate'])) {
            return [
                'success' => true,
                'status' => 'no_cert',
                'valid' => false,
                'message' => 'No peer certificate found.'
            ];
        }

        $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);
        if (!$cert) {
            return [
                'success' => true,
                'status' => 'invalid_cert',
                'valid' => false,
                'message' => 'Failed to parse peer certificate.'
            ];
        }

        $validTo = $cert['validTo_time_t'] ?? 0;
        $now = time();
        $daysRemaining = (int)ceil(($validTo - $now) / 86400);

        return [
            'success' => true,
            'status' => $daysRemaining > 0 ? 'valid' : 'expired',
            'valid' => $daysRemaining > 0,
            'issuer' => $cert['issuer']['O'] ?? ($cert['issuer']['CN'] ?? 'Unknown Issuer'),
            'subject' => $cert['subject']['CN'] ?? $cleanHost,
            'valid_from' => date('Y-m-d H:i:s', $cert['validFrom_time_t'] ?? 0),
            'expires_at' => date('Y-m-d H:i:s', $validTo),
            'days_remaining' => $daysRemaining
        ];
    }

    /**
     * Diagnostic DNS resolution and optional network traceroute.
     * Cross-platform: works on Windows and Linux using native PHP DNS APIs and OS trace tools.
     */
    public static function trackDNS(string $hostname, string $type = 'ANY', bool $trace = false): array
    {
        $host = trim(strtolower($hostname));
        if (empty($host)) {
            return ['success' => false, 'error' => 'Hostname cannot be empty.'];
        }

        $typeMap = [
            'A'     => DNS_A,
            'AAAA'  => DNS_AAAA,
            'CNAME' => DNS_CNAME,
            'MX'    => DNS_MX,
            'TXT'   => DNS_TXT,
            'NS'    => DNS_NS,
            'SOA'   => DNS_SOA,
            'ANY'   => DNS_ANY
        ];

        $dnsType = $typeMap[strtoupper($type)] ?? DNS_ANY;
        $records = @dns_get_record($host, $dnsType);

        $results = [
            'success' => true,
            'hostname' => $host,
            'type' => strtoupper($type),
            'resolved_count' => is_array($records) ? count($records) : 0,
            'records' => $records ?: [],
            'traceroute' => null
        ];

        if ($trace) {
            $isWindows = (PHP_OS_FAMILY === 'Windows');
            $bin = $isWindows ? 'tracert -d -h 15 -w 1000' : 'traceroute -n -m 15 -w 1';
            $safeHost = escapeshellarg($host);
            $output = [];
            @exec("{$bin} {$safeHost} 2>&1", $output);
            $results['traceroute'] = implode("\n", $output);
        }

        return $results;
    }

    /**
     * Gets reverse proxy configuration for a registered domain.
     */
    public static function getReverseProxy(string $pattern): array
    {
        $pattern = strtolower(trim($pattern));
        DomainRouter::loadConfig();
        $domains = DomainRouter::getAllDomains();

        if (!isset($domains[$pattern])) {
            return ['success' => false, 'error' => "Domain '{$pattern}' is not registered."];
        }

        $cfg = $domains[$pattern];
        $proxy = $cfg['reverse_proxy'] ?? [
            'enabled' => false,
            'target' => '',
            'service_type' => 'node',
            'timeout' => 30,
            'preserve_host' => true,
            'websocket' => true
        ];

        return [
            'success' => true,
            'domain' => $pattern,
            'proxy' => $proxy
        ];
    }

    /**
     * Updates reverse proxy configuration for a registered domain (Node, Python, Go, Java, .NET, generic).
     */
    public static function saveReverseProxy(string $pattern, array $proxyConfig): array
    {
        $pattern = strtolower(trim($pattern));
        DomainRouter::loadConfig();
        $domains = DomainRouter::getAllDomains();

        if (!isset($domains[$pattern])) {
            return ['success' => false, 'error' => "Domain '{$pattern}' is not registered."];
        }

        $cfg = $domains[$pattern];
        $target = trim((string)($proxyConfig['target'] ?? ''));
        if (!empty($proxyConfig['enabled']) && (!filter_var($target, FILTER_VALIDATE_URL) || !in_array(strtolower((string)parse_url($target, PHP_URL_SCHEME)), ['http', 'https'], true))) {
            return ['success' => false, 'error' => 'A valid HTTP or HTTPS upstream URL is required.'];
        }
        $cfg['reverse_proxy'] = [
            'enabled' => !empty($proxyConfig['enabled']),
            'target' => $target,
            'service_type' => in_array($proxyConfig['service_type'] ?? '', ['node', 'python', 'go', 'dotnet', 'java', 'generic'], true)
                ? $proxyConfig['service_type']
                : 'generic',
            'timeout' => max(1, (int)($proxyConfig['timeout'] ?? 30)),
            'preserve_host' => !isset($proxyConfig['preserve_host']) || !empty($proxyConfig['preserve_host']),
            'websocket' => !empty($proxyConfig['websocket'])
        ];

        DomainRouter::setDomain($pattern, $cfg);
        if (!DomainRouter::saveConfig()) {
            return ['success' => false, 'error' => 'Failed to persist reverse proxy configuration.'];
        }
        if (($cfg['routing_mode'] ?? 'spp_path') === 'webserver_vhost') {
            try {
                $driver = WebserverDriverResolver::resolve();
                $vhostResult = $driver->installVhost($pattern, $driver->generateVhost($pattern, $cfg));
                if (empty($vhostResult['success'])) {
                    return ['success' => false, 'error' => 'Proxy saved but virtual-host reload failed: ' . ($vhostResult['error'] ?? 'unknown error')];
                }
            } catch (\Throwable $e) {
                return ['success' => false, 'error' => 'Proxy saved but virtual-host reload failed: ' . $e->getMessage()];
            }
        }

        return [
            'success' => true,
            'message' => "Reverse proxy for '{$pattern}' saved successfully.",
            'proxy' => $cfg['reverse_proxy']
        ];
    }

    /**
     * Probes an upstream microservice URL (Node.js, Python, Go, Java) to check connectivity and response time.
     */
    public static function probeProxyTarget(string $targetUrl): array
    {
        $targetUrl = trim($targetUrl);
        if (empty($targetUrl)) {
            return ['success' => false, 'error' => 'Target URL cannot be empty.'];
        }

        $parts = parse_url($targetUrl);
        $host = $parts['host'] ?? '127.0.0.1';
        $port = isset($parts['port']) ? (int)$parts['port'] : (($parts['scheme'] ?? 'http') === 'https' ? 443 : 80);

        $start = microtime(true);
        $fp = @fsockopen($host, $port, $errno, $errstr, 2.0);
        $latencyMs = round((microtime(true) - $start) * 1000, 2);

        if (!$fp) {
            return [
                'success' => true,
                'target' => $targetUrl,
                'reachable' => false,
                'error' => "Connection refused ({$errno}: {$errstr})",
                'latency_ms' => $latencyMs
            ];
        }

        fclose($fp);

        return [
            'success' => true,
            'target' => $targetUrl,
            'host' => $host,
            'port' => $port,
            'reachable' => true,
            'latency_ms' => $latencyMs,
            'message' => "Upstream microservice is responding on port {$port} ({$latencyMs} ms)."
        ];
    }

    /**
     * Detects the public IP address of this server host.
     */
    public static function detectServerPublicIp(): string
    {
        $ip = $_SERVER['SERVER_ADDR'] ?? null;
        if (!empty($ip) && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $ip;
        }

        if (Infra::isLinux()) {
            $out = [];
            @exec("curl -s --max-time 2 https://api.ipify.org 2>/dev/null", $out);
            $curlIp = trim($out[0] ?? '');
            if (!empty($curlIp) && filter_var($curlIp, FILTER_VALIDATE_IP)) {
                return $curlIp;
            }
        }

        return !empty($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : ($_SERVER['LOCAL_ADDR'] ?? (gethostbyname(gethostname()) ?: '127.0.0.1'));
    }

    /**
     * Detects if a valid SSL certificate already exists on the host system for a given domain
     * (e.g. from Certbot, system Apache/Nginx, or SPP storage).
     */
    public static function detectSystemCertificate(string $domain): ?array
    {
        $domain = strtolower(trim($domain));
        if (empty($domain)) {
            return null;
        }

        $candidates = [
            // Linux Certbot live directory
            [
                'cert' => "/etc/letsencrypt/live/{$domain}/fullchain.pem",
                'key' => "/etc/letsencrypt/live/{$domain}/privkey.pem",
            ],
            // Windows Certbot directory
            [
                'cert' => "C:/Certbot/live/{$domain}/fullchain.pem",
                'key' => "C:/Certbot/live/{$domain}/privkey.pem",
            ],
            // SPP internal SSL storage
            [
                'cert' => dirname(__DIR__, 2) . "/storage/ssl/certs/{$domain}.crt",
                'key' => dirname(__DIR__, 2) . "/storage/ssl/private/{$domain}.key",
            ],
            [
                'cert' => dirname(__DIR__, 3) . "/var/sppssl/certs/{$domain}.crt",
                'key' => dirname(__DIR__, 3) . "/var/sppssl/private/{$domain}.key",
            ],
        ];

        foreach ($candidates as $cand) {
            if (file_exists($cand['cert']) && is_readable($cand['cert'])) {
                $raw = @file_get_contents($cand['cert']);
                if ($raw) {
                    $parsed = @openssl_x509_parse($raw);
                    if ($parsed && !empty($parsed['validTo_time_t'])) {
                        $now = time();
                        $validTo = $parsed['validTo_time_t'];
                        $daysRemaining = (int)ceil(($validTo - $now) / 86400);
                        if ($daysRemaining > 0) {
                            return [
                                'valid' => true,
                                'cert_file' => str_replace('\\', '/', realpath($cand['cert']) ?: $cand['cert']),
                                'key_file' => file_exists($cand['key']) ? str_replace('\\', '/', realpath($cand['key']) ?: $cand['key']) : null,
                                'expires_at' => date('Y-m-d H:i:s', $validTo),
                                'days_remaining' => $daysRemaining,
                                'issuer' => $parsed['issuer']['O'] ?? ($parsed['issuer']['CN'] ?? "Let's Encrypt")
                            ];
                        }
                    }
                }
            }
        }

        // Secondary check: If file access is permission-restricted (e.g. root:root on Linux),
        // probe port 443 via self::checkSsl()
        try {
            $liveCheck = self::checkSsl($domain);
            if (!empty($liveCheck['valid']) && ($liveCheck['days_remaining'] ?? 0) > 0) {
                return [
                    'valid' => true,
                    'cert_file' => file_exists("/etc/letsencrypt/live/{$domain}/fullchain.pem") ? "/etc/letsencrypt/live/{$domain}/fullchain.pem" : 'system-webserver',
                    'key_file' => file_exists("/etc/letsencrypt/live/{$domain}/privkey.pem") ? "/etc/letsencrypt/live/{$domain}/privkey.pem" : 'system-webserver',
                    'expires_at' => $liveCheck['expires_at'] ?? date('Y-m-d H:i:s', time() + 8000000),
                    'days_remaining' => $liveCheck['days_remaining'] ?? 90,
                    'issuer' => $liveCheck['issuer'] ?? "Let's Encrypt"
                ];
            }
        } catch (\Throwable $e) {
            // ignore network probe failure
        }

        return null;
    }

    /**
     * Provisions or validates an automated Let's Encrypt SSL/TLS certificate via ACME v2.
     */
    public static function autoProvisionSSL(
        string $domain,
        string $token = '',
        string $keyAuth = '',
        string $email = '',
        bool $staging = false,
        bool $dryRun = false,
        bool $execute = false
    ): array {
        $domain = strtolower(trim($domain));
        if (empty($domain)) {
            $host = preg_replace('/:[0-9]+$/', '', trim($_SERVER['HTTP_HOST'] ?? ''));
            if (!empty($host) && !in_array($host, ['localhost', '127.0.0.1'], true) && !filter_var($host, FILTER_VALIDATE_IP)) {
                $domain = strtolower($host);
            } else {
                DomainRouter::loadConfig();
                $all = array_keys(DomainRouter::getAllDomains());
                $nonWildcard = array_values(array_filter($all, fn($d) => !str_contains($d, '*')));
                if (!empty($nonWildcard)) {
                    $domain = strtolower($nonWildcard[0]);
                }
            }
        }

        if (empty($domain)) {
            return ['success' => false, 'error' => 'Domain cannot be empty.'];
        }

        // 1. Detect if a valid Let's Encrypt / Certbot certificate already exists on the system!
        // Only bypass if not explicitly deploying a challenge token or dry-running
        if (!$dryRun && empty($token) && empty($keyAuth)) {
            $existingCert = self::detectSystemCertificate($domain);
            if ($existingCert && !empty($existingCert['valid']) && ($existingCert['days_remaining'] ?? 0) > 0) {
                DomainRouter::loadConfig();
                $domains = DomainRouter::getAllDomains();
                if (isset($domains[$domain])) {
                    $domains[$domain]['ssl'] = [
                        'enabled' => true,
                        'cert_file' => $existingCert['cert_file'] ?? null,
                        'key_file' => $existingCert['key_file'] ?? null,
                        'expires_at' => $existingCert['expires_at'] ?? null,
                        'issuer' => $existingCert['issuer'] ?? 'Let\'s Encrypt'
                    ];
                    DomainRouter::setDomain($domain, $domains[$domain]);
                    DomainRouter::saveConfig();
                }

                return [
                    'success' => true,
                    'status' => 'already_active',
                    'message' => "Valid SSL certificate already active on server for {$domain} (Expires: {$existingCert['expires_at']}, Issuer: {$existingCert['issuer']}). Successfully linked to SPP domain configuration.",
                    'cert_file' => $existingCert['cert_file'] ?? null,
                    'key_file' => $existingCert['key_file'] ?? null,
                    'expires_at' => $existingCert['expires_at'] ?? null,
                    'issuer' => $existingCert['issuer'] ?? "Let's Encrypt",
                    'days_remaining' => $existingCert['days_remaining'] ?? null
                ];
            }
        }

        $docRoot = !empty($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : null;
        $appDir = defined('SPP_APP_DIR') ? realpath(SPP_APP_DIR) : null;
        $projDir = realpath(dirname(__DIR__, 3));
        $baseDir = $docRoot ?: ($appDir ?: ($projDir ?: dirname(__DIR__, 2)));

        $challengeDirs = array_unique(array_filter([
            $baseDir . DIRECTORY_SEPARATOR . '.well-known' . DIRECTORY_SEPARATOR . 'acme-challenge',
            ($docRoot ? $docRoot . DIRECTORY_SEPARATOR . '.well-known' . DIRECTORY_SEPARATOR . 'acme-challenge' : null),
            ($projDir ? $projDir . DIRECTORY_SEPARATOR . '.well-known' . DIRECTORY_SEPARATOR . 'acme-challenge' : null),
        ]));

        foreach ($challengeDirs as $cd) {
            if (!is_dir($cd)) {
                @mkdir($cd, 0775, true);
            }
        }

        // Full ACME v2 Automated Issuance via AcmeClient
        if ($execute || !empty($email) || $dryRun) {
            // Pre-flight DNS resolution check before triggering live Let's Encrypt validation
            if ($execute && !$dryRun) {
                $serverIp = self::detectServerPublicIp();
                $resolvedIp = @gethostbyname($domain);
                $isLocal = in_array($resolvedIp, ['127.0.0.1', '::1', 'localhost'], true) || str_ends_with($domain, '.local') || str_ends_with($domain, '.test');

                if (!$isLocal && $resolvedIp !== $domain && !empty($serverIp) && $resolvedIp !== $serverIp) {
                    return [
                        'success' => false,
                        'error' => "DNS Resolution Mismatch: Domain '{$domain}' currently resolves to IP {$resolvedIp}, but this server's public IP is {$serverIp}. Let's Encrypt validates certificates by making an HTTP request to http://{$domain}/.well-known/acme-challenge/... which reaches {$resolvedIp} (the old server) and fails with 404. Please point your domain's A record to {$serverIp} and wait for DNS propagation before requesting an SSL certificate.",
                        'resolved_ip' => $resolvedIp,
                        'server_ip' => $serverIp,
                        'dns_mismatch' => true
                    ];
                }
            }

            $acmeClass = '\\SPPMod\\SPPSSL\\AcmeClient';
            if (!class_exists($acmeClass)) {
                $acmeFile = dirname(__DIR__, 2) . '/modules/optional/sppssl/src/AcmeClient.php';
                if (file_exists($acmeFile)) {
                    require_once $acmeFile;
                }
            }

            if (class_exists($acmeClass)) {
                try {
                    $client = new $acmeClass($staging, null, $dryRun);
                    $contactEmail = $email !== '' ? $email : "admin@{$domain}";
                    $res = $client->issueCertificate($domain, $contactEmail, $baseDir);

                    // Update domain configuration with certificate paths if successful
                    if (!empty($res['success'])) {
                        DomainRouter::loadConfig();
                        $domains = DomainRouter::getAllDomains();
                        if (isset($domains[$domain])) {
                            $domains[$domain]['ssl'] = [
                                'enabled' => true,
                                'cert_file' => $res['cert_file'] ?? null,
                                'key_file' => $res['key_file'] ?? null,
                                'expires_at' => $res['expires_at'] ?? null,
                                'issuer' => $res['issuer'] ?? 'Let\'s Encrypt'
                            ];
                            DomainRouter::setDomain($domain, $domains[$domain]);
                            DomainRouter::saveConfig();
                        }
                    }

                    return $res;
                } catch (\Throwable $e) {
                    return [
                        'success' => false,
                        'error' => 'ACME Provisioning failed: ' . $e->getMessage()
                    ];
                }
            }
        }

        if (!empty($token) && !empty($keyAuth)) {
            $safeToken = basename($token);
            $tokenFiles = [];
            foreach ($challengeDirs as $cd) {
                $tokenFile = $cd . DIRECTORY_SEPARATOR . $safeToken;
                @file_put_contents($tokenFile, $keyAuth);
                @chmod($tokenFile, 0644);
                $tokenFiles[] = str_replace('\\', '/', $tokenFile);
            }

            return [
                'success' => true,
                'status' => 'challenge_ready',
                'domain' => $domain,
                'token' => $safeToken,
                'challenge_url' => "http://{$domain}/.well-known/acme-challenge/{$safeToken}",
                'file' => $tokenFiles[0] ?? '',
                'files' => $tokenFiles
            ];
        }

        return [
            'success' => true,
            'status' => 'ready',
            'domain' => $domain,
            'challenge_dir' => str_replace('\\', '/', $challengeDirs[0] ?? '')
        ];
    }

    /**
     * Scans all configured domains and renews certificates that are missing or expiring within $daysThreshold.
     */
    public static function renewExpiringCertificates(int $daysThreshold = 30, bool $dryRun = false): array
    {
        DomainRouter::loadConfig();
        $domains = DomainRouter::getAllDomains();
        $renewed = [];
        $skipped = [];
        $failed = [];

        foreach ($domains as $domain => $cfg) {
            // Skip wildcards
            if (str_contains($domain, '*')) {
                continue;
            }

            $ssl = $cfg['ssl'] ?? [];
            $expiresAt = $ssl['expires_at'] ?? null;
            $needsRenewal = false;

            if (empty($ssl['enabled']) || empty($ssl['cert_file']) || empty($expiresAt)) {
                $needsRenewal = true;
            } else {
                $expTimestamp = strtotime($expiresAt);
                if ($expTimestamp !== false && ($expTimestamp - time()) < ($daysThreshold * 86400)) {
                    $needsRenewal = true;
                }
            }

            if ($needsRenewal) {
                // Renewal is a real issuance unless the caller explicitly requests a dry run.
                $res = self::autoProvisionSSL($domain, '', '', 'admin@' . $domain, false, $dryRun, !$dryRun);
                if (!empty($res['success'])) {
                    $renewed[] = [
                        'domain' => $domain,
                        'cert_file' => $res['cert_file'] ?? null,
                        'expires_at' => $res['expires_at'] ?? null,
                        'issuer' => $res['issuer'] ?? 'Let\'s Encrypt'
                    ];
                } else {
                    $failed[] = [
                        'domain' => $domain,
                        'error' => $res['error'] ?? 'Unknown ACME error'
                    ];
                }
            } else {
                $skipped[] = [
                    'domain' => $domain,
                    'expires_at' => $expiresAt,
                    'days_remaining' => round(($expTimestamp - time()) / 86400, 1)
                ];
            }
        }

        return [
            'success' => true,
            'total_domains' => count($domains),
            'renewed_count' => count($renewed),
            'skipped_count' => count($skipped),
            'failed_count' => count($failed),
            'renewed' => $renewed,
            'skipped' => $skipped,
            'failed' => $failed,
            'dry_run' => $dryRun
        ];
    }

    /**
     * Returns an overview of sovereign SSL & local DNS posture across all domains.
     */
    public static function getSovereignSslOverview(): array
    {
        DomainRouter::loadConfig();
        $domains = DomainRouter::getAllDomains();
        $overview = [];
        $configChanged = false;

        foreach ($domains as $domain => $cfg) {
            if (str_contains($domain, '*')) continue;

            $hasLocalZone = file_exists(DnsZoneManager::getStorageDir() . "/{$domain}.json");
            $ssl = $cfg['ssl'] ?? [];

            // Automatically detect and link host Certbot / system certificates if not yet configured
            if (empty($ssl['enabled'])) {
                $sysCert = self::detectSystemCertificate($domain);
                if ($sysCert && !empty($sysCert['valid']) && ($sysCert['days_remaining'] ?? 0) > 0) {
                    $ssl = [
                        'enabled' => true,
                        'cert_file' => $sysCert['cert_file'] ?? null,
                        'key_file' => $sysCert['key_file'] ?? null,
                        'expires_at' => $sysCert['expires_at'] ?? null,
                        'issuer' => $sysCert['issuer'] ?? 'Let\'s Encrypt'
                    ];
                    $domains[$domain]['ssl'] = $ssl;
                    DomainRouter::setDomain($domain, $domains[$domain]);
                    $configChanged = true;
                }
            }

            $overview[] = [
                'domain' => $domain,
                'force_https' => !empty($cfg['force_https']),
                'has_ssl' => !empty($ssl['enabled']),
                'expires_at' => $ssl['expires_at'] ?? null,
                'issuer' => $ssl['issuer'] ?? 'None',
                'has_sovereign_dns' => $hasLocalZone,
                'dns_serial' => $hasLocalZone ? (DnsZoneManager::getZone($domain)['serial'] ?? null) : null
            ];
        }

        if ($configChanged) {
            DomainRouter::saveConfig();
        }

        return [
            'success' => true,
            'domains_count' => count($overview),
            'sovereign_domains' => $overview
        ];
    }

    /**
     * Checks if Certbot and associated webserver plugins (Apache/Nginx) are installed on the host.
     */
    public static function getCertbotStatus(): array
    {
        $isLinux = Infra::isLinux();
        $isWindows = Infra::isWindows();
        $certbotBin = Infra::hasBinary('certbot');
        $certbotVer = 'N/A';
        $apachePlugin = false;
        $nginxPlugin = false;
        $webserver = Infra::webServer();
        $webserverVersion = $_SERVER['SERVER_SOFTWARE'] ?? ($isLinux ? 'Apache/2.4 (Linux)' : 'Apache/2.4 (Windows)');

        if ($webserver === 'unknown' || $webserver === 'standalone') {
            if (Infra::hasBinary('apache2') || Infra::hasBinary('httpd')) {
                $webserver = 'apache';
            } elseif (Infra::hasBinary('nginx')) {
                $webserver = 'nginx';
            }
        }

        if ($certbotBin) {
            $verOut = [];
            @exec('certbot --version 2>&1', $verOut);
            $certbotVer = trim(implode(' ', $verOut));

            $pluginsOut = [];
            @exec('certbot plugins 2>&1', $pluginsOut);
            $pluginsStr = implode("\n", $pluginsOut);
            if (str_contains($pluginsStr, '* apache') || str_contains($pluginsStr, 'Plugin: apache')) {
                $apachePlugin = true;
            }
            if (str_contains($pluginsStr, '* nginx') || str_contains($pluginsStr, 'Plugin: nginx')) {
                $nginxPlugin = true;
            }
        }

        // Secondary check for Debian/Ubuntu python3-certbot-apache
        if (!$apachePlugin && $isLinux) {
            $dpkgOut = [];
            @exec('dpkg -s python3-certbot-apache 2>/dev/null | grep "Status: install ok installed"', $dpkgOut);
            if (!empty($dpkgOut)) {
                $apachePlugin = true;
            }
        }

        $packageManager = Infra::packageManager();

        return [
            'success' => true,
            'is_linux' => $isLinux,
            'is_windows' => $isWindows,
            'distro' => Infra::distroName(),
            'package_manager' => $packageManager,
            'webserver' => $webserver,
            'webserver_version' => $webserverVersion,
            'certbot_installed' => $certbotBin,
            'certbot_version' => $certbotVer,
            'apache_plugin_installed' => $apachePlugin,
            'nginx_plugin_installed' => $nginxPlugin,
            'can_install' => $isLinux && in_array($packageManager, ['apt', 'dnf', 'yum', 'apk'], true)
        ];
    }

    /**
     * Automatically installs Certbot and the Apache / Nginx plugin using the host package manager.
     */
    public static function installCertbot(string $webserver = 'apache'): array
    {
        if (Infra::isWindows()) {
            return [
                'success' => false,
                'error' => 'System package installation (apt/dnf) is only supported on Linux. On Windows, use Pure-PHP ACME v2 mode.'
            ];
        }

        $agentClientPath = dirname(__DIR__, 2) . '/agent/src/Client.php';
        if (file_exists($agentClientPath)) {
            require_once $agentClientPath;
            try {
                $client = new \SPP\Agent\Client();
                $pkg = ($webserver === 'nginx') ? 'python3-certbot-nginx' : 'python3-certbot-apache';
                $client->call('package.update');
                $res = $client->call('package.install', ['package' => "certbot {$pkg}"]);
                return [
                    'success' => true,
                    'message' => "Certbot and {$webserver} plugin installed via SPP Server Agent.",
                    'output'  => $res['output'] ?? ''
                ];
            } catch (\Throwable $e) {
                // Fall through
            }
        }

        $pkgMgr = Infra::packageManager();
        $output = [];
        $exitCode = 0;
        $isRoot = function_exists('posix_getuid') ? (posix_getuid() === 0) : false;
        $sudo = $isRoot ? '' : 'sudo ';

        if ($pkgMgr === 'apt') {
            $pkg = ($webserver === 'nginx') ? 'python3-certbot-nginx' : 'python3-certbot-apache';
            $cmd = "{$sudo}DEBIAN_FRONTEND=noninteractive apt-get update -y && {$sudo}DEBIAN_FRONTEND=noninteractive apt-get install -y certbot {$pkg} 2>&1";
            @exec($cmd, $output, $exitCode);
        } elseif ($pkgMgr === 'dnf' || $pkgMgr === 'yum') {
            $pkg = ($webserver === 'nginx') ? 'python3-certbot-nginx' : 'python3-certbot-apache';
            $cmd = "{$sudo}dnf install -y certbot {$pkg} 2>&1";
            @exec($cmd, $output, $exitCode);
        } else {
            return [
                'success' => false,
                'error' => "Unsupported Linux package manager: '{$pkgMgr}'."
            ];
        }

        $status = self::getCertbotStatus();
        $isSuccess = ($exitCode === 0) || ($status['certbot_installed'] && ($webserver === 'apache' ? $status['apache_plugin_installed'] : true));

        return [
            'success' => $isSuccess,
            'message' => $isSuccess ? "Certbot and {$webserver} plugin installed successfully!" : "Installation completed with exit code {$exitCode}.",
            'output' => implode("\n", $output),
            'status' => $status
        ];
    }

    /**
     * Pre-flight DNS validation checking if target domains resolve to this server's public IP.
     */
    public static function checkDnsPreflight(array $domains, ?string $serverIp = null): array
    {
        if (!$serverIp || $serverIp === '127.0.0.1') {
            $serverIp = $_SERVER['SERVER_ADDR'] ?? null;
            if (!$serverIp || in_array($serverIp, ['127.0.0.1', '::1', '0.0.0.0'], true)) {
                $ctx = stream_context_create(['http' => ['timeout' => 2]]);
                $curlIp = @file_get_contents('https://api.ipify.org', false, $ctx);
                if ($curlIp && filter_var(trim($curlIp), FILTER_VALIDATE_IP)) {
                    $serverIp = trim($curlIp);
                }
            }
        }
        if (!$serverIp) {
            $serverIp = '127.0.0.1';
        }

        $results = [];
        $allMatched = true;

        foreach ($domains as $domain) {
            $d = strtolower(trim($domain));
            if ($d === '') continue;

            $resolvedIp = null;
            $records = @dns_get_record($d, DNS_A);
            if (!empty($records) && isset($records[0]['ip'])) {
                $resolvedIp = $records[0]['ip'];
            } else {
                $hostIp = @gethostbyname($d);
                if ($hostIp !== $d && filter_var($hostIp, FILTER_VALIDATE_IP)) {
                    $resolvedIp = $hostIp;
                }
            }

            $matches = ($resolvedIp !== null && $resolvedIp === $serverIp);
            if (!$matches && $serverIp !== '127.0.0.1') {
                $allMatched = false;
            }

            $warning = null;
            if (!$resolvedIp) {
                $warning = "Domain '{$d}' does not resolve to any IP address yet. Public DNS records may not have propagated.";
            } elseif (!$matches && $serverIp !== '127.0.0.1') {
                $warning = "Domain '{$d}' resolves to {$resolvedIp}, but this server is {$serverIp}. Let's Encrypt validation will target {$resolvedIp} and return 404 until DNS propagation completes.";
            }

            $results[] = [
                'domain' => $d,
                'resolved_ip' => $resolvedIp ?? 'Unresolved',
                'server_ip' => $serverIp,
                'matches' => $matches,
                'warning' => $warning
            ];
        }

        return [
            'success' => true,
            'server_ip' => $serverIp,
            'all_matched' => $allMatched,
            'results' => $results
        ];
    }

    /**
     * Executes Certbot to provision and install Let's Encrypt certificates directly on the host.
     */
    public static function issueCertbotSsl(
        array $domains,
        string $email = '',
        string $method = 'apache',
        bool $staging = false,
        bool $dryRun = false,
        bool $forceRedirect = true
    ): array {
        $cleanDomains = [];
        foreach ($domains as $d) {
            $cd = strtolower(trim($d));
            if ($cd !== '' && !in_array($cd, $cleanDomains, true)) {
                $cleanDomains[] = $cd;
            }
        }

        if (empty($cleanDomains)) {
            return ['success' => false, 'error' => 'At least one domain must be provided for SSL issuance.'];
        }

        $primaryDomain = $cleanDomains[0];
        if ($email === '') {
            $email = "admin@{$primaryDomain}";
        }

        // Simulation on Windows or environments without certbot binary
        if (Infra::isWindows() || !Infra::hasBinary('certbot')) {
            $certFile = "/etc/letsencrypt/live/{$primaryDomain}/fullchain.pem";
            $keyFile = "/etc/letsencrypt/live/{$primaryDomain}/privkey.pem";
            return [
                'success' => true,
                'dry_run' => $dryRun,
                'staging' => $staging,
                'domains' => $cleanDomains,
                'email' => $email,
                'method' => $method,
                'command' => "certbot run --{$method} -d " . implode(' -d ', $cleanDomains) . ($dryRun ? ' --dry-run' : '') . " (simulated)",
                'exit_code' => 0,
                'cert_file' => $certFile,
                'key_file' => $keyFile,
                'output' => "Certbot simulation on " . PHP_OS_FAMILY . ": Successfully validated domains: " . implode(', ', $cleanDomains),
                'message' => $dryRun ? 'Certbot dry-run validation succeeded! (Simulation)' : 'Certbot SSL certificate successfully issued and installed! (Simulation)'
            ];
        }

        $certbotStatus = self::getCertbotStatus();
        if (!$certbotStatus['certbot_installed']) {
            return [
                'success' => false,
                'error' => "Certbot binary is not installed on this system. Please use the 'Install Certbot & Apache Plugin' button in the UI or use Pure-PHP ACME mode.",
                'status' => $certbotStatus
            ];
        }

        $parts = ['certbot'];
        if ($method === 'apache') {
            $parts[] = 'run';
            $parts[] = '--apache';
        } elseif ($method === 'nginx') {
            $parts[] = 'run';
            $parts[] = '--nginx';
        } else {
            $webroot = realpath(defined('SPP_BASE_DIR') ? SPP_BASE_DIR : dirname(__DIR__, 2));
            $parts[] = 'certonly';
            $parts[] = '--webroot';
            $parts[] = '-w';
            $parts[] = escapeshellarg($webroot ?: '/var/www/spp');
        }

        foreach ($cleanDomains as $cd) {
            $parts[] = '-d';
            $parts[] = escapeshellarg($cd);
        }

        $parts[] = '--non-interactive';
        $parts[] = '--agree-tos';
        $parts[] = '-m';
        $parts[] = escapeshellarg($email);

        if ($forceRedirect && $method !== 'webroot') {
            $parts[] = '--redirect';
        }

        if ($staging) {
            $parts[] = '--staging';
        }

        if ($dryRun) {
            $parts[] = '--dry-run';
        }

        $cmd = implode(' ', $parts);
        if (Infra::isLinux() && function_exists('posix_getuid') && posix_getuid() !== 0) {
            $cmd = 'sudo ' . $cmd;
        }

        $output = [];
        $exitCode = 0;
        @exec($cmd . ' 2>&1', $output, $exitCode);
        $outStr = implode("\n", $output);

        $isSuccess = ($exitCode === 0) || str_contains($outStr, 'Congratulations!') || str_contains($outStr, 'Successfully received certificate');

        $certFile = "/etc/letsencrypt/live/{$primaryDomain}/fullchain.pem";
        $keyFile = "/etc/letsencrypt/live/{$primaryDomain}/privkey.pem";

        if ($isSuccess && !$dryRun) {
            DomainRouter::loadConfig();
            $allDomains = DomainRouter::getAllDomains();
            foreach ($cleanDomains as $cd) {
                if (isset($allDomains[$cd])) {
                    $allDomains[$cd]['ssl'] = [
                        'enabled' => true,
                        'cert_file' => $certFile,
                        'key_file' => $keyFile,
                        'issuer' => $staging ? 'Let\'s Encrypt Staging' : 'Let\'s Encrypt',
                        'expires_at' => date('Y-m-d H:i:s', time() + (90 * 86400)),
                        'method' => 'certbot-' . $method
                    ];
                    DomainRouter::setDomain($cd, $allDomains[$cd]);
                }
            }
            DomainRouter::saveConfig();

            if ($method === 'apache') {
                if (class_exists(HostProvisionService::class)) {
                    HostProvisionService::ensureSslWildcardAlias($primaryDomain);
                }
                @exec('sudo systemctl reload apache2 2>/dev/null || sudo systemctl reload httpd 2>/dev/null');
            } elseif ($method === 'nginx') {
                @exec('sudo systemctl reload nginx 2>/dev/null');
            }
        }

        return [
            'success' => $isSuccess,
            'dry_run' => $dryRun,
            'staging' => $staging,
            'domains' => $cleanDomains,
            'email' => $email,
            'method' => $method,
            'command' => $cmd,
            'exit_code' => $exitCode,
            'cert_file' => $certFile,
            'key_file' => $keyFile,
            'output' => $outStr,
            'message' => $isSuccess
                ? ($dryRun ? 'Certbot dry-run validation succeeded!' : 'Certbot SSL certificate successfully issued and installed!')
                : 'Certbot issuance failed. See output log below.'
        ];
    }

    // =========================================================================
    // DNS ZONE EDITOR & PROPAGATION SUITE
    // =========================================================================

    /**
     * Gets the full DNS zone (records, serial, TTL, DNSSEC) for a domain across its configured provider.
     */
    public static function getZoneRecords(string $domain, string $defaultIp = '127.0.0.1'): array
    {
        $domain = trim($domain);
        if ($domain === '') {
            DomainRouter::loadConfig();
            $domains = DomainRouter::getAllDomains();
            $domain = !empty($domains) ? (string)array_key_first($domains) : 'localhost';
        }

        $mapping = \SPP\Core\Dns\DnsProviderManager::getDomainMapping($domain);
        $providerId = $mapping['provider'] ?? 'bind9';

        if ($providerId === 'bind9') {
            $zone = DnsZoneManager::getZone($domain, $defaultIp);
            if (!empty($zone['success'])) {
                $zone['provider'] = 'bind9';
                $zone['mapping'] = $mapping;
            }
            return $zone;
        }

        // External provider
        try {
            $provider = \SPP\Core\Dns\DnsProviderManager::getProviderForDomain($domain);
            $recRes = $provider->listRecords($mapping['zone_id'] ?? $domain);
            if (!$recRes['success']) {
                // Fallback to local cache if provider unreachable
                $zone = DnsZoneManager::getZone($domain, $defaultIp);
                $zone['provider'] = $providerId;
                $zone['provider_offline'] = true;
                $zone['provider_error'] = $recRes['error'] ?? 'Provider unreachable';
                return $zone;
            }

            $nsRes = $provider->getNameservers($mapping['zone_id'] ?? $domain);
            $dnssecRes = $provider->getDnssecStatus($mapping['zone_id'] ?? $domain);

            return [
                'success' => true,
                'domain' => $domain,
                'provider' => $providerId,
                'zone_id' => $mapping['zone_id'] ?? $domain,
                'mapping' => $mapping,
                'records' => $recRes['records'] ?? [],
                'nameservers' => $nsRes['nameservers'] ?? [],
                'dnssec' => $dnssecRes,
                'ttl' => 300,
                'serial' => date('Ymd01')
            ];
        } catch (\Throwable $e) {
            $zone = DnsZoneManager::getZone($domain, $defaultIp);
            $zone['provider'] = $providerId;
            $zone['provider_error'] = $e->getMessage();
            return $zone;
        }
    }

    /**
     * Adds or updates a record in a domain's DNS zone with safety validation.
     */
    public static function saveZoneRecord(string $domain, array $record): array
    {
        $domain = trim($domain);
        $mapping = \SPP\Core\Dns\DnsProviderManager::getDomainMapping($domain);
        $providerId = $mapping['provider'] ?? 'bind9';

        // 1. Fetch current zone records for CNAME conflict and safety validation
        $currentZone = self::getZoneRecords($domain);
        $existingRecords = $currentZone['records'] ?? [];
        $editingId = $record['id'] ?? null;

        $provider = \SPP\Core\Dns\DnsProviderManager::getProviderForDomain($domain);
        $val = \SPP\Core\Dns\DnsRecordValidator::validate(
            $record,
            $domain,
            $existingRecords,
            $editingId ? (string)$editingId : null,
            $provider->getCapabilities()
        );

        if (!$val['valid']) {
            return [
                'success' => false,
                'error' => implode('; ', $val['errors']),
                'validation_errors' => $val['errors'],
                'warnings' => $val['warnings']
            ];
        }

        $sanitized = $val['sanitized'];
        if (!empty($record['id'])) {
            $sanitized['id'] = $record['id'];
        }

        if ($providerId === 'bind9') {
            $res = DnsZoneManager::saveRecord($domain, $sanitized);
            if (!empty($res['success'])) {
                $res['warnings'] = $val['warnings'];
            }
            return $res;
        }

        // External provider
        try {
            $zoneId = $mapping['zone_id'] ?? $domain;
            if (!empty($sanitized['id'])) {
                $res = $provider->updateRecord($zoneId, (string)$sanitized['id'], $sanitized);
            } else {
                $res = $provider->createRecord($zoneId, $sanitized);
            }

            if (!empty($res['success'])) {
                // Also mirror into local cache and history
                DnsZoneManager::saveRecord($domain, $sanitized);
                $res['warnings'] = $val['warnings'];
            }
            return $res;
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => "Provider '{$providerId}' error: " . $e->getMessage()];
        }
    }

    /**
     * Deletes a DNS record by ID with deletion impact assessment.
     */
    public static function deleteZoneRecord(string $domain, string $recordId): array
    {
        $domain = trim($domain);
        $mapping = \SPP\Core\Dns\DnsProviderManager::getDomainMapping($domain);
        $providerId = $mapping['provider'] ?? 'bind9';

        if ($providerId === 'bind9') {
            return DnsZoneManager::deleteRecord($domain, $recordId);
        }

        try {
            $provider = \SPP\Core\Dns\DnsProviderManager::getProviderForDomain($domain);
            $zoneId = $mapping['zone_id'] ?? $domain;
            $res = $provider->deleteRecord($zoneId, $recordId);
            if (!empty($res['success'])) {
                DnsZoneManager::deleteRecord($domain, $recordId);
            }
            return $res;
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => "Provider '{$providerId}' delete error: " . $e->getMessage()];
        }
    }

    /**
     * Resets a domain's zone to the default BIND template.
     */
    public static function resetZone(string $domain, string $serverIp = '127.0.0.1'): array
    {
        return DnsZoneManager::resetZone($domain, $serverIp);
    }

    /**
     * Exports a domain's zone to standard RFC 1035 text.
     */
    public static function exportZone(string $domain): array
    {
        $text = DnsZoneManager::exportZone($domain);
        return [
            'success' => true,
            'domain' => $domain,
            'zone_text' => $text
        ];
    }

    /**
     * Imports a raw BIND RFC 1035 zone text file.
     */
    public static function importZone(string $domain, string $zoneText): array
    {
        return DnsZoneManager::importZone($domain, $zoneText);
    }

    /**
     * Probes global DNS propagation across Anycast resolvers.
     */
    public static function checkGlobalPropagation(string $hostname, string $type = 'A'): array
    {
        return DnsZoneManager::checkPropagation($hostname, $type);
    }

    /**
     * Retrieves DNSSEC metadata and DS delegation records.
     */
    public static function getDnssec(string $domain): array
    {
        return DnsZoneManager::getDnssec($domain);
    }

    /**
     * Toggles DNSSEC active status for a domain.
     */
    public static function toggleDnssec(string $domain, bool $enabled): array
    {
        return DnsZoneManager::toggleDnssec($domain, $enabled);
    }

    /**
     * Retrieves revision history for a domain's DNS zone.
     */
    public static function getZoneHistory(string $domain): array
    {
        return DnsZoneManager::getZoneHistory($domain);
    }

    /**
     * Previews changes before rolling back to a historical zone revision.
     */
    public static function previewZoneRollback(string $domain, string $revisionId): array
    {
        return DnsZoneManager::previewRollback($domain, $revisionId);
    }

    /**
     * Rolls back a domain's DNS records to a specified revision.
     */
    public static function rollbackZone(string $domain, string $revisionId, ?string $author = null): array
    {
        return DnsZoneManager::rollbackZone($domain, $revisionId, $author);
    }

    /**
     * Synchronizes a domain zone into var/dns/__spp_file_props.yml.
     */
    public static function syncZoneToManifest(string $domain): array
    {
        return DnsZoneManager::syncToManifest($domain);
    }

    /**
     * Alias for syncZoneToManifest.
     */
    public static function syncZoneManifest(string $domain): array
    {
        return self::syncZoneToManifest($domain);
    }

    // =========================================================================
    // UNIFIED MULTI-PROVIDER DNS & REGISTRAR MANAGEMENT APIS
    // =========================================================================

    public static function listDnsProviders(): array
    {
        return [
            'success' => true,
            'providers' => \SPP\Core\Dns\DnsProviderManager::listAvailableProviders(),
            'accounts' => \SPP\Core\Dns\DnsCredentialVault::listAccounts(),
            'mappings' => \SPP\Core\Dns\DnsProviderManager::listDomainMappings()
        ];
    }

    public static function saveDnsProviderAccount(array $account): array
    {
        return \SPP\Core\Dns\DnsCredentialVault::saveAccount($account);
    }

    public static function deleteDnsProviderAccount(string $accountId): array
    {
        return \SPP\Core\Dns\DnsCredentialVault::deleteAccount($accountId);
    }

    public static function testDnsProviderConnection(string $provider, ?string $accountId = null): array
    {
        try {
            $adapter = \SPP\Core\Dns\DnsProviderManager::getProviderInstance($provider, $accountId);
            $res = $adapter->testConnection();
            if ($accountId) {
                \SPP\Core\Dns\DnsCredentialVault::recordVerification($accountId, !empty($res['success']));
            }
            return $res;
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public static function getDomainDnsMapping(string $domain): array
    {
        return [
            'success' => true,
            'mapping' => \SPP\Core\Dns\DnsProviderManager::getDomainMapping($domain)
        ];
    }

    public static function setDomainDnsMapping(string $domain, array $mapping): array
    {
        return \SPP\Core\Dns\DnsProviderManager::setDomainMapping($domain, $mapping);
    }

    public static function listDomainDnsMappings(): array
    {
        return [
            'success' => true,
            'mappings' => \SPP\Core\Dns\DnsProviderManager::listDomainMappings()
        ];
    }

    public static function compareDnsZone(string $domain): array
    {
        return \SPP\Core\Dns\DnsSyncService::compare($domain);
    }

    public static function syncDnsZone(string $domain, string $direction = 'pull', bool $dryRun = false, bool $force = false): array
    {
        if (strtolower($direction) === 'push') {
            return \SPP\Core\Dns\DnsSyncService::push($domain, $dryRun, $force);
        }
        return \SPP\Core\Dns\DnsSyncService::pull($domain, $dryRun, $force);
    }

    public static function auditDnsPropagation(string $hostname, string $type = 'A', ?string $expectedValue = null): array
    {
        return \SPP\Core\Dns\DnsPropagationEngine::auditPropagation($hostname, $type, $expectedValue);
    }

    public static function auditDnssec(string $domain): array
    {
        return \SPP\Core\Dns\DnssecDiagnostics::auditDnssec($domain);
    }

    public static function auditMailDns(string $domain, array $dkimSelectors = ['default', 'mail', 'spp', 'google']): array
    {
        return \SPP\Core\Dns\DnsSslMailBridge::auditMailDns($domain, $dkimSelectors);
    }

    public static function getRegistrarInfo(string $domain): array
    {
        $registrar = \SPP\Core\Dns\DnsRegistrarManager::getRegistrarForDomain($domain);
        $info = $registrar->getDomainInfo($domain);
        $ns = $registrar->getNameservers($domain);
        return [
            'success' => true,
            'domain' => $domain,
            'registrar' => $registrar->getId(),
            'registrar_name' => $registrar->getName(),
            'info' => $info,
            'delegated_nameservers' => $ns['nameservers'] ?? []
        ];
    }

    public static function updateRegistrarNameservers(string $domain, array $nameservers): array
    {
        $registrar = \SPP\Core\Dns\DnsRegistrarManager::getRegistrarForDomain($domain);
        return $registrar->setNameservers($domain, $nameservers);
    }

    /**
     * Lists all domain applications and app domain restrictions.
     */
    public static function listDomainApps(?string $domain = null): array
    {
        DomainRouter::loadConfig();
        $installed = DomainRouter::getInstalledDomainApps($domain);

        // Also gather all registered applications in the framework and their domain restrictions
        $allApps = [];
        if (class_exists('\\SPP\\App')) {
            $settings = \SPP\App::getGlobalSettings();
            $appsMap = $settings['apps'] ?? [];
            foreach ($appsMap as $appName => $appMeta) {
                $restr = DomainRouter::getAppRestrictions($appName);
                $allApps[] = [
                    'name' => $appName,
                    'title' => $appMeta['title'] ?? $appMeta['admin_title'] ?? ucfirst($appName),
                    'src_path' => $restr['src_path'],
                    'is_domain_app' => $restr['is_domain_app'],
                    'installed_domain' => $restr['installed_domain'],
                    'domain_only' => $restr['domain_only'],
                    'allowed_domains' => $restr['allowed_domains'],
                ];
            }
        }

        return [
            'success' => true,
            'domain' => $domain,
            'installed_domain_apps' => $installed,
            'all_apps' => $allApps
        ];
    }

    /**
     * Comprehensive domain applications inventory for a specific domain.
     * Details default root application, secondary sub-route apps, and suggested defaults.
     */
    public static function getDomainApps(string $domain): array
    {
        $domain = strtolower(trim($domain));
        DomainRouter::loadConfig();
        $domainCfg = DomainRouter::getDomain($domain);
        if (!$domainCfg) {
            return ['success' => false, 'error' => "Domain '{$domain}' not found."];
        }

        $account = $domainCfg['account'] ?? 'default';
        $customAppDir = $domainCfg['app_dir'] ?? ($account !== 'default' && is_dir("/home/{$account}/apps") ? "/home/{$account}/apps" : null);
        $customDataDir = $domainCfg['data_dir'] ?? ($account !== 'default' && is_dir("/home/{$account}/sppdata/{$domain}") ? "/home/{$account}/sppdata/{$domain}" : null);

        // 1. Default App
        $rawDefault = $domainCfg['default_app'] ?? 'default';
        $defaultApp = [];
        if (is_array($rawDefault)) {
            $defaultApp = [
                'name' => $rawDefault['app_name'] ?? 'default',
                'title' => $rawDefault['title'] ?? ucfirst($rawDefault['app_name'] ?? 'default'),
                'type' => $rawDefault['type'] ?? 'non_spp',
                'target_path' => $rawDefault['target_path'] ?? '',
                'data_dir' => $rawDefault['data_dir'] ?? ($customDataDir ? "{$customDataDir}/" . ($rawDefault['app_name'] ?? 'default') : ''),
                'index' => $rawDefault['index'] ?? 'index.php',
                'opt_in_sppdata' => !empty($rawDefault['opt_in_sppdata']),
                'is_default' => true,
                'route' => '/',
            ];
        } else {
            $appName = (string)$rawDefault;
            $restr = DomainRouter::getAppRestrictions($appName);
            $settings = class_exists('\\SPP\\App') ? \SPP\App::getGlobalSettings() : [];
            $appMeta = $settings['apps'][$appName] ?? [];
            $defaultApp = [
                'name' => $appName,
                'title' => $appMeta['title'] ?? ucfirst($appName),
                'type' => 'spp',
                'target_path' => $restr['src_path'] ?? ($appMeta['src_path'] ?? "src/{$appName}"),
                'data_dir' => $customDataDir ? "{$customDataDir}/{$appName}" : ($appMeta['data_path'] ?? "var/data/{$appName}"),
                'index' => 'index.php',
                'opt_in_sppdata' => true,
                'is_default' => true,
                'route' => '/',
            ];
        }

        // 2. Sub-Apps
        $subApps = [];
        if (!empty($domainCfg['sub_apps']) && is_array($domainCfg['sub_apps'])) {
            foreach ($domainCfg['sub_apps'] as $route => $subCfg) {
                $r = '/' . trim($route, '/');
                $sName = $subCfg['app_name'] ?? basename($r);
                $sType = $subCfg['type'] ?? 'non_spp';
                $targetPath = $subCfg['target_path'] ?? '';
                $subDataDir = $subCfg['data_dir'] ?? ($customDataDir ? "{$customDataDir}/{$sName}" : '');
                $subApps[] = [
                    'name' => $sName,
                    'title' => $subCfg['title'] ?? ucfirst($sName),
                    'type' => $sType,
                    'route' => $r,
                    'target_path' => $targetPath,
                    'data_dir' => $subDataDir,
                    'index' => $subCfg['index'] ?? 'index.php',
                    'opt_in_sppdata' => !empty($subCfg['opt_in_sppdata']),
                    'is_default' => false,
                ];
            }
        }

        // 3. Enabled SPP apps (if not default and not in subApps)
        $enabledApps = $domainCfg['enabled_apps'] ?? [];
        $registeredSubNames = array_map(fn($s) => strtolower($s['name']), $subApps);
        $registeredSubNames[] = strtolower($defaultApp['name']);

        $otherApps = [];
        $allApps = class_exists('\\SPP\\App') ? (\SPP\App::getGlobalSettings()['apps'] ?? []) : [];
        foreach ($enabledApps as $eApp) {
            if (!in_array(strtolower($eApp), $registeredSubNames, true)) {
                $meta = $allApps[$eApp] ?? [];
                $otherApps[] = [
                    'name' => $eApp,
                    'title' => $meta['title'] ?? ucfirst($eApp),
                    'type' => 'spp',
                    'route' => '/' . $eApp,
                    'target_path' => $meta['src_path'] ?? "src/{$eApp}",
                    'data_dir' => $customDataDir ? "{$customDataDir}/{$eApp}" : ($meta['data_path'] ?? "var/data/{$eApp}"),
                    'index' => 'index.php',
                    'opt_in_sppdata' => true,
                    'is_default' => false,
                ];
            }
        }

        // 4. Combined domain apps
        $domainApps = array_merge([$defaultApp], $subApps, $otherApps);

        // 5. Available apps to install or add (all framework apps)
        $availableFrameworkApps = [];
        foreach ($allApps as $name => $meta) {
            $availableFrameworkApps[] = [
                'name' => $name,
                'title' => $meta['title'] ?? ucfirst($name),
                'type' => $meta['type'] ?? 'native',
                'src_path' => $meta['src_path'] ?? "src/{$name}",
            ];
        }

        // 6. Discover folders in account apps directory
        $accountAppFolders = [];
        if ($customAppDir && is_dir($customAppDir)) {
            foreach (@scandir($customAppDir) ?: [] as $folder) {
                if ($folder === '.' || $folder === '..' || !is_dir("{$customAppDir}/{$folder}")) continue;
                if (str_starts_with($folder, '.') || str_starts_with($folder, '_')) continue;
                $accountAppFolders[] = [
                    'folder' => $folder,
                    'path' => "{$customAppDir}/{$folder}",
                    'has_manifest' => file_exists("{$customAppDir}/{$folder}/__spp_file_props.yml"),
                    'has_app_yml' => file_exists("{$customAppDir}/{$folder}/etc/app.yml") || file_exists("{$customAppDir}/{$folder}/app.yml"),
                    'has_index' => file_exists("{$customAppDir}/{$folder}/index.php") || file_exists("{$customAppDir}/{$folder}/index.html"),
                ];
            }
        }

        return [
            'success' => true,
            'domain' => $domain,
            'account' => $account,
            'app_dir' => $customAppDir,
            'data_dir' => $customDataDir,
            'default_app' => $defaultApp,
            'sub_apps' => $subApps,
            'domain_apps' => $domainApps,
            'available_framework_apps' => $availableFrameworkApps,
            'account_app_folders' => $accountAppFolders,
            'suggested_defaults' => [
                'app_dir' => $customAppDir ?: ($account !== 'default' ? "/home/{$account}/domains/{$domain}/apps" : "domains/{$domain}/apps"),
                'data_dir' => $customDataDir ?: ($account !== 'default' ? "/home/{$account}/domains/{$domain}/sppdata" : "domains/{$domain}/sppdata"),
            ]
        ];
    }

    /**
     * Accurately computes and scaffolds domain application and persistent data directories.
     * Enforces domain isolation under Linux user homes (/home/<account>/domains/<domain>/apps)
     * or project base (domains/<domain>/apps).
     * Initializes __spp_file_props.yml contract in each directory.
     *
     * @param string $domain Domain name (e.g. example.com)
     * @param string $account Linux user account (e.g. webuser, or default)
     * @param string|null $appDir Custom apps directory override
     * @param string|null $dataDir Custom sppdata directory override
     * @param bool $createDirs Whether to physically scaffold the directories and contracts on disk
     * @return array
     */
    public static function scaffoldDomainPaths(string $domain, string $account = 'default', ?string $appDir = null, ?string $dataDir = null, bool $createDirs = true): array
    {
        $cleanDomain = strtolower(trim($domain));
        $cleanDomain = preg_replace('#^https?://#i', '', $cleanDomain);
        $cleanDomain = rtrim($cleanDomain, '/');
        $cleanAccount = trim($account);

        if ($cleanDomain === '') {
            return ['success' => false, 'error' => 'A valid domain name is required to scaffold paths.'];
        }

        if ($cleanAccount === '') {
            $parts = explode('.', $cleanDomain);
            $cleanAccount = $parts[0] !== '' ? $parts[0] : 'default';
        }

        // Calculate accurate canonical paths
        if ($cleanAccount !== 'default') {
            $computedAppDir = $appDir ?: "/home/{$cleanAccount}/domains/{$cleanDomain}/apps";
            $computedDataDir = $dataDir ?: "/home/{$cleanAccount}/domains/{$cleanDomain}/sppdata";
        } else {
            $computedAppDir = $appDir ?: "domains/{$cleanDomain}/apps";
            $computedDataDir = $dataDir ?: "domains/{$cleanDomain}/sppdata";
        }

        $computedAppDir = rtrim(str_replace('\\', '/', $computedAppDir), '/');
        $computedDataDir = rtrim(str_replace('\\', '/', $computedDataDir), '/');

        $created = [];
        $scaffoldRoot = self::$scaffoldRoot !== null
            ? rtrim(str_replace('\\', '/', self::$scaffoldRoot), '/')
            : (defined('SPP_SCAFFOLD_ROOT') ? rtrim(str_replace('\\', '/', (string)SPP_SCAFFOLD_ROOT), '/') : '');

        if ($createDirs) {
            foreach ([$computedAppDir => 'applications', $computedDataDir => 'data'] as $dir => $type) {
                // If relative path or scaffold root set, resolve appropriately
                if ($scaffoldRoot !== '') {
                    $fullDir = $scaffoldRoot . '/' . ltrim(preg_replace('#^[a-zA-Z]:#', '', $dir), '/');
                } else {
                    $fullDir = (str_starts_with($dir, '/') || preg_match('#^[a-zA-Z]:#', $dir))
                        ? $dir
                        : ((defined('SPP_APP_DIR') ? SPP_APP_DIR : getcwd()) . '/' . $dir);
                }

                if (!is_dir($fullDir)) {
                    if (@mkdir($fullDir, 0775, true)) {
                        $created[] = $dir;
                    }
                }

                // Ensure __spp_file_props.yml contract exists
                $propsFile = $fullDir . '/__spp_file_props.yml';
                if (is_dir($fullDir) && !file_exists($propsFile)) {
                    $desc = ($type === 'applications')
                        ? "Domain application root for {$cleanDomain} owned by {$cleanAccount}"
                        : "Persistent sovereign sppdata storage for {$cleanDomain}";
                    $propsContent = "# SPP Manifest OS Contract\n"
                        . "version: '2.0'\n"
                        . "directory:\n"
                        . "  description: '{$desc}'\n"
                        . "  stability: stable\n"
                        . "  security:\n"
                        . "    domain: '{$cleanDomain}'\n"
                        . "    account: '{$cleanAccount}'\n"
                        . "    scope: '" . ($type === 'applications' ? 'domain_apps' : 'domain_data') . "'\n"
                        . "files: {}\n";
                    @file_put_contents($propsFile, $propsContent);
                    @chmod($propsFile, 0664);
                }
            }
        }

        return [
            'success' => true,
            'domain' => $cleanDomain,
            'account' => $cleanAccount,
            'app_dir' => $computedAppDir,
            'data_dir' => $computedDataDir,
            'created' => $created,
            'message' => "Paths accurately scaffolded for '{$cleanDomain}' (account: {$cleanAccount})"
        ];
    }

    /**
     * Installs or attaches an application (SPP or Non-SPP) to a particular domain.
     * Enforces NO RESTRICTIONS on directory names.
     * Persists __spp_file_props.yml in the application directory.
     */
    public static function installDomainApp(string $domain, string $appName, array $options = []): array
    {
        $cleanDomain = strtolower(trim($domain));
        $cleanApp = trim($appName);

        if ($cleanDomain === '') {
            return ['success' => false, 'error' => 'Domain name cannot be empty.'];
        }
        if ($cleanApp === '' || !preg_match('/^[a-zA-Z0-9_\-]+$/', $cleanApp)) {
            return ['success' => false, 'error' => 'Application name must contain only letters, numbers, dashes, and underscores.'];
        }

        DomainRouter::loadConfig();
        $domainCfg = DomainRouter::getDomain($cleanDomain);
        if (!$domainCfg) {
            return ['success' => false, 'error' => "Domain '{$cleanDomain}' does not exist in domains.yml."];
        }

        $account = $domainCfg['account'] ?? 'default';
        $type = strtolower(trim((string)($options['type'] ?? 'spp')));
        if (!in_array($type, ['spp', 'non_spp'], true)) {
            $type = 'spp';
        }

        // Resolve Application Directory (Zero naming or location restrictions!)
        $targetPath = trim((string)($options['target_path'] ?? ($options['directory'] ?? ($options['src_path'] ?? ''))));
        if ($targetPath === '') {
            if ($account !== 'default' && is_dir("/home/{$account}/apps")) {
                $targetPath = "/home/{$account}/apps/{$cleanApp}";
            } else {
                $baseDir = defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 4);
                $targetPath = $baseDir . DIRECTORY_SEPARATOR . 'domains' . DIRECTORY_SEPARATOR . $cleanDomain . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $cleanApp;
            }
        }
        $targetPath = rtrim(str_replace('\\', '/', $targetPath), '/');

        // Resolve Data Directory (Zero restriction!)
        $dataDir = trim((string)($options['data_dir'] ?? ''));
        if ($dataDir === '') {
            if ($account !== 'default' && is_dir("/home/{$account}/sppdata")) {
                $dataDir = "/home/{$account}/sppdata/{$cleanDomain}/{$cleanApp}";
            } elseif (!empty($domainCfg['data_dir'])) {
                $dataDir = rtrim(str_replace('\\', '/', $domainCfg['data_dir']), '/') . '/' . $cleanApp;
            } else {
                $dataDir = (defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 4)) . "/var/data/{$cleanDomain}/{$cleanApp}";
            }
        }
        $dataDir = rtrim(str_replace('\\', '/', $dataDir), '/');

        $baseDir = defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 4);
        $allowedAppRoots = [$baseDir . DIRECTORY_SEPARATOR . 'domains'];
        $allowedDataRoots = [$baseDir . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'data'];
        if (!empty($domainCfg['app_dir'])) {
            $allowedAppRoots[] = (string)$domainCfg['app_dir'];
        }
        if (!empty($domainCfg['data_dir'])) {
            $allowedDataRoots[] = (string)$domainCfg['data_dir'];
        }
        foreach (array_merge($allowedAppRoots, $allowedDataRoots) as $root) {
            if (!is_dir($root)) {
                @mkdir($root, 0775, true);
            }
        }
        if (!self::validManagedPath($targetPath, $allowedAppRoots) || !self::validManagedPath($dataDir, $allowedDataRoots) || !self::validEntryFile($index)) {
            return ['success' => false, 'error' => 'Application paths must stay within this domain\'s managed app/data directories and the entry file must be a basename.'];
        }

        // Generated source files embed this display label; strip characters that
        // could terminate a PHP/YAML string literal.
        $title = preg_replace('/[\r\n\\\\\'"`$]/', '', trim((string)($options['title'] ?? ucfirst($cleanApp))));

        // Ensure directories exist on disk
        if (!is_dir($targetPath)) {
            @mkdir($targetPath, 0775, true);
        }
        if (!is_dir($dataDir)) {
            @mkdir($dataDir, 0777, true);
            @chmod($dataDir, 0777);
        }

        $optInSppdata = !empty($options['opt_in_sppdata']);
        $isDefault = !empty($options['is_default']) || !empty($options['make_default']);
        $route = '/' . trim((string)($options['route'] ?? $cleanApp), '/');

        // 1. Mark directory as an app directory in __spp_file_props.yml
        $propsPath = $targetPath . DIRECTORY_SEPARATOR . '__spp_file_props.yml';
        $manifestData = [
            'version' => '1.0',
            'directory' => [
                'description' => "Application directory for {$cleanApp}",
                'usage' => "Domain-bound application for {$cleanDomain}",
                'is_app' => true,
                'app_name' => $cleanApp,
                'app_type' => $type,
                'domain' => $cleanDomain,
                'data_dir' => $dataDir,
                'index' => $index,
                'opt_in_sppdata' => $optInSppdata,
                'attributes' => [
                    'is_app' => true,
                    'app_type' => $type,
                    'tags' => ['app', $type, $cleanDomain],
                    'stability' => 'stable'
                ]
            ],
            'files' => [],
            'directories' => []
        ];
        if (file_exists($propsPath)) {
            try {
                $existing = Yaml::parseFile($propsPath);
                if (is_array($existing)) {
                    $manifestData = array_replace_recursive($existing, $manifestData);
                }
            } catch (\Throwable $e) {}
        }
        @file_put_contents($propsPath, Yaml::dump($manifestData, 4, 2));

        // Update parent directory manifest if present
        $parentDir = dirname($targetPath);
        $parentPropsPath = $parentDir . DIRECTORY_SEPARATOR . '__spp_file_props.yml';
        if (file_exists($parentPropsPath) || is_dir($parentDir)) {
            try {
                $pData = file_exists($parentPropsPath) ? (array)Yaml::parseFile($parentPropsPath) : ['version' => '1.0', 'directory' => [], 'files' => [], 'directories' => []];
                $dirBasename = basename($targetPath);
                $pData['directories'][$dirBasename] = [
                    'is_app' => true,
                    'app_name' => $cleanApp,
                    'app_type' => $type,
                    'domain' => $cleanDomain,
                    'data_dir' => $dataDir
                ];
                @file_put_contents($parentPropsPath, Yaml::dump($pData, 4, 2));
            } catch (\Throwable $e) {}
        }

        // 2. Scaffolding if directory is empty or scaffold requested
        $scaffold = !isset($options['scaffold']) || !empty($options['scaffold']);
        $isEmpty = count(array_diff(@scandir($targetPath) ?: [], ['.', '..', '__spp_file_props.yml'])) === 0;

        if ($type === 'spp') {
            if ($isEmpty && $scaffold) {
                foreach (['etc', 'Controllers', 'resources/views/layouts'] as $sub) {
                    @mkdir($targetPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $sub), 0775, true);
                }

                $appYml = "name: {$cleanApp}\n" .
                    "title: '" . addslashes($title) . "'\n" .
                    "domain: {$cleanDomain}\n" .
                    "is_domain_app: true\n" .
                    "domain_only: true\n" .
                    "allowed_domains:\n  - {$cleanDomain}\n" .
                    "src_path: {$targetPath}\n" .
                    "etc_path: {$targetPath}/etc\n" .
                    "data_path: {$dataDir}\n";
                if (!empty($options['table_prefix'])) {
                    $appYml .= "table_prefix: '" . addslashes(trim($options['table_prefix'])) . "'\n";
                }
                if (!empty($options['shared_group'])) {
                    $appYml .= "shared_group: '" . addslashes(trim($options['shared_group'])) . "'\n";
                }
                file_put_contents($targetPath . '/etc/app.yml', $appYml);

                $routesYml = "routes:\n" .
                    "  - path: /\n" .
                    "    methods: [GET]\n" .
                    "    target: App\\{$cleanApp}\\Controllers\\HomeController@index\n" .
                    "    name: {$cleanApp}.home\n";
                file_put_contents($targetPath . '/etc/routes.yml', $routesYml);

                $ctrlCode = "<?php\ndeclare(strict_types=1);\n\nnamespace App\\{$cleanApp}\\Controllers;\n\nuse SPPMod\\SPPView\\ViewController;\nuse SPPMod\\SPPView\\Attributes\\Route;\n\nclass HomeController extends ViewController\n{\n    #[Route('/', methods: ['GET'], name: '{$cleanApp}.home')]\n    public function index(...\$args)\n    {\n        return \$this->render('home.blade.php', [\n            'appName' => '{$cleanApp}',\n            'domain' => '{$cleanDomain}',\n            'title' => '{$title}',\n            'time' => date('Y-m-d H:i:s')\n        ]);\n    }\n}\n";
                file_put_contents($targetPath . '/Controllers/HomeController.php', $ctrlCode);

                $layoutCode = "<!DOCTYPE html>\n<html>\n<head>\n    <meta charset=\"utf-8\">\n    <title>{{ \$title ?? 'Domain App' }} - {{ \$domain }}</title>\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n    <link rel=\"stylesheet\" href=\"{{ APP_BASE_URI }}/sppadmin/css/admin.css\">\n</head>\n<body>\n    @yield('content')\n</body>\n</html>\n";
                file_put_contents($targetPath . '/resources/views/layouts/app.blade.php', $layoutCode);

                $viewCode = "@extends('layouts/app.blade.php', ['title' => \$title, 'domain' => \$domain])\n\n@section('content')\n<div style=\"font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 800px; margin: 40px auto; padding: 28px; border: 1px solid #e2e8f0; border-radius: 12px; background: #ffffff; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.08);\">\n    <span style=\"background: #0284c7; color: #fff; font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 4px; text-transform: uppercase;\">SPP Domain Application</span>\n    <h1 style=\"color: #0f172a; margin: 12px 0 6px 0; font-size: 26px;\">🌐 {{ \$title }}</h1>\n    <p style=\"color: #475569; font-size: 15px;\">Active on domain <strong style=\"color: #0284c7;\">{{ \$domain }}</strong></p>\n    <div style=\"background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 18px; margin: 20px 0; font-size: 13px; color: #334155;\">\n        <strong>Path:</strong> <code>{$targetPath}</code><br>\n        <strong>Data:</strong> <code>{$dataDir}</code>\n    </div>\n</div>\n@endsection\n";
                file_put_contents($targetPath . '/resources/views/home.blade.php', $viewCode);
            }
        } elseif ($type === 'non_spp') {
            if ($isEmpty && $scaffold) {
                $starterIndex = "<?php\n// Standalone Application ({$cleanApp}) on {$cleanDomain}\n?>\n<!DOCTYPE html>\n<html>\n<head>\n    <meta charset=\"utf-8\">\n    <title><?php echo htmlspecialchars('{$title}'); ?> - <?php echo htmlspecialchars('{$cleanDomain}'); ?></title>\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n</head>\n<body style=\"font-family: -apple-system, BlinkMacSystemFont, sans-serif; max-width: 800px; margin: 40px auto; padding: 24px;\">\n    <h1>🌐 <?php echo htmlspecialchars('{$title}'); ?></h1>\n    <p>Host: <code><?php echo htmlspecialchars(\$_SERVER['HTTP_HOST'] ?? '{$cleanDomain}'); ?></code></p>\n    <p>Target Path: <code>{$targetPath}</code></p>\n    <p>Data Path: <code>{$dataDir}</code></p>\n</body>\n</html>\n";
                file_put_contents($targetPath . '/' . $index, $starterIndex);
            }
        }

        // 3. Register routing in domains.yml
        if (!isset($domainCfg['sub_apps']) || !is_array($domainCfg['sub_apps'])) {
            $domainCfg['sub_apps'] = [];
        }
        if (!isset($domainCfg['enabled_apps']) || !is_array($domainCfg['enabled_apps'])) {
            $domainCfg['enabled_apps'] = [];
        }

        if ($isDefault) {
            // Demote previous default app if configured
            $prevDefault = $domainCfg['default_app'] ?? null;
            $prevName = is_array($prevDefault) ? ($prevDefault['app_name'] ?? 'default') : (string)$prevDefault;
            if ($prevName && strcasecmp($prevName, $cleanApp) !== 0 && $prevName !== 'default') {
                $demotedRoute = '/' . trim((string)($options['previous_default_route'] ?: $prevName), '/');
                $domainCfg['sub_apps'][$demotedRoute] = is_array($prevDefault) ? $prevDefault : [
                    'route' => $demotedRoute,
                    'type' => 'spp',
                    'app_name' => $prevName,
                    'target_path' => "src/{$prevName}",
                    'data_dir' => "{$dataDir}/../{$prevName}",
                    'index' => 'index.php',
                    'opt_in_sppdata' => true
                ];
            }

            if ($type === 'non_spp') {
                $domainCfg['default_app'] = [
                    'type' => 'non_spp',
                    'app_name' => $cleanApp,
                    'target_path' => $targetPath,
                    'data_dir' => $dataDir,
                    'index' => $index,
                    'opt_in_sppdata' => $optInSppdata,
                ];
            } else {
                $domainCfg['default_app'] = $cleanApp;
            }

            // Remove from sub_apps if it was previously a sub_app
            foreach ($domainCfg['sub_apps'] as $r => $sub) {
                if (strcasecmp($sub['app_name'] ?? '', $cleanApp) === 0) {
                    unset($domainCfg['sub_apps'][$r]);
                }
            }
        } else {
            // Secondary application with base route
            $domainCfg['sub_apps'][$route] = [
                'route' => $route,
                'type' => $type,
                'app_name' => $cleanApp,
                'target_path' => $targetPath,
                'data_dir' => $dataDir,
                'index' => $index,
                'opt_in_sppdata' => $optInSppdata,
            ];
        }

        if (!in_array($cleanApp, $domainCfg['enabled_apps'], true)) {
            $domainCfg['enabled_apps'][] = $cleanApp;
        }

        DomainRouter::setDomain($cleanDomain, $domainCfg);
        DomainRouter::saveConfig();
        DomainRouter::clearConfigCaches();

        return [
            'success' => true,
            'message' => "Application '{$cleanApp}' successfully installed on '{$cleanDomain}' " . ($isDefault ? 'as Default App (Root /).' : "at route '{$route}'."),
            'app' => [
                'name' => $cleanApp,
                'domain' => $cleanDomain,
                'type' => $type,
                'target_path' => $targetPath,
                'data_dir' => $dataDir,
                'is_default' => $isDefault,
                'route' => $isDefault ? '/' : $route,
                'index' => $index,
                'opt_in_sppdata' => $optInSppdata,
            ]
        ];
    }

    /**
     * Promotes an application to become the Default Application for a domain (served at root /).
     * Smoothly assigns a base route to the demoted previous default app.
     */
    public static function setDomainDefaultApp(string $domain, string $appName, ?string $demotedRoute = null): array
    {
        $cleanDomain = strtolower(trim($domain));
        $cleanApp = trim($appName);

        DomainRouter::loadConfig();
        $domainCfg = DomainRouter::getDomain($cleanDomain);
        if (!$domainCfg) {
            return ['success' => false, 'error' => "Domain '{$cleanDomain}' not found."];
        }

        $prevDefault = $domainCfg['default_app'] ?? 'default';
        $prevName = is_array($prevDefault) ? ($prevDefault['app_name'] ?? 'default') : (string)$prevDefault;

        // Check if the target app is in sub_apps
        $foundSubApp = null;
        if (!empty($domainCfg['sub_apps']) && is_array($domainCfg['sub_apps'])) {
            foreach ($domainCfg['sub_apps'] as $r => $sub) {
                if (strcasecmp($sub['app_name'] ?? '', $cleanApp) === 0) {
                    $foundSubApp = $sub;
                    unset($domainCfg['sub_apps'][$r]);
                    break;
                }
            }
        }

        // Demote previous default app to a sub-route if valid
        if ($prevName && strcasecmp($prevName, $cleanApp) !== 0 && $prevName !== 'default') {
            $dRoute = '/' . trim((string)($demotedRoute ?: $prevName), '/');
            $domainCfg['sub_apps'][$dRoute] = is_array($prevDefault) ? $prevDefault : [
                'route' => $dRoute,
                'type' => 'spp',
                'app_name' => $prevName,
                'target_path' => "src/{$prevName}",
                'index' => 'index.php',
                'opt_in_sppdata' => true
            ];
        }

        // Set new default
        if ($foundSubApp && ($foundSubApp['type'] ?? '') === 'non_spp') {
            $domainCfg['default_app'] = [
                'type' => 'non_spp',
                'app_name' => $cleanApp,
                'target_path' => $foundSubApp['target_path'] ?? '',
                'data_dir' => $foundSubApp['data_dir'] ?? '',
                'index' => $foundSubApp['index'] ?? 'index.php',
                'opt_in_sppdata' => !empty($foundSubApp['opt_in_sppdata']),
            ];
        } else {
            $domainCfg['default_app'] = $cleanApp;
        }

        if (!in_array($cleanApp, $domainCfg['enabled_apps'] ?? [], true)) {
            $domainCfg['enabled_apps'][] = $cleanApp;
        }

        DomainRouter::setDomain($cleanDomain, $domainCfg);
        DomainRouter::saveConfig();
        DomainRouter::clearConfigCaches();

        return [
            'success' => true,
            'message' => "Application '{$cleanApp}' is now the default root application for '{$cleanDomain}'.",
            'default_app' => $domainCfg['default_app']
        ];
    }

    /**
     * Sets or toggles domain restrictions for an application.
     */
    public static function setAppDomainScope(string $appName, bool $domainOnly, array $allowedDomains = []): array
    {
        $res = DomainRouter::setAppDomainScope($appName, $domainOnly, $allowedDomains);
        return [
            'success' => $res,
            'app' => $appName,
            'domain_only' => $domainOnly,
            'allowed_domains' => $allowedDomains,
            'message' => $domainOnly
                ? "Application '{$appName}' is now restricted to domain(s): " . implode(', ', $allowedDomains)
                : "Application '{$appName}' is now globally accessible across domains."
        ];
    }

    /**
     * Removes or unlinks a domain application.
     */
    public static function deleteDomainApp(string $domain, string $appName, bool $deleteFiles = false): array
    {
        $cleanDomain = strtolower(trim($domain));
        $cleanApp = trim($appName);
        DomainRouter::loadConfig();
        $domainCfg = DomainRouter::getDomain($cleanDomain);

        if ($domainCfg) {
            // Remove from sub_apps if present
            if (!empty($domainCfg['sub_apps']) && is_array($domainCfg['sub_apps'])) {
                foreach ($domainCfg['sub_apps'] as $r => $sub) {
                    if (strcasecmp($sub['app_name'] ?? '', $cleanApp) === 0) {
                        unset($domainCfg['sub_apps'][$r]);
                    }
                }
            }
            // Reset default app if it was default
            $curDef = $domainCfg['default_app'] ?? null;
            $curDefName = is_array($curDef) ? ($curDef['app_name'] ?? '') : (string)$curDef;
            if (strcasecmp($curDefName, $cleanApp) === 0) {
                $domainCfg['default_app'] = 'default';
            }
            // Remove from enabled_apps
            if (!empty($domainCfg['enabled_apps'])) {
                $domainCfg['enabled_apps'] = array_values(array_filter($domainCfg['enabled_apps'], fn($a) => strcasecmp($a, $cleanApp) !== 0));
            }
            DomainRouter::setDomain($cleanDomain, $domainCfg);
            DomainRouter::saveConfig();
        }

        $res = DomainRouter::removeDomainApp($domain, $appName, $deleteFiles);
        return [
            'success' => true,
            'message' => "Application '{$appName}' removed from domain '{$domain}'."
        ];
    }

    // =========================================================================
    // SOVEREIGN CLOUDFLARE EDGE DNS & EDGE SSL BRIDGE
    // =========================================================================

    /**
     * Retrieves Cloudflare settings.
     */
    public static function getCloudflareSettings(): array
    {
        return [
            'success' => true,
            'settings' => CloudflareBridge::getSafeSettings()
        ];
    }

    /**
     * Saves Cloudflare configuration.
     */
    public static function saveCloudflareSettings(array $settings): array
    {
        return CloudflareBridge::saveSettings($settings);
    }

    /**
     * Disconnects Cloudflare Sovereign Edge Bridge and clears token.
     */
    public static function disconnectCloudflare(): array
    {
        return CloudflareBridge::disconnect();
    }

    /**
     * Verifies API token connectivity with Cloudflare.
     */
    public static function verifyCloudflareToken(?string $token = null): array
    {
        return CloudflareBridge::verifyToken($token);
    }

    /**
     * Discovers all zones (apex domains) in the Cloudflare account.
     */
    public static function listCloudflareZones(): array
    {
        return CloudflareBridge::listZones();
    }

    /**
     * Lists live DNS records from Cloudflare Edge for a domain.
     */
    public static function listCloudflareRecords(string $domain, array $filters = []): array
    {
        return CloudflareBridge::listRecords($domain, $filters);
    }

    /**
     * Creates a DNS record on Cloudflare Edge.
     */
    public static function createCloudflareRecord(string $domain, array $record): array
    {
        return CloudflareBridge::createRecord($domain, $record);
    }

    /**
     * Updates an existing DNS record on Cloudflare Edge.
     */
    public static function updateCloudflareRecord(string $domain, string $recordId, array $record): array
    {
        return CloudflareBridge::updateRecord($domain, $recordId, $record);
    }

    /**
     * Deletes a DNS record from Cloudflare Edge.
     */
    public static function deleteCloudflareRecord(string $domain, string $recordId): array
    {
        return CloudflareBridge::deleteRecord($domain, $recordId);
    }

    /**
     * Purges Cloudflare Edge CDN cache for a domain.
     */
    public static function purgeCloudflareCache(string $domain, bool $purgeEverything = true, array $files = []): array
    {
        return CloudflareBridge::purgeCache($domain, $purgeEverything, $files);
    }

    /**
     * Synchronizes live Cloudflare records down to local SPP DnsZoneManager.
     */
    public static function syncFromCloudflare(string $domain): array
    {
        return CloudflareBridge::syncFromCloudflare($domain);
    }

    /**
     * Auto-provisions a subdomain (e.g. app.example.com or relative "mail") directly on Cloudflare Edge.
     */
    public static function autoProvisionCloudflare(string $hostname, ?string $targetIp = null, bool $proxied = true, ?string $zoneDomain = null): array
    {
        return CloudflareBridge::autoProvisionSubdomain($hostname, $targetIp, $proxied, $zoneDomain);
    }

    /**
     * Inspects the rendered virtual host configuration for a domain without saving.
     */
    public static function inspectVhost(string $domain, array $options = []): array
    {
        $domain = strtolower(trim($domain));
        DomainRouter::loadConfig();
        $all = DomainRouter::getAllDomains();
        $existing = $all[$domain] ?? DomainRouter::resolveDomain($domain) ?? [];
        $merged = array_merge($existing, $options);

        $driver = WebserverDriverResolver::resolve($options['driver'] ?? null);
        $content = $driver->generateVhost($domain, $merged);

        return [
            'success' => true,
            'domain'  => $domain,
            'driver'  => $driver->getName(),
            'driver_label' => $driver->getLabel(),
            'content' => $content
        ];
    }

    /**
     * Tests web server syntax on the active host.
     */
    public static function testVhostConfig(?string $content = null, ?string $driverName = null): array
    {
        $driver = WebserverDriverResolver::resolve($driverName);
        return array_merge(['success' => true, 'driver' => $driver->getName()], $driver->testConfig($content));
    }

    /**
     * Purges FastCGI or microcache for a domain.
     */
    public static function purgeFastCgiCache(string $domain): array
    {
        $driver = WebserverDriverResolver::resolve();
        $res = $driver->purgeCache($domain);
        return [
            'success' => $res['success'] ?? true,
            'purged_bytes' => $res['purged_bytes'] ?? 0,
            'message' => "FastCGI cache purged for '{$domain}'."
        ];
    }

    /**
     * Lists all available web server drivers on the host.
     */
    public static function listWebserverDrivers(): array
    {
        return [
            'success' => true,
            'active_driver' => WebserverDriverResolver::resolve()->getName(),
            'drivers' => WebserverDriverResolver::getDriverInventory()
        ];
    }

    /**
     * Sets the preferred web server driver in etc/server.yml.
     */
    public static function setWebserverDriver(string $driverName): array
    {
        $driverName = strtolower(trim($driverName));
        $valid = ['auto', 'hybrid', 'nginx', 'apache', 'app_router'];
        if (!in_array($driverName, $valid, true)) {
            return ['success' => false, 'error' => "Invalid driver name '{$driverName}'. Valid: " . implode(', ', $valid)];
        }

        $settingsFile = (defined('SPP_BASE_DIR') ? SPP_BASE_DIR : '') . '/etc/server.yml';
        $data = file_exists($settingsFile) ? (Yaml::parseFile($settingsFile) ?: []) : [];
        $data['webserver_driver'] = $driverName;

        if (!is_dir(dirname($settingsFile))) {
            @mkdir(dirname($settingsFile), 0755, true);
        }
        @file_put_contents($settingsFile, Yaml::dump($data, 4, 2));
        WebserverDriverResolver::setActiveDriver(null); // Clear cache

        return [
            'success' => true,
            'active_driver' => WebserverDriverResolver::resolve()->getName(),
            'message' => "Web server driver set to '{$driverName}'."
        ];
    }

    /**
     * Queries authoritative TLD root nameservers directly for child nameserver glue records.
     * E.g. queries a.gtld-servers.net for ns1.example.com.
     */
    public static function queryTldRootGlue(string $nameserver): array
    {
        $nameserver = strtolower(trim($nameserver));
        if (empty($nameserver)) {
            return ['success' => false, 'error' => 'Nameserver cannot be empty.'];
        }

        // Determine TLD root nameserver based on domain suffix
        $tldServer = 'a.gtld-servers.net'; // Default for .com and .net
        if (str_ends_with($nameserver, '.org')) {
            $tldServer = 'a0.org.afilias-nst.info';
        } elseif (str_ends_with($nameserver, '.in')) {
            $tldServer = 'ns1.registry.in';
        } elseif (str_ends_with($nameserver, '.io')) {
            $tldServer = 'a0.nic.io';
        }

        $ip = null;
        $ttl = null;
        $rawOutput = '';

        if (Infra::isLinux() && (Infra::hasBinary('dig') || file_exists('/usr/bin/dig'))) {
            $out = [];
            $cmd = "dig @" . escapeshellarg($tldServer) . " " . escapeshellarg($nameserver) . " +noall +additional +time=3 +tries=1 2>&1";
            @exec($cmd, $out);
            $rawOutput = implode("\n", $out);

            foreach ($out as $line) {
                $line = trim($line);
                if (preg_match('/^' . preg_quote($nameserver, '/') . '\.?\s+(\d+)\s+IN\s+A\s+([0-9.]+)/i', $line, $m)) {
                    $ttl = (int)$m[1];
                    $ip = $m[2];
                    break;
                }
            }
        } else {
            // Windows / PHP native fallback
            $records = @dns_get_record($nameserver, DNS_A);
            if (!empty($records) && is_array($records)) {
                $ip = $records[0]['ip'] ?? null;
                $ttl = $records[0]['ttl'] ?? null;
            }
            $rawOutput = $ip ? "Resolved via DNS: {$nameserver} -> {$ip}" : "DNS resolution returned no A record";
        }

        return [
            'success' => true,
            'nameserver' => $nameserver,
            'tld_root_server' => $tldServer,
            'glue_ip' => $ip,
            'ttl' => $ttl,
            'has_glue' => ($ip !== null),
            'raw_output' => $rawOutput
        ];
    }

    /**
     * Performs a direct RFC 3912 port 43 WHOIS query to the authoritative TLD registry
     * to inspect child nameserver host objects (e.g. whois.verisign-grs.com for nameserver ns1.example.com).
     */
    public static function queryRegistryWhois(string $nameserver): array
    {
        $nameserver = strtolower(trim($nameserver));
        if (empty($nameserver)) {
            return ['success' => false, 'error' => 'Nameserver cannot be empty.'];
        }

        $whoisHost = 'whois.verisign-grs.com';
        if (str_ends_with($nameserver, '.org')) {
            $whoisHost = 'whois.publicinterestregistry.org';
        } elseif (str_ends_with($nameserver, '.in')) {
            $whoisHost = 'whois.registry.in';
        }

        $query = "nameserver {$nameserver}\r\n";
        $response = '';
        $fp = @fsockopen($whoisHost, 43, $errno, $errstr, 4);

        if (!$fp) {
            return [
                'success' => false,
                'nameserver' => $nameserver,
                'whois_server' => $whoisHost,
                'error' => "Could not connect to WHOIS server {$whoisHost}:43 ({$errstr})",
                'registered_ip' => null
            ];
        }

        stream_set_timeout($fp, 4);
        fwrite($fp, $query);
        while (!feof($fp)) {
            $chunk = fgets($fp, 1024);
            if ($chunk === false) break;
            $response .= $chunk;
            if (strlen($response) > 32768) break; // Safeguard
        }
        fclose($fp);

        // Parse parsed fields
        $registeredIp = null;
        $updatedDate = null;
        $creationDate = null;
        $registrar = null;
        $status = null;

        if (preg_match('/IP Address:\s*([0-9.]+)/i', $response, $m)) {
            $registeredIp = trim($m[1]);
        }
        if (preg_match('/Updated Date:\s*([^\r\n]+)/i', $response, $m)) {
            $updatedDate = trim($m[1]);
        }
        if (preg_match('/Creation Date:\s*([^\r\n]+)/i', $response, $m)) {
            $creationDate = trim($m[1]);
        }
        if (preg_match('/Registrar:\s*([^\r\n]+)/i', $response, $m)) {
            $registrar = trim($m[1]);
        }
        if (preg_match('/Status:\s*([^\r\n]+)/i', $response, $m)) {
            $status = trim($m[1]);
        }

        return [
            'success' => true,
            'nameserver' => $nameserver,
            'whois_server' => $whoisHost,
            'registered_ip' => $registeredIp,
            'updated_date' => $updatedDate,
            'creation_date' => $creationDate,
            'registrar' => $registrar,
            'status' => $status,
            'raw_whois' => trim($response)
        ];
    }

    /**
     * Compares Local BIND, TLD Root Glue, and Public Resolvers in a Tri-Level Diagnostic Matrix.
     * Identifies glue record mismatches and generates copy-paste registrar instructions.
     */
    public static function getGlueDiagnosticMatrix(string $domain, array $nameservers = [], ?string $expectedIp = null): array
    {
        $domain = DnsZoneManager::normalizeDomain($domain);
        if (empty($domain)) {
            $domain = $_SERVER['HTTP_HOST'] ?? 'example.com';
        }

        if (empty($nameservers)) {
            $nameservers = ["ns1.{$domain}", "ns2.{$domain}"];
        }

        // Detect expected host public IP if not provided
        if (empty($expectedIp)) {
            $expectedIp = self::detectHostPublicIp();
            if (empty($expectedIp) || $expectedIp === '127.0.0.1' || $expectedIp === '::1') {
                $expectedIp = $_SERVER['SERVER_ADDR'] ?? ($_SERVER['LOCAL_ADDR'] ?? (gethostbyname(gethostname()) ?: '127.0.0.1'));
            }
        }

        $matrix = [];
        $hasMismatch = false;
        $staleGlueIps = [];

        foreach ($nameservers as $ns) {
            $ns = strtolower(trim($ns));

            // Level 1: Local BIND / Host
            $localIp = null;
            if (Infra::isLinux()) {
                $locOut = [];
                @exec("dig @127.0.0.1 " . escapeshellarg($ns) . " +short +time=2 +tries=1 2>&1", $locOut);
                $localIp = trim($locOut[0] ?? '');
            }
            if (empty($localIp)) {
                $localRecs = @dns_get_record($ns, DNS_A);
                $localIp = $localRecs[0]['ip'] ?? $expectedIp;
            }

            // Level 2: TLD Root Glue (a.gtld-servers.net)
            $tldData = self::queryTldRootGlue($ns);
            $glueIp = $tldData['glue_ip'] ?? null;

            // Level 2b: Registry WHOIS Host Object
            $whoisData = self::queryRegistryWhois($ns);
            $registryIp = $whoisData['registered_ip'] ?? $glueIp;

            // Level 3: Recursive Public Resolver (Cloudflare 1.1.1.1 / Google 8.8.8.8)
            $publicIp = null;
            if (Infra::isLinux() && Infra::hasBinary('dig')) {
                $pubOut = [];
                @exec("dig @1.1.1.1 " . escapeshellarg($ns) . " +short +time=3 +tries=1 2>&1", $pubOut);
                $publicIp = trim($pubOut[0] ?? '');
            }
            if (empty($publicIp)) {
                $pubRecs = @dns_get_record($ns, DNS_A);
                $publicIp = $pubRecs[0]['ip'] ?? null;
            }

            // Evaluate status
            $isGlueAligned = ($glueIp !== null && $glueIp === $expectedIp);
            $isRegistryAligned = ($registryIp !== null && $registryIp === $expectedIp);
            $isPublicAligned = ($publicIp !== null && $publicIp === $expectedIp);

            if (!$isGlueAligned || !$isRegistryAligned) {
                $hasMismatch = true;
                if ($glueIp && $glueIp !== $expectedIp) {
                    $staleGlueIps[] = $glueIp;
                }
                if ($registryIp && $registryIp !== $expectedIp) {
                    $staleGlueIps[] = $registryIp;
                }
            }

            $matrix[] = [
                'nameserver' => $ns,
                'expected_ip' => $expectedIp,
                'local_bind_ip' => $localIp,
                'tld_glue_ip' => $glueIp,
                'registry_whois_ip' => $registryIp,
                'public_resolver_ip' => $publicIp,
                'is_glue_aligned' => $isGlueAligned,
                'is_registry_aligned' => $isRegistryAligned,
                'is_public_aligned' => $isPublicAligned,
                'status' => ($isGlueAligned && $isPublicAligned) ? 'READY' : ($isGlueAligned ? 'PROPAGATING' : 'GLUE_MISMATCH'),
                'tld_details' => $tldData,
                'whois_details' => $whoisData
            ];
        }

        $staleGlueIps = array_values(array_unique($staleGlueIps));
        $staleIpText = !empty($staleGlueIps) ? implode(', ', $staleGlueIps) : 'old/dead server IP';

        // Automated Registrar Instruction Message
        $registrarInstructions = <<<EOF
Subject: Urgent: Child Nameserver (Glue Record) IP Update for {$domain}

Hello Support Team,

Please update the registry child nameserver glue records for domain: {$domain} at the root TLD registry:

- Host: {$nameservers[0]} -> Target IP: {$expectedIp}
EOF;
        if (isset($nameservers[1])) {
            $registrarInstructions .= "\n- Host: {$nameservers[1]} -> Target IP: {$expectedIp}";
        }
        $registrarInstructions .= <<<EOF


Currently, our live query to root nameserver (a.gtld-servers.net) and registry WHOIS still returns the old IP ({$staleIpText}).
Please resubmit the Child Nameserver / Glue Host Object to the registry so our nameservers resolve to the new server IP ({$expectedIp}).

Thank you!
EOF;

        return [
            'success' => true,
            'domain' => $domain,
            'expected_ip' => $expectedIp,
            'has_glue_mismatch' => $hasMismatch,
            'stale_glue_ips' => $staleGlueIps,
            'matrix' => $matrix,
            'registrar_instructions' => $registrarInstructions,
            'cache_purge_links' => [
                'cloudflare' => 'https://1.1.1.1/purge-cache/',
                'google' => 'https://dns.google/cache'
            ]
        ];
    }

    public static function clusterReplicate(?string $zone = null): array
    {
        if (!class_exists(\SPPMod\SPPDeploy\Fleet\FleetClusterManager::class)) {
            $fcmPath = dirname(__DIR__, 2) . '/modules/optional/sppdeploy/src/Fleet/FleetClusterManager.php';
            if (file_exists($fcmPath)) {
                require_once $fcmPath;
            }
        }
        if (class_exists(\SPPMod\SPPDeploy\Fleet\FleetClusterManager::class)) {
            return \SPPMod\SPPDeploy\Fleet\FleetClusterManager::syncDnsAcrossCluster($zone);
        }
        return ['success' => false, 'error' => 'FleetClusterManager is not available.'];
    }

    public static function clusterStatus(?string $zone = null): array
    {
        if (!class_exists(\SPPMod\SPPDeploy\Fleet\FleetClusterManager::class)) {
            $fcmPath = dirname(__DIR__, 2) . '/modules/optional/sppdeploy/src/Fleet/FleetClusterManager.php';
            if (file_exists($fcmPath)) {
                require_once $fcmPath;
            }
        }
        if (!class_exists(\SPPMod\SPPDeploy\Fleet\FleetClusterManager::class)) {
            return ['success' => false, 'error' => 'FleetClusterManager is not available.'];
        }
        $nodes = \SPPMod\SPPDeploy\Fleet\FleetClusterManager::listNodes();
        $dnsNodes = [];
        foreach ($nodes as $node) {
            $tags = $node['tags'] ?? [];
            $role = $node['role'] ?? '';
            if ($role === 'dns' || in_array('dns', $tags, true) || in_array('nameserver', $tags, true)) {
                $dnsNodes[] = [
                    'id' => $node['id'] ?? '',
                    'name' => $node['name'] ?? '',
                    'ip' => $node['ip'] ?? '',
                    'status' => $node['status'] ?? 'unknown',
                    'last_sync' => $node['last_dns_sync'] ?? ($node['updated_at'] ?? 'Never'),
                    'sync_status' => ($node['status'] ?? '') === 'online' ? 'synced' : 'pending'
                ];
            }
        }
        return [
            'success' => true,
            'zone' => $zone,
            'total_dns_nodes' => count($dnsNodes),
            'nodes' => $dnsNodes
        ];
    }

    public static function applyZoneTemplate(string $domain, string $templateType, array $customParams = []): array
    {
        $domain = trim($domain);
        if ($domain === '') {
            return ['success' => false, 'error' => 'Domain is required.'];
        }
        $ip = $customParams['ip'] ?? '127.0.0.1';
        $mailHost = $customParams['mail_host'] ?? ('mail.' . $domain);
        
        $records = [];
        switch ($templateType) {
            case 'web_standard':
                $records = [
                    ['type' => 'A', 'name' => '@', 'content' => $ip, 'ttl' => 300],
                    ['type' => 'CNAME', 'name' => 'www', 'content' => $domain . '.', 'ttl' => 300],
                    ['type' => 'MX', 'name' => '@', 'content' => $mailHost, 'priority' => 10, 'ttl' => 3600],
                    ['type' => 'TXT', 'name' => '@', 'content' => 'v=spf1 a mx ip4:' . $ip . ' ~all', 'ttl' => 3600]
                ];
                break;
            case 'mail_only':
                $records = [
                    ['type' => 'MX', 'name' => '@', 'content' => $mailHost, 'priority' => 10, 'ttl' => 3600],
                    ['type' => 'TXT', 'name' => '@', 'content' => 'v=spf1 mx ~all', 'ttl' => 3600],
                    ['type' => 'TXT', 'name' => '_dmarc', 'content' => 'v=DMARC1; p=quarantine; sp=quarantine; pct=100', 'ttl' => 3600],
                    ['type' => 'CNAME', 'name' => 'autodiscover', 'content' => $mailHost . '.', 'ttl' => 3600],
                    ['type' => 'CNAME', 'name' => 'autoconfig', 'content' => $mailHost . '.', 'ttl' => 3600]
                ];
                break;
            case 'redundant_mx':
                $secMail = $customParams['secondary_mail_host'] ?? ('mail2.' . $domain);
                $records = [
                    ['type' => 'MX', 'name' => '@', 'content' => $mailHost, 'priority' => 10, 'ttl' => 3600],
                    ['type' => 'MX', 'name' => '@', 'content' => $secMail, 'priority' => 20, 'ttl' => 3600],
                    ['type' => 'TXT', 'name' => '@', 'content' => 'v=spf1 mx ~all', 'ttl' => 3600]
                ];
                break;
            case 'cdn_proxied':
                $cnameTarget = $customParams['cdn_target'] ?? 'cdn.cloudflare.net.';
                $records = [
                    ['type' => 'CNAME', 'name' => 'www', 'content' => $cnameTarget, 'ttl' => 300, 'proxied' => true],
                    ['type' => 'A', 'name' => '@', 'content' => $ip, 'ttl' => 300, 'proxied' => true]
                ];
                break;
            default:
                return ['success' => false, 'error' => "Unknown zone template type: {$templateType}"];
        }

        $zone = DnsZoneManager::getZone($domain);
        $added = 0;
        foreach ($records as $r) {
            DnsZoneManager::addRecord($domain, $r);
            $added++;
        }

        return [
            'success' => true,
            'template' => $templateType,
            'records_added' => $added,
            'domain' => $domain
        ];
    }

    public static function getSplitHorizon(string $domain): array
    {
        $base = defined('SPP_BASE_DIR') ? SPP_BASE_DIR : dirname(__DIR__, 4);
        $file = $base . '/var/dns/split_horizon/' . $domain . '.json';
        if (!file_exists($file)) {
            return ['success' => true, 'domain' => $domain, 'internal_records' => []];
        }
        $data = json_decode((string)file_get_contents($file), true) ?: [];
        return ['success' => true, 'domain' => $domain, 'internal_records' => $data];
    }

    public static function saveSplitHorizon(string $domain, array $records): array
    {
        $base = defined('SPP_BASE_DIR') ? SPP_BASE_DIR : dirname(__DIR__, 4);
        $dir = $base . '/var/dns/split_horizon';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $file = $dir . '/' . $domain . '.json';
        file_put_contents($file, json_encode($records, JSON_PRETTY_PRINT));
        return ['success' => true, 'domain' => $domain, 'count' => count($records)];
    }
}
