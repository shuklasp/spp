<?php
declare(strict_types=1);

namespace SPP\Core\Dns;

use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * Class DnsCredentialVault
 *
 * Secure encrypted credential store for external DNS and Registrar providers.
 * Encrypts sensitive API tokens and keys using AES-256-GCM.
 * Redacts secrets in all administrative views and diagnostic logs.
 */
class DnsCredentialVault
{
    private const CIPHER = 'aes-256-gcm';
    private static ?array $vaultData = null;

    public static function getVaultFile(): string
    {
        if (class_exists('\\SPP\\Core\\Config\\ConfigIsolationManager')) {
            return \SPP\Core\Config\ConfigIsolationManager::ensureEffectiveConfig('dns-providers.yml');
        }
        $baseDir = defined('SPP_BASE_DIR') ? SPP_BASE_DIR : dirname(__DIR__, 2);
        return $baseDir . '/etc/dns-providers.yml';
    }

    /**
     * Derives a deterministic 256-bit encryption key unique to this SPP host installation.
     */
    private static function getEncryptionKey(): string
    {
        $serverSecret = getenv('SPP_SERVER_SECRET') ?: '';
        if (empty($serverSecret)) {
            $baseDir = defined('SPP_BASE_DIR') ? SPP_BASE_DIR : dirname(__DIR__, 2);
            $secretFile = $baseDir . '/etc/.server_secret';
            if (file_exists($secretFile)) {
                $serverSecret = trim((string)@file_get_contents($secretFile));
            } else {
                $serverSecret = bin2hex(random_bytes(32));
                @file_put_contents($secretFile, $serverSecret);
                @chmod($secretFile, 0600);
            }
        }
        return hash('sha256', 'SPP_DNS_VAULT_' . $serverSecret, true);
    }

