<?php

namespace SPPMod\SPPXDB;

trait XDB_Encryption
{
    public function setEncryptedFields($fields)
    {
        $this->encryptedFields = $fields;
        return $this;
    }

    protected function getEncryptionKey()
    {
        return hash_pbkdf2('sha256', $this->encryptionKey, 'spp-xdb-salt', 10000, 32, true);
    }

    protected function encrypt($value)
    {
        if (empty($value)) {
            return $value;
        }
        $key = $this->getEncryptionKey();
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-gcm'));
        $tag = '';
        $encrypted = openssl_encrypt($value, 'aes-256-gcm', $key, 0, $iv, $tag);
        return base64_encode($iv . $tag . $encrypted);
    }

    public function decrypt($value)
    {
        if (empty($value)) {
            return $value;
        }
        $data = base64_decode($value);
        $key = $this->getEncryptionKey();
        $ivSize = openssl_cipher_iv_length('aes-256-gcm');
        $tagSize = 16;
        
        // If data is too short for GCM, fallback to legacy CBC
        if (strlen($data) <= $ivSize + $tagSize) {
            $legacyIvSize = openssl_cipher_iv_length('aes-256-cbc');
            $iv = substr($data, 0, $legacyIvSize);
            $encrypted = substr($data, $legacyIvSize);
            return openssl_decrypt($encrypted, 'aes-256-cbc', $this->encryptionKey, 0, $iv);
        }

        $iv = substr($data, 0, $ivSize);
        $tag = substr($data, $ivSize, $tagSize);
        $encrypted = substr($data, $ivSize + $tagSize);
        
        $decrypted = openssl_decrypt($encrypted, 'aes-256-gcm', $key, 0, $iv, $tag);
        if ($decrypted === false) {
            // Legacy fallback if decryption fails (might be an old CBC payload that happened to be long)
            $legacyIvSize = openssl_cipher_iv_length('aes-256-cbc');
            $iv = substr($data, 0, $legacyIvSize);
            $encrypted = substr($data, $legacyIvSize);
            return openssl_decrypt($encrypted, 'aes-256-cbc', $this->encryptionKey, 0, $iv);
        }
        return $decrypted;
    }

}
