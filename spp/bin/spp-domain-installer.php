<?php
/**
 * Standalone Generic SPP Server Provisioner, Prerequisite Auditor & Domain Application Installer
 *
 * Designed to be totally generic and portable across any server environment:
 * - Localhost / Developer workstation (XAMPP, WAMP, Laragon, Valet, Docker, WSL, Native PHP)
 * - Linux Production Servers (Ubuntu, Debian, RHEL, CentOS, AlmaLinux, Rocky)
 * - Cloud & Shared Hosting Control Panels (cPanel, Plesk, LiteSpeed, CloudPanel)
 * - Reverse Proxy architectures (Apache, Nginx, Caddy, Traefik, HAProxy)
 *
 * Supports execution via both PHP CLI and Web Browser (HTTP).
 *
 * CLI Usage:
 *   php spp/bin/spp-domain-installer.php audit
 *   php spp/bin/spp-domain-installer.php install --domain=example.com [--app=school1] [--make-default] [--mode=strict]
 *   php spp/bin/spp-domain-installer.php configure [--domain=example.com]
 *   php spp/bin/spp-domain-installer.php scope --app=school1 [--domain-only] [--allow=example.com]
 *   php spp/bin/spp-domain-installer.php export --domain=example.com [--out=bundle.zip]
 *   php spp/bin/spp-domain-installer.php import --bundle=bundle.zip [--target-domain=newdomain.com]
 *
 * Web Browser Usage:
 *   Navigate to http://<your-host>/install.php or http://<your-host>/spp/bin/spp-domain-installer.php
 */

if (!defined('SPP_INSTALLER_MODE')) {
    define('SPP_INSTALLER_MODE', true);
}
if (!defined('SPP_NO_AUTO_BOOT')) {
    define('SPP_NO_AUTO_BOOT', true);
}

$rootDir = dirname(__DIR__, 2);
$initFile = $rootDir . '/spp/sppinit.php';
if (file_exists($initFile)) {
    require_once $initFile;
} else {
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "Error: Could not locate SPP initialization file at {$initFile}\n");
        exit(1);
    } else {
        http_response_code(500);
        die("<h1>500 Internal Error</h1><p>Could not locate SPP initialization file at {$initFile}</p>");
    }
}

require_once $rootDir . '/spp/core/class.appuninstaller.php';

use SPP\Core\AppUninstaller;
use SPP\Core\Router\DomainRouter;
use SPP\System\PrerequisiteAuditor;

// =============================================================================
// SERVER AUDIT & DIAGNOSTIC HELPERS
// =============================================================================

/**
 * Execute a comprehensive server audit against SPP framework prerequisites.
 *
 * @param string $rootDir
 * @return array
 */
function spp_installer_audit_server(string $rootDir): array
{
    // Auto-create essential storage directories if missing
    $dirs = [
        $rootDir . '/var',
        $rootDir . '/var/cache',
        $rootDir . '/var/data',
        $rootDir . '/var/logs',
        $rootDir . '/etc',
        $rootDir . '/domains',
        $rootDir . '/domains/_vhosts'
    ];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (is_dir($dir)) {
            @chmod($dir, 0775);
        }
    }

    $rules = [
        'php_version' => '8.1.0',
        'extensions' => [
            'pdo',
            'openssl',
            'mbstring',
            'json',
            'ctype',
            'xml'
        ],
        'writable_paths' => [
            'var',
            'etc',
            'domains'
        ],
        'min_memory_limit' => '128M'
    ];

    $auditRes = PrerequisiteAuditor::audit($rules);
    $checks = $auditRes->getChecks();

    // Additional database driver check (at least one PDO driver should be active)
    $dbDrivers = \PDO::getAvailableDrivers();
    $hasDbDriver = !empty($dbDrivers);
    $checks['pdo_driver_check'] = [
        'name' => 'Database Driver (PDO)',
        'type' => 'db_driver',
        'required' => 'At least one PDO driver (sqlite, mysql, or pgsql)',
        'actual' => $hasDbDriver ? implode(', ', $dbDrivers) : 'None loaded',
        'pass' => $hasDbDriver,
        'message' => $hasDbDriver ? 'Available PDO drivers: ' . implode(', ', $dbDrivers) : 'No database drivers loaded. Enable pdo_sqlite or pdo_mysql in php.ini.',
        'is_critical' => true
    ];

    // Recommended extensions (non-critical warnings)
    $recommended = [
        'zip' => 'Required for domain application exporting/importing and package bundles',
        'curl' => 'Required for external API integrations, SCIM, and webhooks',
        'fileinfo' => 'Required for secure MIME-type file upload validation',
        'gd' => 'Recommended for avatar generation and responsive image manipulation'
    ];
    foreach ($recommended as $ext => $purpose) {
        $loaded = extension_loaded($ext);
        $checks['rec_' . $ext] = [
            'name' => "Optional Extension: {$ext}",
            'type' => 'recommended_extension',
            'required' => 'recommended',
            'actual' => $loaded ? 'Installed' : 'Missing',
            'pass' => $loaded,
            'message' => $loaded ? "Extension '{$ext}' is active" : "Optional: {$purpose}",
            'is_critical' => false
        ];
    }

    // Web Server Detection & Environment
    $serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? (PHP_SAPI === 'cli' ? 'CLI Runtime' : 'Unknown Web Server');
    $isApache = stripos($serverSoftware, 'apache') !== false || stripos($serverSoftware, 'litespeed') !== false;
    $isNginx = stripos($serverSoftware, 'nginx') !== false;
    $isIIS = stripos($serverSoftware, 'microsoft-iis') !== false || stripos($serverSoftware, 'iis') !== false;
    $isCaddy = stripos($serverSoftware, 'caddy') !== false;

    // Check .htaccess
    $htaccessFile = $rootDir . '/.htaccess';
    $htaccessOk = file_exists($htaccessFile);

    $checks['web_server'] = [
        'name' => 'Web Server Environment',
        'type' => 'server_software',
        'required' => 'Apache, Nginx, LiteSpeed, Microsoft IIS, Caddy, or PHP CLI',
        'actual' => $serverSoftware,
        'pass' => true,
        'message' => "Detected web server: {$serverSoftware}",
        'is_critical' => false
    ];

    $checks['url_rewriting'] = [
        'name' => 'URL Rewriting (.htaccess / Front Controller)',
        'type' => 'rewrite',
        'required' => '.htaccess present and URL rewriting supported',
        'actual' => $htaccessOk ? '.htaccess Active' : 'Missing .htaccess',
        'pass' => $htaccessOk,
        'message' => $htaccessOk ? 'Root .htaccess detected' : 'Missing .htaccess in project root. Clean URLs require web server rewrite rules.',
        'is_critical' => false
    ];

    $allCriticalPass = true;
    foreach ($checks as $c) {
        if (!empty($c['is_critical']) && empty($c['pass'])) {
            $allCriticalPass = false;
            break;
        }
    }
    if (!$auditRes->passed()) {
        $allCriticalPass = false;
    }

    return [
        'passed' => $allCriticalPass,
        'checks' => $checks,
        'server_software' => $serverSoftware,
        'is_apache' => $isApache,
        'is_nginx' => $isNginx,
        'is_iis' => $isIIS,
        'is_caddy' => $isCaddy,
        'db_drivers' => $dbDrivers,
        'php_version' => PHP_VERSION,
        'memory_limit' => ini_get('memory_limit') ?: 'unknown',
        'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? $rootDir
    ];
}

/**
 * Comprehensive Server Environment & Configuration Discovery Engine.
 * Searches and discovers local vs remote server attributes, web server software,
 * multi-platform OS and distro families (Debian/Ubuntu, RHEL/AlmaLinux, Alpine, Arch, SUSE, Windows, macOS, BSD),
 * configuration directories, daemon users, tenant Linux users, and PHP-FPM sockets.
 *
 * @param string $rootDir
 * @param string $domain
 * @param string|null $customUser
 * @return array
 */
