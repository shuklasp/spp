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
        $la->setData([
            'id' => $user->getId(),
            'username' => $user->username,
            'email' => $user->email,
            'role' => "Developer"
        ]);
    } catch (\Exception $e) {
        $la->setStatus('error')->notify("Profile fetch failed: " . $e->getMessage());
    }
}
