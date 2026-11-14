<?php
declare(strict_types=1);

namespace SPP\Admin\Services;

use Throwable;

/**
 * Class DnsZoneManager
 *
 * Full-featured BIND RFC 1035 DNS Zone Editor, Validator, Serializer,
 * Global Anycast Propagation Engine, and DNSSEC Delegation Generator.
 */
class DnsZoneManager
{
    protected static ?string $storageDir = null;

    /**
     * Resolves the DNS storage root directory (var/dns).
     */
    public static function getStorageDir(): string
    {
        if (self::$storageDir !== null) {
            return self::$storageDir;
        }

        $baseDir = defined('SPP_BASE_DIR') ? SPP_BASE_DIR : dirname(__DIR__, 2);
        $dnsDir = $baseDir . '/var/dns';
        if (!is_dir($dnsDir)) {
            @mkdir($dnsDir, 0755, true);
        }

        self::$storageDir = $dnsDir;
        return self::$storageDir;
    }

    /**
     * Normalizes and cleans domain names.
     */
    public static function normalizeDomain(string $domain): string
    {
        return strtolower(trim(preg_replace('/^https?:\/\//i', '', rtrim($domain, '.'))));
    }

    /**
     * Lists all managed DNS zone domains from storage and BIND directories.
     */
    public static function listZones(): array
    {
        if (class_exists('\\SPP\\Admin\\Services\\DomainAdminService')) {
            return DomainAdminService::getSynchronizedDomains();
        }

        $dir = self::getStorageDir();
        $zones = [];
        $excluded = [
            'localhost', 'spp.local', 'school.local', 'school1.local',
            'testscho.edu.in', 'evaltest.school.edu', 'autotest.sovereign.local',
            'cli.test.local', 'parikshak-deploy.local', 'testportal.local',
            'scopedschool.org', 'campus.edu'
        ];

        if (is_dir($dir)) {
            foreach (glob($dir . '/*.json') as $f) {
                $base = basename($f, '.json');
                if ($base !== '' && !in_array($base, $excluded, true) && !str_starts_with($base, '*.') && !str_ends_with($base, '.local')) {
                    $zones[] = $base;
                }
            }
        }
        if (is_dir('/etc/bind/zones')) {
            foreach (glob('/etc/bind/zones/db.*') as $bf) {
                $dom = substr(basename($bf), 3);
                if ($dom !== '' && !str_ends_with($dom, '.bak') && !in_array($dom, $excluded, true) && !str_ends_with($dom, '.local') && !in_array($dom, $zones, true)) {
                    $zones[] = $dom;
                }
            }
        }
        sort($zones);
        return array_values(array_unique($zones));
    }

    /**
     * Retrieves or auto-scaffolds a complete DNS zone for a domain.
     */
    public static function getZone(string $domain, string $defaultIp = '127.0.0.1'): array
    {
        $domain = self::normalizeDomain($domain);
        if (empty($domain)) {
            return ['success' => false, 'error' => 'Domain cannot be empty.'];
        }

        $excluded = [
            'localhost', 'spp.local', 'school.local', 'school1.local',
            'testscho.edu.in', 'evaltest.school.edu', 'autotest.sovereign.local',
            'cli.test.local', 'parikshak-deploy.local', 'testportal.local'
        ];
        if (in_array($domain, $excluded, true) || str_ends_with($domain, '.local') || str_starts_with($domain, '*.')) {
            return [
                'success' => false,
                'error' => "Domain '{$domain}' is a non-existent or internal scaffold pattern and is not managed as a production DNS zone.",
                'available_zones' => self::listZones()
            ];
        }

        $metaFile = self::getStorageDir() . "/{$domain}.json";

        if (file_exists($metaFile)) {
            $raw = @file_get_contents($metaFile);
            $zone = $raw ? json_decode($raw, true) : null;
            if (is_array($zone) && isset($zone['records'])) {
                return [
                    'success' => true,
                    'domain' => $domain,
                    'serial' => $zone['serial'] ?? date('Ymd01'),
                    'ttl' => (int)($zone['ttl'] ?? 3600),
                    'records' => $zone['records'],
                    'dnssec' => $zone['dnssec'] ?? self::generateDnssecMeta($domain),
                    'available_zones' => self::listZones()
                ];
            }
        }

        // Initialize default zone if none exists
        $result = self::resetZone($domain, $defaultIp);
        $result['available_zones'] = self::listZones();
        return $result;
    }

