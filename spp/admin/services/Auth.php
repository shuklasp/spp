<?php
/**
 * Auth Service Group for SPP Admin
 */

function live_Auth_Login($la, $params) {
    $username = trim($params['username'] ?? '');
    $password = $params['password'] ?? '';

    if (empty($username) || empty($password)) {
        return $la->setStatus('error')->notify("Username and password are required.");
    }

    try {
        require_once SPP_MODULES_DIR . '/spp/sppauth/class.sppauth.php';
        
        $success = \SPPMod\SPPAuth\SPPAuth::login($username, $password);
        
        if ($success) {
            $_SESSION['spp_admin_user'] = $username;
            \SPP\SPPSession::setSessionVar('__sppauth_user__', $username);
            $la->setData(['user' => $username])->notify("Login successful.", "success");
        } else {
            $la->setStatus('error')->notify("Invalid username or password.");
        }
    } catch (\Exception $e) {
        $msg = $e->getMessage();
        if (str_starts_with($msg, 'MFA_REQUIRED:')) {
            $token = explode(':', $msg)[1];
            $la->setData(['mfa_challenge' => $token, 'user' => $username]);
        } else {
            $la->setStatus('error')->notify("Authentication error: " . $msg);
        }
    }
}

function live_Auth_VerifyMFA($la, $params) {
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
            // Success! Complete login.
            \SPPMod\SPPAuth\SPPAuth::guard('web')->login($user);
            \SPPMod\SPPAuth\AuditLogger::log('login_success_mfa', $user->id);
            
            $_SESSION['spp_admin_user'] = $user->username;
            \SPP\SPPSession::setSessionVar('__sppauth_user__', $user->username);
            
            // Clean up challenge
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

// --- Magic Links ---

function live_Auth_SendMagicLink($la, $params) {
    try {
        $email = $params['email'] ?? '';
        if (empty($email)) return $la->setStatus('error')->notify("Email is required.");
        
        require_once SPP_MODULES_DIR . '/spp/sppauth/class.magiclink.php';
        
        $db = new \SPPMod\SPPDB\SPPDB();
        $sql = "SELECT id FROM " . \SPPMod\SPPDB\SPPDB::sppTable('users') . " WHERE email = ? LIMIT 1";
        $res = $db->execute_query($sql, [$email]);
        
        // We always return success to prevent email enumeration attacks
        if (!empty($res)) {
            $userId = $res[0]['id'];
            $token = \SPPMod\SPPAuth\MagicLink::createToken($userId, 15);
            // In a real system, send email via SPPMailer.
            // For now, log it.
            $logFile = 'C:/projects/apache/school1/scratch/auth_debug.log';
            file_put_contents($logFile, "[MAGIC LINK] Generated for $email: $token\n", FILE_APPEND);
        }
        
        $la->notify("If an account exists for $email, a magic link has been sent.", "success");
    } catch (\Exception $e) {
        $la->setStatus('error')->notify("Error: " . $e->getMessage());
    }
}

function live_Auth_ConsumeMagicLink($la, $params) {
    try {
        $token = $params['token'] ?? '';
        if (empty($token)) return $la->setStatus('error')->notify("Invalid token.");
        
        require_once SPP_MODULES_DIR . '/spp/sppauth/class.sppauth.php';
        require_once SPP_MODULES_DIR . '/spp/sppauth/class.magiclink.php';
        
        $user = \SPPMod\SPPAuth\MagicLink::consumeToken($token);
        
        if ($user) {
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

function live_Auth_Logout($la, $params) {
    if (isset($_SESSION['spp_admin_fallback'])) {
        unset($_SESSION['spp_admin_fallback']);
    }
    \SPPMod\SPPAuth\SPPAuth::logout();
    $la->notify("Logged out successfully.")->redirect("index.php");
}

function live_Auth_Profile($la, $params) {
    try {
        if (isset($_SESSION['spp_admin_fallback'])) {
            return $la->setData([
                'id' => 0,
                'username' => 'admin',
                'email' => 'system@spp.local',
                'role' => 'System Administrator'
            ]);
        }

        $username = null;
        if (\SPP\SPPSession::sessionVarExists('__sppauth_user__')) {
            $username = \SPP\SPPSession::getSessionVar('__sppauth_user__');
        } elseif (isset($_SESSION['spp_admin_user'])) {
            $username = $_SESSION['spp_admin_user'];
        }

        if (!$username) {
            return $la->setStatus('error');
        }
        
        $user = new \SPPMod\SPPAuth\SPPUser($username);
        $logFile = 'C:/projects/apache/school1/scratch/auth_debug.log';
        $debugInfo = "[SPP ADMIN] Profile for $username: ID=" . $user->getId() . " UNAME=" . $user->get('username') . " EMAIL=" . $user->get('email') . " VALUES=" . json_encode($user->getValues()) . "\n";
        file_put_contents($logFile, $debugInfo, FILE_APPEND);
        
        $la->setData($user->getValues());
    } catch (\Exception $e) {
        $la->setStatus('error')->notify("Profile fetch failed: " . $e->getMessage());
    }
}

function live_Auth_ListApiKeys($la, $params) {
    try {
        $db = new \SPPMod\SPPDB\SPPDB();
        if (!$db->tableExists('api_keys')) {
            return $la->setData([]);
        }
        $keys = $db->execute_query("SELECT id, name, token, status, created_at, expires_at FROM api_keys ORDER BY created_at DESC");
        $la->setData($keys);
    } catch (\Exception $e) {
        $la->setStatus('error')->notify("Failed to list API keys: " . $e->getMessage());
    }
}

function live_Auth_GenerateApiKey($la, $params) {
    try {
        $name = trim($params['name'] ?? '');
        if (empty($name)) {
            return $la->setStatus('error')->notify("API Key name is required.");
        }
        $token = bin2hex(random_bytes(32));
        $id = uniqid();
        $db = new \SPPMod\SPPDB\SPPDB();
        
        $db->execute_query(
            "INSERT INTO api_keys (id, name, token, status, created_at) VALUES (?, ?, ?, 1, NOW())",
            [$id, $name, $token]
        );
        $la->notify("API Key generated for '{$name}'.")->executeClientCode("app.apiKeys.loadKeys()");
    } catch (\Exception $e) {
        $la->setStatus('error')->notify("Failed to generate API key: " . $e->getMessage());
    }
}

function live_Auth_RevokeApiKey($la, $params) {
    try {
        $id = $params['id'] ?? '';
        if (empty($id)) {
            return $la->setStatus('error')->notify("API Key ID is required.");
        }
        $db = new \SPPMod\SPPDB\SPPDB();
        $db->execute_query("UPDATE api_keys SET status = 0 WHERE id = ?", [$id]);
        $la->notify("API Key revoked.")->executeClientCode("app.apiKeys.loadKeys()");
    } catch (\Exception $e) {
        $la->setStatus('error')->notify("Failed to revoke API key: " . $e->getMessage());
    }
}