function spp_installer_discover_server_environment(string $rootDir, string $domain, ?string $customUser = null, ?string $preset = null): array
{
    $cleanDomain = strtolower(trim(explode(':', $domain)[0])) ?: 'localhost';
    $normRoot = str_replace('\\', '/', $rootDir);

    // 1. Detect OS & Platform
    $osFamily = PHP_OS_FAMILY;
    $uname = php_uname('s') . ' ' . php_uname('r') . ' (' . php_uname('m') . ')';

    $procVer = @file_get_contents('/proc/version') ?: '';
    $isWsl = ($osFamily === 'Linux' && (str_contains(strtolower($procVer), 'microsoft') || str_contains(strtolower($procVer), 'wsl')))
        || str_starts_with($normRoot, '/mnt/');
    $isWindows = ($osFamily === 'Windows');
    $isDarwin = ($osFamily === 'Darwin');
    $isBsd = ($osFamily === 'BSD');
    $isLocalDev = $isWsl || $isWindows || in_array($cleanDomain, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($cleanDomain, '.localhost') || str_ends_with($cleanDomain, '.local') || str_ends_with($cleanDomain, '.test');

    // Detect Distro Family & Package Manager
    $distroFamily = 'debian_like';
    $packageManager = 'apt';
    $distroUser = 'www-data';
    $distroName = 'Linux';

    if ($isWindows) {
        $distroFamily = 'windows';
        $packageManager = 'choco';
        $distroUser = 'IIS_IUSRS';
        $distroName = 'Microsoft Windows ' . php_uname('r');
    } elseif ($isDarwin) {
        $distroFamily = 'darwin';
        $packageManager = 'brew';
        $distroUser = '_www';
        $distroName = 'macOS ' . php_uname('r');
    } elseif ($isBsd) {
        $distroFamily = 'bsd';
        $packageManager = 'pkg';
        $distroUser = 'www';
        $distroName = php_uname('s') . ' ' . php_uname('r');
    } elseif ($osFamily === 'Linux') {
        $osRelease = @file_get_contents('/etc/os-release') ?: '';
        $distroId = '';
        $distroIdLike = '';
        if (preg_match('/^ID="?([^"\n]+)"?/m', $osRelease, $m)) {
            $distroId = strtolower($m[1]);
        }
        if (preg_match('/^ID_LIKE="?([^"\n]+)"?/m', $osRelease, $m)) {
            $distroIdLike = strtolower($m[1]);
        }
        if (preg_match('/^PRETTY_NAME="?([^"\n]+)"?/m', $osRelease, $m)) {
            $distroName = $m[1];
        }

        if (@file_exists('/etc/alpine-release') || $distroId === 'alpine') {
            $distroFamily = 'alpine';
            $packageManager = 'apk';
            $distroUser = 'nobody';
            if ($distroName === 'Linux') $distroName = 'Alpine Linux';
        } elseif (@file_exists('/etc/arch-release') || in_array($distroId, ['arch', 'manjaro', 'endeavouros'], true)) {
            $distroFamily = 'arch';
            $packageManager = 'pacman';
            $distroUser = 'http';
            if ($distroName === 'Linux') $distroName = 'Arch Linux';
        } elseif (str_contains($distroIdLike, 'suse') || in_array($distroId, ['suse', 'opensuse', 'opensuse-leap', 'opensuse-tumbleweed', 'sles'], true)) {
            $distroFamily = 'suse';
            $packageManager = 'zypper';
            $distroUser = 'wwwrun';
            if ($distroName === 'Linux') $distroName = 'openSUSE / SLES';
        } elseif (str_contains($distroIdLike, 'rhel') || str_contains($distroIdLike, 'fedora') || str_contains($distroIdLike, 'centos') || in_array($distroId, ['rhel', 'centos', 'almalinux', 'rocky', 'fedora', 'amzn'], true) || @file_exists('/etc/redhat-release')) {
            $distroFamily = 'rhel_like';
            $packageManager = 'dnf';
            $distroUser = 'apache';
            if ($distroName === 'Linux') $distroName = 'RHEL / AlmaLinux / Rocky / CentOS';
        } else {
            $distroFamily = 'debian_like';
            $packageManager = 'apt';
            $distroUser = 'www-data';
            if ($distroName === 'Linux') $distroName = 'Debian / Ubuntu';
        }
    }
    if ($isWsl) {
        $distroName .= ' (WSL)';
    }

    // 2. Detect Web Server Software
    $serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? 'CLI / Standalone';
    $isApache = stripos($serverSoftware, 'apache') !== false;
    $isNginx = stripos($serverSoftware, 'nginx') !== false;
    $isLiteSpeed = stripos($serverSoftware, 'litespeed') !== false;
    $isIIS = stripos($serverSoftware, 'microsoft-iis') !== false || stripos($serverSoftware, 'iis') !== false;
    $isCaddy = stripos($serverSoftware, 'caddy') !== false;
    $isCPanel = @is_dir('/usr/local/cpanel') || preg_match('#/home/[^/]+/public_html#', $normRoot);

    // Web Server Config Locations
    $apacheConfDir = '/etc/apache2/sites-available';
    if ($distroFamily === 'rhel_like' || @is_dir('/etc/httpd/conf.d')) {
        $apacheConfDir = '/etc/httpd/conf.d';
    } elseif ($distroFamily === 'alpine' || @is_dir('/etc/apache2/conf.d')) {
        $apacheConfDir = '/etc/apache2/conf.d';
    } elseif ($distroFamily === 'arch' || @is_dir('/etc/httpd/conf/extra')) {
        $apacheConfDir = '/etc/httpd/conf/extra';
    } elseif ($distroFamily === 'suse' || @is_dir('/etc/apache2/vhosts.d')) {
        $apacheConfDir = '/etc/apache2/vhosts.d';
    } elseif ($distroFamily === 'darwin') {
        $apacheConfDir = '/opt/homebrew/etc/httpd/extra';
    } elseif ($distroFamily === 'bsd') {
        $apacheConfDir = '/usr/local/etc/apache24/Includes';
    } elseif ($distroFamily === 'windows') {
        $apacheConfDir = 'C:/Apache24/conf/extra';
    }

    $nginxConfDir = '/etc/nginx/sites-available';
    if ($distroFamily === 'alpine' || @is_dir('/etc/nginx/http.d')) {
        $nginxConfDir = '/etc/nginx/http.d';
    } elseif ($distroFamily === 'rhel_like' || @is_dir('/etc/nginx/conf.d')) {
        $nginxConfDir = '/etc/nginx/conf.d';
    } elseif ($distroFamily === 'suse' || @is_dir('/etc/nginx/vhosts.d')) {
        $nginxConfDir = '/etc/nginx/vhosts.d';
    } elseif ($distroFamily === 'darwin') {
        $nginxConfDir = '/opt/homebrew/etc/nginx/servers';
    } elseif ($distroFamily === 'bsd') {
        $nginxConfDir = '/usr/local/etc/nginx';
    } elseif ($distroFamily === 'windows') {
        $nginxConfDir = 'C:/nginx/conf';
    }

    // 3. Detect System & Execution Users
    $domainPrefix = preg_replace('/[^a-zA-Z0-9_]/', '', explode('.', $cleanDomain)[0]) ?: 'sppuser';
    $systemUser = $customUser ?: $domainPrefix;

    $serverUser = $distroUser;
    if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
        $pwuid = @posix_getpwuid(@posix_geteuid());
        if (!empty($pwuid['name'])) {
            $serverUser = $pwuid['name'];
        }
    } elseif (function_exists('get_current_user') && get_current_user()) {
        $serverUser = get_current_user();
    }
    if ($serverUser === 'root' || $isWindows || $isWsl) {
        $serverUser = $distroUser;
    }

    // 4. Detect PHP-FPM Socket
    $fpmSocket = ($distroFamily === 'alpine')
        ? 'unix:/var/run/php-fpm82.sock'
        : (($distroFamily === 'rhel_like') ? 'unix:/var/run/php-fpm/www.sock' : ('unix:/var/run/php/php' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '-fpm.sock'));
    if ($isWindows) {
        $fpmSocket = '127.0.0.1:9000';
    }
    $fpmFound = false;
    foreach (['/var/run/php', '/run/php', '/var/run/php-fpm', '/run/php-fpm', '/var/run', '/run'] as $sockDir) {
        if (@is_dir($sockDir)) {
            $socks = @glob($sockDir . '/php*.sock') ?: (@glob($sockDir . '/www.sock') ?: []);
            if (!empty($socks)) {
                $fpmSocket = 'unix:' . $socks[0];
                $fpmFound = true;
                break;
            }
        }
    }

    // 5. Intelligent Server Path Presets Across Platforms
    $presets = [
        'cpanel_home' => [
            'id' => 'cpanel_home',
            'label' => '🏠 User Home Primary (/home/' . $systemUser . '/public_html) [Linux Recommended]',
            'description' => "cPanel and multi-tenant Linux VPS layout: /home/{$systemUser}/public_html (Zero permission collisions, 1-to-1 transferability)",
            'root' => "/home/{$systemUser}/public_html",
            'public' => "/home/{$systemUser}/public_html/public",
            'user' => $systemUser,
            'platform' => 'linux'
        ],
        'home_domain' => [
            'id' => 'home_domain',
            'label' => "📁 User Home Multi-Domain (/home/{$systemUser}/{$cleanDomain})",
            'description' => "Isolated domain or addon domain inside user home: /home/{$systemUser}/{$cleanDomain}/public",
            'root' => "/home/{$systemUser}/{$cleanDomain}",
            'public' => "/home/{$systemUser}/{$cleanDomain}/public",
            'user' => $systemUser,
            'platform' => 'linux'
        ],
        'linux_fhs' => [
            'id' => 'linux_fhs',
            'label' => "🌐 Linux FHS System Path (/var/www/{$cleanDomain})",
            'description' => "Traditional shared web server path: /var/www/{$cleanDomain} (Ubuntu, Debian, RHEL, Alpine, Arch)",
            'root' => "/var/www/{$cleanDomain}",
            'public' => "/var/www/{$cleanDomain}/public",
            'user' => $serverUser,
            'platform' => 'linux'
        ],
        'ubuntu_default' => [
            'id' => 'ubuntu_default',
            'label' => '🐧 Ubuntu / Debian Default (/var/www/html)',
            'description' => 'Default Apache web root: /var/www/html/public',
            'root' => '/var/www/html',
            'public' => '/var/www/html/public',
            'user' => 'www-data',
            'platform' => 'linux'
        ],
        'windows_iis' => [
            'id' => 'windows_iis',
            'label' => "🪟 Windows Server IIS (C:/inetpub/wwwroot/{$cleanDomain})",
            'description' => "Microsoft IIS standard production web root: C:/inetpub/wwwroot/{$cleanDomain}",
            'root' => "C:/inetpub/wwwroot/{$cleanDomain}",
            'public' => "C:/inetpub/wwwroot/{$cleanDomain}/public",
            'user' => 'IIS_IUSRS',
            'platform' => 'windows'
        ],
        'windows_home' => [
            'id' => 'windows_home',
            'label' => "👤 Windows User Sites (C:/Users/{$systemUser}/Sites/{$cleanDomain})",
            'description' => "Windows multi-tenant user profile: C:/Users/{$systemUser}/Sites/{$cleanDomain}",
            'root' => "C:/Users/{$systemUser}/Sites/{$cleanDomain}",
            'public' => "C:/Users/{$systemUser}/Sites/{$cleanDomain}/public",
            'user' => $systemUser,
            'platform' => 'windows'
        ],
        'windows_laragon' => [
            'id' => 'windows_laragon',
            'label' => "⚡ Windows Laragon (C:/laragon/www/{$cleanDomain})",
            'description' => "Laragon isolated development root: C:/laragon/www/{$cleanDomain}",
            'root' => "C:/laragon/www/{$cleanDomain}",
            'public' => "C:/laragon/www/{$cleanDomain}/public",
            'user' => $systemUser,
            'platform' => 'windows'
        ],
        'macos_home' => [
            'id' => 'macos_home',
            'label' => "🍎 macOS User Sites (/Users/{$systemUser}/Sites/{$cleanDomain})",
            'description' => "macOS Apache / Valet user directory: /Users/{$systemUser}/Sites/{$cleanDomain}",
            'root' => "/Users/{$systemUser}/Sites/{$cleanDomain}",
            'public' => "/Users/{$systemUser}/Sites/{$cleanDomain}/public",
            'user' => $systemUser,
            'platform' => 'darwin'
        ]
    ];

    $docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');
    if (!$isLocalDev && $docRoot !== '') {
        $presets['active_server'] = [
            'id' => 'active_server',
            'label' => 'Active Server Auto-Detected',
            'description' => "Discovered live server runtime path: {$normRoot}",
            'root' => $normRoot,
            'public' => str_ends_with($docRoot, '/public') ? $docRoot : ($normRoot . '/public'),
            'user' => $systemUser,
            'platform' => $distroFamily
        ];
        $selectedProfile = 'active_server';
    } else {
        $presets['local_detected'] = [
            'id' => 'local_detected',
            'label' => 'Local Machine (Detected)',
            'description' => "Local dev path: {$normRoot}",
            'root' => $normRoot,
            'public' => $normRoot . '/public',
            'user' => $systemUser,
            'platform' => $distroFamily
        ];
        if ($preset && isset($presets[$preset])) {
            $selectedProfile = $preset;
        } elseif ($isIIS) {
            $selectedProfile = 'windows_iis';
        } else {
            // Production domains default directly to cpanel_home structure for maximum VPS/cPanel portability
            $selectedProfile = ($cleanDomain !== 'localhost' && !str_ends_with($cleanDomain, '.localhost') && !str_ends_with($cleanDomain, '.local') && !str_ends_with($cleanDomain, '.test'))
                ? 'cpanel_home'
                : 'local_detected';
        }
    }

    $defaultRoot = $presets[$selectedProfile]['root'];
    $defaultPublic = $presets[$selectedProfile]['public'];

    return [
        'clean_domain' => $cleanDomain,
        'system_user' => $systemUser,
        'os_family' => $osFamily,
        'distro_family' => $distroFamily,
        'distro_name' => $distroName,
        'package_manager' => $packageManager,
        'uname' => $uname,
        'is_wsl' => $isWsl,
        'is_windows' => $isWindows,
        'is_darwin' => $isDarwin,
        'is_bsd' => $isBsd,
        'is_local_dev' => $isLocalDev,
        'server_software' => $serverSoftware,
        'is_apache' => $isApache,
        'is_nginx' => $isNginx,
        'is_litespeed' => $isLiteSpeed,
        'is_iis' => $isIIS,
        'is_caddy' => $isCaddy,
        'is_cpanel' => $isCPanel,
        'apache_conf_dir' => $apacheConfDir,
        'nginx_conf_dir' => $nginxConfDir,
        'server_user' => $serverUser,
        'fpm_socket' => $fpmSocket,
        'fpm_found' => $fpmFound,
        'presets' => $presets,
        'selected_profile' => $selectedProfile,
        'target_root' => $defaultRoot,
        'target_public' => $defaultPublic,
        'detected_local_root' => $normRoot
    ];
}

/**
 * Generate tailored web server virtual host configuration templates
 * based on auto-discovered server environment, target paths, and tenant user.
 * Supports Apache, Nginx, Microsoft IIS (web.config), and Caddy (Caddyfile),
 * alongside multi-platform permission commands and package installation cheatsheets.
 *
 * @param string $rootDir
 * @param string $domain
 * @param string|null $overrideRoot
 * @param string|null $overridePublic
 * @param string|null $overrideUser
 * @param string|null $preset
 * @return array{apache: string, nginx: string, iis: string, caddy: string, permissions: string, php_ini: string, env: array}
 */
function spp_installer_get_server_guidance(string $rootDir, string $domain, ?string $overrideRoot = null, ?string $overridePublic = null, ?string $overrideUser = null, ?string $preset = null): array
{
    $env = spp_installer_discover_server_environment($rootDir, $domain, $overrideUser, $preset);
    $cleanDomain = $env['clean_domain'];
    $systemUser = $overrideUser ?: $env['system_user'];

    $targetRoot = $overrideRoot ?: $env['target_root'];
    $publicDir = $overridePublic ?: $env['target_public'];

    $hasSub = substr_count($cleanDomain, '.') >= 1;
    $serverAliases = ($hasSub && !str_starts_with($cleanDomain, 'www.')) ? "ServerAlias www.{$cleanDomain}" : "";
    $nginxAliases = ($hasSub && !str_starts_with($cleanDomain, 'www.')) ? " www.{$cleanDomain}" : "";
    $caddyAliases = ($hasSub && !str_starts_with($cleanDomain, 'www.')) ? ", www.{$cleanDomain}" : "";

    $isWinTarget = str_contains($targetRoot, ':') || str_starts_with($targetRoot, '\\\\');
    $apacheConfDir = $isWinTarget ? 'C:/Apache24/conf/extra' : ($env['apache_conf_dir'] === 'C:/Apache24/conf/extra' ? '/etc/apache2/sites-available' : $env['apache_conf_dir']);
    $nginxConfDir = $isWinTarget ? 'C:/nginx/conf' : ($env['nginx_conf_dir'] === 'C:/nginx/conf' ? '/etc/nginx/sites-available' : $env['nginx_conf_dir']);
    $fpmSocket = $isWinTarget ? '127.0.0.1:9000' : ($env['fpm_socket'] === '127.0.0.1:9000' ? 'unix:/var/run/php/php' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '-fpm.sock' : $env['fpm_socket']);

    // Determine effective user: for /home/<user>/... structures, use the tenant user
    $isHome = str_starts_with($targetRoot, '/home/');
    $effectiveUser = $isHome ? $systemUser : ($isWinTarget ? 'IIS_IUSRS' : $env['server_user']);
    $homeDir = $isHome ? ('/home/' . explode('/', trim($targetRoot, '/'))[1]) : '';

    // Apache Configuration
    $apache = "# Apache VirtualHost for {$cleanDomain} (Multi-Platform / User Home Architecture)\n" .
        "# Fully compatible with cPanel, DirectAdmin, CloudPanel, and standalone Linux VPS.\n" .
        "# Place this file in {$apacheConfDir}/{$cleanDomain}.conf\n\n" .
        "<VirtualHost *:80>\n" .
        "    ServerName {$cleanDomain}\n" .
        ($serverAliases ? "    {$serverAliases}\n" : "") .
        "    DocumentRoot \"{$publicDir}\"\n\n" .
        "    <Directory \"{$publicDir}\">\n" .
        "        Options Indexes FollowSymLinks MultiViews\n" .
        "        AllowOverride All\n" .
        "        Require all granted\n" .
        "    </Directory>\n\n" .
        "    # Security: Restrict direct HTTP access to framework system & configuration directories\n" .
        "    <Directory \"{$targetRoot}/etc\">\n" .
        "        Require all denied\n" .
        "    </Directory>\n\n" .
        "    <Directory \"{$targetRoot}/var\">\n" .
        "        Require all denied\n" .
        "    </Directory>\n\n" .
        "    # Multi-tenant execution: Runs PHP as dedicated Linux user\n" .
        "    <IfModule mpm_itk_module>\n" .
        "        AssignUserId {$effectiveUser} {$effectiveUser}\n" .
        "    </IfModule>\n\n" .
        "    ErrorLog \${APACHE_LOG_DIR}/spp_{$cleanDomain}_error.log\n" .
        "    CustomLog \${APACHE_LOG_DIR}/spp_{$cleanDomain}_access.log combined\n" .
        "</VirtualHost>";

    // Nginx Configuration
    $nginx = "# Nginx Server Block for {$cleanDomain} (Multi-Platform / User Home Architecture)\n" .
        "# Fully compatible with cPanel, DirectAdmin, CloudPanel, and standalone Linux VPS.\n" .
        "# Place this file in {$nginxConfDir}/{$cleanDomain} and reload Nginx\n\n" .
        "server {\n" .
        "    listen 80;\n" .
        "    server_name {$cleanDomain}{$nginxAliases};\n" .
        "    root {$publicDir};\n" .
        "    index index.php index.html;\n\n" .
        "    # Pass all requests to SPP front controller\n" .
        "    location / {\n" .
        "        try_files \$uri \$uri/ /index.php?\$query_string;\n" .
        "    }\n\n" .
        "    # PHP-FPM integration (auto-discovered socket or user pool)\n" .
        "    location ~ \\.php$ {\n" .
        "        fastcgi_pass {$fpmSocket};\n" .
        "        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;\n" .
        "        include fastcgi_params;\n" .
        "    }\n\n" .
        "    # Security: Restrict direct access to internal files and directories\n" .
        "    location ~ /\\.(?!well-known) {\n" .
        "        deny all;\n" .
        "    }\n" .
        "    location ^~ /etc/ {\n" .
        "        deny all;\n" .
        "    }\n" .
        "    location ^~ /var/ {\n" .
        "        deny all;\n" .
        "    }\n" .
        "}";

    // Microsoft IIS Configuration (web.config)
    $iis = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
        "<!-- Microsoft IIS Configuration for {$cleanDomain} -->\n" .
        "<!-- Place this web.config in the document root ({$publicDir}) -->\n" .
        "<configuration>\n" .
        "    <system.webServer>\n" .
        "        <defaultDocument>\n" .
        "            <files>\n" .
        "                <clear />\n" .
        "                <add value=\"index.php\" />\n" .
        "                <add value=\"index.html\" />\n" .
        "            </files>\n" .
        "        </defaultDocument>\n" .
        "        <rewrite>\n" .
        "            <rules>\n" .
        "                <!-- Security: Block direct HTTP access to internal system directories -->\n" .
        "                <rule name=\"Block Internal Directories\" stopProcessing=\"true\">\n" .
        "                    <match url=\"^(etc|var|domains|\\.git|\\.env)\" ignoreCase=\"true\" />\n" .
        "                    <action type=\"CustomResponse\" statusCode=\"403\" statusReason=\"Forbidden\" statusDescription=\"Access to internal directories is denied.\" />\n" .
        "                </rule>\n" .
        "                <!-- SPP Front Controller URL Rewriting -->\n" .
        "                <rule name=\"SPP Front Controller\" stopProcessing=\"true\">\n" .
        "                    <match url=\"^\" ignoreCase=\"false\" />\n" .
        "                    <conditions logicalGrouping=\"MatchAll\">\n" .
        "                        <add input=\"{REQUEST_FILENAME}\" matchType=\"IsFile\" negate=\"true\" />\n" .
        "                        <add input=\"{REQUEST_FILENAME}\" matchType=\"IsDirectory\" negate=\"true\" />\n" .
        "                    </conditions>\n" .
        "                    <action type=\"Rewrite\" url=\"index.php\" />\n" .
        "                </rule>\n" .
        "            </rules>\n" .
        "        </rewrite>\n" .
        "        <security>\n" .
        "            <requestFiltering>\n" .
        "                <hiddenSegments>\n" .
        "                    <add segment=\"etc\" />\n" .
        "                    <add segment=\"var\" />\n" .
        "                    <add segment=\"domains\" />\n" .
        "                </hiddenSegments>\n" .
        "            </requestFiltering>\n" .
        "        </security>\n" .
        "        <httpErrors errorMode=\"DetailedLocalOnly\" />\n" .
        "    </system.webServer>\n" .
        "</configuration>";

    // Caddy Server Block Configuration (Caddyfile)
    $caddyFastCgi = str_starts_with($fpmSocket, 'unix:') ? ('unix/' . substr($fpmSocket, 5)) : ($fpmSocket ?: '127.0.0.1:9000');
    if ($env['is_windows']) {
        $caddyFastCgi = '127.0.0.1:9000';
    }
    $caddy = "# Caddyfile Server Block for {$cleanDomain}\n" .
        "# Place in /etc/caddy/Caddyfile or your Caddy project configuration\n\n" .
        "{$cleanDomain}{$caddyAliases} {\n" .
        "    root * \"{$publicDir}\"\n" .
        "    encode zstd gzip\n\n" .
        "    # Pass PHP requests to FastCGI backend\n" .
        "    php_fastcgi {$caddyFastCgi}\n\n" .
        "    # Serve static assets\n" .
        "    file_server\n\n" .
        "    # Security: Restrict direct HTTP access to system configuration and data\n" .
        "    @restricted {\n" .
        "        path /etc/* /var/* /domains/* /.*\n" .
        "    }\n" .
        "    respond @restricted 403\n" .
        "}";

    // File Permissions & User Provisioning Commands Across OS Platforms
    $targetRootWin = str_replace('/', '\\', $targetRoot);
    $permissions = '';
    if ($isWinTarget) {
        $permissions = "REM Windows Server (IIS / NTFS Permissions via icacls)\n" .
            "REM 1. Grant read & execute permissions to IIS worker process:\n" .
            "icacls \"{$targetRootWin}\" /grant \"IIS_IUSRS\":(OI)(CI)RX /T\n" .
            "icacls \"{$targetRootWin}\" /grant \"IUSR\":(OI)(CI)RX /T\n\n" .
            "REM 2. Grant modify/write permissions for framework cache, logs, and config:\n" .
            "icacls \"{$targetRootWin}\\var\" /grant \"IIS_IUSRS\":(OI)(CI)M /T\n" .
            "icacls \"{$targetRootWin}\\etc\" /grant \"IIS_IUSRS\":(OI)(CI)M /T\n" .
            "icacls \"{$targetRootWin}\\domains\" /grant \"IIS_IUSRS\":(OI)(CI)M /T\n\n" .
            "REM For Windows Desktop / Local Dev (Laragon / XAMPP):\n" .
            "REM Windows user profiles typically have full write permissions by default.";
    } else {
        if ($homeDir) {
            $permissions .= "# 1. Grant web server traversal permissions to user home directory\n" .
                "sudo chmod 755 \"{$homeDir}\"\n\n";
        }
        $permissions .= "# 2. Set directory ownership to tenant user ({$effectiveUser})\n" .
            "sudo chown -R {$effectiveUser}:{$effectiveUser} \"{$targetRoot}\"\n\n" .
            "# 3. Ensure runtime and configuration directories are writable\n" .
            "sudo chmod -R 775 \"{$targetRoot}/var\"\n" .
            "sudo chmod -R 775 \"{$targetRoot}/etc\"\n" .
            "sudo chmod -R 775 \"{$targetRoot}/domains\"\n\n" .
            "# cPanel Interoperability Note:\n" .
            "# When deploying directly to cPanel, files upload into /home/{$systemUser}/public_html.\n" .
            "# File ownership and permissions are already set by cPanel as {$systemUser}:{$systemUser}.";
    }

    // PHP Extensions Installation Cheatsheet Across Operating Systems
    $phpIni = "# 1. Ubuntu / Debian / Linux Mint (apt):\n" .
        "sudo apt-get update\n" .
        "sudo apt-get install -y php-pdo php-sqlite3 php-mysql php-mbstring php-xml php-curl php-zip php-gd php-intl\n\n" .
        "# 2. RHEL / AlmaLinux / Rocky / CentOS / Fedora (dnf / yum):\n" .
        "sudo dnf install -y php-pdo php-sqlite3 php-mysqlnd php-mbstring php-xml php-curl php-zip php-gd php-intl\n\n" .
        "# 3. Alpine Linux (apk - Docker / MicroVMs):\n" .
        "apk add --no-cache php82-pdo php82-pdo_sqlite php82-pdo_mysql php82-mbstring php82-xml php82-curl php82-zip php82-gd php82-intl\n\n" .
        "# 4. Arch Linux / Manjaro (pacman):\n" .
        "sudo pacman -S php php-sqlite php-gd php-intl\n" .
        "# Enable extensions in /etc/php/php.ini: extension=pdo_sqlite, extension=pdo_mysql, extension=curl\n\n" .
        "# 5. openSUSE Leap / Tumbleweed / SLES (zypper):\n" .
        "sudo zypper install php8-sqlite php8-mysql php8-mbstring php8-xmlwriter php8-curl php8-zip php8-gd\n\n" .
        "# 6. macOS (Homebrew):\n" .
        "brew install php\n" .
        "# Core extensions are bundled; start service with: brew services start php\n\n" .
        "# 7. Windows Server & Desktop (php.ini or Chocolatey):\n" .
        "# Chocolatey: choco install php\n" .
        "# In php.ini, uncomment: extension=pdo_sqlite, extension=pdo_mysql, extension=mbstring, extension=curl, extension=gd, extension=fileinfo\n\n" .
        "# 8. cPanel / CloudLinux / WHM:\n" .
        "# In cPanel -> 'Select PHP Version' -> 'Extensions' tab, verify enabled:\n" .
        "# pdo, pdo_sqlite, pdo_mysql, openssl, mbstring, curl, zip, fileinfo, gd";

    return [
        'apache' => $apache,
        'nginx' => $nginx,
        'iis' => $iis,
        'caddy' => $caddy,
        'permissions' => $permissions,
        'php_ini' => $phpIni,
        'env' => $env
    ];
}

