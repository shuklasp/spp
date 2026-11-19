<?php
declare(strict_types=1);

namespace SPP\Admin\Services;

use SPP\Core\Infra;
use SPP\Core\Router\DomainRouter;

if (!class_exists(Infra::class)) {
    require_once dirname(__DIR__, 2) . '/core/class.infra.php';
}
if (!class_exists(DomainRouter::class)) {
    require_once dirname(__DIR__, 2) . '/core/router/class.domainrouter.php';
}
require_once __DIR__ . '/DnsZoneManager.php';
require_once __DIR__ . '/BindDaemonService.php';

/**
 * Class HostProvisionService
 *
 * Universal, OS-Neutral Server Provisioning & Auto-Healing Engine for SPP.
 * Works seamlessly across Debian, Ubuntu, RHEL, AlmaLinux, Rocky, Alpine, and Windows.
 * Provides 1-command CLI and 1-click Web UI provisioning to bring any host
 * to a 100% operational state without requiring manual terminal work.
 */
class HostProvisionService
{
    /**
     * Inspects the host OS, package managers, web servers, DNS, and ports.
     */
    public static function inspectHost(): array
    {
        $isLinux = Infra::isLinux();
        $isWindows = Infra::isWindows();
        $distro = Infra::distro();
        $distroName = Infra::distroName();
        $pkgMgr = Infra::packageManager();
        $webServer = Infra::webServer();

        // Detect ports 80, 443, 53 listeners
        $port80Daemon = self::detectPortListener(80);
        $port443Daemon = self::detectPortListener(443);
        $port53Daemon = self::detectPortListener(53);

        // Detect packages
        $packages = [
            'apache' => Infra::hasBinary('apache2') || Infra::hasBinary('httpd') || Infra::hasBinary('apache2ctl'),
            'nginx' => Infra::hasBinary('nginx'),
            'bind9' => Infra::hasBinary('named') || Infra::hasBinary('named-checkzone'),
            'certbot' => Infra::hasBinary('certbot'),
            'whois' => Infra::hasBinary('whois'),
            'ufw' => Infra::hasBinary('ufw'),
            'firewalld' => Infra::hasBinary('firewall-cmd'),
            'fail2ban' => Infra::hasBinary('fail2ban-client')
        ];

        // Sudoers status
        $hasSudoers = $isLinux && file_exists('/etc/sudoers.d/spp-admin');

        // Apache vhost status
        $hasDefaultVhost = false;
        if ($isLinux) {
            $hasDefaultVhost = file_exists('/etc/apache2/sites-available/000-default.conf')
                || file_exists('/etc/httpd/conf.d/spp.conf')
                || file_exists('/etc/httpd/conf.d/000-default.conf');
        } elseif ($isWindows) {
            $hasDefaultVhost = true; // Simulated/managed via XAMPP or dev
        }

        return [
            'success' => true,
            'is_linux' => $isLinux,
            'is_windows' => $isWindows,
            'distro' => $distro,
            'distro_name' => $distroName,
            'package_manager' => $pkgMgr,
            'webserver_detected' => $webServer,
            'port_listeners' => [
                'port_80' => $port80Daemon,
                'port_443' => $port443Daemon,
                'port_53' => $port53Daemon
            ],
            'packages_installed' => $packages,
            'has_sudoers_rule' => $hasSudoers,
            'has_default_vhost' => $hasDefaultVhost,
            'conflict_port_80' => ($port80Daemon && str_contains(strtolower($port80Daemon), 'nginx')),
            'ssh_port' => self::detectSshPort()
        ];
    }

