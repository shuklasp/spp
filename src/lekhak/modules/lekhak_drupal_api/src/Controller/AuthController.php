<?php
namespace Lekhak\Modules\LekhakDrupalApi\Controller;

use SPPMod\SPPDB\SPPDB;
use SPPMod\SPPAuth\SPPAuth;

class AuthController
{

    public function login()
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        $uname = $data['name'] ?? '';
        $passwd = $data['pass'] ?? '';

        if (empty($uname) || empty($passwd)) {
            http_response_code(400);
            return json_encode(["message" => "Missing credentials"]);
        }

        $db = new SPPDB();
        $user = $db->execute_query("SELECT * FROM users WHERE username = ? AND password = ?", [$uname, $passwd]);

        if (empty($user)) {
            http_response_code(403);
            return json_encode(["message" => "Sorry, unrecognized username or password."]);
        }

        // Establish session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['uid'] = $user[0]['id'];
        $_SESSION['uname'] = $user[0]['username'];

        // Return standard Drupal format
        return json_encode([
            "current_user" => [
                "uid" => (string) $user[0]['id'],
                "roles" => ["authenticated"],
                "name" => $user[0]['username']
            ],
            "csrf_token" => "dummy-token-for-lekhak",
            "logout_token" => "dummy-logout-token"
        ], JSON_UNESCAPED_SLASHES);
    }
}