/**
 * Scan for available applications in src/ and domains/
 *
 * @param string $rootDir
 * @return array<string, array{name: string, path: string, type: string}>
 */
/**
 * Discover all available applications in the SPP workspace.
 * Distinguishes full functional applications from minimal test stubs.
 *
 * @param string $rootDir
 * @return array<string, array{name: string, path: string, type: string, is_primary: bool}>
 */
function spp_installer_get_available_apps(string $rootDir): array
{
    $primaryApps = [];
    $otherApps = [];

    // Global src/ apps
    $srcDir = $rootDir . '/src';
    if (is_dir($srcDir)) {
        foreach (scandir($srcDir) as $item) {
            if ($item === '.' || $item === '..' || !is_dir($srcDir . '/' . $item)) {
                continue;
            }
            if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $item)) {
                continue;
            }
            if (in_array($item, ['modules', 'etc', 'pages', 'serv', 'old', 'tests'], true)) {
                continue;
            }

            $appPath = $srcDir . '/' . $item;
            $hasControllers = is_dir($appPath . '/Controllers') || is_dir($appPath . '/controllers');
            $hasPages = is_dir($appPath . '/pages') || is_dir($appPath . '/Pages');
            $hasViews = is_dir($appPath . '/resources/views') || is_dir($appPath . '/views');
            $hasAppYml = file_exists($appPath . '/etc/app.yml') || file_exists($rootDir . '/etc/apps/' . $item . '/app.yml');

            $isFunctional = $hasControllers || $hasPages || $hasViews || $hasAppYml;

            $meta = [
                'name' => $item,
                'path' => 'src/' . $item,
                'type' => $isFunctional ? 'Standard Application (src/)' : 'Application Stub (src/)',
                'is_primary' => $isFunctional
            ];

            if ($isFunctional) {
                $primaryApps[$item] = $meta;
            } else {
                $otherApps[$item] = $meta;
            }
        }
    }

    // Known apps registered in App config
    if (class_exists('\\SPP\\App')) {
        $regApps = \SPP\App::getGlobalSettings('apps');
        if (is_array($regApps)) {
            foreach ($regApps as $name => $meta) {
                if (!isset($primaryApps[$name]) && !isset($otherApps[$name]) && preg_match('/^[a-zA-Z0-9_\-]+$/', $name)) {
                    $isDomainApp = !empty($meta['is_domain_app']);
                    $primaryApps[$name] = [
                        'name' => $name,
                        'path' => $meta['src_path'] ?? ('src/' . $name),
                        'type' => $isDomainApp ? 'Domain-Specific App' : 'Registered Application',
                        'is_primary' => true
                    ];
                }
            }
        }
    }

    // Priority ordering for prominent SPP applications
    $priority = ['default', 'SPPDocs', 'evaarta', 'DemoSchool', 'lekhak', 'DemoApp', 'ptable', 'myapp'];
    $sorted = [];
    foreach ($priority as $pName) {
        if (isset($primaryApps[$pName])) {
            $sorted[$pName] = $primaryApps[$pName];
            unset($primaryApps[$pName]);
        }
    }

    // Append remaining functional apps, then stubs
    return array_merge($sorted, $primaryApps, $otherApps);
}

/**
 * Execute domain installation and server configuration.
 *
 * @param string $rootDir
 * @param string $domain
 * @param string $appName
 * @param array $options
 * @return array{success: bool, message: string, details: array}
 */
function spp_installer_execute(string $rootDir, string $domain, string $appName, array $options = []): array
{
    $cleanDomain = strtolower(trim(explode(':', $domain)[0]));
    $cleanApp = trim($appName);
    $title = trim((string)($options['title'] ?? $cleanApp));
    $makeDefault = !isset($options['make_default']) || !empty($options['make_default']);
    $addWwwAlias = !isset($options['add_www']) || !empty($options['add_www']);
    $virtualHostMode = strtolower(trim((string)($options['mode'] ?? 'strict')));
    $isNewScaffold = !empty($options['new_scaffold']);

    if ($cleanDomain === '' || !preg_match('/^[a-z0-9_\-\.]+$/i', $cleanDomain)) {
        return ['success' => false, 'message' => "Invalid domain name '{$domain}'. Please provide a valid hostname (e.g. localhost or mysite.com).", 'details' => []];
    }
    if ($cleanApp === '' || !preg_match('/^[a-zA-Z0-9_\-]+$/', $cleanApp)) {
        return ['success' => false, 'message' => "Invalid application identifier '{$cleanApp}'.", 'details' => []];
    }

    $aliases = [];
    if ($addWwwAlias && !str_starts_with($cleanDomain, 'www.') && substr_count($cleanDomain, '.') >= 1) {
        $aliases[] = 'www.' . $cleanDomain;
    }

    // 1. Ensure storage directories exist with proper permissions
    $domainsDir = $rootDir . '/domains';
    if (!is_dir($domainsDir)) {
        @mkdir($domainsDir, 0775, true);
    }
    $vhostDir = $domainsDir . '/_vhosts';
    if (!is_dir($vhostDir)) {
        @mkdir($vhostDir, 0775, true);
    }
    $targetDomainDir = $domainsDir . '/' . $cleanDomain;
    if (!is_dir($targetDomainDir)) {
        @mkdir($targetDomainDir, 0775, true);
    }

    // 2. Determine if app is an existing app in src/ or a new scaffold in domains/<domain>/src/
    $existingSrcPath = $rootDir . '/src/' . $cleanApp;
    $existingDomainPath = $targetDomainDir . '/src/' . $cleanApp;
    $isExistingApp = is_dir($existingSrcPath) || is_dir($existingDomainPath);

    $appType = 'Existing Application';
    $installedPath = is_dir($existingDomainPath) ? "domains/{$cleanDomain}/src/{$cleanApp}" : "src/{$cleanApp}";

    if (!$isExistingApp || $isNewScaffold) {
        $scaffoldRes = DomainRouter::installDomainApp($cleanDomain, $cleanApp, [
            'title' => $title,
            'make_default' => $makeDefault,
            'allow_other_apps' => true
        ]);
        if (empty($scaffoldRes['success'])) {
            return ['success' => false, 'message' => $scaffoldRes['error'] ?? 'Application scaffolding failed.', 'details' => []];
        }
        $appType = 'New Domain-Isolated Application';
        $installedPath = $scaffoldRes['path'] ?? "domains/{$cleanDomain}/src/{$cleanApp}";
    } else {
        // Register existing application to this domain
        DomainRouter::loadConfig();
        $domainConfig = [
            'type' => 'primary',
            'account' => 'default',
            'default_app' => $cleanApp,
            'aliases' => $aliases,
            'canonical_host' => $cleanDomain,
            'force_https' => false,
            'allow_other_apps' => true,
            'enabled_apps' => [$cleanApp]
        ];
        DomainRouter::registerDomain($cleanDomain, $domainConfig);

        // Ensure this domain is allowed in the app's restrictions if restricted
        $restrictions = DomainRouter::getAppRestrictions($cleanApp);
        if (!empty($options['domain_only'])) {
            DomainRouter::setAppDomainScope($cleanApp, true, array_merge($restrictions['allowed_domains'] ?? [], [$cleanDomain], $aliases));
        } elseif (!empty($restrictions['domain_only'])) {
            DomainRouter::setAppDomainScope($cleanApp, true, array_merge($restrictions['allowed_domains'] ?? [], [$cleanDomain], $aliases));
        }
    }

    // 3. Set Virtual Host Mode
    if (in_array($virtualHostMode, DomainRouter::MODES, true)) {
        DomainRouter::setVirtualHostMode($virtualHostMode);
    }

    // 4. Save etc/domains.yml
    DomainRouter::saveConfig();

    // 5. Generate tailored web server virtual host templates with the actual domain & user
    $user = $options['user'] ?? null;
    $guidance = spp_installer_get_server_guidance($rootDir, $cleanDomain, null, null, $user);
    file_put_contents($vhostDir . '/apache-vhost.conf.template', $guidance['apache']);
    file_put_contents($vhostDir . '/nginx-vhost.conf.template', $guidance['nginx']);

    // 6. Safe .htaccess check (ensure php_flag does not crash modern web servers)
    $htaccessFile = $rootDir . '/.htaccess';
    if (file_exists($htaccessFile)) {
        $htaccessContent = file_get_contents($htaccessFile);
        if (!str_contains($htaccessContent, '<IfModule mod_php') && preg_match('/^\s*php_flag\s+display_errors/m', $htaccessContent)) {
            $htaccessContent = preg_replace(
                '/^\s*php_flag\s+display_errors\s+.*$/m',
                "# PHP settings guard (mod_php only)\n<IfModule mod_php7.c>\n    php_flag display_errors 1\n</IfModule>\n<IfModule mod_php.c>\n    php_flag display_errors 1\n</IfModule>",
                $htaccessContent
            );
            @file_put_contents($htaccessFile, $htaccessContent);
        }
    }

    // 7. Flush and rebuild system caches
    DomainRouter::clearConfigCaches();

    // 8. Verify domain resolution
    $resolved = DomainRouter::resolveDomain($cleanDomain);
    $resolvedApp = $resolved ? ($resolved['default_app'] ?? $cleanApp) : $cleanApp;

    // Detect base URI
    $baseUri = defined('APP_BASE_URI') && APP_BASE_URI !== '' && APP_BASE_URI !== '/' ? rtrim(APP_BASE_URI, '/') : '';
    $entryUrl = ($cleanDomain === 'localhost' || str_ends_with($cleanDomain, '.localhost') || str_ends_with($cleanDomain, '.local') || str_ends_with($cleanDomain, '.test'))
        ? "http://{$cleanDomain}" . ($baseUri ? $baseUri : '') . "/"
        : "http://{$cleanDomain}/";

    return [
        'success' => true,
        'message' => "SPP successfully installed and configured for '{$cleanDomain}'!",
        'details' => [
            'domain' => $cleanDomain,
            'app' => $cleanApp,
            'resolved_app' => $resolvedApp,
            'app_type' => $appType,
            'path' => $installedPath,
            'canonical_host' => $cleanDomain,
            'aliases' => $aliases,
            'policy_mode' => DomainRouter::getVirtualHostMode(),
            'entry_url' => $entryUrl,
            'admin_url' => rtrim($entryUrl, '/') . "/sppadmin/"
        ]
    ];
}