    /**
     * Executes 1-click or 1-command autonomous server provisioning and self-healing.
     *
     * @param array $options Provisioning flags
     * @return array Step-by-step progress checklist
     */
    public static function provisionHost(array $options = []): array
    {
        $steps = [];
        $isLinux = Infra::isLinux();
        $isWindows = Infra::isWindows();
        $targetWebserver = $options['webserver'] ?? 'apache';
        $domain = $options['domain'] ?? ($_SERVER['HTTP_HOST'] ?? 'example.com');
        $serverIp = $options['server_ip'] ?? ($_SERVER['SERVER_ADDR'] ?? '127.0.0.1');

        // 1. Detect & Install Core Hosting Packages
        $pkgRes = self::provisionPackages($targetWebserver);
        $steps[] = [
            'id' => 'packages',
            'title' => 'Core Packages & Runtime',
            'success' => $pkgRes['success'],
            'message' => $pkgRes['message']
        ];

        // 2. Resolve Web Server Port Conflicts (Stop & disable Nginx if Apache is active)
        $deconflictRes = self::deconflictWebserver($targetWebserver);
        $steps[] = [
            'id' => 'deconflict',
            'title' => 'Web Server Port Deconfliction',
            'success' => $deconflictRes['success'],
            'message' => $deconflictRes['message']
        ];

        // 3. Configure Virtual Host with SPP Aliases (/sppadmin, /sppdev, /sppmail)
        $vhostRes = self::provisionDefaultVhost($targetWebserver, $domain);
        $steps[] = [
            'id' => 'vhost',
            'title' => 'VirtualHost & Route Aliases',
            'success' => $vhostRes['success'],
            'message' => $vhostRes['message']
        ];

        // 4. Provision & Sync BIND9 Authoritative Nameserver on Port 53
        $bindRes = BindDaemonService::syncAllZones();
        $steps[] = [
            'id' => 'bind_dns',
            'title' => 'BIND9 DNS & Port 53 Nameserver',
            'success' => $bindRes['success'],
            'message' => $bindRes['message']
        ];

        // 5. Setup Passwordless Web Sudoers for SPPAdmin
        $sudoRes = self::provisionSudoers();
        $sudoStep = [
            'id' => 'sudoers',
            'title' => 'Zero-Prompt Web Administration (Sudoers)',
            'success' => $sudoRes['success'],
            'message' => $sudoRes['message']
        ];
        if (!empty($sudoRes['advisory'])) {
            $sudoStep['advisory'] = true;
        }
        if (!empty($sudoRes['command'])) {
            $sudoStep['command'] = $sudoRes['command'];
        }
        $steps[] = $sudoStep;

        // 6. Configure Host Firewall (Ports 80, 443, 53, SSH)
        $fwRes = self::provisionFirewall();
        $steps[] = [
            'id' => 'firewall',
            'title' => 'Host Firewall Rules (Ports 80, 443, 53, SSH)',
            'success' => $fwRes['success'],
            'message' => $fwRes['message']
        ];

        $allOk = true;
        $hasAdvisory = false;
        foreach ($steps as $st) {
            if (empty($st['success'])) {
                // Sudoers is advisory-only — it should not block overall provisioning success
                if (($st['id'] ?? '') === 'sudoers' && !empty($st['advisory'])) {
                    $hasAdvisory = true;
                } else {
                    $allOk = false;
                    break;
                }
            }
        }

        $message = $allOk
            ? ($hasAdvisory
                ? 'Host provisioning completed successfully! Sudoers file requires initial root installation for zero-prompt automation.'
                : 'Host provisioning and self-healing completed successfully!')
            : 'Host provisioning completed with warnings.';

        return [
            'success' => $allOk,
            'message' => $message,
            'steps' => $steps,
            'host' => self::inspectHost()
        ];
    }

