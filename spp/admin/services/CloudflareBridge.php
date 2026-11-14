<?php
declare(strict_types=1);

namespace SPP\Admin\Services;

use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * Class CloudflareBridge
 *
 * Sovereign Cloudflare Edge DNS & Edge SSL Bridge for SPP.
 * Features:
 * - Direct REST API v4 integration with Cloudflare Edge.
 * - Automatic API Token verification and Zone auto-discovery.
 * - 300ms Global Edge DNS provisioning (A, AAAA, CNAME, TXT, MX, SRV).
 * - Cloudflare Proxy (Orange Cloud) toggling for DDoS mitigation and instant SSL.
 * - Bi-directional synchronization between local RFC 1035 DnsZoneManager and Cloudflare.
 * - 1-Click Global Edge CDN Cache Purge.
 * - 1-Click Subdomain Auto-Provisioner (e.g., app.example.com -> apex example.com -> Cloudflare A record).
 */
class CloudflareBridge
{
    private const CF_API_BASE = 'https://api.cloudflare.com/client/v4';
    private static ?array $settings = null;

    /**
     * Resolves the config file location (etc/cloudflare.yml).
     */
    public static function getConfigFile(): string
    {
        if (class_exists('\\SPP\\Core\\Config\\ConfigIsolationManager')) {
            return \SPP\Core\Config\ConfigIsolationManager::ensureEffectiveConfig('cloudflare.yml');
        }
        $baseDir = defined('SPP_BASE_DIR') ? SPP_BASE_DIR : dirname(__DIR__, 2);
        return $baseDir . '/etc/cloudflare.yml';
    }

    /**
     * Loads Cloudflare configuration.
     */
    public static function getSettings(): array
    {
        if (self::$settings !== null) {
            return self::$settings;
        }

        $file = self::getConfigFile();
        $defaults = [
            'enabled' => false,
            'api_token' => '',
            'account_id' => '',
            'default_proxied' => true,
            'default_ttl' => 1, // 1 = Auto in Cloudflare
            'zones' => [], // 'example.com' => 'zone_id_here'
            'auto_provision_subdomains' => true,
            'last_verified_at' => null
        ];

        if (file_exists($file) && class_exists(Yaml::class)) {
            try {
                $parsed = Yaml::parseFile($file) ?: [];
                self::$settings = array_merge($defaults, $parsed);
            } catch (Throwable $e) {
                self::$settings = $defaults;
            }
        } else {
            self::$settings = $defaults;
        }

        return self::$settings;
    }

    /**
     * Returns sanitized settings safe for frontend exposure (masks token).
     */
    public static function getSafeSettings(): array
    {
        $cfg = self::getSettings();
        $maskedToken = '';
        if (!empty($cfg['api_token'])) {
            $len = strlen($cfg['api_token']);
            $maskedToken = $len > 8 ? substr($cfg['api_token'], 0, 4) . '...' . substr($cfg['api_token'], -4) : '••••••••';
        }

        return [
            'enabled' => !empty($cfg['enabled']),
            'has_token' => !empty($cfg['api_token']),
            'masked_token' => $maskedToken,
            'account_id' => $cfg['account_id'] ?? '',
            'default_proxied' => $cfg['default_proxied'] ?? true,
            'default_ttl' => $cfg['default_ttl'] ?? 1,
            'zones' => $cfg['zones'] ?? [],
            'auto_provision_subdomains' => !empty($cfg['auto_provision_subdomains']),
            'last_verified_at' => $cfg['last_verified_at'] ?? null
        ];
    }