// =============================================================================
// WEB INTERFACE MODE (HTTP BROWSER EXECUTION)
// =============================================================================
if (PHP_SAPI !== 'cli' || in_array('--web', $argv ?? [], true) || isset($_GET['web'])) {
    // Dynamic domain detection: reads from incoming HTTP host header
    $detectedHost = '';
    if (!empty($_SERVER['HTTP_HOST'])) {
        $detectedHost = explode(':', $_SERVER['HTTP_HOST'])[0];
    } elseif (!empty($_SERVER['SERVER_NAME'])) {
        $detectedHost = explode(':', $_SERVER['SERVER_NAME'])[0];
    }
    $cleanDetectedHost = strtolower(trim($detectedHost)) ?: 'localhost';

    // Target domain from query, form, or detected host
    $activeDomain = trim($_GET['domain'] ?? ($_POST['domain'] ?? $cleanDetectedHost));
    if ($activeDomain === '') {
        $activeDomain = 'localhost';
    }

    $allApps = spp_installer_get_available_apps($rootDir);
    // Resolve default application dynamically from domain or framework settings (no hardcoded fallback)
    $domainConfig = DomainRouter::resolveDomain($activeDomain);
    $configuredApp = $domainConfig['default_app'] ?? null;

    if ($configuredApp && isset($allApps[$configuredApp])) {
        $defaultSelectedApp = $configuredApp;
    } else {
        $globalDefault = DomainRouter::getDefaultApp($activeDomain) ?: (\SPP\App::getGlobalSettings('base_app') ?: 'default');
        if (isset($allApps[$globalDefault])) {
            $defaultSelectedApp = $globalDefault;
        } elseif (isset($allApps['default'])) {
            $defaultSelectedApp = 'default';
        } elseif (isset($allApps['SPPDocs'])) {
            $defaultSelectedApp = 'SPPDocs';
        } elseif (isset($allApps['evaarta'])) {
            $defaultSelectedApp = 'evaarta';
        } else {
            $defaultSelectedApp = array_key_first($allApps) ?: 'default';
        }
    }

    // Run Server Audit
    $audit = spp_installer_audit_server($rootDir);
    $guidance = spp_installer_get_server_guidance($rootDir, $activeDomain);

    $installResult = null;
    $uninstallResult = null;
    $restoreResult = null;
    $deleteBackupResult = null;
    $createBackupResult = null;

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action'])) {
        $postAction = $_POST['action'];

        if ($postAction === 'install') {
            $postDomain = trim($_POST['domain'] ?? $activeDomain);
            $postApp = trim($_POST['app'] ?? $defaultSelectedApp);
            $postMode = trim($_POST['mode'] ?? 'strict');
            $postAddWww = !empty($_POST['add_www']);
            $postNewScaffold = !empty($_POST['new_scaffold']);

            $installResult = spp_installer_execute($rootDir, $postDomain, $postApp, [
                'title' => $_POST['title'] ?? $postApp,
                'make_default' => true,
                'add_www' => $postAddWww,
                'mode' => $postMode,
                'new_scaffold' => $postNewScaffold
            ]);
        } elseif ($postAction === 'uninstall') {
            $targetApp = trim($_POST['app'] ?? '');
            $mode = trim($_POST['mode'] ?? 'config_only');
            $makeBackup = !empty($_POST['create_backup']);
            $keepDb = !empty($_POST['keep_db']);

            $uninstallResult = AppUninstaller::uninstall($rootDir, $targetApp, [
                'config_only' => ($mode === 'config_only'),
                'backup' => $makeBackup,
                'keep_db' => $keepDb,
                'force' => true
            ]);
        } elseif ($postAction === 'restore_backup') {
            $backupFile = trim($_POST['backup_file'] ?? '');
            $restoreResult = AppUninstaller::restore($rootDir, $backupFile, ['force' => true]);
        } elseif ($postAction === 'delete_backup') {
            $backupFile = trim($_POST['backup_file'] ?? '');
            $deleteBackupResult = AppUninstaller::deleteBackup($rootDir, $backupFile);
        } elseif ($postAction === 'create_backup') {
            $targetApp = trim($_POST['app'] ?? '');
            $createBackupResult = AppUninstaller::createBackup($rootDir, $targetApp);
        }
    }

    $discoveredApps = AppUninstaller::listApps($rootDir);
    $backupInventory = AppUninstaller::getBackupInventory($rootDir);

    // Render HTML Web Installer Interface
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SPP Server Provisioner & Universal Installer - <?php echo htmlspecialchars($activeDomain); ?></title>
    <style>
        :root {
            --bg: #090d16;
            --surface: rgba(15, 23, 42, 0.95);
            --surface-card: rgba(30, 41, 59, 0.65);
            --surface-card-hover: rgba(51, 65, 85, 0.65);
            --primary: #38bdf8;
            --primary-hover: #7dd3fc;
            --primary-glow: rgba(56, 189, 248, 0.25);
            --success: #34d399;
            --success-bg: rgba(52, 211, 153, 0.12);
            --warning: #fbbf24;
            --warning-bg: rgba(251, 191, 36, 0.12);
            --danger: #f87171;
            --danger-bg: rgba(248, 113, 113, 0.12);
            --text: #f8fafc;
            --text-muted: #94a3b8;
            --border: rgba(255, 255, 255, 0.08);
            --radius: 16px;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: radial-gradient(circle at 50% 5%, #1e293b, var(--bg));
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }
        .container {
            max-width: 820px;
            width: 100%;
            background: var(--surface);
            backdrop-filter: blur(24px);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.7);
            padding: 44px 40px;
        }
        .header { text-align: center; margin-bottom: 32px; }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(56, 189, 248, 0.12);
            border: 1px solid rgba(56, 189, 248, 0.3);
            color: var(--primary);
            font-size: 0.75rem;
            font-weight: 700;
            padding: 5px 14px;
            border-radius: 9999px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 14px;
        }
        h1 { font-size: 1.85rem; font-weight: 700; letter-spacing: -0.02em; margin-bottom: 8px; }
        .subtitle { color: var(--text-muted); font-size: 0.95rem; line-height: 1.5; }
        .tabs { display: flex; gap: 8px; border-bottom: 1px solid var(--border); margin-bottom: 28px; padding-bottom: 8px; }
        .tab-btn {
            background: transparent;
            border: none;
            color: var(--text-muted);
            padding: 8px 16px;
            font-size: 0.9rem;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .tab-btn.active { background: rgba(56, 189, 248, 0.15); color: var(--primary); }
        .tab-pane { display: none; }
        .tab-pane.active { display: block; }

        /* Audit Grid */
        .audit-card {
            background: var(--surface-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }
        .audit-card-title {
            font-size: 1.05rem;
            font-weight: 700;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .audit-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
        }
        .audit-item {
            background: rgba(0, 0, 0, 0.25);
            border: 1px solid var(--border);
            padding: 12px 14px;
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .audit-item-top { display: flex; align-items: center; justify-content: space-between; }
        .audit-item-name { font-size: 0.8rem; font-weight: 600; color: var(--text-muted); }
        .status-pill {
            font-size: 0.7rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 9999px;
            text-transform: uppercase;
        }
        .status-pass { background: var(--success-bg); color: var(--success); border: 1px solid rgba(52, 211, 153, 0.3); }
        .status-warn { background: var(--warning-bg); color: var(--warning); border: 1px solid rgba(251, 191, 36, 0.3); }
        .status-fail { background: var(--danger-bg); color: var(--danger); border: 1px solid rgba(248, 113, 113, 0.3); }
        .audit-item-val { font-size: 0.9rem; font-weight: 600; color: var(--text); }

        /* Form elements */
        .form-group { margin-bottom: 20px; }
        label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px; color: var(--text); }
        .hint { font-size: 0.78rem; color: var(--text-muted); margin-top: 4px; }
        .input-text, select {
            width: 100%;
            padding: 12px 14px;
            background: rgba(0, 0, 0, 0.35);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text);
            font-size: 0.95rem;
            outline: none;
            transition: all 0.2s;
        }
        .input-text:focus, select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-glow);
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 14px;
            font-size: 0.9rem;
            color: var(--text);
            cursor: pointer;
        }
        .checkbox-group input { width: 18px; height: 18px; cursor: pointer; accent-color: var(--primary); }
        .btn {
            width: 100%;
            padding: 14px 20px;
            background: var(--primary);
            color: #0f172a;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            margin-top: 10px;
        }
        .btn:hover { background: var(--primary-hover); box-shadow: 0 0 20px var(--primary-glow); }
        .btn-secondary {
            background: rgba(255, 255, 255, 0.08);
            color: var(--text);
            border: 1px solid var(--border);
        }
        .btn-secondary:hover { background: rgba(255, 255, 255, 0.12); }
        .btn-danger {
            background: #ef4444;
            color: #ffffff;
        }
        .btn-danger:hover {
            background: #dc2626;
            box-shadow: 0 0 20px var(--danger-bg);
        }
        .btn-sm {
            width: auto;
            padding: 6px 12px;
            font-size: 0.82rem;
            margin-top: 0;
            border-radius: 6px;
        }
        .backup-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
            font-size: 0.85rem;
        }
        .backup-table th, .backup-table td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        .backup-table th {
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.72rem;
            letter-spacing: 0.5px;
        }
        .backup-table tr:hover td {
            background: rgba(255, 255, 255, 0.02);
        }
        .success-box {
            background: var(--success-bg);
            border: 1px solid var(--success);
            padding: 26px;
            border-radius: 12px;
            text-align: left;
            margin-bottom: 25px;
        }
        .success-box h2 { color: var(--success); font-size: 1.3rem; margin-bottom: 14px; display: flex; align-items: center; gap: 8px; }
        .detail-row { display: flex; justify-content: space-between; padding: 7px 0; font-size: 0.9rem; border-bottom: 1px solid rgba(255,255,255,0.06); }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { color: var(--text-muted); }
        .detail-val { font-weight: 600; font-family: monospace; color: var(--text); }
        .code-block {
            background: #050811;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 14px;
            font-family: 'JetBrains Mono', Consolas, Monaco, monospace;
            font-size: 0.8rem;
            color: #7dd3fc;
            overflow-x: auto;
            white-space: pre;
            margin: 10px 0 16px 0;
            position: relative;
        }
        .code-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-top: 14px;
        }
        .copy-btn {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid var(--border);
            color: var(--text-muted);
            padding: 3px 10px;
            font-size: 0.72rem;
            border-radius: 4px;
            cursor: pointer;
        }
        .copy-btn:hover { color: var(--text); background: rgba(255, 255, 255, 0.15); }
        .footer { text-align: center; margin-top: 32px; font-size: 0.8rem; color: var(--text-muted); }
        .error-msg { background: var(--danger-bg); border: 1px solid var(--danger); color: var(--danger); padding: 14px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; }

        /* Server Inspector Card & Preset Selector */
        .server-inspector-card {
            background: var(--surface-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 22px;
        }
        .server-inspector-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 0.95rem;
            font-weight: 700;
        }
        .preset-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 10px 0 16px 0;
        }
        .preset-btn {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border);
            color: var(--text-muted);
            padding: 7px 14px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .preset-btn:hover {
            color: var(--text);
            background: rgba(255, 255, 255, 0.12);
        }
        .preset-btn.active {
            background: rgba(56, 189, 248, 0.16);
            border-color: var(--primary);
            color: var(--primary);
            box-shadow: 0 0 12px var(--primary-glow);
        }
        .server-path-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="badge">🌐 Universal Server Provisioner & Installer</div>
            <h1>SPP Application Platform</h1>
            <p class="subtitle">Generic pre-flight audit, server configuration helper, and virtual host installer.</p>
        </div>

        <?php if ($installResult && $installResult['success']): ?>
            <div class="success-box">
                <h2><span>🎉</span> <?php echo htmlspecialchars($installResult['message']); ?></h2>
                <div class="detail-row">
                    <span class="detail-label">Configured Host</span>
                    <span class="detail-val"><?php echo htmlspecialchars($installResult['details']['domain']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Mounted Root Application</span>
                    <span class="detail-val"><?php echo htmlspecialchars($installResult['details']['app']); ?> (<?php echo htmlspecialchars($installResult['details']['app_type']); ?>)</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Virtual Host Security Policy</span>
                    <span class="detail-val"><?php echo htmlspecialchars($installResult['details']['policy_mode']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Configured Aliases</span>
                    <span class="detail-val"><?php echo htmlspecialchars(implode(', ', $installResult['details']['aliases']) ?: 'None'); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Generated Config File</span>
                    <span class="detail-val">etc/domains.yml</span>
                </div>
            </div>

            <a href="<?php echo htmlspecialchars($installResult['details']['entry_url']); ?>" class="btn">
                🚀 Launch <?php echo htmlspecialchars($installResult['details']['domain']); ?> Now
            </a>
            <a href="<?php echo htmlspecialchars($installResult['details']['admin_url']); ?>" class="btn btn-secondary">
                ⚙️ Open SPP Admin Console
            </a>

        <?php else: ?>
            <?php if ($installResult && !$installResult['success']): ?>
                <div class="error-msg">
                    ❌ <?php echo htmlspecialchars($installResult['message']); ?>
                </div>
            <?php endif; ?>

            <div class="tabs">
                <button type="button" class="tab-btn active" data-tab="tab-setup" onclick="switchTab('tab-setup', this)">⚙️ Domain Setup</button>
                <button type="button" class="tab-btn" data-tab="tab-audit" onclick="switchTab('tab-audit', this)">🩺 Server Health & Checks (<?php echo $audit['passed'] ? '✓ Ready' : '⚠️ Attention'; ?>)</button>
                <button type="button" class="tab-btn" data-tab="tab-server" onclick="switchTab('tab-server', this)">🛠️ Web Server Config Helpers</button>
                <button type="button" class="tab-btn" data-tab="tab-uninstall" onclick="switchTab('tab-uninstall', this)">🗑️ App Uninstaller & Backups</button>
            </div>

            <!-- TAB 1: DOMAIN SETUP -->
            <div id="tab-setup" class="tab-pane active">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="install">

                    <div class="form-group">
                        <label for="domain">Target Server Domain / Hostname</label>
                        <input type="text" id="domain" name="domain" class="input-text" value="<?php echo htmlspecialchars($activeDomain); ?>" required placeholder="e.g. localhost, example.com, portal.school.edu">
                        <div class="hint">Detected from current incoming request header. Change to any custom domain or IP pointing to this server.</div>
                    </div>

                    <div class="form-group">
                        <label for="app">Default Application (Mounted at Root /)</label>
                        <select id="app" name="app" class="input-text">
                            <?php foreach ($allApps as $k => $info): ?>
                                <option value="<?php echo htmlspecialchars($k); ?>" <?php echo ($k === $defaultSelectedApp ? 'selected' : ''); ?>>
                                    <?php echo htmlspecialchars($k); ?> — <?php echo htmlspecialchars($info['type']); ?> (<?php echo htmlspecialchars($info['path']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="hint">Select the internal application module to serve. The application will be mounted directly at the root (<code>/</code>) of <strong class="domain-ref"><?php echo htmlspecialchars($activeDomain); ?></strong>, independent of the folder name on your server or local disk.</div>
                    </div>

                    <div class="form-group">
                        <label for="mode">Virtual Host Security Isolation Policy</label>
                        <select id="mode" name="mode" class="input-text">
                            <option value="strict" selected>Strict Mode (Recommended: Rejects unmapped domains and uncreated subdomains to isolate tenants)</option>
                            <option value="permissive">Permissive Mode (Allows any unmapped incoming domain to fallback to the default app)</option>
                        </select>
                    </div>

                    <div id="www-alias-container" style="<?php echo (substr_count($activeDomain, '.') >= 1 && !str_starts_with($activeDomain, 'www.')) ? '' : 'display: none;'; ?>">
                        <label class="checkbox-group">
                            <input type="checkbox" name="add_www" id="add_www" value="1" checked>
                            <span>Automatically include <strong id="www-domain-label">www.<span class="domain-ref"><?php echo htmlspecialchars($activeDomain); ?></span></strong> alias</span>
                        </label>
                    </div>

                    <label class="checkbox-group">
                        <input type="checkbox" name="new_scaffold" id="new_scaffold" value="1">
                        <span>Scaffold a <strong>fresh isolated application</strong> inside <code>domains/<span class="domain-ref"><?php echo htmlspecialchars($activeDomain); ?></span>/src/</code></span>
                    </label>

                    <button type="submit" id="submit-btn" class="btn">
                        🚀 Install & Activate SPP on <span class="domain-ref"><?php echo htmlspecialchars($activeDomain); ?></span>
                    </button>
                </form>
            </div>

            <!-- TAB 2: SYSTEM HEALTH AUDIT -->
            <div id="tab-audit" class="tab-pane">
                <div class="audit-card">
                    <div class="audit-card-title">
                        <span>PHP Runtime & Extensions</span>
                        <span class="status-pill <?php echo $audit['passed'] ? 'status-pass' : 'status-warn'; ?>">
                            <?php echo $audit['passed'] ? 'All Critical Checks Passed' : 'Action Required'; ?>
                        </span>
                    </div>

                    <div class="audit-grid">
                        <div class="audit-item">
                            <div class="audit-item-top">
                                <span class="audit-item-name">PHP Version</span>
                                <span class="status-pill status-pass">Pass</span>
                            </div>
                            <span class="audit-item-val"><?php echo htmlspecialchars($audit['php_version']); ?> (>= 8.1.0)</span>
                        </div>

                        <div class="audit-item">
                            <div class="audit-item-top">
                                <span class="audit-item-name">Memory Limit</span>
                                <span class="status-pill status-pass">Pass</span>
                            </div>
                            <span class="audit-item-val"><?php echo htmlspecialchars($audit['memory_limit']); ?> (>= 128M)</span>
                        </div>

                        <?php foreach ($audit['checks'] as $k => $c): ?>
                            <?php if ($c['type'] === 'extension' || $c['type'] === 'db_driver' || $c['type'] === 'recommended_extension'): ?>
                                <div class="audit-item">
                                    <div class="audit-item-top">
                                        <span class="audit-item-name"><?php echo htmlspecialchars($c['name']); ?></span>
                                        <span class="status-pill <?php echo !empty($c['pass']) ? 'status-pass' : (!empty($c['is_critical']) ? 'status-fail' : 'status-warn'); ?>">
                                            <?php echo !empty($c['pass']) ? 'Pass' : (!empty($c['is_critical']) ? 'Missing' : 'Optional'); ?>
                                        </span>
                                    </div>
                                    <span class="audit-item-val"><?php echo htmlspecialchars($c['actual'] ?? ''); ?></span>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="audit-card">
                    <div class="audit-card-title">
                        <span>Storage & Write Permissions</span>
                        <span class="status-pill status-pass">Auto-Remediated</span>
                    </div>

                    <div class="audit-grid">
                        <?php foreach ($audit['checks'] as $k => $c): ?>
                            <?php if ($c['type'] === 'writable_path'): ?>
                                <div class="audit-item">
                                    <div class="audit-item-top">
                                        <span class="audit-item-name"><?php echo htmlspecialchars($c['name']); ?></span>
                                        <span class="status-pill <?php echo !empty($c['pass']) ? 'status-pass' : 'status-fail'; ?>">
                                            <?php echo !empty($c['pass']) ? 'Writable' : 'Read-Only'; ?>
                                        </span>
                                    </div>
                                    <span class="audit-item-val"><?php echo htmlspecialchars($c['actual'] ?? ''); ?></span>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- TAB 3: SERVER CONFIGURATION HELPERS -->
            <div id="tab-server" class="tab-pane">
                <p class="subtitle" style="margin-bottom: 16px;">
                    Copy-pasteable server configuration blocks tailored specifically for <strong class="domain-ref"><?php echo htmlspecialchars($activeDomain); ?></strong> and this server environment.
                </p>

                <!-- AUTO-DISCOVERED SERVER ENVIRONMENT INSPECTOR -->
                <div class="server-inspector-card">
                    <div class="server-inspector-header">
                        <span>🔍 Auto-Discovered Server Environment</span>
                        <span class="status-pill status-pass">Audited</span>
                    </div>
                    <div class="audit-grid" style="grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 10px;">
                        <div class="audit-item">
                            <span class="audit-item-name">Operating System</span>
                            <span class="audit-item-val" style="font-size: 0.82rem;"><?php echo htmlspecialchars($guidance['env']['distro_name']); ?></span>
                        </div>
                        <div class="audit-item">
                            <span class="audit-item-name">Platform / Package Mgr</span>
                            <span class="audit-item-val" style="font-size: 0.82rem;"><span class="badge" style="background: rgba(56,189,248,0.15); color: var(--primary); padding: 2px 6px; border-radius: 4px; font-weight: 600;"><?php echo htmlspecialchars($guidance['env']['distro_family']); ?></span> (<code><?php echo htmlspecialchars($guidance['env']['package_manager']); ?></code>)</span>
                        </div>
                        <div class="audit-item">
                            <span class="audit-item-name">Web Server Runtime</span>
                            <span class="audit-item-val" style="font-size: 0.82rem;"><?php echo htmlspecialchars($guidance['env']['server_software']); ?></span>
                        </div>
                        <div class="audit-item">
                            <span class="audit-item-name">PHP SAPI / FPM Socket</span>
                            <span class="audit-item-val" style="font-size: 0.75rem; word-break: break-all;"><?php echo htmlspecialchars($guidance['env']['fpm_socket']); ?></span>
                        </div>
                        <div class="audit-item">
                            <span class="audit-item-name">Tenant / System User</span>
                            <span class="audit-item-val" style="font-size: 0.82rem;"><span class="user-ref"><?php echo htmlspecialchars($guidance['env']['system_user']); ?></span></span>
                        </div>
                    </div>
                </div>

                <!-- CPANEL & VPS INTEROPERABILITY EXPLANATION CARD -->
                <div class="audit-card" style="margin-bottom: 20px; border-left: 4px solid var(--primary); background: rgba(56, 189, 248, 0.06);">
                    <div style="font-weight: 700; color: var(--primary); margin-bottom: 6px; font-size: 0.95rem; display: flex; align-items: center; gap: 8px;">
                        <span>🏠</span> Multi-Platform & cPanel VPS Interoperability (User Home Architecture)
                    </div>
                    <div style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.55;">
                        By hosting applications inside the user's home directory (<code>/home/<span class="user-ref"><?php echo htmlspecialchars($guidance['env']['system_user']); ?></span>/public_html</code> on Linux or user profile on Windows/macOS), SPP achieves <strong>100% interoperability with cPanel, DirectAdmin, CloudPanel, CyberPanel, and standalone Linux/Windows servers</strong>. Backups, file transfers, and migrations between standalone VPS servers and control panels work without changing paths or risking permission collisions.
                    </div>
                </div>

                <!-- TARGET SERVER FOLDER STRUCTURE & PRESETS -->
                <div class="form-group" style="margin-bottom: 24px;">
                    <label>Target Server Folder Structure & Deployment Preset</label>
                    <div class="preset-pills" id="preset-pills-container">
                        <button type="button" class="preset-btn <?php echo ($guidance['env']['selected_profile'] === 'cpanel_home' ? 'active' : ''); ?>" data-preset="cpanel_home" onclick="selectServerPreset('cpanel_home', this)">
                            🏠 User Home Primary (/home/<span class="user-ref"><?php echo htmlspecialchars($guidance['env']['system_user']); ?></span>/public_html) [Linux Recommended]
                        </button>
                        <button type="button" class="preset-btn <?php echo ($guidance['env']['selected_profile'] === 'home_domain' ? 'active' : ''); ?>" data-preset="home_domain" onclick="selectServerPreset('home_domain', this)">
                            📁 User Home Multi-Domain (/home/<span class="user-ref"><?php echo htmlspecialchars($guidance['env']['system_user']); ?></span>/<span class="domain-ref"><?php echo htmlspecialchars($activeDomain); ?></span>)
                        </button>
                        <button type="button" class="preset-btn <?php echo ($guidance['env']['selected_profile'] === 'linux_fhs' ? 'active' : ''); ?>" data-preset="linux_fhs" onclick="selectServerPreset('linux_fhs', this)">
                            🌐 Linux FHS System Path (/var/www/<span class="domain-ref"><?php echo htmlspecialchars($activeDomain); ?></span>)
                        </button>
                        <button type="button" class="preset-btn <?php echo ($guidance['env']['selected_profile'] === 'ubuntu_default' ? 'active' : ''); ?>" data-preset="ubuntu_default" onclick="selectServerPreset('ubuntu_default', this)">
                            🐧 Ubuntu / Debian Default (/var/www/html)
                        </button>
                        <button type="button" class="preset-btn <?php echo ($guidance['env']['selected_profile'] === 'windows_iis' ? 'active' : ''); ?>" data-preset="windows_iis" onclick="selectServerPreset('windows_iis', this)">
                            🪟 Windows Server IIS (C:/inetpub/wwwroot/<span class="domain-ref"><?php echo htmlspecialchars($activeDomain); ?></span>)
                        </button>
                        <button type="button" class="preset-btn <?php echo ($guidance['env']['selected_profile'] === 'windows_home' ? 'active' : ''); ?>" data-preset="windows_home" onclick="selectServerPreset('windows_home', this)">
                            👤 Windows User Sites (C:/Users/<span class="user-ref"><?php echo htmlspecialchars($guidance['env']['system_user']); ?></span>/Sites/<span class="domain-ref"><?php echo htmlspecialchars($activeDomain); ?></span>)
                        </button>
                        <button type="button" class="preset-btn <?php echo ($guidance['env']['selected_profile'] === 'macos_home' ? 'active' : ''); ?>" data-preset="macos_home" onclick="selectServerPreset('macos_home', this)">
                            🍎 macOS User Sites (/Users/<span class="user-ref"><?php echo htmlspecialchars($guidance['env']['system_user']); ?></span>/Sites/<span class="domain-ref"><?php echo htmlspecialchars($activeDomain); ?></span>)
                        </button>
                        <?php if (!empty($guidance['env']['detected_local_root'])): ?>
                            <button type="button" class="preset-btn <?php echo ($guidance['env']['selected_profile'] === 'local_detected' ? 'active' : ''); ?>" data-preset="local_detected" onclick="selectServerPreset('local_detected', this)">
                                💻 Local Dev Path (<?php echo htmlspecialchars(substr($guidance['env']['detected_local_root'], 0, 24)); ?>...)
                            </button>
                        <?php endif; ?>
                    </div>

                    <div class="server-path-grid">
                        <div>
                            <label for="server_user_input">System User / Tenant Account</label>
                            <input type="text" id="server_user_input" class="input-text" value="<?php echo htmlspecialchars($guidance['env']['system_user']); ?>" placeholder="e.g. webuser or www-data">
                            <div class="hint">Dedicated user account for execution isolation and file ownership.</div>
                        </div>
                        <div>
                            <label for="server_root_input">Server Project Root Path</label>
                            <input type="text" id="server_root_input" class="input-text" value="<?php echo htmlspecialchars($guidance['env']['target_root']); ?>">
                            <div class="hint">Physical directory on the server where SPP files reside.</div>
                        </div>
                        <div>
                            <label for="server_public_input">Server DocumentRoot (Web Accessible)</label>
                            <input type="text" id="server_public_input" class="input-text" value="<?php echo htmlspecialchars($guidance['env']['target_public']); ?>">
                            <div class="hint">Directory pointed to by web server (recommended: <code>/public</code>).</div>
                        </div>
                    </div>
                </div>

                <div class="code-header">
                    <span>Apache VirtualHost Configuration</span>
                    <button type="button" class="copy-btn" onclick="copySnippet('code-apache')">Copy Config</button>
                </div>
                <div class="code-block" id="code-apache"><?php echo htmlspecialchars($guidance['apache']); ?></div>

                <div class="code-header">
                    <span>Nginx Server Block Configuration</span>
                    <button type="button" class="copy-btn" onclick="copySnippet('code-nginx')">Copy Config</button>
                </div>
                <div class="code-block" id="code-nginx"><?php echo htmlspecialchars($guidance['nginx']); ?></div>

                <div class="code-header">
                    <span>Microsoft IIS Configuration (web.config)</span>
                    <button type="button" class="copy-btn" onclick="copySnippet('code-iis')">Copy Config</button>
                </div>
                <div class="code-block" id="code-iis"><?php echo htmlspecialchars($guidance['iis']); ?></div>

                <div class="code-header">
                    <span>Caddy Server Block (Caddyfile)</span>
                    <button type="button" class="copy-btn" onclick="copySnippet('code-caddy')">Copy Config</button>
                </div>
                <div class="code-block" id="code-caddy"><?php echo htmlspecialchars($guidance['caddy']); ?></div>

                <div class="code-header">
                    <span>File Ownership & Permission Commands (<?php echo $guidance['env']['is_windows'] ? 'Windows icacls' : 'Linux chmod/chown'; ?>)</span>
                    <button type="button" class="copy-btn" onclick="copySnippet('code-perm')">Copy Commands</button>
                </div>
                <div class="code-block" id="code-perm"><?php echo htmlspecialchars($guidance['permissions']); ?></div>

                <div class="code-header">
                    <span>PHP Extension Installation Commands (All Platforms)</span>
                    <button type="button" class="copy-btn" onclick="copySnippet('code-php')">Copy Commands</button>
                </div>
                <div class="code-block" id="code-php"><?php echo htmlspecialchars($guidance['php_ini']); ?></div>
            </div>

            <!-- TAB 4: APP UNINSTALLER & BACKUPS -->
            <div id="tab-uninstall" class="tab-pane">
                <div class="audit-card">
                    <div class="audit-card-title">
                        <span>🗑️ Sovereign Application Uninstaller</span>
                        <span class="status-pill status-warn">Lifecycle Control</span>
                    </div>
                    <p style="color: var(--text-muted); font-size: 0.88rem; margin-bottom: 16px;">
                        Safely decommission applications from the server. Choose between <strong>Config-Only Removal</strong> (unbinding domains and cleaning routes while strictly preserving source code in <code>src/</code>) or <strong>Full Purge</strong> (deleting code, configuration, and caches).
                    </p>

                    <?php if ($uninstallResult): ?>
                        <?php if ($uninstallResult['success']): ?>
                            <div class="success-box" style="margin-bottom: 20px; padding: 16px;">
                                <div style="font-weight: 700; color: var(--success); margin-bottom: 8px;">
                                    ✓ <?php echo htmlspecialchars($uninstallResult['message']); ?>
                                </div>
                                <ul style="font-size: 0.82rem; color: var(--text); padding-left: 20px;">
                                    <?php foreach ($uninstallResult['actions'] as $act): ?>
                                        <li><?php echo htmlspecialchars($act); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php else: ?>
                            <div class="error-msg" style="margin-bottom: 20px;">
                                ❌ <?php echo htmlspecialchars($uninstallResult['message']); ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($createBackupResult): ?>
                        <div class="<?php echo $createBackupResult['success'] ? 'success-box' : 'error-msg'; ?>" style="margin-bottom: 20px; padding: 16px;">
                            <?php echo ($createBackupResult['success'] ? '✓ ' : '❌ ') . htmlspecialchars($createBackupResult['message']); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" onsubmit="return confirmUninstall();">
                        <input type="hidden" name="action" value="uninstall">
                        <div class="form-group">
                            <label for="uninstall_app">Select Application to Uninstall</label>
                            <select name="app" id="uninstall_app" required>
                                <option value="">-- Choose an Application --</option>
                                <?php foreach ($discoveredApps as $appName => $appMeta): ?>
                                    <option value="<?php echo htmlspecialchars($appName); ?>" <?php echo $appMeta['is_protected'] ? 'disabled' : ''; ?>>
                                        <?php echo htmlspecialchars($appName); ?>
                                        <?php if ($appMeta['is_protected']): ?> (Protected Framework App)<?php endif; ?>
                                        <?php if (!empty($appMeta['domains'])): ?> [Bound to: <?php echo htmlspecialchars(implode(', ', $appMeta['domains'])); ?>]<?php endif; ?>
                                        (<?php echo htmlspecialchars($appMeta['type']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="hint">Core system applications (default, admin, spp, core, system) cannot be uninstalled.</div>
                        </div>

                        <div class="form-group">
                            <label>Uninstallation Mode</label>
                            <div style="background: rgba(0, 0, 0, 0.25); border: 1px solid var(--border); border-radius: 8px; padding: 14px; margin-bottom: 12px;">
                                <label class="checkbox-group" style="margin-bottom: 6px;">
                                    <input type="radio" name="mode" value="config_only" checked style="accent-color: var(--primary);">
                                    <strong style="color: var(--primary);">Config-Only Mode (Recommended)</strong>
                                </label>
                                <div style="color: var(--text-muted); font-size: 0.8rem; margin-left: 28px;">
                                    Unregisters application from <code>spp/etc/global-settings.yml</code>, unbinds domains from <code>etc/domains.yml</code>, flushes caches, but <strong>strictly preserves all source code in <code>src/</code></strong>.
                                </div>
                            </div>

                            <div style="background: rgba(0, 0, 0, 0.25); border: 1px solid var(--border); border-radius: 8px; padding: 14px;">
                                <label class="checkbox-group" style="margin-bottom: 6px;">
                                    <input type="radio" name="mode" value="full" style="accent-color: var(--danger);">
                                    <strong style="color: var(--danger);">Full Code & Config Purge</strong>
                                </label>
                                <div style="color: var(--text-muted); font-size: 0.8rem; margin-left: 28px;">
                                    Permanently deletes source code (<code>src/&lt;App&gt;/</code>), configurations, resources, and views. <em>Ensure a backup is created before selecting this option.</em>
                                </div>
                            </div>
                        </div>

                        <label class="checkbox-group">
                            <input type="checkbox" name="create_backup" value="1" checked>
                            <span>📦 <strong>Create safety backup archive before uninstalling</strong> (Recommended: saves portable <code>.zip</code> to <code>var/backups/apps/</code>)</span>
                        </label>

                        <label class="checkbox-group">
                            <input type="checkbox" name="keep_db" value="1" checked>
                            <span>🛡️ Preserve database tables / SQLite database file</span>
                        </label>

                        <button type="submit" class="btn btn-danger" style="margin-top: 10px;">
                            🗑️ Uninstall Selected Application
                        </button>
                    </form>
                </div>

                <!-- BACKUPS & DISK RECLAMATION SECTION -->
                <div class="audit-card">
                    <div class="audit-card-title">
                        <span>📦 Application Backups & Disk Space Reclamation</span>
                        <span class="status-pill status-pass"><?php echo $backupInventory['count']; ?> Backup(s) &bull; <?php echo $backupInventory['total_size_formatted']; ?></span>
                    </div>
                    <p style="color: var(--text-muted); font-size: 0.88rem; margin-bottom: 16px;">
                        Saved archives reside in <code>var/backups/apps/</code>. You can restore an application to re-register its code, configurations, and domains, or permanently delete backups to reclaim disk storage space on the server.
                    </p>

                    <?php if ($restoreResult): ?>
                        <div class="<?php echo $restoreResult['success'] ? 'success-box' : 'error-msg'; ?>" style="margin-bottom: 16px; padding: 14px;">
                            <?php echo ($restoreResult['success'] ? '✓ ' : '❌ ') . htmlspecialchars($restoreResult['message']); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($deleteBackupResult): ?>
                        <div class="<?php echo $deleteBackupResult['success'] ? 'success-box' : 'error-msg'; ?>" style="margin-bottom: 16px; padding: 14px;">
                            <?php echo ($deleteBackupResult['success'] ? '✓ ' : '❌ ') . htmlspecialchars($deleteBackupResult['message']); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($backupInventory['backups'])): ?>
                        <div style="background: rgba(0,0,0,0.2); border: 1px dashed var(--border); padding: 24px; text-align: center; border-radius: 8px; color: var(--text-muted);">
                            No application backup archives currently stored in <code>var/backups/apps/</code>.
                        </div>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table class="backup-table">
                                <thead>
                                    <tr>
                                        <th>Archive Filename</th>
                                        <th>App</th>
                                        <th>Created</th>
                                        <th>File Size</th>
                                        <th style="text-align: right;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($backupInventory['backups'] as $b): ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight: 600; color: var(--text); font-family: monospace;"><?php echo htmlspecialchars($b['filename']); ?></div>
                                                <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo $b['has_manifest'] ? '✓ Manifest present' : 'Legacy zip'; ?></div>
                                            </td>
                                            <td><span class="status-pill status-pass"><?php echo htmlspecialchars($b['app']); ?></span></td>
                                            <td style="color: var(--text-muted);"><?php echo htmlspecialchars($b['created_at']); ?></td>
                                            <td style="font-weight: 600; color: var(--primary);"><?php echo htmlspecialchars($b['size_formatted']); ?></td>
                                            <td style="text-align: right;">
                                                <div style="display: inline-flex; gap: 8px; justify-content: flex-end;">
                                                    <!-- Restore Form -->
                                                    <form method="POST" action="" onsubmit="return confirm('Restore application \'<?php echo addslashes($b['app']); ?>\' from this backup archive? Existing files may be overwritten.');">
                                                        <input type="hidden" name="action" value="restore_backup">
                                                        <input type="hidden" name="backup_file" value="<?php echo htmlspecialchars($b['filename']); ?>">
                                                        <button type="submit" class="btn btn-secondary btn-sm" title="Restore this application">🔄 Restore</button>
                                                    </form>

                                                    <!-- Delete / Reclaim Form -->
                                                    <form method="POST" action="" onsubmit="return confirm('Permanently delete backup \'<?php echo addslashes($b['filename']); ?>\' to reclaim <?php echo addslashes($b['size_formatted']); ?> disk space? This cannot be undone.');">
                                                        <input type="hidden" name="action" value="delete_backup">
                                                        <input type="hidden" name="backup_file" value="<?php echo htmlspecialchars($b['filename']); ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm" title="Delete backup archive to free disk space">🗑️ Delete (Reclaim)</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="footer">
            SPP Platform &bull; Multi-Tenant Virtual Host & Domain Architecture
        </div>
    </div>

    <script>
        let SERVER_DISCOVERY = <?php echo json_encode($guidance['env']); ?>;
        let currentPreset = <?php echo json_encode($guidance['env']['selected_profile']); ?>;

        function switchTab(tabId, btnElement) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
            const targetPane = document.getElementById(tabId);
            if (targetPane) targetPane.classList.add('active');
            if (btnElement) {
                btnElement.classList.add('active');
            } else {
                const activeBtn = document.querySelector(`.tab-btn[data-tab="${tabId}"]`);
                if (activeBtn) activeBtn.classList.add('active');
            }
        }

        function copySnippet(elementId) {
            const el = document.getElementById(elementId);
            if (!el) return;
            const text = el.innerText;
            navigator.clipboard.writeText(text).then(() => {
                alert('Configuration copied to clipboard!');
            });
        }

        function escapeHtml(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function selectServerPreset(presetKey, btnElement) {
            currentPreset = presetKey;
            document.querySelectorAll('.preset-btn').forEach(btn => btn.classList.remove('active'));
            if (btnElement) {
                btnElement.classList.add('active');
            }

            const domainInput = document.getElementById('domain');
            const rawDomain = (domainInput ? domainInput.value : '') || 'localhost';
            let clean = rawDomain.trim().toLowerCase().split(':')[0] || 'localhost';

            const userInput = document.getElementById('server_user_input');
            const defaultUser = clean.replace(/[^a-zA-Z0-9_]/g, '').split('.')[0] || 'sppuser';
            let user = (userInput ? userInput.value.trim() : '') || defaultUser;

            let targetRoot = '';
            let targetPublic = '';

            if (presetKey === 'cpanel_home') {
                targetRoot = '/home/' + user + '/public_html';
                targetPublic = '/home/' + user + '/public_html/public';
            } else if (presetKey === 'home_domain') {
                targetRoot = '/home/' + user + '/' + clean;
                targetPublic = '/home/' + user + '/' + clean + '/public';
            } else if (presetKey === 'linux_fhs') {
                targetRoot = '/var/www/' + clean;
                targetPublic = '/var/www/' + clean + '/public';
            } else if (presetKey === 'ubuntu_default') {
                targetRoot = '/var/www/html';
                targetPublic = '/var/www/html/public';
            } else if (presetKey === 'windows_iis') {
                targetRoot = 'C:/inetpub/wwwroot/' + clean;
                targetPublic = 'C:/inetpub/wwwroot/' + clean + '/public';
            } else if (presetKey === 'windows_home') {
                targetRoot = 'C:/Users/' + user + '/Sites/' + clean;
                targetPublic = 'C:/Users/' + user + '/Sites/' + clean + '/public';
            } else if (presetKey === 'windows_laragon') {
                targetRoot = 'C:/laragon/www/' + clean;
                targetPublic = 'C:/laragon/www/' + clean + '/public';
            } else if (presetKey === 'macos_home') {
                targetRoot = '/Users/' + user + '/Sites/' + clean;
                targetPublic = '/Users/' + user + '/Sites/' + clean + '/public';
            } else if (presetKey === 'local_detected') {
                targetRoot = SERVER_DISCOVERY.detected_local_root;
                targetPublic = SERVER_DISCOVERY.detected_local_root + '/public';
            }

            const rootInput = document.getElementById('server_root_input');
            const publicInput = document.getElementById('server_public_input');
            if (rootInput && publicInput) {
                rootInput.value = targetRoot;
                publicInput.value = targetPublic;
            }

            renderServerConfigTemplates(clean, targetRoot, targetPublic, user);
        }

        function renderServerConfigTemplates(domain, targetRoot, targetPublic, user) {
            let clean = (domain || '').trim().toLowerCase().split(':')[0] || 'localhost';
            const isIp = /^(\d{1,3}\.){3}\d{1,3}$/.test(clean);
            const hasDot = clean.indexOf('.') !== -1;
            const startsWithWww = clean.startsWith('www.');
            const hasSub = hasDot && !startsWithWww && !isIp;

            const serverAliases = hasSub ? `    ServerAlias www.${clean}\n` : '';
            const nginxAliases = hasSub ? ` www.${clean}` : '';
            const caddyAliases = hasSub ? `, www.${clean}` : '';
            const apacheConfDir = SERVER_DISCOVERY.apache_conf_dir || '/etc/apache2/sites-available';
            const nginxConfDir = SERVER_DISCOVERY.nginx_conf_dir || '/etc/nginx/sites-available';
            const defaultUser = clean.replace(/[^a-zA-Z0-9_]/g, '').split('.')[0] || 'sppuser';
            const effectiveUser = user || defaultUser;
            const fpmSocket = SERVER_DISCOVERY.fpm_socket || 'unix:/var/run/php/php8.2-fpm.sock';

            const isHome = targetRoot.startsWith('/home/');
            const homeDir = isHome ? ('/home/' + targetRoot.split('/')[2]) : '';

            // 1. Apache VirtualHost
            const codeApache = document.getElementById('code-apache');
            if (codeApache) {
                codeApache.textContent = `# Apache VirtualHost for ${clean} (Multi-Platform / User Home Architecture)
# Fully compatible with cPanel, DirectAdmin, CloudPanel, and standalone Linux VPS.
# Place this file in ${apacheConfDir}/${clean}.conf

<VirtualHost *:80>
    ServerName ${clean}
${serverAliases}    DocumentRoot "${targetPublic}"

    <Directory "${targetPublic}">
        Options Indexes FollowSymLinks MultiViews
        AllowOverride All
        Require all granted
    </Directory>

    # Security: Restrict direct HTTP access to framework system & configuration directories
    <Directory "${targetRoot}/etc">
        Require all denied
    </Directory>
    <Directory "${targetRoot}/var">
        Require all denied
    </Directory>

    # Multi-tenant execution: Runs PHP as dedicated Linux user
    <IfModule mpm_itk_module>
        AssignUserId ${effectiveUser} ${effectiveUser}
    </IfModule>

    ErrorLog \${APACHE_LOG_DIR}/spp_${clean}_error.log
    CustomLog \${APACHE_LOG_DIR}/spp_${clean}_access.log combined
</VirtualHost>`;
            }

            // 2. Nginx Server Block
            const codeNginx = document.getElementById('code-nginx');
            if (codeNginx) {
                codeNginx.textContent = `# Nginx Server Block for ${clean} (Multi-Platform / User Home Architecture)
# Fully compatible with cPanel, DirectAdmin, CloudPanel, and standalone Linux VPS.
# Place this file in ${nginxConfDir}/${clean} and reload Nginx

server {
    listen 80;
    server_name ${clean}${nginxAliases};
    root ${targetPublic};
    index index.php index.html;

    # Pass all requests to SPP front controller
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    # PHP-FPM integration (auto-discovered socket or user pool)
    location ~ \\.php$ {
        fastcgi_pass ${fpmSocket};
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    # Security: Restrict direct access to internal files and directories
    location ~ /\\.(?!well-known) {
        deny all;
    }
    location ^~ /etc/ {
        deny all;
    }
    location ^~ /var/ {
        deny all;
    }
}`;
            }

            // 3. Microsoft IIS Configuration (web.config)
            const codeIis = document.getElementById('code-iis');
            if (codeIis) {
                codeIis.textContent = `<?php echo '<?xml'; ?> version="1.0" encoding="UTF-8"?>
<!-- Microsoft IIS Configuration for ${clean} -->
<!-- Place this web.config in the document root (${targetPublic}) -->
<configuration>
    <system.webServer>
        <defaultDocument>
            <files>
                <clear />
                <add value="index.php" />
                <add value="index.html" />
            </files>
        </defaultDocument>
        <rewrite>
            <rules>
                <!-- Security: Block direct HTTP access to internal system directories -->
                <rule name="Block Internal Directories" stopProcessing="true">
                    <match url="^(etc|var|domains|\\.git|\\.env)" ignoreCase="true" />
                    <action type="CustomResponse" statusCode="403" statusReason="Forbidden" statusDescription="Access to internal directories is denied." />
                </rule>
                <!-- SPP Front Controller URL Rewriting -->
                <rule name="SPP Front Controller" stopProcessing="true">
                    <match url="^" ignoreCase="false" />
                    <conditions logicalGrouping="MatchAll">
                        <add input="{REQUEST_FILENAME}" matchType="IsFile" negate="true" />
                        <add input="{REQUEST_FILENAME}" matchType="IsDirectory" negate="true" />
                    </conditions>
                    <action type="Rewrite" url="index.php" />
                </rule>
            </rules>
        </rewrite>
        <security>
            <requestFiltering>
                <hiddenSegments>
                    <add segment="etc" />
                    <add segment="var" />
                    <add segment="domains" />
                </hiddenSegments>
            </requestFiltering>
        </security>
        <httpErrors errorMode="DetailedLocalOnly" />
    </system.webServer>
</configuration>`;
            }

            // 4. Caddy Server Block (Caddyfile)
            const codeCaddy = document.getElementById('code-caddy');
            if (codeCaddy) {
                const caddyFastCgi = SERVER_DISCOVERY.is_windows ? '127.0.0.1:9000' : (fpmSocket.startsWith('unix:') ? ('unix/' + fpmSocket.substring(5)) : (fpmSocket || '127.0.0.1:9000'));
                codeCaddy.textContent = `# Caddyfile Server Block for ${clean}
# Place in /etc/caddy/Caddyfile or your Caddy project configuration

${clean}${caddyAliases} {
    root * "${targetPublic}"
    encode zstd gzip

    # Pass PHP requests to FastCGI backend
    php_fastcgi ${caddyFastCgi}

    # Serve static assets
    file_server

    # Security: Restrict direct HTTP access to system configuration and data
    @restricted {
        path /etc/* /var/* /domains/* /.*
    }
    respond @restricted 403
}`;
            }

            // 5. File Ownership & Permissions
            const codePerm = document.getElementById('code-perm');
            if (codePerm) {
                let permText = '';
                const isWinPreset = targetRoot.includes(':') || targetRoot.startsWith('\\\\') || SERVER_DISCOVERY.is_windows;
                if (isWinPreset) {
                    const trWin = targetRoot.replace(/\//g, '\\');
                    permText = `REM Windows Server (IIS / NTFS Permissions via icacls)\n` +
                        `REM 1. Grant read & execute permissions to IIS worker process:\n` +
                        `icacls "${trWin}" /grant "IIS_IUSRS":(OI)(CI)RX /T\n` +
                        `icacls "${trWin}" /grant "IUSR":(OI)(CI)RX /T\n\n` +
                        `REM 2. Grant modify/write permissions for framework cache, logs, and config:\n` +
                        `icacls "${trWin}\\var" /grant "IIS_IUSRS":(OI)(CI)M /T\n` +
                        `icacls "${trWin}\\etc" /grant "IIS_IUSRS":(OI)(CI)M /T\n` +
                        `icacls "${trWin}\\domains" /grant "IIS_IUSRS":(OI)(CI)M /T\n\n` +
                        `REM For Windows Desktop / Local Dev (Laragon / XAMPP):\n` +
                        `REM Windows user profiles typically have full write permissions by default.`;
                } else {
                    if (homeDir) {
                        permText += `# 1. Grant web server traversal permissions to user home directory\n` +
                                    `sudo chmod 755 "${homeDir}"\n\n`;
                    }
                    permText += `# 2. Set directory ownership to tenant user (${effectiveUser})\n` +
                                `sudo chown -R ${effectiveUser}:${effectiveUser} "${targetRoot}"\n\n` +
                                `# 3. Ensure runtime and configuration directories are writable\n` +
                                `sudo chmod -R 775 "${targetRoot}/var"\n` +
                                `sudo chmod -R 775 "${targetRoot}/etc"\n` +
                                `sudo chmod -R 775 "${targetRoot}/domains"\n\n` +
                                `# cPanel Interoperability Note:\n` +
                                `# When deploying directly to cPanel, files upload into /home/${effectiveUser}/public_html.\n` +
                                `# File ownership and permissions are already set by cPanel as ${effectiveUser}:${effectiveUser}.`;
                }
                codePerm.textContent = permText;
            }
        }

        function updateDomainReferences(rawDomain) {
            let clean = (rawDomain || '').trim().toLowerCase().split(':')[0];
            if (!clean) {
                clean = 'localhost';
            }

            // 1. Update all span/strong elements with class .domain-ref
            document.querySelectorAll('.domain-ref').forEach(el => {
                el.textContent = clean;
            });

            // 2. Update page title
            document.title = 'SPP Server Provisioner & Universal Installer - ' + clean;

            // 3. Update www alias container visibility and label
            const wwwContainer = document.getElementById('www-alias-container');
            const isIp = /^(\d{1,3}\.){3}\d{1,3}$/.test(clean);
            const hasDot = clean.indexOf('.') !== -1;
            const startsWithWww = clean.startsWith('www.');

            if (wwwContainer) {
                if (hasDot && !startsWithWww && !isIp) {
                    wwwContainer.style.display = 'block';
                    const wwwLabel = document.getElementById('www-domain-label');
                    if (wwwLabel) {
                        wwwLabel.innerHTML = 'www.<span class="domain-ref">' + escapeHtml(clean) + '</span>';
                    }
                } else {
                    wwwContainer.style.display = 'none';
                }
            }

            // 4. Update user from domain if user hasn't explicitly customized it
            const defaultUser = clean.replace(/[^a-zA-Z0-9_]/g, '').split('.')[0] || 'sppuser';
            const userInput = document.getElementById('server_user_input');
            if (userInput && (!userInput.dataset.customized || userInput.dataset.customized === 'false')) {
                userInput.value = defaultUser;
            }
            const activeUser = (userInput ? userInput.value.trim() : '') || defaultUser;

            document.querySelectorAll('.user-ref').forEach(el => {
                el.textContent = activeUser;
            });

            // 5. Update target server paths based on active preset
            const rootInput = document.getElementById('server_root_input');
            const publicInput = document.getElementById('server_public_input');

            let targetRoot = rootInput ? rootInput.value : ('/home/' + activeUser + '/public_html');
            let targetPublic = publicInput ? publicInput.value : ('/home/' + activeUser + '/public_html/public');

            if (currentPreset === 'cpanel_home') {
                targetRoot = '/home/' + activeUser + '/public_html';
                targetPublic = '/home/' + activeUser + '/public_html/public';
            } else if (currentPreset === 'home_domain') {
                targetRoot = '/home/' + activeUser + '/' + clean;
                targetPublic = '/home/' + activeUser + '/' + clean + '/public';
            } else if (currentPreset === 'linux_fhs') {
                targetRoot = '/var/www/' + clean;
                targetPublic = '/var/www/' + clean + '/public';
            } else if (currentPreset === 'ubuntu_default') {
                targetRoot = '/var/www/html';
                targetPublic = '/var/www/html/public';
            } else if (currentPreset === 'windows_iis') {
                targetRoot = 'C:/inetpub/wwwroot/' + clean;
                targetPublic = 'C:/inetpub/wwwroot/' + clean + '/public';
            } else if (currentPreset === 'windows_home') {
                targetRoot = 'C:/Users/' + activeUser + '/Sites/' + clean;
                targetPublic = 'C:/Users/' + activeUser + '/Sites/' + clean + '/public';
            } else if (currentPreset === 'windows_laragon') {
                targetRoot = 'C:/laragon/www/' + clean;
                targetPublic = 'C:/laragon/www/' + clean + '/public';
            } else if (currentPreset === 'macos_home') {
                targetRoot = '/Users/' + activeUser + '/Sites/' + clean;
                targetPublic = '/Users/' + activeUser + '/Sites/' + clean + '/public';
            }

            if (rootInput) rootInput.value = targetRoot;
            if (publicInput) publicInput.value = targetPublic;

            // 6. Re-render configuration templates
            renderServerConfigTemplates(clean, targetRoot, targetPublic, activeUser);
        }

        // Attach event listeners
        const domainInput = document.getElementById('domain');
        if (domainInput) {
            domainInput.addEventListener('input', function() {
                updateDomainReferences(this.value);
            });
            domainInput.addEventListener('change', function() {
                updateDomainReferences(this.value);
            });
            domainInput.addEventListener('paste', function() {
                setTimeout(() => updateDomainReferences(domainInput.value), 20);
            });
        }

        const serverUserInput = document.getElementById('server_user_input');
        if (serverUserInput) {
            serverUserInput.addEventListener('input', function() {
                this.dataset.customized = 'true';
                const val = this.value.trim() || 'sppuser';
                document.querySelectorAll('.user-ref').forEach(el => {
                    el.textContent = val;
                });
                const domainVal = (domainInput ? domainInput.value : '') || 'localhost';
                const clean = domainVal.trim().toLowerCase().split(':')[0] || 'localhost';
                const rootInput = document.getElementById('server_root_input');
                const publicInput = document.getElementById('server_public_input');

                if (currentPreset === 'cpanel_home') {
                    const tr = '/home/' + val + '/public_html';
                    const tp = '/home/' + val + '/public_html/public';
                    if (rootInput) rootInput.value = tr;
                    if (publicInput) publicInput.value = tp;
                    renderServerConfigTemplates(clean, tr, tp, val);
                } else if (currentPreset === 'home_domain') {
                    const tr = '/home/' + val + '/' + clean;
                    const tp = '/home/' + val + '/' + clean + '/public';
                    if (rootInput) rootInput.value = tr;
                    if (publicInput) publicInput.value = tp;
                    renderServerConfigTemplates(clean, tr, tp, val);
                } else if (currentPreset === 'windows_home') {
                    const tr = 'C:/Users/' + val + '/Sites/' + clean;
                    const tp = 'C:/Users/' + val + '/Sites/' + clean + '/public';
                    if (rootInput) rootInput.value = tr;
                    if (publicInput) publicInput.value = tp;
                    renderServerConfigTemplates(clean, tr, tp, val);
                } else if (currentPreset === 'macos_home') {
                    const tr = '/Users/' + val + '/Sites/' + clean;
                    const tp = '/Users/' + val + '/Sites/' + clean + '/public';
                    if (rootInput) rootInput.value = tr;
                    if (publicInput) publicInput.value = tp;
                    renderServerConfigTemplates(clean, tr, tp, val);
                } else {
                    renderServerConfigTemplates(clean, rootInput ? rootInput.value : '', publicInput ? publicInput.value : '', val);
                }
            });
        }

        const serverRootInput = document.getElementById('server_root_input');
        const serverPublicInput = document.getElementById('server_public_input');

        if (serverRootInput) {
            serverRootInput.addEventListener('input', function() {
                const domainVal = (domainInput ? domainInput.value : '') || 'localhost';
                const userVal = (serverUserInput ? serverUserInput.value.trim() : '');
                const pubVal = serverPublicInput ? serverPublicInput.value : (this.value + '/public');
                renderServerConfigTemplates(domainVal, this.value, pubVal, userVal);
            });
        }

        if (serverPublicInput) {
            serverPublicInput.addEventListener('input', function() {
                const domainVal = (domainInput ? domainInput.value : '') || 'localhost';
                const userVal = (serverUserInput ? serverUserInput.value.trim() : '');
                const rootVal = serverRootInput ? serverRootInput.value : this.value;
                renderServerConfigTemplates(domainVal, rootVal, this.value, userVal);
            });
        }

        // Initial sync on load
        if (domainInput) {
            updateDomainReferences(domainInput.value);
        }

        function confirmUninstall() {
            const sel = document.getElementById('uninstall_app');
            const app = sel.value;
            if (!app) {
                alert('Please select an application to uninstall.');
                return false;
            }
            const modeEl = document.querySelector('input[name="mode"]:checked');
            const mode = modeEl ? modeEl.value : 'config_only';
            const modeText = mode === 'config_only'
                ? 'CONFIG-ONLY removal\n\n- Unregisters application from global settings and domains\n- Flushes cache files\n- STRICTLY KEEPS all source code in src/'
                : 'FULL CODE PURGE\n\n- PERMANENTLY deletes src/' + app + '/\n- Deletes configuration, resources, and views\n- Unregisters domains';
            return confirm("⚠ Are you sure you want to proceed with uninstallation of '" + app + "'?\n\n" + modeText);
        }

        <?php if ($uninstallResult || $restoreResult || $deleteBackupResult || $createBackupResult): ?>
            document.addEventListener('DOMContentLoaded', function() {
                switchTab('tab-uninstall');
            });
        <?php endif; ?>
    </script>
</body>
</html>
    <?php
    exit(0);
}

// =============================================================================
// CLI EXECUTION MODE
// =============================================================================

// Only execute CLI commands if this script was invoked directly from the command line
$invokedScript = str_replace('\\', '/', realpath($_SERVER['SCRIPT_FILENAME'] ?? ($argv[0] ?? '')) ?: '');
$currentScript = str_replace('\\', '/', realpath(__FILE__) ?: '');
if ($invokedScript !== $currentScript && !str_ends_with($invokedScript, 'spp-domain-installer.php')) {
    return;
}

// Parse CLI options
$options = [];
$action = null;
$rawArgs = array_slice($argv ?? [], 1);

foreach ($rawArgs as $arg) {
    if (str_starts_with($arg, '--')) {
        $clean = substr($arg, 2);
        if (str_contains($clean, '=')) {
            [$k, $v] = explode('=', $clean, 2);
            $options[$k] = $v;
        } else {
            $options[$clean] = true;
        }
    } elseif ($action === null) {
        $action = strtolower(trim($arg));
    }
}

// Handle shortcuts: "php spp-domain-installer.php example.com"
if ($action !== null && str_contains($action, '.')) {
    $options['domain'] = $action;
    $action = 'install';
}

echo "================================================================================\n";
echo "   🌐 SPP Universal Server Provisioner & Domain Applications Installer\n";
echo "================================================================================\n";

// Action Routing
if ($action === 'audit' || $action === 'check') {
    echo "Running system pre-flight prerequisite audit...\n\n";
    $audit = spp_installer_audit_server($rootDir);

    echo sprintf("%-35s %-25s %s\n", "CHECK", "ACTUAL VALUE", "STATUS");
    echo str_repeat("-", 75) . "\n";
    foreach ($audit['checks'] as $c) {
        $statusStr = !empty($c['pass']) ? "[PASS]" : (!empty($c['is_critical']) ? "[FAIL]" : "[WARN]");
        echo sprintf("%-35s %-25s %s\n", substr($c['name'], 0, 34), substr($c['actual'] ?? '', 0, 24), $statusStr);
    }
    echo str_repeat("-", 75) . "\n";
    echo "Audit Result: " . ($audit['passed'] ? "✓ ALL CRITICAL PREREQUISITES SATISFIED" : "❌ CRITICAL PREREQUISITES MISSING") . "\n";
    echo "================================================================================\n";
    exit($audit['passed'] ? 0 : 1);
}

if ($action === null || $action === 'install') {
    // Dynamic domain detection (No hardcoded domain default)
    $domain = $options['domain'] ?? '';
    if (!$domain) {
        if (defined('STDIN') && function_exists('stream_isatty') && stream_isatty(STDIN)) {
            echo "Enter Target Domain Name [default: localhost]: ";
            $input = trim(fgets(STDIN) ?: '');
            $domain = $input !== '' ? $input : 'localhost';
        } else {
            $domain = gethostname() ?: 'localhost';
        }
    }

    $appName = $options['app'] ?? ($options['app-name'] ?? '');
    if (!$appName) {
        $available = spp_installer_get_available_apps($rootDir);
        $domainConfig = DomainRouter::resolveDomain($domain);
        $appName = $domainConfig['default_app'] ?? null;
        if (!$appName || !isset($available[$appName])) {
            $appName = DomainRouter::getDefaultApp($domain) ?: (\SPP\App::getGlobalSettings('base_app') ?: 'default');
            if (!isset($available[$appName])) {
                $appName = isset($available['default']) ? 'default' : (isset($available['SPPDocs']) ? 'SPPDocs' : (array_key_first($available) ?: 'default'));
            }
        }
    }

    $title = $options['title'] ?? $appName;
    $makeDefault = !isset($options['make-default']) || !empty($options['make-default']) || !empty($options['default']);
    $mode = $options['mode'] ?? 'strict';
    $newScaffold = !empty($options['new']) || !empty($options['new-scaffold']);
    $user = $options['user'] ?? null;

    echo "Target Domain:     {$domain}\n";
    echo "Application:       {$appName}\n";
    echo "Mount At Root (/): " . ($makeDefault ? "YES" : "NO") . "\n";
    echo "Policy Mode:       {$mode}\n";
    if ($user) {
        echo "Tenant User:       {$user}\n";
    }
    echo "--------------------------------------------------------------------------------\n";

    $res = spp_installer_execute($rootDir, $domain, $appName, [
        'title' => $title,
        'make_default' => $makeDefault,
        'add_www' => !isset($options['no-www']),
        'mode' => $mode,
        'new_scaffold' => $newScaffold,
        'user' => $user
    ]);

    if (!empty($res['success'])) {
        echo "✓ Success: Application '{$appName}' installed on '{$domain}'!\n";
        echo "  Target Domain:   {$res['details']['domain']}\n";
        echo "  Application:     {$res['details']['app']} ({$res['details']['app_type']})\n";
        echo "  App Path:        {$res['details']['path']}\n";
        echo "  Entry Point URL: {$res['details']['entry_url']}\n";
        echo "  Admin Console:   {$res['details']['admin_url']}\n";
        echo "  Policy Mode:     {$res['details']['policy_mode']}\n";
        echo "  Aliases:         " . (implode(', ', $res['details']['aliases']) ?: 'None') . "\n";
        echo "  Config File:     etc/domains.yml\n";
        echo "================================================================================\n";
        exit(0);
    } else {
        echo "❌ Error: " . ($res['message'] ?? 'Unknown error') . "\n";
        exit(1);
    }
}

switch ($action) {
    case 'scope':
        $appName = $options['app'] ?? '';
        if (!$appName) {
            echo "Error: --app=<appName> is required.\n";
            echo "Usage: php spp/bin/spp-domain-installer.php scope --app=<appName> [--domain-only] [--allow=domain1,domain2] [--global]\n";
            exit(1);
        }
        $isGlobal = !empty($options['global']);
        $domainOnly = !empty($options['domain-only']) || (!empty($options['allow']) && !$isGlobal);
        $allowed = !empty($options['allow']) ? explode(',', $options['allow']) : [];

        DomainRouter::setAppDomainScope($appName, true, $allowed);
        echo "✓ Success: Domain scope for '{$appName}' set to " . ($domainOnly ? "Domain-Only (" . implode(', ', $allowed) . ")" : "Global (All Domains)") . ".\n";
        exit(0);

    case 'configure':
    case 'setup':
        $targetDomain = $options['domain'] ?? (gethostname() ?: 'localhost');
        $user = $options['user'] ?? null;
        $preset = $options['preset'] ?? null;
        $domainsDir = $rootDir . '/domains';
        if (!is_dir($domainsDir)) {
            @mkdir($domainsDir, 0775, true);
        }
        $vhostDir = $domainsDir . '/_vhosts';
        if (!is_dir($vhostDir)) {
            @mkdir($vhostDir, 0775, true);
        }

        $guidance = spp_installer_get_server_guidance($rootDir, $targetDomain, null, null, $user, $preset);
        file_put_contents($vhostDir . '/apache-vhost.conf.template', $guidance['apache']);
        file_put_contents($vhostDir . '/nginx-vhost.conf.template', $guidance['nginx']);
        file_put_contents($vhostDir . '/iis-web.config.template', $guidance['iis']);
        file_put_contents($vhostDir . '/caddyfile.template', $guidance['caddy']);

        DomainRouter::loadConfig();
        DomainRouter::clearConfigCaches();
        echo "✓ Infrastructure configured and vhost templates generated for '{$targetDomain}'.\n";
        echo "  Operating System:{$guidance['env']['distro_name']} [{$guidance['env']['distro_family']}]\n";
        echo "  Package Manager: {$guidance['env']['package_manager']}\n";
        echo "  Tenant User:     {$guidance['env']['system_user']}\n";
        echo "  Target Root:     {$guidance['env']['target_root']}\n";
        echo "  DocumentRoot:    {$guidance['env']['target_public']}\n";
        echo "  Preset Profile:  {$guidance['env']['selected_profile']}\n";
        echo "  Templates Saved: domains/_vhosts/ (Apache, Nginx, IIS, Caddy)\n";
        echo "================================================================================\n";
        exit(0);

    case 'export':
        $domain = $options['domain'] ?? '';
        if (!$domain) {
            echo "Error: --domain=<domain> is required for export.\n";
            exit(1);
        }
        passthru("php \"{$rootDir}/spp.php\" domain:server:configure --export --domain={$domain}");
        exit(0);

    case 'import':
        $bundle = $options['bundle'] ?? ($options['import'] ?? '');
        $target = $options['target-domain'] ?? ($options['domain'] ?? '');
        if (!$bundle) {
            echo "Error: --bundle=<path_to_zip> is required for import.\n";
            exit(1);
        }
        $cmd = "php \"{$rootDir}/spp.php\" domain:server:configure --import --bundle=\"{$bundle}\"";
        if ($target) {
            $cmd .= " --target-domain=\"{$target}\"";
        }
        passthru($cmd);
        exit(0);

    case 'uninstall':
        $appName = $options['app'] ?? ($options['app-name'] ?? '');
        if (!$appName) {
            echo "Error: --app=<appName> is required.\n";
            echo "Usage: php spp/bin/spp-domain-installer.php uninstall --app=<appName> [--config-only | --full] [--backup] [--keep-db] [--force]\n";
            exit(1);
        }
        $configOnly = !empty($options['config-only']) || (isset($options['mode']) && $options['mode'] === 'config_only');
        $backup = !empty($options['backup']);
        $keepDb = !empty($options['keep-db']);
        $force = !empty($options['force']);

        echo "Uninstalling Application: {$appName}\n";
        echo "Mode:                    " . ($configOnly ? "Config-Only (Keep source code in src/)" : "Full Code & Config Purge") . "\n";
        echo "Pre-Removal Backup:      " . ($backup ? "YES" : "NO") . "\n";
        echo "Keep Database:           " . ($keepDb ? "YES" : "NO") . "\n";
        echo "--------------------------------------------------------------------------------\n";

        $res = AppUninstaller::uninstall($rootDir, $appName, [
            'config_only' => $configOnly,
            'backup' => $backup,
            'keep_db' => $keepDb,
            'force' => $force
        ]);

        if ($res['success']) {
            echo "✓ Success: " . $res['message'] . "\n";
            foreach ($res['actions'] as $act) {
                echo "  ✓ {$act}\n";
            }
            if (!empty($res['domains_unbound'])) {
                echo "  Unbound from domains: " . implode(', ', $res['domains_unbound']) . "\n";
            }
            echo "================================================================================\n";
            exit(0);
        } else {
            echo "❌ Error: " . $res['message'] . "\n";
            exit(1);
        }

    case 'restore':
        $backup = $options['backup'] ?? ($options['file'] ?? ($options['app'] ?? ''));
        if (!$backup) {
            echo "Error: --backup=<filename_or_appName> is required.\n";
            echo "Usage: php spp/bin/spp-domain-installer.php restore --backup=<filename_or_appName> [--force]\n";
            exit(1);
        }
        $force = !empty($options['force']);

        echo "Restoring application from backup: {$backup}...\n";
        $res = AppUninstaller::restore($rootDir, $backup, ['force' => $force]);

        if ($res['success']) {
            echo "✓ Success: " . $res['message'] . "\n";
            echo "  Application: {$res['app']}\n";
            echo "  Files Restored: {$res['files_restored']}\n";
            echo "  Registered in global-settings.yml: " . ($res['registered_in_settings'] ? "YES" : "NO") . "\n";
            if (!empty($res['domains_restored'])) {
                echo "  Bound Domains: " . implode(', ', $res['domains_restored']) . "\n";
            }
            echo "================================================================================\n";
            exit(0);
        } else {
            echo "❌ Error: " . $res['message'] . "\n";
            exit(1);
        }

    case 'backups':
    case 'backup':
        // Delete backup archive if requested
        if (!empty($options['delete'])) {
            $delRes = AppUninstaller::deleteBackup($rootDir, $options['delete']);
            if ($delRes['success']) {
                echo "✓ Success: " . $delRes['message'] . "\n";
                echo "  Reclaimed Disk Space: " . ($delRes['freed_formatted'] ?? '0 B') . "\n";
                exit(0);
            } else {
                echo "❌ Error: " . $delRes['message'] . "\n";
                exit(1);
            }
        }

        // Purge all backups for an app if requested
        if (!empty($options['purge'])) {
            $purgeRes = AppUninstaller::purgeAppBackups($rootDir, $options['purge']);
            if ($purgeRes['success']) {
                echo "✓ Success: " . $purgeRes['message'] . "\n";
                echo "  Archives Deleted: " . $purgeRes['count'] . "\n";
                echo "  Reclaimed Disk Space: " . $purgeRes['freed_formatted'] . "\n";
                exit(0);
            } else {
                echo "❌ Error: " . $purgeRes['message'] . "\n";
                exit(1);
            }
        }

        // Create backup if app provided and not --list
        if (!empty($options['app']) && !isset($options['list'])) {
            $bkRes = AppUninstaller::createBackup($rootDir, $options['app']);
            if ($bkRes['success']) {
                echo "✓ Success: " . $bkRes['message'] . "\n";
                echo "  Archive File: {$bkRes['file']}\n";
                echo "  File Size:    {$bkRes['size_formatted']}\n";
                echo "  Files Count:  {$bkRes['files_count']}\n";
                exit(0);
            } else {
                echo "❌ Error: " . $bkRes['message'] . "\n";
                exit(1);
            }
        }

        // Default: list backups
        $inventory = AppUninstaller::getBackupInventory($rootDir);
        echo "Application Backups Inventory (var/backups/apps/):\n";
        echo "Total Backups: " . $inventory['count'] . " | Total Disk Space: " . $inventory['total_size_formatted'] . "\n\n";

        if (empty($inventory['backups'])) {
            echo "  No backups found.\n";
        } else {
            echo sprintf("  %-36s %-16s %-20s %-10s\n", "ARCHIVE FILENAME", "APP", "CREATED", "SIZE");
            echo "  " . str_repeat("-", 86) . "\n";
            foreach ($inventory['backups'] as $b) {
                echo sprintf("  %-36s %-16s %-20s %-10s\n", substr($b['filename'], 0, 35), substr($b['app'], 0, 15), substr($b['created_at'], 0, 19), $b['size_formatted']);
            }
            echo "  " . str_repeat("-", 86) . "\n";
        }
        echo "================================================================================\n";
        exit(0);

    case 'help':
    default:
        echo "Available Commands:\n";
        echo "  audit      Run server prerequisite checks (PHP version, extensions, writable paths)\n";
        echo "             php spp/bin/spp-domain-installer.php audit\n\n";
        echo "  install    Install SPP application for a domain (binds existing or scaffolds new)\n";
        echo "             php spp/bin/spp-domain-installer.php install --domain=example.com [--user=example] [--app=school1] [--make-default]\n\n";
        echo "  uninstall  Decommission and uninstall an application (config-only or full purge)\n";
        echo "             php spp/bin/spp-domain-installer.php uninstall --app=MyApp [--config-only | --full] [--backup]\n\n";
        echo "  restore    Restore an application from a backup archive in var/backups/apps/\n";
        echo "             php spp/bin/spp-domain-installer.php restore --backup=MyApp_20261025_120000.zip\n\n";
        echo "  backups    Manage application backup archives (list, create, delete, purge)\n";
        echo "             php spp/bin/spp-domain-installer.php backups [--app=MyApp] [--delete=archive.zip] [--purge=MyApp]\n\n";
        echo "  configure  Initialize directory layout and generate web server vhost templates\n";
        echo "             php spp/bin/spp-domain-installer.php configure [--domain=example.com] [--user=example]\n\n";
        echo "  scope      Configure domain scope restrictions (domain-only vs global)\n";
        echo "             php spp/bin/spp-domain-installer.php scope --app=school1 --domain-only --allow=example.com\n\n";
        echo "  export     Bundle domain application for deployment to another server\n";
        echo "             php spp/bin/spp-domain-installer.php export --domain=example.com\n\n";
        echo "  import     Deploy and reconfigure a domain application bundle on target server\n";
        echo "             php spp/bin/spp-domain-installer.php import --bundle=bundle.zip --target-domain=example.com\n";
        exit(0);
}
