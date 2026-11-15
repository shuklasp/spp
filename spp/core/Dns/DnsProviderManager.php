<?php
declare(strict_types=1);

namespace SPP\Core\Dns;

use SPP\Core\Dns\Providers\AbstractDnsProvider;
use SPP\Core\Dns\Providers\Bind9Provider;
use SPP\Core\Dns\Providers\CloudflareProvider;
use SPP\Core\Dns\Providers\CustomDnsProvider;
use SPP\Core\Dns\Providers\DigitalOceanProvider;
use SPP\Core\Dns\Providers\GoogleCloudDnsProvider;
use SPP\Core\Dns\Providers\Route53Provider;
use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * Class DnsProviderManager
 *
 * Centralized DNS Provider Registry, Domain-to-Provider Mapper,
 * and Provider Adapter Factory for SPP.
 */
class DnsProviderManager
{
    private static ?array $domainMappings = null;

    /**
     * Map of built-in provider classes.
     */
    private static array $providers = [
        'bind9'             => Bind9Provider::class,
        'cloudflare'        => CloudflareProvider::class,
        'route53'           => Route53Provider::class,
        'digitalocean'      => DigitalOceanProvider::class,
        'google_cloud_dns'  => GoogleCloudDnsProvider::class,
        'custom'            => CustomDnsProvider::class
    ];

    public static function getMappingFile(): string
    {
        if (class_exists('\\SPP\\Core\\Config\\ConfigIsolationManager')) {
            return \SPP\Core\Config\ConfigIsolationManager::ensureEffectiveConfig('dns-domains.yml');
        }
        $baseDir = defined('SPP_BASE_DIR') ? SPP_BASE_DIR : dirname(__DIR__, 2);
        return $baseDir . '/etc/dns-domains.yml';
    }

    /**
     * Registers an external provider adapter class.
     */
    public static function registerProvider(string $id, string $class): void
    {
        if (!is_subclass_of($class, DnsProviderInterface::class)) {
            throw new \InvalidArgumentException("Provider class '{$class}' must implement DnsProviderInterface.");
        }
        self::$providers[strtolower($id)] = $class;
    }

    /**
     * Lists all registered provider adapters with metadata and capabilities.
     */
    public static function listAvailableProviders(): array
    {
        $list = [];
        foreach (self::$providers as $id => $class) {
            /** @var DnsProviderInterface $instance */
            $instance = new $class();
            $list[$id] = [
                'id' => $instance->getId(),
                'name' => $instance->getName(),
                'capabilities' => $instance->getCapabilities(),
                'accounts_count' => count(DnsCredentialVault::listAccounts($id))
            ];
        }
        return $list;
    }

    /**
     * Loads domain mappings from etc/dns-domains.yml.
     */
    private static function loadMappings(): array
    {
        if (self::$domainMappings !== null) {
            return self::$domainMappings;
        }

        $file = self::getMappingFile();
        $defaults = ['domains' => []];

        if (file_exists($file) && class_exists(Yaml::class)) {
            try {
                $parsed = Yaml::parseFile($file) ?: [];
                self::$domainMappings = array_merge($defaults, $parsed);
            } catch (Throwable $e) {
                self::$domainMappings = $defaults;
            }
        } else {
            self::$domainMappings = $defaults;
        }

        return self::$domainMappings;
    }

    /**
     * Persists domain mappings to etc/dns-domains.yml.
     */
    private static function persistMappings(): bool
    {
        $file = self::getMappingFile();
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        if (class_exists(Yaml::class)) {
            $yaml = Yaml::dump(self::$domainMappings, 6, 2);
            return @file_put_contents($file, $yaml) !== false;
        }
        return false;
    }