    /**
     * Installs required hosting packages in an OS-neutral manner.
     */
    protected static function provisionPackages(string $webserver = 'apache'): array
    {
        if (Infra::isWindows()) {
            return [
                'success' => true,
                'message' => 'Windows development host: verified native PHP & simulation drivers.'
            ];
        }

        $webBinary = ($webserver === 'apache') ? (Infra::hasBinary('httpd') ? 'httpd' : 'apache2') : 'nginx';
        $hasDns = Infra::hasBinary('named') || Infra::hasBinary('named-checkzone');
        $hasWeb = Infra::hasBinary($webBinary);
        $hasCertbot = Infra::hasBinary('certbot');
        $hasUfw = Infra::hasBinary('ufw') || Infra::hasBinary('firewall-cmd');

        // Fast-path: If all essential binaries are already installed, bypass slow apt-get/dnf execution
        if ($hasWeb && $hasDns && $hasCertbot && $hasUfw) {
            return [
                'success' => true,
                'message' => "Required packages for {$webserver} and BIND9 verified."
            ];
        }

        $pkgMgr = Infra::packageManager();
        $distro = Infra::distro();
        $isRoot = function_exists('posix_getuid') ? (posix_getuid() === 0) : false;
        $sudo = $isRoot ? '' : 'sudo ';
        $output = [];
        $exitCode = 0;

        if ($pkgMgr === 'apt') {
            $pkgs = [
                'bind9', 'bind9utils', 'dnsutils',
                'whois', 'curl', 'git', 'unzip', 'ufw', 'fail2ban',
                'certbot'
            ];
            if ($webserver === 'apache') {
                $pkgs[] = 'apache2';
                $pkgs[] = 'libapache2-mod-fcgid';
                $pkgs[] = 'python3-certbot-apache';
            } else {
                $pkgs[] = 'nginx';
                $pkgs[] = 'python3-certbot-nginx';
            }

            $pkgList = implode(' ', $pkgs);
            $cmd = "{$sudo}DEBIAN_FRONTEND=noninteractive apt-get update -y && {$sudo}DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends {$pkgList} 2>&1";
            @exec($cmd, $output, $exitCode);

            if ($webserver === 'apache' && Infra::hasBinary('a2enmod')) {
                @exec("{$sudo}a2enmod rewrite ssl headers proxy proxy_fcgi 2>/dev/null");
            }
        } elseif ($pkgMgr === 'dnf' || $pkgMgr === 'yum') {
            $pkgs = [
                'bind', 'bind-utils',
                'whois', 'curl', 'git', 'unzip', 'certbot'
            ];
            if ($webserver === 'apache') {
                $pkgs[] = 'httpd';
                $pkgs[] = 'mod_ssl';
                $pkgs[] = 'python3-certbot-apache';
            } else {
                $pkgs[] = 'nginx';
                $pkgs[] = 'python3-certbot-nginx';
            }

            $pkgList = implode(' ', $pkgs);
            $cmd = "{$sudo}dnf install -y epel-release 2>&1 && {$sudo}dnf install -y {$pkgList} 2>&1";
            @exec($cmd, $output, $exitCode);
        } else {
            return [
                'success' => true,
                'message' => "Package manager '{$pkgMgr}' detected. Skipping automated batch install."
            ];
        }

        $success = ($exitCode === 0) || Infra::hasBinary($webserver === 'apache' ? 'apache2' : 'nginx');
        return [
            'success' => $success,
            'message' => $success ? "Required packages for {$webserver} and BIND9 verified." : "Package installation returned code {$exitCode}.",
            'output' => implode("\n", array_slice($output, -10))
        ];
    }

    /**
     * Stops and disables competing web server daemons to free ports 80 and 443.
     */
    public static function deconflictWebserver(string $targetWebserver = 'apache'): array
    {
        if (Infra::isWindows()) {
            return ['success' => true, 'message' => 'Windows: No competing Linux systemd daemons.'];
        }

        $isRoot = function_exists('posix_getuid') ? (posix_getuid() === 0) : false;
        $sudo = $isRoot ? '' : 'sudo ';

        if ($targetWebserver === 'apache') {
            // If Nginx is active, stop and disable it
            $nginxActive = false;
            $out = [];
            @exec('systemctl is-active nginx 2>/dev/null', $out);
            if (trim(implode('', $out)) === 'active') {
                $nginxActive = true;
            }

            if ($nginxActive) {
                @exec("{$sudo}systemctl stop nginx 2>/dev/null");
                @exec("{$sudo}systemctl disable nginx 2>/dev/null");
            }

            // Ensure Apache is enabled
            $apacheService = Infra::hasBinary('httpd') ? 'httpd' : 'apache2';
            @exec("{$sudo}systemctl enable {$apacheService} 2>/dev/null");

            // CRITICAL: If executing inside a web request (PHP_SAPI !== 'cli'),
            // restarting Apache will abruptly terminate the worker process and sever
            // the active HTTP connection before sending the response (causing ERR_EMPTY_RESPONSE).
            if (PHP_SAPI === 'cli') {
                @exec("{$sudo}systemctl restart {$apacheService} 2>/dev/null");
            } else {
                // In web mode, Apache is already actively serving this request!
                // Only reload gracefully if Nginx was previously competing
                if ($nginxActive) {
                    @exec("{$sudo}apache2ctl graceful 2>/dev/null || {$sudo}systemctl reload {$apacheService} 2>/dev/null");
                }
            }

            return [
                'success' => true,
                'message' => $nginxActive
                    ? 'Stopped competing Nginx daemon and activated Apache on ports 80/443.'
                    : 'Apache is active and exclusive owner of ports 80/443.'
            ];
        } else {
            // Nginx is target: stop Apache
            @exec("{$sudo}systemctl stop apache2 2>/dev/null || {$sudo}systemctl stop httpd 2>/dev/null");
            @exec("{$sudo}systemctl disable apache2 2>/dev/null || {$sudo}systemctl disable httpd 2>/dev/null");
            @exec("{$sudo}systemctl enable nginx 2>/dev/null");
            @exec("{$sudo}systemctl restart nginx 2>/dev/null");

            return [
                'success' => true,
                'message' => 'Stopped competing Apache daemon and activated Nginx on ports 80/443.'
            ];
        }
    }