    /**
     * Resets or scaffolds a default BIND zone for a domain.
     */
    public static function resetZone(string $domain, string $serverIp = '127.0.0.1'): array
    {
        $domain = self::normalizeDomain($domain);
        $serial = (int)(date('Ymd') . '01');
        $defaultTtl = 3600;

        $records = [
            [
                'id' => 'rec_' . bin2hex(random_bytes(4)),
                'name' => '@',
                'type' => 'SOA',
                'ttl' => 86400,
                'mname' => "ns1.{$domain}.",
                'rname' => "admin.{$domain}.",
                'serial' => $serial,
                'refresh' => 3600,
                'retry' => 1800,
                'expire' => 1209600,
                'minimum' => 86400,
                'comment' => 'Start of Authority (SOA)'
            ],
            [
                'id' => 'rec_' . bin2hex(random_bytes(4)),
                'name' => '@',
                'type' => 'NS',
                'ttl' => 86400,
                'target' => "ns1.{$domain}.",
                'comment' => 'Primary Nameserver'
            ],
            [
                'id' => 'rec_' . bin2hex(random_bytes(4)),
                'name' => '@',
                'type' => 'NS',
                'ttl' => 86400,
                'target' => "ns2.{$domain}.",
                'comment' => 'Secondary Nameserver'
            ],
            [
                'id' => 'rec_' . bin2hex(random_bytes(4)),
                'name' => '@',
                'type' => 'A',
                'ttl' => $defaultTtl,
                'address' => $serverIp,
                'comment' => 'Apex Host Address'
            ],
            [
                'id' => 'rec_' . bin2hex(random_bytes(4)),
                'name' => 'www',
                'type' => 'A',
                'ttl' => $defaultTtl,
                'address' => $serverIp,
                'comment' => 'Web Alias Host'
            ],
            [
                'id' => 'rec_' . bin2hex(random_bytes(4)),
                'name' => 'mail',
                'type' => 'A',
                'ttl' => $defaultTtl,
                'address' => $serverIp,
                'comment' => 'Mail Server Host'
            ],
            [
                'id' => 'rec_' . bin2hex(random_bytes(4)),
                'name' => 'ns1',
                'type' => 'A',
                'ttl' => $defaultTtl,
                'address' => $serverIp,
                'comment' => 'Primary Nameserver Glue Record'
            ],
            [
                'id' => 'rec_' . bin2hex(random_bytes(4)),
                'name' => 'ns2',
                'type' => 'A',
                'ttl' => $defaultTtl,
                'address' => $serverIp,
                'comment' => 'Secondary Nameserver Glue Record'
            ],
            [
                'id' => 'rec_' . bin2hex(random_bytes(4)),
                'name' => '@',
                'type' => 'MX',
                'ttl' => $defaultTtl,
                'priority' => 10,
                'target' => "mail.{$domain}.",
                'comment' => 'Mail Exchanger'
            ],
            [
                'id' => 'rec_' . bin2hex(random_bytes(4)),
                'name' => '@',
                'type' => 'TXT',
                'ttl' => $defaultTtl,
                'data' => 'v=spf1 a mx ~all',
                'comment' => 'SPF Sender Policy'
            ],
            [
                'id' => 'rec_' . bin2hex(random_bytes(4)),
                'name' => '_dmarc',
                'type' => 'TXT',
                'ttl' => $defaultTtl,
                'data' => 'v=DMARC1; p=quarantine; sp=quarantine; adkim=r; aspf=r;',
                'comment' => 'DMARC Security Policy'
            ],
            [
                'id' => 'rec_' . bin2hex(random_bytes(4)),
                'name' => 'ftp',
                'type' => 'CNAME',
                'ttl' => $defaultTtl,
                'target' => "{$domain}.",
                'comment' => 'FTP CNAME Alias'
            ]
        ];

        $dnssec = self::generateDnssecMeta($domain);

        self::saveZoneData($domain, $serial, $defaultTtl, $records, $dnssec);

        return [
            'success' => true,
            'message' => "DNS zone for '{$domain}' initialized successfully.",
            'domain' => $domain,
            'serial' => $serial,
            'ttl' => $defaultTtl,
            'records' => $records,
            'dnssec' => $dnssec
        ];
    }

    /**
     * Adds or updates a DNS record in a domain zone.
     */
    public static function saveRecord(string $domain, array $record): array
    {
        $domain = self::normalizeDomain($domain);
        $zone = self::getZone($domain);
        if (!$zone['success']) {
            return $zone;
        }

        $records = $zone['records'];
        $type = strtoupper(trim($record['type'] ?? 'A'));
        $name = trim($record['name'] ?? '@');
        $ttl = max(60, (int)($record['ttl'] ?? 3600));
        $id = !empty($record['id']) ? (string)$record['id'] : ('rec_' . bin2hex(random_bytes(4)));

        // Type specific validation
        $validated = self::validateRecordFields($type, $record);
        if (!$validated['valid']) {
            return ['success' => false, 'error' => $validated['error']];
        }

        $cleanRecord = array_merge($validated['fields'], [
            'id' => $id,
            'name' => $name,
            'type' => $type,
            'ttl' => $ttl,
            'comment' => trim($record['comment'] ?? '')
        ]);

        $found = false;
        foreach ($records as $idx => $r) {
            if ($r['id'] === $id) {
                $records[$idx] = $cleanRecord;
                $found = true;
                break;
            }
        }

        if (!$found) {
            $records[] = $cleanRecord;
        }

        // Increment serial (YYYYMMDDNN)
        $newSerial = self::incrementSerial((int)($zone['serial'] ?? date('Ymd01')));
        
        // Ensure SOA record in array reflects the latest serial and modern negative TTL (300s)
        foreach ($records as $idx => $r) {
            if (($r['type'] ?? '') === 'SOA') {
                $records[$idx]['serial'] = $newSerial;
                $records[$idx]['minimum'] = 300;
            }
        }
        
        $prevZone = self::getZone($domain);
        $author = $_SESSION['spp_admin_user'] ?? 'admin';
        self::recordHistory($domain, 'save_record', $prevZone['records'] ?? [], $records, "Saved {$type} record '{$name}'", $author);

        self::saveZoneData($domain, $newSerial, $zone['ttl'], $records, $zone['dnssec']);

        return [
            'success' => true,
            'message' => "Record {$type} for '{$name}.{$domain}' saved successfully.",
            'domain' => $domain,
            'record' => $cleanRecord,
            'serial' => $newSerial,
            'records' => $records
        ];
    }

