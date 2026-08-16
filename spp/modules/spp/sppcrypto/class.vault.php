<?php
namespace SPPMod\SPPCrypto;

/**
 * Class Vault
 * 
 * The SPP Secure Key Vault.
 * Manages encryption and decryption of application secrets.
 */
class Vault
{
    /**
     * Gets the master cipher key (read from secure SPP config, not DB).
     */
    private static function getMasterKey(): string
    {
        // Mocking reading from spp/etc/config.php
        return 'spp-webos-master-encryption-key-256bit';
    }

    /**
     * Encrypts a secret value.
     */
    public static function encryptSecret(string $plainText): string
    {
        $key = hash('sha256', self::getMasterKey(), true);
        if (function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')) {
            $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
            $cipher = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plainText, '', $nonce, $key);
            return 'SODIUM_[' . base64_encode($nonce . $cipher) . ']';
        } elseif (function_exists('openssl_encrypt')) {
            $iv = random_bytes(openssl_cipher_iv_length('aes-256-gcm'));
            $cipher = openssl_encrypt($plainText, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
            return 'OSSL_[' . base64_encode($iv . $tag . $cipher) . ']';
        }
        throw new \Exception("No secure encryption available");
    }

    /**
     * Decrypts a secret value.
     */
    public static function decryptSecret(string $cipherText): string
    {
        $key = hash('sha256', self::getMasterKey(), true);
        if (strpos($cipherText, 'SODIUM_[') === 0) {
            $base64 = substr($cipherText, 8, -1);
            $decoded = base64_decode($base64);
            $nonceLen = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
            $nonce = substr($decoded, 0, $nonceLen);
            $cipher = substr($decoded, $nonceLen);
            $plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($cipher, '', $nonce, $key);
            if ($plain === false) throw new \Exception("Decryption failed");
            return $plain;
        } elseif (strpos($cipherText, 'OSSL_[') === 0) {
            $base64 = substr($cipherText, 6, -1);
            $decoded = base64_decode($base64);
            $ivLen = openssl_cipher_iv_length('aes-256-gcm');
            $iv = substr($decoded, 0, $ivLen);
            $tag = substr($decoded, $ivLen, 16);
            $cipher = substr($decoded, $ivLen + 16);
            $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
            if ($plain === false) throw new \Exception("Decryption failed");
            return $plain;
        } elseif (strpos($cipherText, 'ENCRYPTED_[') === 0) {
            $base64 = substr($cipherText, 11, -1);
            return base64_decode($base64);
        }
        return $cipherText;
    }

    /**
     * Retrieves all secrets for a specific guest app and formats them as a .env string.
     */
    public static function synthesizeEnvFile(string $appAlias): string
    {
        // In reality, this queries the Vault DB tables for the app's keys.
        $mockVaultDb = [
            'wordpress:blog' => [
                'STRIPE_API_KEY' => 'ENCRYPTED_[c2tfdGVzdF9tb2NrX3N0cmlwZV9rZXk=]',
                'AWS_ACCESS_KEY' => 'ENCRYPTED_[QUtJQVRFU1RNT0NLT0s=]'
            ]
        ];

        if (!isset($mockVaultDb[$appAlias])) {
            return '';
        }

        $envString = '';
        foreach ($mockVaultDb[$appAlias] as $key => $cipherText) {
            $plainText = self::decryptSecret($cipherText);
            $envString .= "$key=$plainText\n";
        }

        return $envString;
    }
}
