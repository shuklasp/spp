<?php

namespace SPPMod\SPPAuth;

class MFA
{
    /**
     * Generate a random base32 string for the 2FA secret.
     */
    public static function generateSecret(int $length = 16): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $chars[random_int(0, 31)];
        }
        return $secret;
    }

    /**
     * Verify a 6-digit TOTP code against a secret.
     */
    public static function verifyCode(string $secret, string $code, int $discrepancy = 1): bool
    {
        $currentTimeSlice = floor(time() / 30);
        
        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculatedCode = self::getCode($secret, $currentTimeSlice + $i);
            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Generate a TOTP code.
     */
    private static function getCode(string $secret, int $timeSlice): string
    {
        $secretKey = self::base32Decode($secret);
        
        // Pack time into 8 bytes (64 bit)
        $time = pack('N*', 0) . pack('N*', $timeSlice);
        
        // Generate HMAC-SHA1
        $hash = hash_hmac('sha1', $time, $secretKey, true);
        
        // Extract 4 bytes based on offset
        $offset = ord(substr($hash, -1)) & 0x0F;
        $hashPart = substr($hash, $offset, 4);
        
        // Get numeric value
        $value = unpack('N', $hashPart)[1];
        $value = $value & 0x7FFFFFFF;
        
        // Modulo 1 million for 6 digits
        $modulo = pow(10, 6);
        return str_pad($value % $modulo, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Decode a base32 string.
     */
    private static function base32Decode(string $secret): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $decoded = '';
        $buffer = 0;
        $bufferBits = 0;
        
        $secret = strtoupper($secret);
        $len = strlen($secret);
        
        for ($i = 0; $i < $len; $i++) {
            $char = $secret[$i];
            $val = strpos($chars, $char);
            if ($val === false) continue;
            
            $buffer = ($buffer << 5) | $val;
            $bufferBits += 5;
            
            if ($bufferBits >= 8) {
                $bufferBits -= 8;
                $decoded .= chr(($buffer >> $bufferBits) & 0xFF);
            }
        }
        return $decoded;
    }
}