    /**
     * Saves Cloudflare configuration.
     */
    public static function saveSettings(array $input): array
    {
        $current = self::getSettings();
        $new = $current;

        if (isset($input['enabled'])) {
            $new['enabled'] = (bool)$input['enabled'];
        }
        if (isset($input['api_token']) && trim((string)$input['api_token']) !== '' && !str_contains((string)$input['api_token'], '••••')) {
            $new['api_token'] = trim((string)$input['api_token']);
        }
        if (isset($input['account_id'])) {
            $new['account_id'] = trim((string)$input['account_id']);
        }
        if (isset($input['default_proxied'])) {
            $new['default_proxied'] = (bool)$input['default_proxied'];
        }
        if (isset($input['default_ttl'])) {
            $new['default_ttl'] = (int)$input['default_ttl'];
        }
        if (isset($input['auto_provision_subdomains'])) {
            $new['auto_provision_subdomains'] = (bool)$input['auto_provision_subdomains'];
        }
        if (isset($input['zones']) && is_array($input['zones'])) {
            $new['zones'] = array_merge($new['zones'], $input['zones']);
        }

        // Verify token if updated
        if (!empty($new['api_token'])) {
            $verify = self::verifyToken($new['api_token']);
            if ($verify['success']) {
                $new['enabled'] = true;
                $new['last_verified_at'] = date('Y-m-d H:i:s');
            }
        }

        $file = self::getConfigFile();
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        if (class_exists(Yaml::class)) {
            file_put_contents($file, Yaml::dump($new, 4, 2));
        }

        self::$settings = $new;
        return [
            'success' => true,
            'message' => 'Cloudflare settings saved successfully.',
            'settings' => self::getSafeSettings()
        ];
    }

