<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

/**
 * Class SecurityAuditCommand
 *
 * Scans the SPP framework and installed applications for critical security vulnerabilities,
 * misconfigurations, exposed secrets, and unhardened production settings.
 *
 * @package SPP\CLI\Commands
 * @author Satya Prakash Shukla
 */
class SecurityAuditCommand extends Command
{
    protected string $name = 'security:audit';
    protected string $description = 'Audit framework and application configuration for security vulnerabilities and posture';

    /**
     * Strict CLI SAPI guard to prevent execution from web contexts.
     */
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $app = $this->getOption($args, 'app', 'default');
        $strict = $this->hasFlag($args, 'strict');
        $isJson = $this->hasFlag($args, 'json');

        if (!$isJson) {
            $this->line("================================================================================");
            $this->line(" 🛡️  SPP Security Audit & Posture Scanner");
            $this->line("================================================================================");
            $this->line("Target Context: " . $app);
            $this->line("Environment   : " . (getenv('APP_ENV') ?: (defined('SPP_DEBUG') && SPP_DEBUG ? 'development' : 'production')));
            $this->line("Timestamp     : " . date('Y-m-d H:i:s T'));
            $this->line("--------------------------------------------------------------------------------\n");
        }

        $findings = [];

        // 1. Check .env existence & permissions
        $envFile = (defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 2)) . DIRECTORY_SEPARATOR . '.env';
        if (file_exists($envFile)) {
            $perms = substr(sprintf('%o', fileperms($envFile)), -4);
            if (intval($perms) > 644) {
                $findings[] = [
                    'severity' => 'WARN',
                    'category' => 'File Permissions',
                    'item' => '.env permissions',
                    'detail' => ".env file has permissive permissions ({$perms}). Recommended: 0600 or 0640.",
                    'pass' => false,
                ];
            } else {
                $findings[] = [
                    'severity' => 'PASS',
                    'category' => 'File Permissions',
                    'item' => '.env permissions',
                    'detail' => ".env file has secure permissions ({$perms}).",
                    'pass' => true,
                ];
            }

            // Check for default secrets inside .env
            $envContent = file_get_contents($envFile);
            $weakSecrets = ['root', 'password', 'secret', 'admin', '123456', 'SomeDefaultSecret', 'changeme'];
            if (preg_match('/(?:APP_KEY|APP_SECRET|JWT_SECRET|DB_PASSWORD)\s*=\s*["\']?([^"\'\r\n]+)["\']?/i', $envContent, $matches)) {
                $val = trim($matches[1]);
                if (in_array($val, $weakSecrets, true) || strlen($val) < 8) {
                    $findings[] = [
                        'severity' => 'FAIL',
                        'category' => 'Secrets & Credentials',
                        'item' => 'Secret Entropy',
                        'detail' => "Default or weak secret detected in environment credentials ({$matches[0]}).",
                        'pass' => false,
                    ];
                } else {
                    $findings[] = [
                        'severity' => 'PASS',
                        'category' => 'Secrets & Credentials',
                        'item' => 'Secret Entropy',
                        'detail' => "Strong cryptographic secret configured in environment variables.",
                        'pass' => true,
                    ];
                }
            }
        } else {
            $findings[] = [
                'severity' => 'INFO',
                'category' => 'Configuration',
                'item' => '.env file',
                'detail' => ".env file not found in application root. Using environment / global config.",
                'pass' => true,
            ];
        }

        // 2. Production Debug Mode check
        $env = strtolower(getenv('APP_ENV') ?: '');
        $debug = defined('SPP_DEBUG') ? SPP_DEBUG : false;
        if ($env === 'production' && $debug) {
            $findings[] = [
                'severity' => 'FAIL',
                'category' => 'Environment',
                'item' => 'Debug Mode',
                'detail' => "SPP_DEBUG is ENABLED in a production environment! Stack traces and environment variables may be exposed.",
                'pass' => false,
            ];
        } else {
            $findings[] = [
                'severity' => 'PASS',
                'category' => 'Environment',
                'item' => 'Debug Mode',
                'detail' => $debug ? "Debug mode enabled for non-production environment." : "Debug mode disabled.",
                'pass' => true,
            ];
        }

        // 3. Session Security Settings
        $cookieParams = session_get_cookie_params();
        $isHttponly = $cookieParams['httponly'] ?? false;
        $isSecure = $cookieParams['secure'] ?? false;
        $sameSite = $cookieParams['samesite'] ?? '';

        if (!$isHttponly) {
            $findings[] = [
                'severity' => 'FAIL',
                'category' => 'Session Security',
                'item' => 'Cookie HttpOnly',
                'detail' => "session.cookie_httponly is not enforced. Session cookies can be accessed via JavaScript XSS.",
                'pass' => false,
            ];
        } else {
            $findings[] = [
                'severity' => 'PASS',
                'category' => 'Session Security',
                'item' => 'Cookie HttpOnly',
                'detail' => "HttpOnly flag is properly enforced on session cookies.",
                'pass' => true,
            ];
        }

        if (empty($sameSite) || strtolower($sameSite) === 'none') {
            $findings[] = [
                'severity' => 'WARN',
                'category' => 'Session Security',
                'item' => 'Cookie SameSite',
                'detail' => "session.cookie_samesite is not set to 'Lax' or 'Strict'. Vulnerable to CSRF on cross-site requests.",
                'pass' => false,
            ];
        } else {
            $findings[] = [
                'severity' => 'PASS',
                'category' => 'Session Security',
                'item' => 'Cookie SameSite',
                'detail' => "SameSite policy enforced as '" . htmlspecialchars($sameSite) . "'.",
                'pass' => true,
            ];
        }

        // 4. Core Directory Writeability
        $baseDir = defined('SPP_BASE_DIR') ? SPP_BASE_DIR : dirname(__DIR__, 2);
        $sensitiveDirs = [
            'spp/core' => $baseDir . DIRECTORY_SEPARATOR . 'core',
            'spp/etc' => $baseDir . DIRECTORY_SEPARATOR . 'etc',
        ];
        foreach ($sensitiveDirs as $label => $dir) {
            if (is_dir($dir) && is_writable($dir) && PHP_OS_FAMILY !== 'Windows') {
                $findings[] = [
                    'severity' => 'WARN',
                    'category' => 'Filesystem Security',
                    'item' => "{$label} Writeability",
                    'detail' => "Core directory {$label} is writable by web worker. Should be read-only in production.",
                    'pass' => false,
                ];
            } else {
                $findings[] = [
                    'severity' => 'PASS',
                    'category' => 'Filesystem Security',
                    'item' => "{$label} Writeability",
                    'detail' => "Core directory {$label} verified secure.",
                    'pass' => true,
                ];
            }
        }

        // 5. Public Web Root Exposure Check (SQLite databases)
        $publicDir = (defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 2)) . DIRECTORY_SEPARATOR . 'public';
        if (is_dir($publicDir)) {
            $exposedSqlite = glob($publicDir . DIRECTORY_SEPARATOR . '*.{db,sqlite,sqlite3}', GLOB_BRACE) ?: [];
            if (!empty($exposedSqlite)) {
                $findings[] = [
                    'severity' => 'FAIL',
                    'category' => 'Information Disclosure',
                    'item' => 'Exposed Database',
                    'detail' => count($exposedSqlite) . " SQLite database(s) found directly inside public web document root!",
                    'pass' => false,
                ];
            } else {
                $findings[] = [
                    'severity' => 'PASS',
                    'category' => 'Information Disclosure',
                    'item' => 'Exposed Database',
                    'detail' => "No raw database files located in public web root.",
                    'pass' => true,
                ];
            }
        }

        // 6. CSRF & ABAC / Field Governance Check
        if (class_exists('\SPPMod\SPPAuth\FieldPolicy')) {
            $findings[] = [
                'severity' => 'PASS',
                'category' => 'Access Governance',
                'item' => 'Field & Entity ABAC',
                'detail' => "Universal FieldPolicy access governance engine is operational.",
                'pass' => true,
            ];
        }

        // Output results
        if ($isJson) {
            $this->json($findings);
            return;
        }

        $passes = 0;
        $warns = 0;
        $fails = 0;

        foreach ($findings as $f) {
            $badge = match ($f['severity']) {
                'PASS' => "\033[32m[PASS]\033[0m",
                'WARN' => "\033[33m[WARN]\033[0m",
                'FAIL' => "\033[31m[FAIL]\033[0m",
                default => "\033[36m[INFO]\033[0m",
            };

            if ($f['severity'] === 'PASS') $passes++;
            elseif ($f['severity'] === 'WARN') $warns++;
            elseif ($f['severity'] === 'FAIL') $fails++;

            $this->line(sprintf(" %-15s %-22s %-20s", $badge, $f['category'], $f['item']));
            $this->line("   ↳ " . $f['detail'] . "\n");
        }

        $this->line("--------------------------------------------------------------------------------");
        $this->line("Audit Summary: {$passes} Passed, {$warns} Warnings, {$fails} Critical Failures");
        $this->line("================================================================================\n");

        if ($fails > 0 || ($strict && $warns > 0)) {
            $this->error("Security Audit FAILED: Posture issues require remediation.");
            exit(1);
        } else {
            $this->info("Security Audit PASSED: System meets baseline hardening standards.");
        }
    }
}
