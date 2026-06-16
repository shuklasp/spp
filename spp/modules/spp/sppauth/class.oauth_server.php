<?php

namespace SPPMod\SPPAuth;

use SPPMod\SPPDB\SPPDB;

/**
 * class OAuthServer
 *
 * Skeleton implementation to act as an OAuth 2.0 Identity Provider.
 * Allows third-party apps to authenticate users against SPPAuth.
 */
class OAuthServer
{
    /**
     * Step 1: Handle the /authorize endpoint
     */
    public function authorize(string $clientId, string $redirectUri, string $state)
    {
        // 1. Verify $clientId exists and $redirectUri matches what's registered
        $db = new SPPDB();
        $sql = "SELECT id, name FROM " . SPPDB::sppTable('oauth_clients') . " WHERE id = ? AND redirect_uri = ?";
        $client = $db->execute_query($sql, [$clientId, $redirectUri]);

        if (empty($client)) {
            echo "<!DOCTYPE html><html><head><title>Invalid Request</title>";
            echo "<style>body{font-family:sans-serif;text-align:center;padding:50px;}</style></head>";
            echo "<body><h2>OAuth Error</h2><p>Invalid client_id or redirect_uri.</p></body></html>";
            exit;
        }

        $clientName = $client[0]['name'];

        // 2. Ensure user is logged in
        $guard = new WebGuard();
        if (!$guard->check()) {
            // Redirect to login page, then back here
            header("Location: /login?redirect=" . urlencode($_SERVER['REQUEST_URI']));
            exit;
        }

        $userId = $guard->id();

        // 3. Handle Consent Submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['consent'])) {
            if ($_POST['consent'] === 'allow') {
                // Generate Authorization Code
                $authCode = bin2hex(random_bytes(16));
                $expiresAt = date('Y-m-d H:i:s', strtotime('+5 minutes'));

                $db->execute_query("INSERT INTO " . SPPDB::sppTable('oauth_auth_codes') . " (id, client_id, user_id, redirect_uri, expires_at) VALUES (?, ?, ?, ?, ?)", [
                    $authCode, $clientId, $userId, $redirectUri, $expiresAt
                ]);

                header("Location: {$redirectUri}?code={$authCode}&state={$state}");
                exit;
            } else {
                header("Location: {$redirectUri}?error=access_denied&state={$state}");
                exit;
            }
        }

        // 4. Display Consent Screen (HTML)
        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>Authorize App</title>
            <style>
                body { font-family: -apple-system, sans-serif; background: #f3f4f6; display:flex; align-items:center; justify-content:center; height:100vh; margin:0; }
                .card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); width: 100%; max-width: 400px; text-align: center; }
                .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; margin: 5px; }
                .btn-allow { background: #3b82f6; color: white; }
                .btn-deny { background: #ef4444; color: white; }
            </style>
        </head>
        <body>
            <div class='card'>
                <h2>Authorize {$clientName}</h2>
                <p><strong>{$clientName}</strong> wants to access your account profile and perform actions on your behalf.</p>
                <form method='POST'>
                    <button type='submit' name='consent' value='allow' class='btn btn-allow'>Allow Access</button>
                    <button type='submit' name='consent' value='deny' class='btn btn-deny'>Deny</button>
                </form>
            </div>
        </body>
        </html>";
        exit;
    }

    /**
     * Step 2: Handle the /token endpoint
     */
    public function issueToken(string $clientId, string $clientSecret, string $code)
    {
        $db = new SPPDB();
        
        // 1. Verify client credentials
        $client = $db->execute_query("SELECT id FROM " . SPPDB::sppTable('oauth_clients') . " WHERE id = ? AND secret = ?", [$clientId, $clientSecret]);
        if (empty($client)) {
            http_response_code(401);
            echo json_encode(['error' => 'invalid_client']);
            exit;
        }

        // 2. Verify Authorization Code
        $authCode = $db->execute_query("SELECT user_id, redirect_uri FROM " . SPPDB::sppTable('oauth_auth_codes') . " WHERE id = ? AND client_id = ? AND expires_at > NOW()", [$code, $clientId]);
        if (empty($authCode)) {
            http_response_code(400);
            echo json_encode(['error' => 'invalid_grant']);
            exit;
        }

        $userId = $authCode[0]['user_id'];

        // Delete used auth code
        $db->execute_query("DELETE FROM " . SPPDB::sppTable('oauth_auth_codes') . " WHERE id = ?", [$code]);

        // 3. Issue Access Token
        $accessToken = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $db->execute_query("INSERT INTO " . SPPDB::sppTable('oauth_tokens') . " (access_token, client_id, user_id, expires_at) VALUES (?, ?, ?, ?)", [
            $accessToken, $clientId, $userId, $expiresAt
        ]);

        header('Content-Type: application/json');
        echo json_encode([
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => 3600
        ]);
        exit;
    }
}
