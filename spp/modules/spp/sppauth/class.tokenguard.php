<?php

namespace SPPMod\SPPAuth;

use SPPMod\SPPDB\SPPDB;

/**
 * class TokenGuard
 *
 * Driver for stateless API authentication via Opaque Bearer Tokens.
 */
class TokenGuard implements GuardInterface
{
    private ?object $user = null;
    private ?string $currentToken = null;

    public function check(): bool
    {
        return $this->user() && !($this->user() instanceof AnonymousUser);
    }

    public function can(string $permission): bool
    {
        // For API tokens, we often check token scopes. 
        // For simplicity here, we delegate to the user's main permissions.
        $user = $this->user();
        if (!$user || $user instanceof AnonymousUser) {
            return false;
        }

        // Ideally cache perms or delegate to user->can() if we add it.
        // Assuming webguard's RBAC applies to APIs too for now.
        // Since TokenGuard is stateless, we rebuild or check DB directly.
        $webGuard = new WebGuard();
        $webGuard->login($user); // Temporary context switch for RBAC check
        return $webGuard->can($permission);
    }

    public function user()
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $token = $this->getBearerToken();
        if (!$token) {
            $this->user = new AnonymousUser();
            return $this->user;
        }

        $db = new SPPDB();
        $sql = "SELECT userid FROM " . SPPDB::sppTable('personal_access_tokens') . " 
                WHERE token = ? AND (expires_at IS NULL OR expires_at > NOW())";
        $res = $db->execute_query($sql, [hash('sha256', $token)]);

        if (!empty($res)) {
            $this->currentToken = $token;
            try {
                $this->user = new SPPUser($res[0]['userid']);
            } catch (\Exception $e) {
                $this->user = new AnonymousUser();
            }
        } else {
            $this->user = new AnonymousUser();
        }

        return $this->user;
    }

    public function id()
    {
        $user = $this->user();
        return $user ? $user->id : null;
    }

    public function login($user, bool $remember = false)
    {
        // Stateless guards don't log in via session. 
        // They just set the user instance for the current request.
        $this->user = $user;
    }

    public function logout()
    {
        // For TokenGuard, logout means revoking the current token.
        if ($this->currentToken) {
            $db = new SPPDB();
            $sql = "DELETE FROM " . SPPDB::sppTable('personal_access_tokens') . " WHERE token = ?";
            $db->execute_query($sql, [hash('sha256', $this->currentToken)]);
        }
        $this->user = new AnonymousUser();
        $this->currentToken = null;
    }

    /**
     * Generate a new Personal Access Token for a user.
     */
    public static function createToken(SPPUser $user, string $name = 'API Key', ?string $expiresAt = null): string
    {
        $plainToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $plainToken);

        $db = new SPPDB();
        
        // Ensure name column exists gracefully
        try {
            $db->execute_query('ALTER TABLE ' . SPPDB::sppTable('personal_access_tokens') . ' ADD COLUMN name VARCHAR(255) DEFAULT "API Key"');
        } catch (\Exception $e) {}

        $db->insertValues(SPPDB::sppTable('personal_access_tokens'), [
            'name' => $name,
            'token' => $hashedToken,
            'userid' => $user->getId(),
            'expires_at' => $expiresAt
        ]);

        return $plainToken;
    }

    private function getBearerToken(): ?string
    {
        $headers = null;
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER["Authorization"]);
        } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) { // Nginx or fast CGI
            $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }

        if (!empty($headers)) {
            if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                return $matches[1];
            }
        }
        return null;
    }
}
