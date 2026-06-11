<?php

namespace SPPMod\SPPAPI;

/**
 * JWTAuth
 * Lightweight, dependency-free utility to encode and decode JSON Web Tokens using HMAC SHA256.
 */
class JWTAuth
{
    /**
     * Base64Url Encode
     */
    private static function base64UrlEncode(string $data): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    /**
     * Base64Url Decode
     */
    private static function base64UrlDecode(string $data): string
    {
        $b64 = str_replace(['-', '_'], ['+', '/'], $data);
        if ($pad = strlen($b64) % 4) {
            $b64 .= str_repeat('=', 4 - $pad);
        }
        return base64_decode($b64);
    }

    /**
     * Generate a signed JWT
     * 
     * @param array $payload Data to embed in the token.
     * @param string $secret HMAC signature secret.
     * @param int $expiration Expiration time in seconds from now.
     * @return string Signed JWT token.
     */
    public static function encode(array $payload, string $secret, int $expiration = 3600): string
    {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        
        $payload['iat'] = time();
        $payload['exp'] = time() + $expiration;
        $payloadJson = json_encode($payload);

        $base64UrlHeader = self::base64UrlEncode($header);
        $base64UrlPayload = self::base64UrlEncode($payloadJson);

        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret, true);
        $base64UrlSignature = self::base64UrlEncode($signature);

        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    /**
     * Decode and verify a signed JWT
     * 
     * @param string $token The JWT token.
     * @param string $secret HMAC signature secret.
     * @return array|false Payload array if valid, false if invalid or expired.
     */
    public static function decode(string $token, string $secret)
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        list($header, $payload, $signature) = $parts;

        // Verify Signature
        $expectedSignature = hash_hmac('sha256', $header . "." . $payload, $secret, true);
        $expectedBase64UrlSignature = self::base64UrlEncode($expectedSignature);

        if (!hash_equals($expectedBase64UrlSignature, $signature)) {
            return false;
        }

        // Decode Payload
        $payloadData = json_decode(self::base64UrlDecode($payload), true);
        if (!$payloadData) {
            return false;
        }

        // Verify Expiration
        if (isset($payloadData['exp']) && $payloadData['exp'] < time()) {
            return false; // Expired
        }

        return $payloadData;
    }
}
