<?php
namespace App\Samvaad\Serv;

/**
 * ============================================================================
 * AuthController — Login / Logout with SPPAuth
 * ============================================================================
 *
 * HOW AUTHENTICATION WORKS:
 * SPPAuth provides session-based authentication with guards.
 * Guards define different auth strategies (web, api, etc.)
 *
 * KEY METHODS:
 *   SPPAuth::guard('web')->login($user)        — Create session
 *   SPPAuth::guard('web')->logout()             — Destroy session
 *   SPPAuth::authSessionExists()                — Check if logged in
 *   SPPAuth::guard('web')->user()               — Get current user
 *
 * IN BLADE TEMPLATES:
 *   @sppauth ... @endsppauth                   — Show if authenticated
 *   @sppguest ... @endsppguest                 — Show if guest
 * ============================================================================
 */
class AuthController extends \SPPMod\SPPView\ViewController
{
    public function loginForm()
    {
        return $this->render('login', [
            'app_name' => 'Samvaad',
            'base_url' => \SPP\App::getBaseUrl('Samvaad'),
            'error' => ''
        ]);
    }

    public function login()
    {
        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            if (!empty($username) && !empty($password)) {
                try {
                    // Demo credential check — replace with real auth
                    if ($username === 'admin' && ($password === 'admin' || $password === 'password')) {
                        $user = (object)['id' => 'admin', 'username' => 'admin', 'email' => 'admin@localhost'];
                        \SPPMod\SPPAuth\SPPAuth::guard('web')->login($user);
                        header('Location: ' . \SPP\App::url('dashboard', 'Samvaad'));
                        exit;
                    } else {
                        $error = 'Invalid credentials.';
                    }
                } catch (\Exception $e) {
                    $error = 'Auth error: ' . $e->getMessage();
                }
            }
        }

        return $this->render('login', [
            'app_name' => 'Samvaad',
            'base_url' => \SPP\App::getBaseUrl('Samvaad'),
            'error' => $error
        ]);
    }

    public function logout()
    {
        if (class_exists('\SPPMod\SPPAuth\SPPAuth')) {
            \SPPMod\SPPAuth\SPPAuth::guard('web')->logout();
        }
        header('Location: ' . \SPP\App::url('home', 'Samvaad'));
        exit;
    }

    /**
     * API Login — returns JSON token (for API mode)
     */
    public function apiLogin()
    {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'POST required']);
            return;
        }

        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        // Demo auth — replace with real validation
        if ($username === 'admin' && $password === 'admin') {
            echo json_encode([
                'status' => 'ok',
                'token' => bin2hex(random_bytes(32)),
                'user' => ['id' => 1, 'username' => 'admin']
            ]);
        } else {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Invalid credentials']);
        }
    }
}