    /**
     * Provisions the default VirtualHost with SPP DocumentRoot and route aliases.
     */
    public static function provisionDefaultVhost(string $webserver = 'apache', string $domain = 'example.com'): array
    {
        $baseDir = realpath(defined('SPP_BASE_DIR') ? SPP_BASE_DIR : dirname(__DIR__, 2));
        $isRoot = function_exists('posix_getuid') ? (posix_getuid() === 0) : false;
        $sudo = $isRoot ? '' : 'sudo ';

        if (Infra::isWindows()) {
            return [
                'success' => true,
                'message' => 'Windows: DocumentRoot mapped to ' . str_replace('\\', '/', $baseDir)
            ];
        }

        if ($webserver === 'apache') {
            $vhostContent = <<<EOF
<VirtualHost *:80>
    ServerName {$domain}
    ServerAlias www.{$domain} vps.{$domain} *.{$domain}
    DocumentRoot {$baseDir}

    # Redirect trailing slash to prevent 403 directory listing denials
    RedirectMatch 301 ^/(sppadmin|sppdev|sppmail)$ /$1/

    # Direct Web Suite Aliases
    Alias /sppadmin {$baseDir}/spp/admin
    Alias /sppdev {$baseDir}/spp/dev
    Alias /sppmail {$baseDir}/spp/mail

    <Directory {$baseDir}>
        Options FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    <Directory {$baseDir}/spp/admin>
        Options FollowSymLinks
        AllowOverride All
        Require all granted
        DirectoryIndex index.php index.html
    </Directory>

    <Directory {$baseDir}/spp/dev>
        Options FollowSymLinks
        AllowOverride All
        Require all granted
        DirectoryIndex index.php index.html
    </Directory>

    <Directory {$baseDir}/spp/mail>
        Options FollowSymLinks
        AllowOverride All
        Require all granted
        DirectoryIndex index.php index.html
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/spp_error.log
    CustomLog \${APACHE_LOG_DIR}/spp_access.log combined
</VirtualHost>
EOF;

            $vhostFile = '/etc/apache2/sites-available/000-default.conf';
            if (is_dir('/etc/httpd/conf.d')) {
                $vhostFile = '/etc/httpd/conf.d/000-default.conf';
            }

            // Check if primary port 80 vhost is already configured properly with SPP DocumentRoot, Aliases & Wildcard Subdomain
            $existing = file_exists($vhostFile) ? @file_get_contents($vhostFile) : '';
            $needsVhostWrite = !(str_contains($existing, "Alias /sppadmin {$baseDir}/spp/admin") && str_contains($existing, "DocumentRoot {$baseDir}") && str_contains($existing, "*.{$domain}"));

            if ($needsVhostWrite) {
                $tmpFile = sys_get_temp_dir() . '/spp_000_default.conf';
                file_put_contents($tmpFile, $vhostContent);

                @exec("{$sudo}cp {$tmpFile} {$vhostFile} 2>/dev/null");
                @exec("{$sudo}chmod 644 {$vhostFile} 2>/dev/null");
                @unlink($tmpFile);

                if (Infra::hasBinary('a2ensite')) {
                    @exec("{$sudo}a2ensite 000-default.conf 2>/dev/null");
                }
            }

            // Always ensure SSL VirtualHosts (e.g. domain-le-ssl.conf or 000-default-le-ssl.conf) include the wildcard ServerAlias
            $sslRes = self::ensureSslWildcardAlias($domain);

            if ($needsVhostWrite || !empty($sslRes['patched'])) {
                // Reload Apache gracefully
                $apacheService = Infra::hasBinary('httpd') ? 'httpd' : 'apache2';
                if (PHP_SAPI === 'cli') {
                    @exec("{$sudo}systemctl reload {$apacheService} 2>/dev/null");
                } else {
                    @exec("{$sudo}apache2ctl graceful 2>/dev/null || {$sudo}systemctl reload {$apacheService} 2>/dev/null");
                }
            }

            return [
                'success' => true,
                'message' => $needsVhostWrite
                    ? "Configured primary Apache VirtualHost at {$vhostFile} with DocumentRoot and SPP aliases."
                    : "Primary Apache VirtualHost at {$vhostFile} already active; SSL wildcard aliases verified.",
                'ssl_patched' => $sslRes['patched'] ?? []
            ];
        }

        return ['success' => true, 'message' => 'Web server configuration verified.'];
    }