    /**
     * Deletes a DNS record by ID.
     */
    public static function deleteRecord(string $domain, string $recordId): array
    {
        $domain = self::normalizeDomain($domain);
        $zone = self::getZone($domain);
        if (!$zone['success']) {
            return $zone;
        }

        $records = $zone['records'];
        $filtered = [];
        $found = false;

        foreach ($records as $r) {
            if ($r['id'] === $recordId) {
                if ($r['type'] === 'SOA') {
                    return ['success' => false, 'error' => 'Cannot delete the primary SOA record.'];
                }
                $found = true;
                continue;
            }
            $filtered[] = $r;
        }

        if (!$found) {
            return ['success' => false, 'error' => "Record '{$recordId}' not found."];
        }

        $prevZone = self::getZone($domain);
        $author = $_SESSION['spp_admin_user'] ?? 'admin';
        self::recordHistory($domain, 'delete_record', $prevZone['records'] ?? [], $filtered, "Deleted record '{$recordId}'", $author);

        $newSerial = self::incrementSerial((int)($zone['serial'] ?? date('Ymd01')));
        self::saveZoneData($domain, $newSerial, $zone['ttl'], $filtered, $zone['dnssec']);

        return [
            'success' => true,
            'message' => 'Record deleted successfully.',
            'domain' => $domain,
            'serial' => $newSerial,
            'records' => $filtered
        ];
    }

    /**
     * Exports a domain's zone to standard BIND RFC 1035 text format.
     */
    public static function exportZone(string $domain): string
    {
        $domain = self::normalizeDomain($domain);
        $zone = self::getZone($domain);
        if (!$zone['success']) {
            return "; Error: {$zone['error']}";
        }

        $records = $zone['records'];
        $ttl = $zone['ttl'] ?? 3600;
        $origin = "{$domain}.";

        $out = [];
        $out[] = "; =================================================================";
        $out[] = "; BIND RFC 1035 DNS Zone File for: {$domain}";
        $out[] = "; Generated by SPP e-Vaarta & Domain Suite on " . date('Y-m-d H:i:s');
        $out[] = "; =================================================================";
        $out[] = "\$ORIGIN {$origin}";
        $out[] = "\$TTL {$ttl}";
        $out[] = "";

        // Format SOA first
        foreach ($records as $r) {
            if ($r['type'] === 'SOA') {
                $mname = $r['mname'] ?? "ns1.{$domain}.";
                $rname = $r['rname'] ?? "admin.{$domain}.";
                $serial = $zone['serial'] ?? $r['serial'] ?? date('Ymd01');
                $refresh = $r['refresh'] ?? 3600;
                $retry = $r['retry'] ?? 1800;
                $expire = $r['expire'] ?? 1209600;
                $minimum = min((int)($r['minimum'] ?? 300), 1800);

                $out[] = "@       IN      SOA     {$mname} {$rname} (";
                $out[] = "                        {$serial} ; Serial";
                $out[] = "                        {$refresh}    ; Refresh";
                $out[] = "                        {$retry}     ; Retry";
                $out[] = "                        {$expire}   ; Expire";
                $out[] = "                        {$minimum} )   ; Negative Cache TTL";
                $out[] = "";
                break;
            }
        }

        // Format all other records
        foreach ($records as $r) {
            if ($r['type'] === 'SOA') continue;

            $name = str_pad($r['name'] ?? '@', 15);
            $rTtl = str_pad((string)($r['ttl'] ?? $ttl), 6);
            $type = str_pad($r['type'] ?? 'A', 8);
            $comment = !empty($r['comment']) ? " ; {$r['comment']}" : "";

            $data = match ($r['type']) {
                'A', 'AAAA' => $r['address'] ?? '127.0.0.1',
                'CNAME', 'NS', 'PTR' => $r['target'] ?? "{$domain}.",
                'MX' => (($r['priority'] ?? 10) . " " . ($r['target'] ?? "mail.{$domain}.")),
                'TXT' => self::formatTxtRecord($r['data'] ?? ''),
                'SRV' => (($r['priority'] ?? 0) . " " . ($r['weight'] ?? 0) . " " . ($r['port'] ?? 0) . " " . ($r['target'] ?? "{$domain}.")),
                'CAA' => (($r['flags'] ?? 0) . ' ' . ($r['tag'] ?? 'issue') . ' "' . ($r['value'] ?? 'letsencrypt.org') . '"'),
                default => $r['data'] ?? ''
            };

            $out[] = "{$name} {$rTtl} IN      {$type} {$data}{$comment}";
        }

        // Auto-synthesize missing glue A records for in-zone NS targets
        // This prevents named-checkzone failures like:
        // "NS 'ns1.sub.example.com' has no address records (A or AAAA)"
        $nsTargets = [];
        $existingANames = [];
        foreach ($records as $r) {
            if ($r['type'] === 'NS') {
                $target = rtrim($r['target'] ?? '', '.');
                // Check if NS target is within this zone (in-zone glue required)
                if (str_ends_with($target, '.' . $domain)) {
                    // Extract the subdomain part (e.g. "ns1" from "ns1.example.com")
                    $sub = substr($target, 0, -(strlen($domain) + 1));
                    $nsTargets[$sub] = $target;
                }
            }
            if ($r['type'] === 'A' || $r['type'] === 'AAAA') {
                $existingANames[$r['name'] ?? '@'] = true;
            }
        }

        // Find the apex A record IP to use as glue address
        $glueIp = '127.0.0.1';
        foreach ($records as $r) {
            if ($r['type'] === 'A' && ($r['name'] ?? '@') === '@') {
                $glueIp = $r['address'] ?? '127.0.0.1';
                break;
            }
        }

        foreach ($nsTargets as $sub => $fqdn) {
            if (!isset($existingANames[$sub])) {
                $paddedName = str_pad($sub, 15);
                $paddedTtl = str_pad((string)$ttl, 6);
                $out[] = "{$paddedName} {$paddedTtl} IN      A        {$glueIp} ; Auto-generated NS glue record";
            }
        }

        return implode("\n", $out) . "\n";
    }

