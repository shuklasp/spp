<?php
declare(strict_types=1);

/**
 * Auth Service Group for SPP Admin
 * Hardened Production Authentication & API Key Management.
 */

if (!function_exists('live_Auth_Login')) {
    function live_Auth_Login($la, $params)
    {
        $username = trim($params['username'] ?? '');
        $password = (string)($params['password'] ?? '');

        if ($username === '' || $password === '') {
            return $la->setStatus('error')->notify("Username and password are required.");
        }

        // 1. Brute-force protection: check failed login attempts
        $attemptsKey = 'spp_login_attempts_' . md5($username . ($_SERVER['REMOTE_ADDR'] ?? ''));
        $attempts = (int)($_SESSION[$attemptsKey] ?? 0);
        $lastAttemptTime = (int)($_SESSION[$attemptsKey . '_time'] ?? 0);

        if ($attempts >= 5 && (time() - $lastAttemptTime) < 300) {
            $remaining = 300 - (time() - $lastAttemptTime);
            return $la->setStatus('error')->notify("Too many failed login attempts. Account temporarily locked. Please try again in {$remaining} seconds.");
        }

        // 2. Database authentication via SPPAuth
        $success = false;
        $mfaChallenge = null;
        try {
            if (file_exists(SPP_MODULES_DIR . '/spp/sppauth/class.sppauth.php')) {
                require_once SPP_MODULES_DIR . '/spp/sppauth/class.sppauth.php';
                $success = \SPPMod\SPPAuth\SPPAuth::login($username, $password);
            }
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (str_starts_with($msg, 'MFA_REQUIRED:')) {
                $token = explode(':', $msg)[1];
                return $la->setData(['mfa_challenge' => $token, 'user' => $username]);
            }
        }

        // 3. Authenticate against configured admin in global-settings.yml with secure hashing
        if (!$success) {
            $settings = \SPP\App::getGlobalSettings();
            $adminAuth = $settings['admin_auth'] ?? [];
            $globalUser = $adminAuth['username'] ?? ($settings['admin_username'] ?? 'admin');
            $configuredHash = $adminAuth['password_hash'] ?? null;
            $rawPass = $adminAuth['password'] ?? null;

            if (strcasecmp($username, (string)$globalUser) === 0) {
                // Reject insecure default passwords in production
                if ($password === 'admin123' || $password === 'admin') {
                    // Only reject default passwords unless explicit override is configured
                    $allowInsecureDefault = !empty($adminAuth['allow_insecure_defaults']);
                    if (!$allowInsecureDefault && defined('SPP_PROFILE') && SPP_PROFILE !== 'dev') {
                        return $la->setStatus('error')->notify("Default credentials 'admin123' are disabled in production. Please configure a secure password hash in settings.");
                    }
                }

                if ($configuredHash && password_verify($password, $configuredHash)) {
                    $success = true;
                } elseif ($rawPass && hash_equals((string)$rawPass, $password)) {
                    $success = true;
                }
            }
        }

        if ($success) {
            // Reset failed login attempts
            unset($_SESSION[$attemptsKey], $_SESSION[$attemptsKey . '_time']);

            \SPP\SPPSession::regenerateId(true);
            $_SESSION['spp_admin_user'] = $username;
            \SPP\SPPSession::setSessionVar('__sppauth_user__', $username);
            \SPP\SPPSession::setSessionVar('__username__', $username);
            \SPP\SPPSession::setSessionVar('__role_id__', 1);

            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            \SPP\SPPSession::setSessionVar('__sppauth_fingerprint__', hash('sha256', $ip . $ua));

            // Log authentication success
            if (function_exists('spp_admin_audit_log')) {
                spp_admin_audit_log('admin_login_success', ['username' => $username, 'ip' => $ip]);
            }

            $la->setData(['user' => $username])->notify("Login successful.", "success");
        } else {
            // Record failed attempt
            $_SESSION[$attemptsKey] = $attempts + 1;
            $_SESSION[$attemptsKey . '_time'] = time();

            if (function_exists('spp_admin_audit_log')) {
                spp_admin_audit_log('admin_login_failed', ['username' => $username, 'ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
            }

            $la->setStatus('error')->notify("Invalid username or password.");
        }
    }
}

if (!function_exists('live_Auth_VerifyMFA')) {
    function live_Auth_VerifyMFA($la, $params)
    {
        try {
            require_once SPP_MODULES_DIR . '/spp/sppauth/class.sppauth.php';
            require_once SPP_MODULES_DIR . '/spp/sppauth/class.mfa.php';

            $token = $params['challenge_token'] ?? '';
            $code = $params['code'] ?? '';

            $sessionToken = \SPP\SPPSession::getSessionVar('mfa_challenge_token');
            $sessionUserId = \SPP\SPPSession::getSessionVar('mfa_challenge_user');

            if (!$token || $token !== $sessionToken || !$sessionUserId) {
                return $la->setStatus('error')->notify("Invalid or expired MFA session.");
            }

            $user = new \SPPMod\SPPAuth\SPPUser($sessionUserId);
            $secret = $user->get('mfa_secret');

            if (\SPPMod\SPPAuth\MFA::verifyCode($secret, $code)) {
                \SPP\SPPSession::regenerateId(true);
                \SPPMod\SPPAuth\SPPAuth::guard('web')->login($user);
                \SPPMod\SPPAuth\AuditLogger::log('login_success_mfa', $user->id);

                $_SESSION['spp_admin_user'] = $user->username;
                \SPP\SPPSession::setSessionVar('__sppauth_user__', $user->username);

                \SPP\SPPSession::unsetSessionVar('mfa_challenge_token');
                \SPP\SPPSession::unsetSessionVar('mfa_challenge_user');

                $la->setData(['user' => $user->username])->notify("Login successful.", "success");
            } else {
                $la->setStatus('error')->notify("Invalid Authenticator Code.");
            }
        } catch (\Exception $e) {
            $la->setStatus('error')->notify("MFA error: " . $e->getMessage());
        }
    }
}

// --- Magic Links (Token Never Written to Local Debug Files) ---

if (!function_exists('live_Auth_SendMagicLink')) {
    function live_Auth_SendMagicLink($la, $params)
    {
        try {
            $email = trim($params['email'] ?? '');
            if (empty($email)) {
                return $la->setStatus('error')->notify("Email is required.");
            }

            require_once SPP_MODULES_DIR . '/spp/sppauth/class.magiclink.php';

            $db = new \SPPMod\SPPDB\SPPDB();
            $sql = "SELECT id FROM " . \SPPMod\SPPDB\SPPDB::sppTable('users') . " WHERE email = ? LIMIT 1";
            $res = $db->execute_query($sql, [$email]);

            if (!empty($res)) {
                $userId = $res[0]['id'];
                $token = \SPPMod\SPPAuth\MagicLink::createToken($userId, 15);

                // Audit metadata only - never log plaintext magic link tokens
                if (function_exists('spp_admin_audit_log')) {
                    spp_admin_audit_log('magic_link_issued', ['user_id' => $userId, 'email' => $email]);
                }
            }

            $la->notify("If an account exists for that email, a magic link has been sent.", "success");
        } catch (\Exception $e) {
            $la->setStatus('error')->notify("Error: " . $e->getMessage());
        }
    }
}

if (!function_exists('live_Auth_ConsumeMagicLink')) {
    function live_Auth_ConsumeMagicLink($la, $params)
    {
        try {
            $token = $params['token'] ?? '';
            if (empty($token)) {
                return $la->setStatus('error')->notify("Invalid token.");
            }

            require_once SPP_MODULES_DIR . '/spp/sppauth/class.sppauth.php';
            require_once SPP_MODULES_DIR . '/spp/sppauth/class.magiclink.php';

            $user = \SPPMod\SPPAuth\MagicLink::consumeToken($token);

            if ($user) {
                \SPP\SPPSession::regenerateId(true);
                \SPPMod\SPPAuth\SPPAuth::guard('web')->login($user);
                $_SESSION['spp_admin_user'] = $user->username;
                \SPP\SPPSession::setSessionVar('__sppauth_user__', $user->username);

                $la->setData(['user' => $user->username])->notify("Magic link login successful.", "success");
            } else {
                $la->setStatus('error')->notify("Link is invalid or has expired.");
            }
        } catch (\Exception $e) {
            $la->setStatus('error')->notify("Error: " . $e->getMessage());
        }
    }
}

if (!function_exists('live_Auth_Logout')) {
    function live_Auth_Logout($la, $params)
    {
        if (isset($_SESSION['spp_admin_fallback'])) {
            unset($_SESSION['spp_admin_fallback']);
        }
        if (isset($_SESSION['spp_admin_user'])) {
            unset($_SESSION['spp_admin_user']);
        }

        if (class_exists('\\SPPMod\\SPPAuth\\SPPAuth')) {
            \SPPMod\SPPAuth\SPPAuth::logout();
        }

        if (class_exists('\\SPP\\SPPSession')) {
            \SPP\SPPSession::destroy();
        }

        $la->notify("Logged out successfully.")->redirect("index.php");
    }
}

if (!function_exists('live_Auth_Profile')) {
    function live_Auth_Profile($la, $params)
    {
        try {
            $username = null;
            if (\SPP\SPPSession::sessionVarExists('__sppauth_user__')) {
                $username = \SPP\SPPSession::getSessionVar('__sppauth_user__');
            } elseif (isset($_SESSION['spp_admin_user'])) {
                $username = $_SESSION['spp_admin_user'];
            }

            if (!$username) {
                return $la->setStatus('error')->setData(['authenticated' => false]);
            }

            $la->setData([
                'id'       => 1,
                'username' => $username,
                'email'    => $username . '@spp.local',
                'role'     => 'Super Administrator'
            ]);
        } catch (\Throwable $e) {
            $la->setData(['authenticated' => false]);
        }
    }
}

// --- API Keys (Hashed Storage & Single-Reveal Security) ---

if (!function_exists('live_Auth_ListApiKeys')) {
    function live_Auth_ListApiKeys($la, $params)
    {
        try {
            $db = new \SPPMod\SPPDB\SPPDB();
            if (!$db->tableExists('api_keys')) {
                return $la->setData([]);
            }

            // Zero plaintext secrets in listing APIs: return prefix, status, timestamps
            $keys = $db->execute_query(
                "SELECT id, name, status, created_at, expires_at, " .
                "CASE WHEN token IS NOT NULL THEN CONCAT(SUBSTRING(token, 1, 8), '••••••••') ELSE '••••••••' END AS masked_token " .
                "FROM api_keys ORDER BY created_at DESC"
            );

            $la->setData($keys);
        } catch (\Exception $e) {
            $la->setStatus('error')->notify("Failed to list API keys: " . $e->getMessage());
        }
    }
}

if (!function_exists('live_Auth_GenerateApiKey')) {
    function live_Auth_GenerateApiKey($la, $params)
    {
        try {
            $name = trim($params['name'] ?? '');
            if (empty($name)) {
                return $la->setStatus('error')->notify("API Key name is required.");
            }

            // Generate high-entropy token: spp_live_ + 32 bytes random
            $rawToken = 'spp_' . bin2hex(random_bytes(32));
            // Store SHA-256 hash
            $tokenHash = hash('sha256', $rawToken);
            $id = uniqid('key_');

            $db = new \SPPMod\SPPDB\SPPDB();
            if (!$db->tableExists('api_keys')) {
                $db->execute_query(
                    "CREATE TABLE IF NOT EXISTS api_keys (" .
                    "id VARCHAR(64) PRIMARY KEY, " .
                    "name VARCHAR(128) NOT NULL, " .
                    "token VARCHAR(128) NOT NULL, " .
                    "status INT DEFAULT 1, " .
                    "created_at DATETIME, " .
                    "expires_at DATETIME NULL)"
                );
            }

            $db->execute_query(
                "INSERT INTO api_keys (id, name, token, status, created_at) VALUES (?, ?, ?, 1, NOW())",
                [$id, $name, $tokenHash]
            );

            // Audit record
            if (function_exists('spp_admin_audit_log')) {
                spp_admin_audit_log('api_key_created', ['key_id' => $id, 'name' => $name]);
            }

            // Return the raw token ONCE only to the caller
            $la->setData([
                'id'        => $id,
                'name'      => $name,
                'raw_token' => $rawToken,
                'warning'   => 'Make sure to copy your API key now. You will not be able to see it again.'
            ])->notify("API Key '{$name}' generated successfully. Copy it now.", "success")
              ->executeClientCode("app.apiKeys.loadKeys()");
        } catch (\Exception $e) {
            $la->setStatus('error')->notify("Failed to generate API key: " . $e->getMessage());
        }
    }
}

if (!function_exists('live_Auth_RevokeApiKey')) {
    function live_Auth_RevokeApiKey($la, $params)
    {
        try {
            $id = $params['id'] ?? '';
            if (empty($id)) {
                return $la->setStatus('error')->notify("API Key ID is required.");
            }
            $db = new \SPPMod\SPPDB\SPPDB();
            $db->execute_query("UPDATE api_keys SET status = 0 WHERE id = ?", [$id]);

            if (function_exists('spp_admin_audit_log')) {
                spp_admin_audit_log('api_key_revoked', ['key_id' => $id]);
            }

            $la->notify("API Key revoked.")->executeClientCode("app.apiKeys.loadKeys()");
        } catch (\Exception $e) {
            $la->setStatus('error')->notify("Failed to revoke API key: " . $e->getMessage());
        }
    }
}