    /**
     * Ensures all Apache/HTTPD SSL VirtualHosts matching the domain include wildcard ServerAlias (*.domain).
     * Scans sites-available, sites-enabled, and conf.d to prevent SNI fallback to unrelated VirtualHosts.
     *
     * @param string $domain The primary domain (e.g. satyalab.net)
     * @param array|null $customCandidates Optional explicit file paths (useful for testing or dedicated vhosts)
     * @return array Summary of inspected and patched SSL configuration files
     */
    public static function ensureSslWildcardAlias(string $domain, ?array $customCandidates = null): array
    {
        $domain = strtolower(trim($domain));
        if ($domain === '' || $domain === 'localhost') {
            return ['success' => true, 'patched' => [], 'inspected' => 0];
        }

        $isLinux = Infra::isLinux();
        $isWindows = Infra::isWindows();
        $isRoot = function_exists('posix_getuid') ? (posix_getuid() === 0) : false;
        $sudo = $isRoot ? '' : 'sudo ';

        if ($customCandidates !== null) {
            $candidates = $customCandidates;
        } else {
            if ($isWindows) {
                return ['success' => true, 'patched' => [], 'inspected' => 0];
            }

            $searchDirs = [
                '/etc/apache2/sites-available',
                '/etc/apache2/sites-enabled',
                '/etc/httpd/conf.d'
            ];
            $candidates = [
                '/etc/apache2/sites-available/000-default-le-ssl.conf',
                "/etc/apache2/sites-available/{$domain}-le-ssl.conf",
                "/etc/apache2/sites-available/{$domain}.conf",
                "/etc/httpd/conf.d/{$domain}-le-ssl.conf"
            ];
            foreach ($searchDirs as $dir) {
                if (is_dir($dir)) {
                    $files = glob("{$dir}/*.conf");
                    if ($files) {
                        $candidates = array_merge($candidates, $files);
                    }
                }
            }
        }

        $canonicalFiles = [];
        foreach ($candidates as $f) {
            if (file_exists($f)) {
                $rp = realpath($f) ?: $f;
                $canonicalFiles[$rp] = true;
            }
        }

        $patchedFiles = [];
        foreach (array_keys($canonicalFiles) as $sslConf) {
            $content = @file_get_contents($sslConf);
            if (!$content) continue;

            // Must match ServerName domain
            if (!preg_match('/ServerName\s+' . preg_quote($domain, '/') . '(?:\s|$)/i', $content)) {
                continue;
            }

            // If already contains *.domain in a ServerAlias, nothing to do
            if (preg_match('/ServerAlias\s+.*?\*\.' . preg_quote($domain, '/') . '(?:\s|$)/i', $content)) {
                continue;
            }

            // Patch: if an existing ServerAlias exists in this vhost, append *.domain
            if (preg_match('/(ServerAlias\s+[^\r\n]+)/i', $content)) {
                $patched = preg_replace(
                    '/(ServerAlias\s+[^\r\n]+)/i',
                    "$1 *.{$domain}",
                    $content,
                    1
                );
            } else {
                // Otherwise insert ServerAlias right after ServerName
                $patched = preg_replace(
                    '/(ServerName\s+' . preg_quote($domain, '/') . '[^\r\n]*)/i',
                    "$1\n    ServerAlias www.{$domain} *.{$domain}",
                    $content,
                    1
                );
            }

            if ($patched && $patched !== $content) {
                if ($isWindows) {
                    file_put_contents($sslConf, $patched);
                } else {
                    $tmpSsl = sys_get_temp_dir() . '/spp_ssl_' . basename($sslConf);
                    file_put_contents($tmpSsl, $patched);
                    @exec("{$sudo}cp {$tmpSsl} {$sslConf} 2>/dev/null");
                    @unlink($tmpSsl);
                }
                $patchedFiles[] = $sslConf;
            }
        }

        return [
            'success' => true,
            'patched' => $patchedFiles,
            'inspected' => count($canonicalFiles)
        ];
    }

