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
        require_once SPP_MODULES_DIR . '/spp/sppauth/class.sppuser.php';
        
        $user = new \SPPMod\SPPAuth\SPPUser($username);
        if (!$user->getId()) {
            return $la->setStatus('error')->notify("Invalid username or password.");
        }

        if (password_verify($password, $user->password)) {
            $_SESSION['spp_admin_user'] = $username;
            \SPP\SPPSession::setSessionVar('__sppauth_user__', $username);
            
            $la->setData(['user' => $username])
               ->notify("Login successful.", "success");
        } else {
            $la->setStatus('error')->notify("Invalid username or password.");
        }
    } catch (\Exception $e) {
        $la->setStatus('error')->notify("Authentication error: " . $e->getMessage());
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