    /**
     * Resolves the configured DNS provider for a specific domain.
     * Defaults to 'bind9' (local sovereign BIND) if not explicitly mapped.
     */
    public static function getDomainMapping(string $domain): array
    {
        $clean = DnsRecordValidator::toPunycode($domain);
        $mappings = self::loadMappings();

        $res = null;
        if (!empty($mappings['domains'][$clean])) {
            $res = array_merge([
                'domain' => $clean,
                'provider' => 'bind9',
                'account_id' => null,
                'zone_id' => $clean,
                'registrar' => 'whois',
                'sync_mode' => 'spp_managed',
                'dnssec_enabled' => false,
                'nameservers' => []
            ], $mappings['domains'][$clean]);
        }

        // Check if apex domain has a mapping (e.g. sub.example.com maps to example.com's provider)
        if (!$res) {
            $parts = explode('.', $clean);
            if (count($parts) > 2) {
                $apex = implode('.', array_slice($parts, -2));
                if (!empty($mappings['domains'][$apex])) {
                    $parent = $mappings['domains'][$apex];
                    $res = [
                        'domain' => $clean,
                        'provider' => $parent['provider'] ?? 'bind9',
                        'account_id' => $parent['account_id'] ?? null,
                        'zone_id' => $parent['zone_id'] ?? $apex,
                        'registrar' => $parent['registrar'] ?? 'whois',
                        'sync_mode' => $parent['sync_mode'] ?? 'spp_managed',
                        'dnssec_enabled' => !empty($parent['dnssec_enabled']),
                        'nameservers' => $parent['nameservers'] ?? []
                    ];
                }
            }
        }

        // Fallback default: Sovereign Native BIND9
        if (!$res) {
            $res = [
                'domain' => $clean,
                'provider' => 'bind9',
                'account_id' => null,
                'zone_id' => $clean,
                'registrar' => 'whois',
                'sync_mode' => 'spp_managed',
                'dnssec_enabled' => false,
                'nameservers' => []
            ];
        }

        // If provider is Cloudflare and zone_id is not yet a 32-char hex ID, auto-resolve via CloudflareBridge
        if ($res['provider'] === 'cloudflare' && !preg_match('/^[a-f0-9]{32}$/i', (string)($res['zone_id'] ?? ''))) {
            if (class_exists(\SPP\Admin\Services\CloudflareBridge::class)) {
                $resolved = \SPP\Admin\Services\CloudflareBridge::resolveZoneId($clean);
                if ($resolved) {
                    $res['zone_id'] = $resolved;
                }
            }
        }

        return $res;
    }

    /**
     * Assigns or updates the DNS provider and account mapping for a domain.
     */
    public static function setDomainMapping(string $domain, array $config): array
    {
        $clean = DnsRecordValidator::toPunycode($domain);
        self::loadMappings();

        $provider = strtolower(trim((string)($config['provider'] ?? 'bind9')));
        if (!isset(self::$providers[$provider])) {
            return ['success' => false, 'error' => "Unknown DNS provider '{$provider}'."];
        }

        $existing = self::$domainMappings['domains'][$clean] ?? [];
        $record = [
            'domain' => $clean,
            'provider' => $provider,
            'account_id' => $config['account_id'] ?? ($existing['account_id'] ?? null),
            'zone_id' => $config['zone_id'] ?? ($existing['zone_id'] ?? $clean),
            'registrar' => strtolower((string)($config['registrar'] ?? ($existing['registrar'] ?? 'whois'))),
            'sync_mode' => $config['sync_mode'] ?? ($existing['sync_mode'] ?? 'spp_managed'),
            'dnssec_enabled' => isset($config['dnssec_enabled']) ? (bool)$config['dnssec_enabled'] : (!empty($existing['dnssec_enabled'])),
            'nameservers' => (array)($config['nameservers'] ?? ($existing['nameservers'] ?? [])),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        self::$domainMappings['domains'][$clean] = $record;
        self::persistMappings();

        return [
            'success' => true,
            'message' => "DNS Provider for '{$clean}' set to '{$provider}'.",
            'mapping' => $record
        ];
    }

    /**
     * Deletes a domain's custom provider mapping, reverting to BIND9 default.
     */
    public static function deleteDomainMapping(string $domain): array
    {
        $clean = DnsRecordValidator::toPunycode($domain);
        self::loadMappings();

        if (isset(self::$domainMappings['domains'][$clean])) {
            unset(self::$domainMappings['domains'][$clean]);
            self::persistMappings();
        }

        return ['success' => true, 'message' => "Provider mapping for '{$clean}' removed (reverted to default BIND9)."];
    }

    /**
     * Lists all domain provider mappings.
     */
    public static function listDomainMappings(): array
    {
        $mappings = self::loadMappings();
        return $mappings['domains'] ?? [];
    }

    /**
     * Instantiates and connects a DNS Provider adapter for a specific domain.
     */
    public static function getProviderForDomain(string $domain): DnsProviderInterface
    {
        $mapping = self::getDomainMapping($domain);
        $providerId = $mapping['provider'];
        $accountId = $mapping['account_id'];

        return self::getProviderInstance($providerId, $accountId);
    }

    /**
     * Instantiates and initializes a DNS Provider adapter by ID and optional Vault Account ID.
     */
    public static function getProviderInstance(string $providerId, ?string $accountId = null): DnsProviderInterface
    {
        $cleanId = strtolower(trim($providerId));
        if (!isset(self::$providers[$cleanId])) {
            throw new \InvalidArgumentException("Unknown DNS provider '{$providerId}'.");
        }

        $class = self::$providers[$cleanId];
        /** @var DnsProviderInterface $adapter */
        $adapter = new $class();

        $credentials = [];
        if (!empty($accountId)) {
            $account = DnsCredentialVault::getAccount($accountId);
            if ($account) {
                $credentials = array_merge($account['credentials'] ?? [], [
                    'account_id' => $account['id'],
                    'read_only' => !empty($account['read_only']),
                    'name' => $account['name']
                ]);
            }
        }

        $adapter->connect($credentials);
        return $adapter;
    }
}