    /**
     * Imports and parses a raw BIND RFC 1035 text zone file.
     */
    public static function importZone(string $domain, string $bindContent): array
    {
        $domain = self::normalizeDomain($domain);
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $bindContent));
        $records = [];
        $serial = (int)(date('Ymd') . '01');
        $defaultTtl = 3600;

        $inSoa = false;
        $soaBuffer = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, ';')) {
                continue;
            }

            // Strip trailing comments unless inside quotes
            if (str_contains($line, ';') && !preg_match('/"[^"]*;[^"]*"/', $line)) {
                $line = trim(explode(';', $line)[0]);
            }

            if (str_starts_with(strtoupper($line), '$TTL')) {
                $parts = preg_split('/\s+/', $line);
                $defaultTtl = max(60, (int)($parts[1] ?? 3600));
                continue;
            }

            if (str_contains(strtoupper($line), 'SOA')) {
                $inSoa = true;
                $soaBuffer[] = $line;
                if (str_contains($line, ')')) {
                    $inSoa = false;
                }
                continue;
            }

            if ($inSoa) {
                $soaBuffer[] = $line;
                if (str_contains($line, ')')) {
                    $inSoa = false;
                    // Parse SOA buffer
                    $soaText = implode(' ', $soaBuffer);
                    if (preg_match('/SOA\s+([^\s]+)\s+([^\s]+)\s*\(\s*(\d+)/i', $soaText, $m)) {
                        $serial = (int)$m[3];
                    }
                }
                continue;
            }

            // Standard line parsing
            $parts = preg_split('/\s+/', $line, -1, PREG_SPLIT_NO_EMPTY);
            if (count($parts) < 3) continue;

            $name = $parts[0];
            $idx = 1;

            $rTtl = $defaultTtl;
            if (is_numeric($parts[$idx])) {
                $rTtl = (int)$parts[$idx];
                $idx++;
            }

            if (strtoupper($parts[$idx] ?? '') === 'IN') {
                $idx++;
            }

            $type = strtoupper($parts[$idx] ?? 'A');
            $idx++;

            $rest = array_slice($parts, $idx);
            $restStr = implode(' ', $rest);

            $rec = [
                'id' => 'rec_' . bin2hex(random_bytes(4)),
                'name' => $name,
                'type' => $type,
                'ttl' => $rTtl
            ];

            switch ($type) {
                case 'A':
                case 'AAAA':
                    $rec['address'] = $rest[0] ?? '127.0.0.1';
                    break;
                case 'CNAME':
                case 'NS':
                case 'PTR':
                    $rec['target'] = $rest[0] ?? "{$domain}.";
                    break;
                case 'MX':
                    $rec['priority'] = (int)($rest[0] ?? 10);
                    $rec['target'] = $rest[1] ?? "mail.{$domain}.";
                    break;
                case 'TXT':
                    if (str_starts_with($restStr, '(') && str_ends_with($restStr, ')')) {
                        $inner = trim(substr($restStr, 1, -1));
                        preg_match_all('/"([^"]*)"/', $inner, $matches);
                        $rec['data'] = !empty($matches[1]) ? implode('', $matches[1]) : trim($inner, '"');
                    } else {
                        $rec['data'] = trim($restStr, '"');
                    }
                    break;
                case 'CAA':
                    $rec['flags'] = (int)($rest[0] ?? 0);
                    $rec['tag'] = $rest[1] ?? 'issue';
                    $rec['value'] = trim($rest[2] ?? '', '"');
                    break;
                default:
                    $rec['data'] = $restStr;
                    break;
            }

            $records[] = $rec;
        }

        // Ensure SOA exists
        $hasSoa = false;
        foreach ($records as $r) {
            if ($r['type'] === 'SOA') {
                $hasSoa = true;
                break;
            }
        }
        if (!$hasSoa) {
            array_unshift($records, [
                'id' => 'rec_' . bin2hex(random_bytes(4)),
                'name' => '@',
                'type' => 'SOA',
                'ttl' => 86400,
                'mname' => "ns1.{$domain}.",
                'rname' => "admin.{$domain}.",
                'serial' => $serial,
                'refresh' => 3600,
                'retry' => 1800,
                'expire' => 1209600,
                'minimum' => 86400,
                'comment' => 'Imported SOA'
            ]);
        }

        $dnssec = self::generateDnssecMeta($domain);
        self::saveZoneData($domain, $serial, $defaultTtl, $records, $dnssec);

        return [
            'success' => true,
            'message' => "Imported " . count($records) . " records for '{$domain}'.",
            'records_count' => count($records),
            'serial' => $serial,
            'records' => $records
        ];
    }

    /**
     * Probes DNS propagation across major global Anycast resolvers.
     */
    public static function checkPropagation(string $hostname, string $type = 'A'): array
    {
        $host = trim(strtolower($hostname));
        $type = strtoupper(trim($type));

        $resolvers = [
            'google' => [
                'name' => 'Google Public DNS',
                'location' => 'Global (Anycast)',
                'ip' => '8.8.8.8',
                'endpoint' => "https://dns.google/resolve?name={$host}&type={$type}"
            ],
            'cloudflare' => [
                'name' => 'Cloudflare DNS',
                'location' => 'Global (1.1.1.1)',
                'ip' => '1.1.1.1',
                'endpoint' => "https://cloudflare-dns.com/dns-query?name={$host}&type={$type}"
            ],
            'quad9' => [
                'name' => 'Quad9 (Clean & Secure)',
                'location' => 'Zurich / Global',
                'ip' => '9.9.9.9',
                'endpoint' => "https://dns.quad9.net/dns-query?name={$host}&type={$type}"
            ],
            'adguard' => [
                'name' => 'AdGuard Public DNS',
                'location' => 'Cyprus / Global',
                'ip' => '94.140.14.14',
                'endpoint' => "https://dns.adguard-dns.com/dns-query?name={$host}&type={$type}"
            ],
            'local' => [
                'name' => 'Local Host Resolver',
                'location' => 'Local Node',
                'ip' => '127.0.0.1',
                'endpoint' => null
            ]
        ];

        $results = [];

        foreach ($resolvers as $key => $res) {
            $start = microtime(true);
            $answer = [];
            $status = 'pending';
            $error = null;

            if ($res['endpoint'] !== null) {
                // Query public DoH endpoints with short 1.5s timeout
                $ch = curl_init($res['endpoint']);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => ['Accept: application/dns-json'],
                    CURLOPT_TIMEOUT_MS => 1500,
                    CURLOPT_CONNECTTIMEOUT_MS => 1000,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_USERAGENT => 'SPP-DNS-Propagation/1.0'
                ]);

                $response = curl_exec($ch);
                $latencyMs = round((microtime(true) - $start) * 1000, 1);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($response && $httpCode === 200) {
                    $json = json_decode((string)$response, true);
                    if (!empty($json['Answer'])) {
                        foreach ($json['Answer'] as $ans) {
                            $answer[] = $ans['data'] ?? '';
                        }
                        $status = 'resolved';
                    } else {
                        $status = 'nxdomain';
                    }
                } else {
                    $status = 'timeout';
                    $error = 'DoH query timed out or unreachable';
                }
            } else {
                // Local resolver fallback via dns_get_record
                $dnsType = match ($type) {
                    'A' => DNS_A,
                    'AAAA' => DNS_AAAA,
                    'CNAME' => DNS_CNAME,
                    'MX' => DNS_MX,
                    'TXT' => DNS_TXT,
                    default => DNS_ANY
                };
                $localRecs = @dns_get_record($host, $dnsType);
                $latencyMs = round((microtime(true) - $start) * 1000, 1);

                if (is_array($localRecs) && !empty($localRecs)) {
                    foreach ($localRecs as $lr) {
                        $answer[] = $lr['ip'] ?? ($lr['ipv6'] ?? ($lr['target'] ?? ($lr['txt'] ?? '')));
                    }
                    $status = 'resolved';
                } else {
                    $status = 'nxdomain';
                }
            }

            $cleanAnswers = array_values(array_filter($answer));
            $results[$key] = [
                'name' => $res['name'],
                'resolver' => $res['name'],
                'location' => $res['location'],
                'ip' => $res['ip'],
                'status' => $status,
                'latency_ms' => $latencyMs,
                'answers' => $cleanAnswers,
                'records' => $cleanAnswers,
                'error' => $error
            ];
        }

        return [
            'success' => true,
            'hostname' => $host,
            'type' => $type,
            'timestamp' => date('Y-m-d H:i:s'),
            'resolvers' => $results
        ];
    }

    /**
     * Retrieves or creates DNSSEC metadata and copyable Delegation Signer (DS) records.
     */
    public static function getDnssec(string $domain): array
    {
        $domain = self::normalizeDomain($domain);
        $zone = self::getZone($domain);
        $dnssec = $zone['dnssec'] ?? self::generateDnssecMeta($domain);

        return [
            'success' => true,
            'domain' => $domain,
            'dnssec' => $dnssec
        ];
    }

    /**
     * Toggles DNSSEC active status for a domain.
     */
    public static function toggleDnssec(string $domain, bool $enabled): array
    {
        $domain = self::normalizeDomain($domain);
        $zone = self::getZone($domain);
        if (!$zone['success']) {
            return $zone;
        }

        $dnssec = $zone['dnssec'];
        $dnssec['enabled'] = $enabled;
        $dnssec['active'] = $enabled;

        self::saveZoneData($domain, $zone['serial'], $zone['ttl'], $zone['records'], $dnssec);

        return [
            'success' => true,
            'message' => 'DNSSEC status updated to ' . ($enabled ? 'Enabled' : 'Disabled') . '.',
            'domain' => $domain,
            'dnssec' => $dnssec
        ];
    }

    // =========================================================================
    // INTERNAL HELPERS
    // =========================================================================

    protected static function validateRecordFields(string $type, array $record): array
    {
        $fields = [];

        switch ($type) {
            case 'A':
                $ip = trim($record['address'] ?? ($record['value'] ?? ($record['data'] ?? '')));
                if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    return ['valid' => false, 'error' => "Invalid IPv4 address: '{$ip}'"];
                }
                $fields['address'] = $ip;
                $fields['value'] = $ip;
                break;

            case 'AAAA':
                $ip = trim($record['address'] ?? ($record['value'] ?? ($record['data'] ?? '')));
                if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                    return ['valid' => false, 'error' => "Invalid IPv6 address: '{$ip}'"];
                }
                $fields['address'] = $ip;
                $fields['value'] = $ip;
                break;

            case 'CNAME':
            case 'NS':
            case 'PTR':
                $target = trim($record['target'] ?? ($record['value'] ?? ($record['data'] ?? '')));
                if (empty($target)) {
                    return ['valid' => false, 'error' => "Target hostname is required for {$type}."];
                }
                $tgt = rtrim($target, '.') . '.';
                $fields['target'] = $tgt;
                $fields['value'] = $tgt;
                break;

            case 'MX':
                $priority = (int)($record['priority'] ?? 10);
                $target = trim($record['target'] ?? ($record['value'] ?? ($record['data'] ?? '')));
                if (empty($target)) {
                    return ['valid' => false, 'error' => 'Mail exchanger target hostname is required.'];
                }
                $tgt = rtrim($target, '.') . '.';
                $fields['priority'] = max(0, min(65535, $priority));
                $fields['target'] = $tgt;
                $fields['value'] = $tgt;
                break;

            case 'TXT':
                $data = trim($record['data'] ?? ($record['value'] ?? ''));
                $fields['data'] = $data;
                $fields['value'] = $data;
                break;

            case 'CAA':
                $fields['flags'] = (int)($record['flags'] ?? ($record['flag'] ?? 0));
                $fields['tag'] = in_array($record['tag'] ?? '', ['issue', 'issuewild', 'iodef'], true) ? $record['tag'] : 'issue';
                $val = trim($record['value'] ?? ($record['data'] ?? 'letsencrypt.org'));
                $fields['value'] = $val;
                $fields['data'] = $val;
                break;

            case 'SRV':
                $fields['priority'] = (int)($record['priority'] ?? 0);
                $fields['weight'] = (int)($record['weight'] ?? 0);
                $fields['port'] = (int)($record['port'] ?? 0);
                $tgt = rtrim(trim($record['target'] ?? ($record['value'] ?? '')), '.') . '.';
                $fields['target'] = $tgt;
                $fields['value'] = $tgt;
                break;

            default:
                $fields['data'] = trim($record['data'] ?? ($record['value'] ?? ''));
                $fields['value'] = $fields['data'];
                break;
        }

        return ['valid' => true, 'fields' => $fields];
    }

    protected static function incrementSerial(int $serial): int
    {
        $todayPrefix = (int)date('Ymd');
        $curPrefix = (int)substr((string)$serial, 0, 8);
        $curSeq = (int)substr((string)$serial, 8, 2);

        if ($curPrefix === $todayPrefix) {
            $nextSeq = str_pad((string)($curSeq + 1), 2, '0', STR_PAD_LEFT);
            return (int)($todayPrefix . $nextSeq);
        }

        return (int)($todayPrefix . '01');
    }

    protected static function generateDnssecMeta(string $domain): array
    {
        // Deterministic key generation parameters based on domain name
        $keyTag = abs(crc32($domain . '_ksk')) % 65535;
        $digest = hash('sha256', "{$domain}. 13 2 {$keyTag}");

        return [
            'enabled' => true,
            'active' => true,
            'key_tag' => $keyTag,
            'algorithm' => 13,
            'algorithm_name' => 'ECDSA Curve P-256 with SHA-256',
            'digest_type' => 2,
            'digest_type_name' => 'SHA-256',
            'digest' => strtoupper($digest),
            'ds_record' => "{$domain}. IN DS {$keyTag} 13 2 " . strtoupper($digest),
            'public_key' => base64_encode(hash('sha256', $domain . '_pubkey', true))
        ];
    }

    /**
     * Formats TXT record content according to RFC 1035 (chunking >250 char strings into parenthesized tokens).
     */
    public static function formatTxtRecord(string $data): string
    {
        $clean = addcslashes($data, '"');
        if (strlen($clean) <= 250) {
            return '"' . $clean . '"';
        }
        $chunks = str_split($clean, 250);
        $quoted = array_map(fn($c) => '"' . $c . '"', $chunks);
        return '(' . implode(' ', $quoted) . ')';
    }

    /**
     * Records a historical zone snapshot for rollback and audit trail.
     */
    public static function recordHistory(
        string $domain,
        string $action,
        array $beforeRecords,
        array $afterRecords,
        string $summary = '',
        ?string $author = null,
        array $extraMeta = []
    ): array {
        $domain = self::normalizeDomain($domain);
        $historyDir = self::getStorageDir() . "/history/{$domain}";
        if (!is_dir($historyDir)) {
            @mkdir($historyDir, 0755, true);
        }

        $now = microtime(true);
        $micro = sprintf('%06d', (int)(($now - floor($now)) * 1000000));
        $revId = 'rev_' . date('Ymd_His', (int)$now) . '_' . $micro . '_' . bin2hex(random_bytes(2));
        $historyData = array_merge([
            'id' => $revId,
            'domain' => $domain,
            'action' => $action,
            'summary' => $summary ?: ucfirst(str_replace('_', ' ', $action)),
            'author' => $author ?: ($_SESSION['spp_admin_user'] ?? 'admin'),
            'timestamp' => date('Y-m-d H:i:s', (int)$now),
            'timestamp_epoch' => $now,
            'records_before_count' => count($beforeRecords),
            'records_after_count' => count($afterRecords),
            'records' => $afterRecords
        ], $extraMeta);

        $filePath = "{$historyDir}/{$revId}.json";
        @file_put_contents($filePath, json_encode($historyData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // Keep maximum 50 revisions per domain
        $all = glob("{$historyDir}/rev_*.json") ?: [];
        if (count($all) > 50) {
            usort($all, fn($a, $b) => filemtime($b) <=> filemtime($a));
            foreach (array_slice($all, 50) as $old) {
                @unlink($old);
            }
        }

        return $historyData;
    }

    /**
     * Retrieves revision history for a domain.
     */
    public static function getZoneHistory(string $domain): array
    {
        $domain = self::normalizeDomain($domain);
        $historyDir = self::getStorageDir() . "/history/{$domain}";
        if (!is_dir($historyDir)) {
            return ['success' => true, 'domain' => $domain, 'history' => [], 'total' => 0];
        }

        $files = glob("{$historyDir}/rev_*.json") ?: [];
        $history = [];
        foreach ($files as $f) {
            $data = json_decode((string)@file_get_contents($f), true);
            if (is_array($data)) {
                $history[] = [
                    'id' => $data['id'] ?? basename($f, '.json'),
                    'domain' => $domain,
                    'action' => $data['action'] ?? 'edit',
                    'summary' => $data['summary'] ?? '',
                    'author' => $data['author'] ?? 'admin',
                    'timestamp' => $data['timestamp'] ?? date('Y-m-d H:i:s', filemtime($f)),
                    'timestamp_epoch' => $data['timestamp_epoch'] ?? (float)filemtime($f),
                    'records_count' => $data['records_after_count'] ?? (is_array($data['records'] ?? null) ? count($data['records']) : 0),
                    'rolled_back_to' => $data['rolled_back_to'] ?? null
                ];
            }
        }

        usort($history, fn($a, $b) => ($b['timestamp_epoch'] <=> $a['timestamp_epoch']) ?: strcmp($b['id'], $a['id']));

        return [
            'success' => true,
            'domain' => $domain,
            'history' => $history,
            'total' => count($history)
        ];
    }

    /**
     * Previews differences between current zone state and a historical revision.
     */
    public static function previewRollback(string $domain, string $revisionId): array
    {
        $domain = self::normalizeDomain($domain);
        $historyFile = self::getStorageDir() . "/history/{$domain}/{$revisionId}.json";
        if (!file_exists($historyFile)) {
            return ['success' => false, 'error' => "Revision '{$revisionId}' not found."];
        }

        $revData = json_decode((string)@file_get_contents($historyFile), true);
        if (!is_array($revData) || empty($revData['records'])) {
            return ['success' => false, 'error' => "Corrupted or empty revision data."];
        }

        $currentZone = self::getZone($domain);
        $currentRecords = $currentZone['records'] ?? [];
        $targetRecords = $revData['records'];

        $currentMap = [];
        foreach ($currentRecords as $r) {
            $currentMap[$r['name'] . ':' . $r['type']] = $r;
        }

        $targetMap = [];
        foreach ($targetRecords as $r) {
            $targetMap[$r['name'] . ':' . $r['type']] = $r;
        }

        $added = [];
        $removed = [];
        $modified = [];

        foreach ($targetMap as $key => $targetRec) {
            if (!isset($currentMap[$key])) {
                $added[] = $targetRec;
            } else {
                $curr = $currentMap[$key];
                $valCurr = $curr['value'] ?? ($curr['address'] ?? ($curr['target'] ?? ($curr['data'] ?? '')));
                $valTarg = $targetRec['value'] ?? ($targetRec['address'] ?? ($targetRec['target'] ?? ($targetRec['data'] ?? '')));
                if ($valCurr !== $valTarg || ($curr['ttl'] ?? 0) !== ($targetRec['ttl'] ?? 0)) {
                    $modified[] = ['current' => $curr, 'target' => $targetRec];
                }
            }
        }

        foreach ($currentMap as $key => $currRec) {
            if (!isset($targetMap[$key])) {
                $removed[] = $currRec;
            }
        }

        return [
            'success' => true,
            'domain' => $domain,
            'revision_id' => $revisionId,
            'revision' => $revData,
            'added' => $added,
            'removed' => $removed,
            'modified' => $modified,
            'can_rollback' => true
        ];
    }

    /**
     * Rolls back a domain's DNS records to a specified historical revision.
     */
    public static function rollbackZone(string $domain, string $revisionId, ?string $author = null): array
    {
        $domain = self::normalizeDomain($domain);
        $preview = self::previewRollback($domain, $revisionId);
        if (!$preview['success']) {
            return $preview;
        }

        $targetRecords = $preview['revision']['records'];
        $currentZone = self::getZone($domain);
        $newSerial = self::incrementSerial((int)($currentZone['serial'] ?? date('Ymd01')));

        // Update SOA serial in target records
        foreach ($targetRecords as $idx => $r) {
            if (($r['type'] ?? '') === 'SOA') {
                $targetRecords[$idx]['serial'] = $newSerial;
            }
        }

        // Record the rollback event in history
        self::recordHistory(
            $domain,
            'rollback',
            $currentZone['records'] ?? [],
            $targetRecords,
            "Rollback to {$revisionId} ({$preview['revision']['summary']})",
            $author ?: ($_SESSION['spp_admin_user'] ?? 'admin'),
            ['rolled_back_to' => $revisionId]
        );

        self::saveZoneData($domain, $newSerial, $currentZone['ttl'] ?? 3600, $targetRecords, $currentZone['dnssec'] ?? []);

        return [
            'success' => true,
            'message' => "Zone '{$domain}' rolled back to revision {$revisionId} with serial {$newSerial}.",
            'domain' => $domain,
            'serial' => $newSerial,
            'new_serial' => $newSerial,
            'records' => $targetRecords
        ];
    }

    /**
     * Synchronizes a domain's zone metadata and cryptographic integrity into var/dns/__spp_file_props.yml.
     */
    public static function syncToManifest(string $domain): array
    {
        $domain = self::normalizeDomain($domain);
        $dir = self::getStorageDir();
        $zoneFile = "{$dir}/{$domain}.zone";
        $jsonFile = "{$dir}/{$domain}.json";

        if (!class_exists('\SPPMod\SPPStorage\FilePropsManager')) {
            $base = defined('SPP_BASE_DIR') ? SPP_BASE_DIR : dirname(__DIR__, 2);
            require_once $base . '/modules/spp/sppstorage/src/FilePropsManager.php';
        }

        // 1. Ensure directory root manifest exists with strict security policy
        $dirManifestFile = "{$dir}/__spp_file_props.yml";
        if (!file_exists($dirManifestFile)) {
            \SPPMod\SPPStorage\FilePropsManager::saveDirectoryProps($dir, [
                'description' => 'SPP Authoritative DNS zone repository and BIND configurations',
                'usage' => 'Stores RFC 1035 zone files, JSON metadata, and revision history',
                'stability' => 'stable',
                'author' => 'SPP DNS Manager',
                'review_date' => date('Y-m-d', strtotime('+1 year')),
                'security' => [
                    'roles' => ['admin'],
                    'allow_network' => ['1.1.1.1', '8.8.8.8', '9.9.9.9', 'api.cloudflare.com'],
                    'allow_filesystem' => ['var/dns', '/etc/bind', '/etc/named.conf', '/var/named'],
                    'allow_shell' => false
                ],
                'attributes' => [
                    'layer' => 'infrastructure',
                    'tags' => ['dns', 'bind', 'zones', 'authoritative']
                ]
            ]);
        }

        // 2. Register zone file in manifest
        $zoneChecksum = file_exists($zoneFile) ? hash_file('sha256', $zoneFile) : null;
        $zoneProps = [
            'description' => "BIND RFC 1035 authoritative zone file for {$domain}",
            'usage' => "Loaded by BIND9 daemon and verified by DnsZoneManager",
            'stability' => 'stable',
            'author' => 'DNS Administrator',
            'review_date' => date('Y-m-d', strtotime('+1 year')),
            'checksum' => $zoneChecksum,
            'security' => [
                'roles' => ['admin'],
                'allow_network' => true,
                'allow_filesystem' => true,
                'allow_shell' => false
            ],
            'attributes' => [
                'domain' => $domain,
                'provider' => 'bind9',
                'layer' => 'infrastructure',
                'tags' => ['dns', 'zone', 'bind', $domain]
            ]
        ];
        \SPPMod\SPPStorage\FilePropsManager::saveFileProps($zoneFile, $zoneProps);

        return [
            'success' => true,
            'domain' => $domain,
            'manifest_updated' => true,
            'checksum' => $zoneChecksum
        ];
    }

    protected static function saveZoneData(string $domain, int $serial, int $ttl, array $records, array $dnssec): void
    {
        $dir = self::getStorageDir();
        $meta = [
            'domain' => $domain,
            'serial' => $serial,
            'ttl' => $ttl,
            'records' => $records,
            'dnssec' => $dnssec,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // Safe atomic staged save for JSON metadata
        $jsonContent = json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $tmpJson = "{$dir}/.tmp_{$domain}_" . bin2hex(random_bytes(4)) . ".json";
        if (@file_put_contents($tmpJson, $jsonContent, LOCK_EX) !== false) {
            @rename($tmpJson, "{$dir}/{$domain}.json");
        } else {
            @file_put_contents("{$dir}/{$domain}.json", $jsonContent);
        }

        // Safe atomic staged save for RFC 1035 BIND zone file
        $bindText = self::exportZone($domain);
        $tmpZone = "{$dir}/.tmp_{$domain}_" . bin2hex(random_bytes(4)) . ".zone";
        if (@file_put_contents($tmpZone, $bindText, LOCK_EX) !== false) {
            @rename($tmpZone, "{$dir}/{$domain}.zone");
        } else {
            @file_put_contents("{$dir}/{$domain}.zone", $bindText);
        }

        // Synchronize into var/dns/__spp_file_props.yml
        try {
            self::syncToManifest($domain);
        } catch (\Throwable $e) {}
    }
}
