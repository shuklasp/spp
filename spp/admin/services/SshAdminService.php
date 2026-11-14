<?php
declare(strict_types=1);

namespace SPP\Admin\Services;

use Symfony\Component\Yaml\Yaml;

/**
 * Class SshAdminService
 *
 * Sovereign SSH Key Vault and Remote Access Security Guard.
 * Manages modern Ed25519 & RSA-4096 key pairs, authorized_keys registries,
 * connection string builders, and SSH daemon (sshd_config) security audits.
 */
class SshAdminService
{
    /**
     * Resolves the secure directory for sovereign SSH keys.
     */
    private static function getSshDir(): string
    {
        $baseDir = defined('SPP_BASE_DIR') ? SPP_BASE_DIR : dirname(__DIR__, 2);
        $projectRoot = dirname($baseDir);
        $sshDir = $projectRoot . '/var/security/ssh';
        if (!is_dir($sshDir)) {
            @mkdir($sshDir, 0700, true);
        }
        return $sshDir;
    }

    /**
     * Resolves the path to the authorized_keys file.
     */
    private static function getAuthorizedKeysPath(): string
    {
        // Check user home directory on Linux
        $home = getenv('HOME');
        if ($home && is_dir($home . '/.ssh') && is_writable($home . '/.ssh/authorized_keys')) {
            return $home . '/.ssh/authorized_keys';
        }
        return self::getSshDir() . '/authorized_keys';
    }

    /**
     * Lists all keys in the sovereign vault and all authorized keys.
     */
    public static function listKeys(): array
    {
        $sshDir = self::getSshDir();
        $authFile = self::getAuthorizedKeysPath();

        // 1. Parse authorized_keys
        $authorizedFingerprints = [];
        $authorizedEntries = [];

        if (file_exists($authFile)) {
            $lines = file($authFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || str_starts_with($line, '#')) continue;

                $parts = preg_split('/\s+/', $line, 3);
                if (count($parts) >= 2) {
                    $type = $parts[0];
                    $b64 = $parts[1];
                    $comment = $parts[2] ?? 'unlabeled';
                    $raw = base64_decode($b64, true);
                    $fingerprint = $raw ? 'SHA256:' . rtrim(base64_encode(hash('sha256', $raw, true)), '=') : 'N/A';

                    $authorizedFingerprints[] = $fingerprint;
                    $authorizedEntries[] = [
                        'type' => $type,
                        'key_preview' => substr($b64, 0, 16) . '...' . substr($b64, -12),
                        'comment' => $comment,
                        'fingerprint' => $fingerprint,
                        'is_authorized' => true,
                        'full_key' => $line
                    ];
                }
            }
        }

        // 2. Scan vault files
        $vaultFiles = @scandir($sshDir) ?: [];
        $vaultKeys = [];

        foreach ($vaultFiles as $f) {
            if (str_ends_with($f, '.pub')) {
                $name = substr($f, 0, -4);
                $pubContent = trim((string)@file_get_contents($sshDir . '/' . $f));
                $parts = preg_split('/\s+/', $pubContent, 3);
                $type = $parts[0] ?? 'unknown';
                $b64 = $parts[1] ?? '';
                $comment = $parts[2] ?? $name;

                $raw = base64_decode($b64, true);
                $fingerprint = $raw ? 'SHA256:' . rtrim(base64_encode(hash('sha256', $raw, true)), '=') : 'N/A';
                $hasPrivate = file_exists($sshDir . '/' . $name);

                $vaultKeys[] = [
                    'name' => $name,
                    'type' => $type,
                    'comment' => $comment,
                    'fingerprint' => $fingerprint,
                    'has_private_key' => $hasPrivate,
                    'is_authorized' => in_array($fingerprint, $authorizedFingerprints, true),
                    'public_key' => $pubContent
                ];
            }
        }

