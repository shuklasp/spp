<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class AdminAuthCommand extends Command
{
    protected string $name = 'admin:auth';
    protected string $description = 'Manage Admin Auth operations. Usage: admin:auth <action> [--payload=...] [--json]';

    public function isHidden(): bool { return true; }

    public function execute(array $args): void
    {
        $action = $this->getArgument($args, 0) ?? 'default';
        $payloadRaw = $this->getOption($args, 'payload', '{}');
        $payload = json_decode($payloadRaw, true) ?: [];

        $methodName = 'handle' . str_replace(' ', '', ucwords(str_replace('_', ' ', $action)));
        if (method_exists($this, $methodName)) {
            $this->$methodName($payload, $args);
        } else {
            $this->json(['success' => false, 'error' => "Unknown action: $action"], $args);
        }
    }

    private function handleLogin(array $payload, array $args): void {

    $username = trim($payload['username'] ?? '');
    $password = $payload['password'] ?? '';

    if (empty($username) || empty($password)) {
        $this->json(['success' => false, 'error' => "Username and password are required."], $args); return;
    }

    try {
        require_once SPP_MODULES_DIR . '/spp/sppauth/class.sppauth.php';

        $success = \SPPMod\SPPAuth\SPPAuth::login($username, $password);

        if ($success) {
            // \SPP\SPPSession::regenerateId(true);
            $_SESSION['spp_admin_user'] = $username;
            \SPP\SPPSession::setSessionVar('__sppauth_user__', $username);
            $this->json(['user' => $username], $args); return;
        } else {
            $this->json(['success' => false, 'error' => "Invalid username or password."], $args); return;
        }
    } catch (\Exception $e) {
        $msg = $e->getMessage();
        if (str_starts_with($msg, 'MFA_REQUIRED:')) {
            $token = explode(':', $msg)[1];
            $this->json(['mfa_challenge' => $token, 'user' => $username], $args); return;
        } else {
            $this->json(['success' => false, 'error' => "Authentication error: " . $msg], $args); return;
        }
    }

    }

    private function handleVerifymfa(array $payload, array $args): void {

    try {
        require_once SPP_MODULES_DIR . '/spp/sppauth/class.sppauth.php';
        require_once SPP_MODULES_DIR . '/spp/sppauth/class.mfa.php';

        $token = $payload['challenge_token'] ?? '';
        $code = $payload['code'] ?? '';

        $sessionToken = \SPP\SPPSession::getSessionVar('mfa_challenge_token');
        $sessionUserId = \SPP\SPPSession::getSessionVar('mfa_challenge_user');

        if (!$token || $token !== $sessionToken || !$sessionUserId) {
            $this->json(['success' => false, 'error' => "Invalid or expired MFA session."], $args); return;
        }

        $user = new \SPPMod\SPPAuth\SPPUser($sessionUserId);
        $secret = $user->get('mfa_secret');

        if (\SPPMod\SPPAuth\MFA::verifyCode($secret, $code)) {
            // Success! Complete login.
            \SPP\SPPSession::regenerateId(true);
            \SPPMod\SPPAuth\SPPAuth::guard('web')->login($user);
            \SPPMod\SPPAuth\AuditLogger::log('login_success_mfa', $user->id);

            $_SESSION['spp_admin_user'] = $user->username;
            \SPP\SPPSession::setSessionVar('__sppauth_user__', $user->username);

            // Clean up challenge
            \SPP\SPPSession::unsetSessionVar('mfa_challenge_token');
            \SPP\SPPSession::unsetSessionVar('mfa_challenge_user');

            $this->json(['user' => $user->username], $args); return;
        } else {
            $this->json(['success' => false, 'error' => "Invalid Authenticator Code."], $args); return;
        }
    } catch (\Exception $e) {
        $this->json(['success' => false, 'error' => "MFA error: " . $e->getMessage()], $args); return;
    }

    }

    private function handleSendmagiclink(array $payload, array $args): void {

    try {
        $email = $payload['email'] ?? '';
        if (empty($email)) {
            $this->json(['success' => false, 'error' => "Email is required."], $args); return;
        }

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
        }

        $this->json(['success' => true, 'message' => "If an account exists for $email, a magic link has been sent."], $args); return;
    } catch (\Exception $e) {
        $this->json(['success' => false, 'error' => "Error: " . $e->getMessage()], $args); return;
    }

    }

    private function handleConsumemagiclink(array $payload, array $args): void {

    try {
        $token = $payload['token'] ?? '';
        if (empty($token)) {
            $this->json(['success' => false, 'error' => "Invalid token."], $args); return;
        }

        require_once SPP_MODULES_DIR . '/spp/sppauth/class.sppauth.php';
        require_once SPP_MODULES_DIR . '/spp/sppauth/class.magiclink.php';

        $user = \SPPMod\SPPAuth\MagicLink::consumeToken($token);

        if ($user) {
            \SPPMod\SPPAuth\SPPAuth::guard('web')->login($user);
            $_SESSION['spp_admin_user'] = $user->username;
            \SPP\SPPSession::setSessionVar('__sppauth_user__', $user->username);

            $this->json(['user' => $user->username], $args); return;
        } else {
            $this->json(['success' => false, 'error' => "Link is invalid or has expired."], $args); return;
        }
    } catch (\Exception $e) {
        $this->json(['success' => false, 'error' => "Error: " . $e->getMessage()], $args); return;
    }

    }

    private function handleLogout(array $payload, array $args): void {

    require_once SPP_MODULES_DIR . '/spp/sppauth/class.sppauth.php';
    if (isset($_SESSION['spp_admin_fallback'])) {
        unset($_SESSION['spp_admin_fallback']);
    }
    \SPPMod\SPPAuth\SPPAuth::logout();
    $this->json(['success' => true, 'message' => "Logged out successfully.", 'redirect' => "index.php"], $args); return;

    }

    private function handleProfile(array $payload, array $args): void {

    try {
        if (isset($_SESSION['spp_admin_fallback'])) {
            $this->json([
                'id' => 0,
                'username' => 'admin',
                'email' => 'system@spp.local',
                'role' => 'System Administrator'
            ], $args); return;
        }

        $username = null;
        if (\SPP\SPPSession::sessionVarExists('__sppauth_user__')) {
            $username = \SPP\SPPSession::getSessionVar('__sppauth_user__');
        } elseif (isset($_SESSION['spp_admin_user'])) {
            $username = $_SESSION['spp_admin_user'];
        }

        if (!$username) {
            $this->json(['success' => false, 'error' => "No authenticated user found."], $args); return;
        }

        $user = new \SPPMod\SPPAuth\SPPUser($username);

        $this->json($user->getValues(), $args); return;
    } catch (\Exception $e) {
        $this->json(['success' => false, 'error' => "Profile fetch failed: " . $e->getMessage()], $args); return;
    }

    }

    private function handleListapikeys(array $payload, array $args): void {

    try {
        $db = new \SPPMod\SPPDB\SPPDB();
        if (!$db->tableExists('api_keys')) {
            $this->json([], $args); return;
        }
        $keys = $db->execute_query("SELECT id, name, token, status, created_at, expires_at FROM api_keys ORDER BY created_at DESC");
        $this->json($keys, $args); return;
    } catch (\Exception $e) {
        $this->json(['success' => false, 'error' => "Failed to list API keys: " . $e->getMessage()], $args); return;
    }

    }

    private function handleGenerateapikey(array $payload, array $args): void {

    try {
        $name = trim($payload['name'] ?? '');
        if (empty($name)) {
            $this->json(['success' => false, 'error' => "API Key name is required."], $args); return;
        }
        $token = bin2hex(random_bytes(32));
        $id = uniqid();
        $db = new \SPPMod\SPPDB\SPPDB();

        $db->execute_query(
            "INSERT INTO api_keys (id, name, token, status, created_at) VALUES (?, ?, ?, 1, NOW())",
            [$id, $name, $token]
        );
        $this->executeClientCode("app.apiKeys.loadKeys()");
        $this->json(['success' => true, 'message' => "API Key generated for '{$name}'."], $args); return;
    } catch (\Exception $e) {
        $this->json(['success' => false, 'error' => "Failed to generate API key: " . $e->getMessage()], $args); return;
    }

    }

    private function handleRevokeapikey(array $payload, array $args): void {

    try {
        $id = $payload['id'] ?? '';
        if (empty($id)) {
            $this->json(['success' => false, 'error' => "API Key ID is required."], $args); return;
        }
        $db = new \SPPMod\SPPDB\SPPDB();
        $db->execute_query("UPDATE api_keys SET status = 0 WHERE id = ?", [$id]);
        $this->executeClientCode("app.apiKeys.loadKeys()");
        $this->json(['success' => true, 'message' => "API Key revoked."], $args); return;
    } catch (\Exception $e) {
        $this->json(['success' => false, 'error' => "Failed to revoke API key: " . $e->getMessage()], $args); return;
    }

    }

}