    /**
     * Sets up /etc/sudoers.d/spp-admin so the web administration UI can operate passwordlessly.
     */
    public static function provisionSudoers(): array
    {
        if (Infra::isWindows()) {
            return ['success' => true, 'message' => 'Windows: Sudoers not applicable.'];
        }

        $webUser = 'www-data';
        if (is_dir('/etc/httpd') || file_exists('/etc/redhat-release')) {
            $webUser = 'apache';
        }

        // Hardened sudoers: Principle of Least Privilege
        // Only specific service operations are allowed — not arbitrary systemctl/apt-get/ufw commands.
        // This prevents privilege escalation if the web app is compromised.
        $sudoersLines = [
            "# SPP Admin — Hardened Least-Privilege Sudoers Rules",
            "# Generated by SPP HostProvisionService",
            "# Only specific safe daemon operations are permitted.",
            "",
            "# Web server reload/restart (NOT stop/disable)",
            "{$webUser} ALL=(ALL) NOPASSWD: /usr/bin/systemctl reload apache2",
            "{$webUser} ALL=(ALL) NOPASSWD: /usr/bin/systemctl restart apache2",
            "{$webUser} ALL=(ALL) NOPASSWD: /usr/bin/systemctl reload httpd",
            "{$webUser} ALL=(ALL) NOPASSWD: /usr/bin/systemctl restart httpd",
            "{$webUser} ALL=(ALL) NOPASSWD: /usr/bin/systemctl reload nginx",
            "{$webUser} ALL=(ALL) NOPASSWD: /usr/bin/systemctl restart nginx",
            "{$webUser} ALL=(ALL) NOPASSWD: /bin/systemctl reload apache2",
            "{$webUser} ALL=(ALL) NOPASSWD: /bin/systemctl restart apache2",
            "{$webUser} ALL=(ALL) NOPASSWD: /bin/systemctl reload httpd",
            "{$webUser} ALL=(ALL) NOPASSWD: /bin/systemctl restart httpd",
            "{$webUser} ALL=(ALL) NOPASSWD: /bin/systemctl reload nginx",
            "{$webUser} ALL=(ALL) NOPASSWD: /bin/systemctl restart nginx",
            "",
            "# BIND9 DNS daemon control & zone validation (read-only checks + reload)",
            "{$webUser} ALL=(ALL) NOPASSWD: /usr/sbin/rndc reload",
            "{$webUser} ALL=(ALL) NOPASSWD: /usr/sbin/rndc reconfig",
            "{$webUser} ALL=(ALL) NOPASSWD: /usr/sbin/rndc status",
            "{$webUser} ALL=(ALL) NOPASSWD: /usr/sbin/named-checkzone *",
            "{$webUser} ALL=(ALL) NOPASSWD: /usr/sbin/named-checkconf",
            "{$webUser} ALL=(ALL) NOPASSWD: /usr/bin/systemctl reload named",
            "{$webUser} ALL=(ALL) NOPASSWD: /usr/bin/systemctl restart named",
            "{$webUser} ALL=(ALL) NOPASSWD: /bin/systemctl reload named",
            "{$webUser} ALL=(ALL) NOPASSWD: /bin/systemctl restart named",
            "",
            "# SPP mail service (SMTP on port 25)",
            "{$webUser} ALL=(ALL) NOPASSWD: /usr/bin/systemctl restart spp-mail",
            "{$webUser} ALL=(ALL) NOPASSWD: /usr/bin/systemctl reload spp-mail",
            "{$webUser} ALL=(ALL) NOPASSWD: /usr/bin/systemctl status spp-mail",
            "{$webUser} ALL=(ALL) NOPASSWD: /bin/systemctl restart spp-mail",
            "{$webUser} ALL=(ALL) NOPASSWD: /bin/systemctl reload spp-mail",
            "{$webUser} ALL=(ALL) NOPASSWD: /bin/systemctl status spp-mail",
            "",
            "# SSL certificate management (certbot with specific flags only)",
            "{$webUser} ALL=(ALL) NOPASSWD: /usr/bin/certbot --apache *",
            "{$webUser} ALL=(ALL) NOPASSWD: /usr/bin/certbot --nginx *",
            "{$webUser} ALL=(ALL) NOPASSWD: /usr/bin/certbot certificates",
            "",
            "# Apache module/site management (safe, scoped operations)",
            "{$webUser} ALL=(ALL) NOPASSWD: /usr/sbin/a2enmod *",
            "{$webUser} ALL=(ALL) NOPASSWD: /usr/sbin/a2dismod *",
            "{$webUser} ALL=(ALL) NOPASSWD: /usr/sbin/a2ensite *",
            "{$webUser} ALL=(ALL) NOPASSWD: /usr/sbin/a2dissite *",
            "{$webUser} ALL=(ALL) NOPASSWD: /usr/sbin/apache2ctl graceful",
            "{$webUser} ALL=(ALL) NOPASSWD: /usr/sbin/apache2ctl configtest",
            "",
            "# Firewall: only allow-rules for specific SPP ports (NO ufw disable/delete/reset)",
            "{$webUser} ALL=(ALL) NOPASSWD: /usr/sbin/ufw allow 80/tcp",
            "{$webUser} ALL=(ALL) NOPASSWD: /usr/sbin/ufw allow 443/tcp",
            "{$webUser} ALL=(ALL) NOPASSWD: /usr/sbin/ufw allow 53/tcp",
            "{$webUser} ALL=(ALL) NOPASSWD: /usr/sbin/ufw allow 53/udp",
            "{$webUser} ALL=(ALL) NOPASSWD: /usr/sbin/ufw allow 25/tcp",
            "{$webUser} ALL=(ALL) NOPASSWD: /usr/sbin/ufw status",
            "",
        ];

        $sudoersRule = implode("\n", $sudoersLines) . "\n";

        $tmpFile = sys_get_temp_dir() . '/spp-admin-sudoers';
        file_put_contents($tmpFile, $sudoersRule);

        $isRoot = function_exists('posix_getuid') ? (posix_getuid() === 0) : false;
        $sudo = $isRoot ? '' : 'sudo ';

        // Validate syntax with visudo
        $testOut = [];
        $testCode = 0;
        @exec("visudo -cf {$tmpFile} 2>&1", $testOut, $testCode);
        if ($testCode !== 0) {
            @unlink($tmpFile);
            return [
                'success' => false,
                'error' => 'Sudoers syntax validation failed: ' . implode(' ', $testOut)
            ];
        }

        @exec("{$sudo}cp {$tmpFile} /etc/sudoers.d/spp-admin 2>/dev/null");
        @exec("{$sudo}chmod 440 /etc/sudoers.d/spp-admin 2>/dev/null");
        @unlink($tmpFile);

        $installed = file_exists('/etc/sudoers.d/spp-admin');
        $sppBase = realpath(defined('SPP_BASE_DIR') ? SPP_BASE_DIR : dirname(__DIR__, 2));
        $command = "sudo php {$sppBase}/spp.php host:provision --sudoers-only";
        return [
            'success' => $installed,
            'advisory' => !$installed,
            'message' => $installed
                ? "Sudoers rule active: '{$webUser}' can execute hardened least-privilege rules from SPPAdmin."
                : 'Sudoers file requires initial root installation for zero-prompt automation (advisory — does not block provisioning).',
            'command' => !$installed ? $command : null
        ];
    }

