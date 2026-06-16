<?php
declare(strict_types=1);

namespace SPPMod\SPPAPI;

if (class_exists('\\SPP\\SPPEvent')) {
    \SPP\SPPEvent::listen('api.auth.token_request', function(\SPP\EventParams $params) {
        // Fire verify credentials to allow auth providers to validate
        \SPP\SPPEvent::fireEvent('auth.verify_credentials', $params);

        // If authenticated successfully, generate the JWT token
        if ($params->get('authenticated') && $params->get('user_id')) {
            $secret = \SPP\Module::getConfig('jwt_secret', 'sppapi') ?: getenv('SPP_JWT_SECRET');
            if (!$secret || $secret === 'auto-generated-secret-key-change-me' || $secret === 'default-secret') {
                throw new \RuntimeException('JWT secret is not configured.');
            }
            $expires = \SPP\Module::getConfig('jwt_expires_in', 'sppapi') ?: 3600;
            
            $payload = [
                'user_id' => $params->get('user_id'),
                'username' => $params->get('username')
            ];

            if (!class_exists('\\SPPMod\\SPPAPI\\JWTAuth')) {
                require_once __DIR__ . '/src/JWTAuth.php';
            }

            $token = \SPPMod\SPPAPI\JWTAuth::encode($payload, $secret, (int)$expires);
            
            $params->set('token', $token);
            $params->set('expires_in', (int)$expires);
        }
    });

    \SPP\SPPEvent::listen('api.auth.verify_token', function(\SPP\EventParams $params) {
        if ($params->get('is_valid')) return; // Already validated

        $configVal2 = \SPP\Module::getConfig('enable_jwt', 'sppapi');
        $enableJwt2 = $configVal2 === true || $configVal2 === 'true' || $configVal2 === '1' || $configVal2 === 1;

        if ($enableJwt2) {
            if (!class_exists('\\SPPMod\\SPPAPI\\JWTAuth')) {
                require_once __DIR__ . '/src/JWTAuth.php';
            }
            $secret = \SPP\Module::getConfig('jwt_secret', 'sppapi') ?: getenv('SPP_JWT_SECRET');
            if (!$secret || $secret === 'auto-generated-secret-key-change-me' || $secret === 'default-secret') {
                throw new \RuntimeException('JWT secret is not configured.');
            }
            $payload = \SPPMod\SPPAPI\JWTAuth::decode($params->get('token'), $secret);
            if ($payload !== false) {
                $params->set('is_valid', true);
            }
        }

        // 2. Check Permanent API Key
        if (!$params->get('is_valid')) {
            if (class_exists('\\SPPMod\\SPPDB\\SPPDB')) {
                $db = new \SPPMod\SPPDB\SPPDB();
                if ($db->tableExists('api_keys')) {
                    $token = $params->get('token');
                    $keys = $db->execute_query("SELECT id, status, expires_at FROM api_keys WHERE token = ? LIMIT 1", [$token]);
                    if (!empty($keys)) {
                        $key = $keys[0];
                        if ($key['status'] == 1 && (empty($key['expires_at']) || strtotime($key['expires_at']) > time())) {
                            $params->set('is_valid', true);
                        }
                    }
                }
            }
        }
    });
}