    /**
     * Encrypts plaintext string using AES-256-GCM.
     */
    public static function encrypt(string $plaintext): string
    {
        if ($plaintext === '') {
            return '';
        }
        $key = self::getEncryptionKey();
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($ciphertext === false) {
            return $plaintext;
        }
        return 'ENC:' . base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Decrypts AES-256-GCM encrypted string.
     */
    public static function decrypt(string $data): string
    {
        if (!str_starts_with($data, 'ENC:')) {
            return $data; // Unencrypted legacy fallback
        }
        $raw = base64_decode(substr($data, 4), true);
        if ($raw === false || strlen($raw) < 28) {
            return '';
        }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ciphertext = substr($raw, 28);
        $key = self::getEncryptionKey();

        $decrypted = openssl_decrypt($ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);
        return $decrypted !== false ? $decrypted : '';
    }

    /**
     * Loads the vault from disk.
     */
    private static function load(): array
    {
        if (self::$vaultData !== null) {
            return self::$vaultData;
        }

        $file = self::getVaultFile();
        $defaults = [
            'version' => 2,
            'accounts' => []
        ];

        if (file_exists($file) && class_exists(Yaml::class)) {
            try {
                $parsed = Yaml::parseFile($file) ?: [];
                self::$vaultData = array_merge($defaults, $parsed);
            } catch (Throwable $e) {
                self::$vaultData = $defaults;
            }
        } else {
            self::$vaultData = $defaults;
        }

        // Migrate legacy etc/cloudflare.yml if present and no Cloudflare accounts configured yet
        self::migrateLegacyCloudflare();

        return self::$vaultData;
    }

    /**
     * Seamlessly migrates legacy etc/cloudflare.yml into the encrypted vault.
     */
    private static function migrateLegacyCloudflare(): void
    {
        $baseDir = defined('SPP_BASE_DIR') ? SPP_BASE_DIR : dirname(__DIR__, 2);
        $legacyCf = $baseDir . '/etc/cloudflare.yml';
        if (file_exists($legacyCf) && empty(self::$vaultData['accounts']['cf_default']) && class_exists(Yaml::class)) {
            try {
                $cf = Yaml::parseFile($legacyCf) ?: [];
                if (!empty($cf['api_token'])) {
                    self::$vaultData['accounts']['cf_default'] = [
                        'id' => 'cf_default',
                        'name' => 'Default Cloudflare Account',
                        'provider' => 'cloudflare',
                        'credentials' => [
                            'api_token' => self::encrypt((string)$cf['api_token']),
                            'account_id' => (string)($cf['account_id'] ?? '')
                        ],
                        'read_only' => false,
                        'default_proxied' => $cf['default_proxied'] ?? true,
                        'created_at' => date('Y-m-d H:i:s'),
                        'last_verified_at' => $cf['last_verified_at'] ?? null
                    ];
                    self::persist();
                }
            } catch (Throwable $e) {
                // Ignore migration errors
            }
        }
    }

    /**
     * Persists vault data to disk.
     */
    private static function persist(): bool
    {
        $file = self::getVaultFile();
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        if (class_exists(Yaml::class)) {
            $yaml = Yaml::dump(self::$vaultData, 6, 2);
            $res = @file_put_contents($file, $yaml);
            if ($res !== false) {
                @chmod($file, 0600);
                return true;
            }
        }
        return false;
    }

    /**
     * Lists all registered provider accounts with secrets masked.
     */
    public static function listAccounts(?string $providerFilter = null): array
    {
        $data = self::load();
        $accounts = [];

        foreach ($data['accounts'] ?? [] as $id => $acc) {
            if ($providerFilter !== null && ($acc['provider'] ?? '') !== $providerFilter) {
                continue;
            }
            $accounts[] = self::maskAccount($acc);
        }

        return $accounts;
    }

    /**
     * Retrieves an account by ID with decrypted secrets (INTERNAL USE ONLY).
     */
    public static function getAccount(string $accountId): ?array
    {
        $data = self::load();
        if (empty($data['accounts'][$accountId])) {
            return null;
        }

        $acc = $data['accounts'][$accountId];
        $creds = $acc['credentials'] ?? [];
        $decryptedCreds = [];

        foreach ($creds as $k => $v) {
            if (is_string($v) && str_starts_with($v, 'ENC:')) {
                $decryptedCreds[$k] = self::decrypt($v);
            } else {
                $decryptedCreds[$k] = $v;
            }
        }

        $acc['credentials'] = $decryptedCreds;
        return $acc;
    }

    /**
     * Saves or updates a provider account. Encrypts sensitive fields automatically.
     */
    public static function saveAccount(array $input): array
    {
        self::load();
        $id = trim((string)($input['id'] ?? ''));
        if (empty($id)) {
            $id = 'acc_' . bin2hex(random_bytes(4));
        }

        $existing = self::$vaultData['accounts'][$id] ?? null;
        $provider = strtolower(trim((string)($input['provider'] ?? ($existing['provider'] ?? ''))));
        $name = trim((string)($input['name'] ?? ($existing['name'] ?? ucfirst($provider) . ' Account')));

        if (empty($provider)) {
            return ['success' => false, 'error' => 'Provider identifier is required.'];
        }

        $inputCreds = (array)($input['credentials'] ?? []);
        $existingCreds = $existing['credentials'] ?? [];
        $finalCreds = [];

        $sensitiveFields = ['api_token', 'secret_access_key', 'private_key', 'api_key', 'password', 'token'];

        foreach ($inputCreds as $k => $v) {
            $val = trim((string)$v);
            // If user supplied masked value '••••', retain existing secret
            if (str_contains($val, '••••') || str_contains($val, '...')) {
                if (!empty($existingCreds[$k])) {
                    $finalCreds[$k] = $existingCreds[$k];
                }
            } else {
                if (in_array($k, $sensitiveFields, true) && !empty($val)) {
                    $finalCreds[$k] = self::encrypt($val);
                } else {
                    $finalCreds[$k] = $val;
                }
            }
        }

        // Retain unedited existing fields
        foreach ($existingCreds as $k => $v) {
            if (!array_key_exists($k, $finalCreds)) {
                $finalCreds[$k] = $v;
            }
        }

        $accountRecord = [
            'id' => $id,
            'name' => $name,
            'provider' => $provider,
            'credentials' => $finalCreds,
            'read_only' => !empty($input['read_only']),
            'settings' => (array)($input['settings'] ?? ($existing['settings'] ?? [])),
            'allowed_zones' => (array)($input['allowed_zones'] ?? ($existing['allowed_zones'] ?? [])),
            'created_at' => $existing['created_at'] ?? date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'last_verified_at' => $existing['last_verified_at'] ?? null
        ];

        self::$vaultData['accounts'][$id] = $accountRecord;
        self::persist();

        return [
            'success' => true,
            'message' => "Provider account '{$name}' saved securely.",
            'account' => self::maskAccount($accountRecord)
        ];
    }

    /**
     * Deletes a provider account.
     */
    public static function deleteAccount(string $accountId): array
    {
        self::load();
        if (empty(self::$vaultData['accounts'][$accountId])) {
            return ['success' => false, 'error' => "Account '{$accountId}' not found."];
        }

        unset(self::$vaultData['accounts'][$accountId]);
        self::persist();

        return ['success' => true, 'message' => "Account '{$accountId}' deleted."];
    }

    /**
     * Updates verification status timestamp.
     */
    public static function recordVerification(string $accountId, bool $success): void
    {
        self::load();
        if (!empty(self::$vaultData['accounts'][$accountId])) {
            self::$vaultData['accounts'][$accountId]['last_verified_at'] = date('Y-m-d H:i:s');
            self::$vaultData['accounts'][$accountId]['verification_status'] = $success ? 'verified' : 'failed';
            self::persist();
        }
    }

    /**
     * Redacts secrets in an account data structure for safe frontend display.
     */
    public static function maskAccount(array $account): array
    {
        $masked = $account;
        $creds = $account['credentials'] ?? [];
        $maskedCreds = [];

        $sensitiveFields = ['api_token', 'secret_access_key', 'private_key', 'api_key', 'password', 'token'];

        foreach ($creds as $k => $v) {
            if (in_array($k, $sensitiveFields, true)) {
                $raw = is_string($v) ? (str_starts_with($v, 'ENC:') ? self::decrypt($v) : $v) : '';
                $len = strlen($raw);
                if ($len > 8) {
                    $maskedCreds[$k] = substr($raw, 0, 4) . '••••' . substr($raw, -4);
                } else {
                    $maskedCreds[$k] = !empty($raw) ? '••••••••' : '';
                }
                $maskedCreds['has_' . $k] = !empty($raw);
            } else {
                $maskedCreds[$k] = $v;
            }
        }

        $masked['credentials'] = $maskedCreds;
        return $masked;
    }
}