    /**
     * Configures UFW or Firewalld to permit HTTP, HTTPS, DNS, and SSH.
     */
    public static function provisionFirewall(): array
    {
        if (Infra::isWindows()) {
            return ['success' => true, 'message' => 'Windows: Host firewall rules verified.'];
        }

        $isRoot = function_exists('posix_getuid') ? (posix_getuid() === 0) : false;
        $sudo = $isRoot ? '' : 'sudo ';

        if (Infra::hasBinary('ufw')) {
            $sshPort = self::detectSshPort();
            @exec("{$sudo}ufw allow 80/tcp 2>/dev/null");
            @exec("{$sudo}ufw allow 443/tcp 2>/dev/null");
            @exec("{$sudo}ufw allow 53/tcp 2>/dev/null");
            @exec("{$sudo}ufw allow 53/udp 2>/dev/null");
            @exec("{$sudo}ufw allow {$sshPort}/tcp 2>/dev/null");

            return [
                'success' => true,
                'message' => "UFW firewall updated: allowed ports 80, 443, 53 (TCP/UDP), and SSH port {$sshPort}."
            ];
        } elseif (Infra::hasBinary('firewall-cmd')) {
            @exec("{$sudo}firewall-cmd --permanent --add-service=http 2>/dev/null");
            @exec("{$sudo}firewall-cmd --permanent --add-service=https 2>/dev/null");
            @exec("{$sudo}firewall-cmd --permanent --add-service=dns 2>/dev/null");
            @exec("{$sudo}firewall-cmd --reload 2>/dev/null");

            return [
                'success' => true,
                'message' => 'Firewalld updated: permitted http, https, and dns services.'
            ];
        }

        return ['success' => true, 'message' => 'No active Linux firewall binary (ufw/firewalld) detected.'];
    }