    /**
     * Makes an authorized REST request to Cloudflare API v4.
     */
    public static function request(string $endpoint, string $method = 'GET', ?array $data = null, ?string $overrideToken = null): array
    {
        $cfg = self::getSettings();
        $token = $overrideToken ?: ($cfg['api_token'] ?? '');

        if (empty($token)) {
            return [
                'success' => false,
                'error' => 'Cloudflare API Token is not configured. Please enter your API token in Cloudflare Settings.'
            ];
        }

        $url = str_starts_with($endpoint, 'http') ? $endpoint : self::CF_API_BASE . '/' . ltrim($endpoint, '/');
        $ch = curl_init($url);

        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        if (!empty($data) && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'], true)) {
            curl_setopt($CURLOPT_POSTFIELDS ?? CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_SLASHES));
        }

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return [
                'success' => false,
                'error' => 'Cloudflare connection failed: ' . ($err ?: 'Network timeout')
            ];
        }

        $json = json_decode($response, true);
        if (!is_array($json)) {
            return [
                'success' => false,
                'error' => "Invalid response from Cloudflare (HTTP {$httpCode}): " . substr($response, 0, 150)
            ];
        }

        $isOk = ($httpCode >= 200 && $httpCode < 300) && (!empty($json['success']) || !empty($json['result']));
        if (!$isOk) {
            $errMsg = 'Cloudflare API Error';
            if (!empty($json['errors']) && is_array($json['errors'])) {
                $errMessages = array_column($json['errors'], 'message');
                $errMsg = implode('; ', $errMessages);
            }
            return [
                'success' => false,
                'http_code' => $httpCode,
                'error' => $errMsg,
                'raw' => $json
            ];
        }

        return [
            'success' => true,
            'http_code' => $httpCode,
            'result' => $json['result'] ?? [],
            'result_info' => $json['result_info'] ?? []
        ];
    }

    /**
     * Verifies that the API token is active and valid.
     */
    public static function verifyToken(?string $token = null): array
    {
        $res = self::request('/user/tokens/verify', 'GET', null, $token);
        if (!$res['success']) {
            return [
                'success' => false,
                'error' => $res['error'] ?? 'API token verification failed.'
            ];
        }

        $status = $res['result']['status'] ?? 'unknown';
        return [
            'success' => $status === 'active',
            'status' => $status,
            'message' => "Token is active and authenticated with Cloudflare."
        ];
    }

    /**
     * Lists all zones (domains) accessible by the API token.
     */
    public static function listZones(): array
    {
        $res = self::request('/zones?per_page=50&status=active', 'GET');
        if (!$res['success']) {
            return $res;
        }

        $zones = [];
        $zoneMap = [];
        foreach ($res['result'] as $z) {
            $name = strtolower(trim((string)$z['name']));
            $id = (string)$z['id'];
            $zones[] = [
                'id' => $id,
                'name' => $name,
                'status' => $z['status'] ?? 'active',
                'name_servers' => $z['name_servers'] ?? [],
                'paused' => !empty($z['paused']),
                'type' => $z['type'] ?? 'full'
            ];
            $zoneMap[$name] = $id;
        }

        // Cache mapped zones
        $current = self::getSettings();
        $current['zones'] = array_merge($current['zones'] ?? [], $zoneMap);
        self::$settings = $current;

        return [
            'success' => true,
            'count' => count($zones),
            'zones' => $zones
        ];
    }

    /**
     * Resolves the Cloudflare Zone ID for a domain (auto-discovers if not cached).
     */
    public static function resolveZoneId(string $domain): ?string
    {
        $clean = strtolower(trim(preg_replace('/^https?:\/\//i', '', rtrim($domain, '.'))));
        $cfg = self::getSettings();

        // 1. Direct cache lookup
        if (!empty($cfg['zones'][$clean])) {
            return $cfg['zones'][$clean];
        }

        // 2. Apex extraction (e.g. app.example.com -> example.com)
        $apex = self::extractApexDomain($clean);
        if (!empty($cfg['zones'][$apex])) {
            return $cfg['zones'][$apex];
        }

        // 3. Cloudflare live API lookup by zone name
        $searchDomain = $apex ?: $clean;
        $res = self::request("/zones?name={$searchDomain}", 'GET');
        if ($res['success'] && !empty($res['result'][0]['id'])) {
            $zoneId = (string)$res['result'][0]['id'];
            // Cache in memory and file
            $cfg['zones'][$searchDomain] = $zoneId;
            self::$settings = $cfg;
            $file = self::getConfigFile();
            if (class_exists(Yaml::class)) {
                @file_put_contents($file, Yaml::dump($cfg, 4, 2));
            }
            return $zoneId;
        }

        return null;
    }

    /**
     * Extracts the apex domain from a subdomain (e.g. app.example.com -> example.com).
     */
    public static function extractApexDomain(string $hostname): string
    {
        $parts = explode('.', strtolower(trim($hostname)));
        $count = count($parts);
        if ($count <= 2) {
            return implode('.', $parts);
        }
        // Handle common 2-level TLDs (e.g. .co.uk, .com.au, .co.in, .net.in)
        $secondLevelTlds = ['co', 'com', 'net', 'org', 'gov', 'edu', 'ac'];
        if ($count >= 3 && in_array($parts[$count - 2], $secondLevelTlds, true)) {
            return $parts[$count - 3] . '.' . $parts[$count - 2] . '.' . $parts[$count - 1];
        }

        return $parts[$count - 2] . '.' . $parts[$count - 1];
    }

    /**
     * Lists DNS records for a zone directly from Cloudflare Edge.
     */
    public static function listRecords(string $domain, array $filters = []): array
    {
        $zoneId = self::resolveZoneId($domain);
        if (!$zoneId) {
            return [
                'success' => false,
                'error' => "Cloudflare Zone ID could not be resolved for domain '{$domain}'. Ensure the domain is added to your Cloudflare account."
            ];
        }

        $query = [];
        if (!empty($filters['type'])) $query['type'] = strtoupper(trim((string)$filters['type']));
        if (!empty($filters['name'])) $query['name'] = trim((string)$filters['name']);
        if (!empty($filters['search'])) $query['search'] = trim((string)$filters['search']);
        $query['per_page'] = 100;

        $queryString = http_build_query($query);
        $res = self::request("/zones/{$zoneId}/dns_records?{$queryString}", 'GET');
        if (!$res['success']) {
            return $res;
        }

        $records = [];
        foreach ($res['result'] as $r) {
            $records[] = [
                'id' => $r['id'],
                'type' => $r['type'],
                'name' => $r['name'],
                'content' => $r['content'],
                'proxiable' => !empty($r['proxiable']),
                'proxied' => !empty($r['proxied']),
                'ttl' => (int)$r['ttl'],
                'priority' => $r['priority'] ?? null,
                'comment' => $r['comment'] ?? '',
                'modified_on' => $r['modified_on'] ?? $r['created_on'] ?? null
            ];
        }

        return [
            'success' => true,
            'zone_id' => $zoneId,
            'domain' => $domain,
            'count' => count($records),
            'records' => $records
        ];
    }

    /**
     * Creates a new DNS record on Cloudflare Edge.
     */
    public static function createRecord(string $domain, array $record): array
    {
        $zoneId = self::resolveZoneId($domain);
        if (!$zoneId) {
            return ['success' => false, 'error' => "Cloudflare Zone not found for domain '{$domain}'."];
        }

        $type = strtoupper(trim((string)($record['type'] ?? 'A')));
        $name = trim((string)($record['name'] ?? ($record['host'] ?? '@')));
        $content = trim((string)($record['content'] ?? ($record['value'] ?? ($record['target'] ?? ''))));
        $ttl = (int)($record['ttl'] ?? 1); // 1 = Auto
        $proxied = isset($record['proxied']) ? (bool)$record['proxied'] : in_array($type, ['A', 'AAAA', 'CNAME'], true);

        // MX / SRV priorities
        $priority = isset($record['priority']) ? (int)$record['priority'] : ($type === 'MX' ? 10 : null);

        if (empty($content)) {
            return ['success' => false, 'error' => "Record content/value cannot be empty."];
        }

        $payload = [
            'type' => $type,
            'name' => $name,
            'content' => $content,
            'ttl' => $ttl,
            'proxied' => $proxied,
            'comment' => $record['comment'] ?? 'Managed by SPP Sovereign WebOS'
        ];
        if ($priority !== null) {
            $payload['priority'] = $priority;
        }

        $res = self::request("/zones/{$zoneId}/dns_records", 'POST', $payload);
        if (!$res['success']) {
            return $res;
        }

        // Also sync local DnsZoneManager cache
        try {
            if (class_exists(DnsZoneManager::class)) {
                $apex = self::extractApexDomain($domain);
                DnsZoneManager::saveRecord($apex, [
                    'id' => $res['result']['id'] ?? null,
                    'type' => $type,
                    'host' => $name,
                    'value' => $content,
                    'ttl' => $ttl,
                    'cf_proxied' => $proxied
                ]);
            }
        } catch (Throwable $e) {}

        return [
            'success' => true,
            'message' => "Record '{$name}' ({$type}) published to Cloudflare Edge successfully.",
            'record' => $res['result']
        ];
    }

    /**
     * Updates an existing DNS record on Cloudflare Edge.
     */
    public static function updateRecord(string $domain, string $recordId, array $record): array
    {
        $zoneId = self::resolveZoneId($domain);
        if (!$zoneId) {
            return ['success' => false, 'error' => "Cloudflare Zone not found for domain '{$domain}'."];
        }

        $payload = [
            'type' => strtoupper(trim((string)$record['type'])),
            'name' => trim((string)$record['name']),
            'content' => trim((string)$record['content']),
            'ttl' => (int)($record['ttl'] ?? 1),
            'proxied' => !empty($record['proxied'])
        ];
        if (isset($record['priority'])) {
            $payload['priority'] = (int)$record['priority'];
        }

        $res = self::request("/zones/{$zoneId}/dns_records/{$recordId}", 'PUT', $payload);
        if (!$res['success']) {
            return $res;
        }

        return [
            'success' => true,
            'message' => "Record updated on Cloudflare Edge.",
            'record' => $res['result']
        ];
    }

    /**
     * Deletes a DNS record from Cloudflare Edge.
     */
    public static function deleteRecord(string $domain, string $recordId): array
    {
        $zoneId = self::resolveZoneId($domain);
        if (!$zoneId) {
            return ['success' => false, 'error' => "Cloudflare Zone not found for domain '{$domain}'."];
        }

        $res = self::request("/zones/{$zoneId}/dns_records/{$recordId}", 'DELETE');
        if (!$res['success']) {
            return $res;
        }

        // Also delete from local DnsZoneManager cache
        try {
            if (class_exists(DnsZoneManager::class)) {
                $apex = self::extractApexDomain($domain);
                DnsZoneManager::deleteRecord($apex, $recordId);
            }
        } catch (Throwable $e) {}

        return [
            'success' => true,
            'message' => "Record removed from Cloudflare Edge successfully."
        ];
    }

    /**
     * Purges Cloudflare Edge CDN cache (all files or specific URLs).
     */
    public static function purgeCache(string $domain, bool $purgeEverything = true, array $files = []): array
    {
        $zoneId = self::resolveZoneId($domain);
        if (!$zoneId) {
            return ['success' => false, 'error' => "Cloudflare Zone not found for domain '{$domain}'."];
        }

        $payload = [];
        if ($purgeEverything || empty($files)) {
            $payload['purge_everything'] = true;
        } else {
            $payload['files'] = array_values($files);
        }

        $res = self::request("/zones/{$zoneId}/purge_cache", 'POST', $payload);
        if (!$res['success']) {
            return $res;
        }

        return [
            'success' => true,
            'message' => $purgeEverything ? "Cloudflare Global Edge Cache purged completely." : "Specified assets purged from Cloudflare Edge Cache."
        ];
    }

    /**
     * Synchronizes all DNS records from Cloudflare Edge down to the local DnsZoneManager.
     */
    public static function syncFromCloudflare(string $domain): array
    {
        $res = self::listRecords($domain);
        if (!$res['success']) {
            return $res;
        }

        $apex = self::extractApexDomain($domain);
        $importedCount = 0;

        if (class_exists(DnsZoneManager::class)) {
            // Reset local zone and re-populate
            DnsZoneManager::resetZone($apex);
            foreach ($res['records'] as $r) {
                DnsZoneManager::saveRecord($apex, [
                    'id' => $r['id'],
                    'type' => $r['type'],
                    'host' => $r['name'],
                    'value' => $r['content'],
                    'ttl' => $r['ttl'],
                    'priority' => $r['priority'] ?? null,
                    'cf_proxied' => !empty($r['proxied'])
                ]);
                $importedCount++;
            }
        }

        return [
            'success' => true,
            'message' => "Synchronized {$importedCount} DNS records from Cloudflare Edge into SPP Zone Manager.",
            'count' => $importedCount,
            'records' => $res['records']
        ];
    }

    /**
     * Resolves the server's public IP address.
     */
    public static function detectServerIp(): string
    {
        // 1. Check server environment variables
        $candidates = [
            $_SERVER['SERVER_ADDR'] ?? '',
            $_SERVER['LOCAL_ADDR'] ?? '',
            getHostByName(getHostName())
        ];

        foreach ($candidates as $ip) {
            if (!empty($ip) && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }

        // 2. Query public ip resolver fallback (fast 1s timeout)
        $providers = [
            'https://api.ipify.org',
            'https://icanhazip.com',
            'https://ifconfig.me/ip'
        ];

        foreach ($providers as $url) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 2,
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            $ip = trim((string)curl_exec($ch));
            curl_close($ch);
            if (!empty($ip) && filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
    }

    /**
     * 1-Click Subdomain Auto-Provisioner on Cloudflare Edge.
     * E.g.: app.example.com -> Creates A record on Cloudflare pointing to this server IP with Proxy active!
     */
    public static function autoProvisionSubdomain(string $hostname, ?string $targetIp = null, bool $proxied = true): array
    {
        $hostname = strtolower(trim(preg_replace('/^https?:\/\//i', '', rtrim($hostname, '.'))));
        $apex = self::extractApexDomain($hostname);

        $zoneId = self::resolveZoneId($apex);
        if (!$zoneId) {
            return [
                'success' => false,
                'error' => "Apex domain '{$apex}' was not found in your Cloudflare account. Please configure your Cloudflare API token or add '{$apex}' in Cloudflare."
            ];
        }

        $serverIp = $targetIp ?: self::detectServerIp();

        // 1. Check if record already exists on Cloudflare
        $existing = self::listRecords($apex, ['name' => $hostname]);
        if ($existing['success'] && !empty($existing['records'])) {
            $rec = $existing['records'][0];
            // Update if IP differs
            if ($rec['content'] !== $serverIp || (bool)$rec['proxied'] !== $proxied) {
                return self::updateRecord($apex, (string)$rec['id'], [
                    'type' => 'A',
                    'name' => $hostname,
                    'content' => $serverIp,
                    'ttl' => 1,
                    'proxied' => $proxied
                ]);
            }
            return [
                'success' => true,
                'message' => "Subdomain '{$hostname}' is already provisioned on Cloudflare Edge pointing to {$serverIp}.",
                'record' => $rec
            ];
        }

        // 2. Create new A record on Cloudflare
        return self::createRecord($apex, [
            'type' => 'A',
            'name' => $hostname,
            'content' => $serverIp,
            'ttl' => 1,
            'proxied' => $proxied,
            'comment' => "Auto-provisioned by SPP Sovereign WebOS for app"
        ]);
    }
}