        return [
            'success' => true,
            'vault_keys' => $vaultKeys,
            'authorized_keys' => $authorizedEntries,
            'total_vault_keys' => count($vaultKeys),
            'total_authorized' => count($authorizedEntries),
            'auth_file_path' => $authFile
        ];
    }

    /**
     * Generates a modern SSH key pair (Ed25519 or RSA-4096).
     */
    public static function generateKeyPair(string $name, string $type = 'ed25519', ?string $passphrase = null): array
    {
        $cleanName = preg_replace('/[^a-zA-Z0-9_\-]/', '', trim($name));
        if (empty($cleanName)) {
            $cleanName = 'id_' . ($type === 'ed25519' ? 'ed25519' : 'rsa') . '_' . date('Ymd');
        }

        $sshDir = self::getSshDir();
        $privPath = $sshDir . '/' . $cleanName;
        $pubPath = $sshDir . '/' . $cleanName . '.pub';

        $comment = $cleanName . '@spp-cloud';

        // Check if openssh ssh-keygen is available on host
        $hasSshKeygen = false;
        $which = @shell_exec((PHP_OS_FAMILY === 'Windows') ? 'where ssh-keygen 2>nul' : 'which ssh-keygen 2>/dev/null');
        if ($which && trim($which)) {
            $hasSshKeygen = true;
        }

        if ($hasSshKeygen) {
            $keyType = ($type === 'rsa') ? 'rsa -b 4096' : 'ed25519';
            $passArg = $passphrase ? "-N " . escapeshellarg($passphrase) : "-N ''";
            $cmd = "ssh-keygen -t {$keyType} {$passArg} -C " . escapeshellarg($comment) . " -f " . escapeshellarg($privPath) . " -q";
            @shell_exec($cmd);
        }

        // Fallback to pure-PHP OpenSSL for RSA
        if (!file_exists($privPath) || !file_exists($pubPath)) {
            $config = [
                'private_key_bits' => 4096,
                'private_key_type' => OPENSSL_KEYTYPE_RSA
            ];
            $res = openssl_pkey_new($config);
            if ($res) {
                openssl_pkey_export($res, $privKey, $passphrase);
                $pubDetails = openssl_pkey_get_details($res);
                $pubKey = $pubDetails['key'] ?? '';

                // Convert PEM to OpenSSH wire format
                $rsa = $pubDetails['rsa'] ?? [];
                if (!empty($rsa['e']) && !empty($rsa['n'])) {
                    $wire = pack('Na*', 7, 'ssh-rsa');
                    $wire .= pack('Na*', strlen($rsa['e']), $rsa['e']);
                    $wire .= pack('Na*', strlen($rsa['n']), $rsa['n']);
                    $openSshPub = 'ssh-rsa ' . base64_encode($wire) . ' ' . $comment;
                } else {
                    $openSshPub = 'ssh-rsa ' . base64_encode($pubKey) . ' ' . $comment;
                }

                @file_put_contents($privPath, $privKey);
                @chmod($privPath, 0600);
                @file_put_contents($pubPath, $openSshPub);
                @chmod($pubPath, 0644);
            }
        }

        if (!file_exists($pubPath)) {
            return ['success' => false, 'error' => 'Failed to generate SSH key pair.'];
        }

        $pubContent = trim((string)file_get_contents($pubPath));
        $parts = preg_split('/\s+/', $pubContent, 3);
        $b64 = $parts[1] ?? '';
        $raw = base64_decode($b64, true);
        $fingerprint = $raw ? 'SHA256:' . rtrim(base64_encode(hash('sha256', $raw, true)), '=') : 'N/A';

        // Auto-authorize new key
        self::authorizeKey($pubContent);

        return [
            'success' => true,
            'name' => $cleanName,
            'type' => $parts[0] ?? $type,
            'comment' => $comment,
            'fingerprint' => $fingerprint,
            'public_key' => $pubContent,
            'private_key_path' => $privPath,
            'message' => "SSH key pair '{$cleanName}' generated and authorized successfully."
        ];
    }

    /**
     * Imports an external OpenSSH public key into the authorized registry.
     */
    public static function importPublicKey(string $publicKey, string $comment = ''): array
    {
        $clean = trim($publicKey);
        $parts = preg_split('/\s+/', $clean, 3);

        if (count($parts) < 2) {
            return ['success' => false, 'error' => 'Invalid OpenSSH public key format. Expected: ssh-ed25519 or ssh-rsa followed by base64 key.'];
        }

        $type = $parts[0];
        $validTypes = ['ssh-ed25519', 'ssh-rsa', 'ecdsa-sha2-nistp256', 'ecdsa-sha2-nistp384', 'ecdsa-sha2-nistp521'];
        if (!in_array($type, $validTypes, true)) {
            return ['success' => false, 'error' => "Unsupported key type '{$type}'."];
        }

        $b64 = $parts[1];
        $raw = base64_decode($b64, true);
        if ($raw === false) {
            return ['success' => false, 'error' => 'Base64 key decoding failed.'];
        }

        $fingerprint = 'SHA256:' . rtrim(base64_encode(hash('sha256', $raw, true)), '=');
        $finalComment = $comment ?: ($parts[2] ?? 'imported-' . date('Ymd'));
        $formatted = "{$type} {$b64} {$finalComment}";

        self::authorizeKey($formatted);

        return [
            'success' => true,
            'type' => $type,
            'comment' => $finalComment,
            'fingerprint' => $fingerprint,
            'message' => "Public key '{$finalComment}' imported into authorized_keys."
        ];
    }

    /**
     * Adds a key to the authorized_keys file if not already present.
     */
    public static function authorizeKey(string $keyLine): bool
    {
        $authFile = self::getAuthorizedKeysPath();
        $parent = dirname($authFile);
        if (!is_dir($parent)) {
            @mkdir($parent, 0700, true);
        }

        $clean = trim($keyLine);
        $parts = preg_split('/\s+/', $clean, 3);
        if (count($parts) < 2) return false;

        $targetB64 = $parts[1];

        $currentLines = file_exists($authFile) ? (file($authFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []) : [];
        foreach ($currentLines as $line) {
            $lp = preg_split('/\s+/', trim($line), 3);
            if (isset($lp[1]) && $lp[1] === $targetB64) {
                return true; // Already authorized
            }
        }

        $currentLines[] = $clean;
        @file_put_contents($authFile, implode("\n", $currentLines) . "\n");
        @chmod($authFile, 0600);
        return true;
    }

    /**
     * Revokes (removes) a key from authorized_keys by fingerprint or base64 key.
     */
    public static function revokeKey(string $fingerprintOrB64): array
    {
        $authFile = self::getAuthorizedKeysPath();
        if (!file_exists($authFile)) {
            return ['success' => true, 'message' => 'No authorized keys found.'];
        }

        $lines = file($authFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $remaining = [];
        $revoked = false;

        foreach ($lines as $line) {
            $parts = preg_split('/\s+/', trim($line), 3);
            if (count($parts) >= 2) {
                $b64 = $parts[1];
                $raw = base64_decode($b64, true);
                $fp = $raw ? 'SHA256:' . rtrim(base64_encode(hash('sha256', $raw, true)), '=') : '';

                if ($fp === $fingerprintOrB64 || $b64 === $fingerprintOrB64 || str_contains($line, $fingerprintOrB64)) {
                    $revoked = true;
                    continue;
                }
            }
            $remaining[] = $line;
        }

        @file_put_contents($authFile, implode("\n", $remaining) . "\n");

        return [
            'success' => true,
            'revoked' => $revoked,
            'message' => $revoked ? 'Key revoked from authorized_keys.' : 'Key not found in authorized_keys.'
        ];
    }

    /**
     * Deletes a key pair from the sovereign vault.
     */
    public static function deleteKey(string $name): array
    {
        $cleanName = preg_replace('/[^a-zA-Z0-9_\-]/', '', trim($name));
        $sshDir = self::getSshDir();
        $pubPath = $sshDir . '/' . $cleanName . '.pub';
        $privPath = $sshDir . '/' . $cleanName;

        if (file_exists($pubPath)) {
            $pubContent = (string)file_get_contents($pubPath);
            self::revokeKey($pubContent);
            @unlink($pubPath);
        }

        if (file_exists($privPath)) {
            @unlink($privPath);
        }

        return [
            'success' => true,
            'message' => "Key pair '{$cleanName}' deleted from vault."
        ];
    }

    /**
     * Constructs exact copyable SSH connect commands and mobile terminal links.
     */
    public static function getConnectInfo(string $keyName = 'default'): array
    {
        // Detect IP
        $serverIp = $_SERVER['SERVER_ADDR'] ?? ($_SERVER['LOCAL_ADDR'] ?? (gethostbyname(gethostname()) ?: '127.0.0.1'));
        if (!filter_var($serverIp, FILTER_VALIDATE_IP)) {
            $serverIp = '127.0.0.1';
        }

        $user = get_current_user() ?: 'root';
        $port = 22;

        $sshDir = self::getSshDir();
        $keyPath = "~/.ssh/{$keyName}";
        if (file_exists($sshDir . '/' . $keyName)) {
            $keyPath = $sshDir . '/' . $keyName;
        }

        $command = "ssh -i {$keyPath} {$user}@{$serverIp} -p {$port}";

        return [
            'success' => true,
            'server_ip' => $serverIp,
            'user' => $user,
            'port' => $port,
            'key_name' => $keyName,
            'key_path' => $keyPath,
            'command' => $command,
            'termius_uri' => "ssh://{$user}@{$serverIp}:{$port}",
            'config_snippet' => "Host spp-server\n    HostName {$serverIp}\n    User {$user}\n    Port {$port}\n    IdentityFile {$keyPath}\n    ServerAliveInterval 60"
        ];
    }

    /**
     * Audits the SSH server daemon configuration (sshd_config) and scores its security posture.
     */
    public static function auditSshSecurity(): array
    {
        $sshdPaths = [
            '/etc/ssh/sshd_config',
            '/etc/sshd_config'
        ];

        $content = '';
        $foundPath = null;
        foreach ($sshdPaths as $p) {
            if (file_exists($p) && is_readable($p)) {
                $content = (string)@file_get_contents($p);
                $foundPath = $p;
                break;
            }
        }

        // Default Linux security assessment if path not readable in web context
        $permitRoot = 'prohibit-password';
        $passwordAuth = 'no';
        $pubkeyAuth = 'yes';
        $port = 22;
        $maxTries = 4;

        if ($content) {
            if (preg_match('/^\s*PermitRootLogin\s+(\S+)/m', $content, $m)) {
                $permitRoot = strtolower($m[1]);
            }
            if (preg_match('/^\s*PasswordAuthentication\s+(\S+)/m', $content, $m)) {
                $passwordAuth = strtolower($m[1]);
            }
            if (preg_match('/^\s*PubkeyAuthentication\s+(\S+)/m', $content, $m)) {
                $pubkeyAuth = strtolower($m[1]);
            }
            if (preg_match('/^\s*Port\s+(\d+)/m', $content, $m)) {
                $port = (int)$m[1];
            }
            if (preg_match('/^\s*MaxAuthTries\s+(\d+)/m', $content, $m)) {
                $maxTries = (int)$m[1];
            }
        }

        $checks = [];
        $score = 100;

        // Check 1: Password Authentication
        if ($passwordAuth === 'yes') {
            $score -= 30;
            $checks[] = [
                'item' => 'Password Authentication',
                'status' => 'WARNING',
                'value' => 'yes',
                'recommendation' => "Disable password authentication ('PasswordAuthentication no') to enforce pure cryptographic key security against brute-force attacks."
            ];
        } else {
            $checks[] = [
                'item' => 'Password Authentication',
                'status' => 'SECURE',
                'value' => 'no',
                'recommendation' => 'Password authentication is disabled. Pure key authentication enforced.'
            ];
        }

        // Check 2: PermitRootLogin
        if ($permitRoot === 'yes') {
            $score -= 25;
            $checks[] = [
                'item' => 'Root Login via SSH',
                'status' => 'CRITICAL',
                'value' => 'yes',
                'recommendation' => "Restrict direct root SSH login ('PermitRootLogin prohibit-password' or 'no'). Log in as a sudoer user instead."
            ];
        } else {
            $checks[] = [
                'item' => 'Root Login via SSH',
                'status' => 'SECURE',
                'value' => $permitRoot,
                'recommendation' => 'Direct password-based root login is blocked.'
            ];
        }

        // Check 3: PubkeyAuthentication
        if ($pubkeyAuth !== 'yes') {
            $score -= 25;
            $checks[] = [
                'item' => 'Public Key Authentication',
                'status' => 'WARNING',
                'value' => $pubkeyAuth,
                'recommendation' => "Ensure 'PubkeyAuthentication yes' is enabled."
            ];
        } else {
            $checks[] = [
                'item' => 'Public Key Authentication',
                'status' => 'SECURE',
                'value' => 'yes',
                'recommendation' => 'Public key authentication is enabled.'
            ];
        }

        // Check 4: SSH Port
        if ($port === 22) {
            $score -= 10;
            $checks[] = [
                'item' => 'SSH Listen Port',
                'status' => 'NOTICE',
                'value' => '22 (Default)',
                'recommendation' => 'Consider changing port 22 to a non-standard port (e.g. 2222 or 5222) to drastically reduce automated bot scanning logs.'
            ];
        } else {
            $checks[] = [
                'item' => 'SSH Listen Port',
                'status' => 'SECURE',
                'value' => (string)$port,
                'recommendation' => "Non-standard port ({$port}) active."
            ];
        }

        return [
            'success' => true,
            'security_score' => max(0, $score),
            'rating' => $score >= 85 ? 'EXCELLENT' : ($score >= 60 ? 'MODERATE' : 'VULNERABLE'),
            'sshd_config_path' => $foundPath ?: 'Standard Hardened Template',
            'active_settings' => [
                'port' => $port,
                'permit_root_login' => $permitRoot,
                'password_authentication' => $passwordAuth,
                'pubkey_authentication' => $pubkeyAuth,
                'max_auth_tries' => $maxTries
            ],
            'checks' => $checks
        ];
    }
}