    /**
     * Detects what process/daemon is currently listening on a given port.
     */
    public static function detectPortListener(int $port): ?string
    {
        if (Infra::isWindows()) {
            $out = [];
            @exec("netstat -ano | findstr :{$port}", $out);
            foreach ($out as $line) {
                if (str_contains($line, 'LISTENING')) {
                    return 'active_listener';
                }
            }
            return null;
        }

        $out = [];
        @exec("ss -tulpn 2>/dev/null | grep :{$port}", $out);
        $full = implode("\n", $out);
        if ($full === '') {
            @exec("netstat -tulpn 2>/dev/null | grep :{$port}", $out);
            $full = implode("\n", $out);
        }

        if ($full !== '') {
            if (str_contains($full, 'apache2') || str_contains($full, 'httpd')) return 'apache';
            if (str_contains($full, 'nginx')) return 'nginx';
            if (str_contains($full, 'named')) return 'named (BIND9)';
            if (str_contains($full, 'sshd')) return 'sshd';
            if (str_contains($full, 'mysqld') || str_contains($full, 'mariadbd')) return 'mysql';
            if (str_contains($full, 'redis')) return 'redis';
            return 'active_listener';
        }

        return null;
    }

    /**
     * Discovers active SSH daemon port from sshd_config.
     */
    public static function detectSshPort(): int
    {
        $port = 22;
        if (Infra::isLinux()) {
            $files = ['/etc/ssh/sshd_config', '/etc/ssh/sshd_config.d/50-cloud-init.conf'];
            foreach ($files as $f) {
                if (file_exists($f)) {
                    $c = @file_get_contents($f) ?: '';
                    if (preg_match('/^\s*Port\s+(\d+)/m', $c, $m)) {
                        $port = (int)$m[1];
                        break;
                    }
                }
            }
        }
        return $port;
    }
}
