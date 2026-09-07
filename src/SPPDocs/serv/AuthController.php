<?php
namespace App\SPPDocs\Serv;

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
class AuthController
{
    public function loginForm()
    {
        $blade = \SPPMod\Drishyam\SPPBlade::getInstance();
        return $blade->renderInstance('login', [
            'app_name' => 'SPPDocs',
            'base_url' => \SPP\App::getBaseUrl('SPPDocs'),
            'error' => ''
        ]);
    }

    public function registerForm()
    {
        $blade = \SPPMod\Drishyam\SPPBlade::getInstance();
        return $blade->renderInstance('register', [
            'app_name' => 'SPPDocs',
            'base_url' => \SPP\App::getBaseUrl('SPPDocs'),
            'error' => '',
            'success' => ''
        ]);
    }

    public function register()
    {
        $error = '';
        $success = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $password_confirm = $_POST['password_confirm'] ?? '';
            $redirect = trim($_POST['redirect'] ?? '');

            if (empty($username) || empty($password)) {
                $error = 'All fields are required.';
            } elseif ($password !== $password_confirm) {
                $error = 'Passwords do not match.';
            } else {
                try {
                    if (!function_exists('get_xdb')) {
                        throw new \Exception('SPPXDB module is not loaded. Cannot use XDB for registration.');
                    }
                    
                    $xdb = get_xdb('auth', 'users');
                    
                    // Check if user already exists
                    $existing = $xdb->where('username', $username)->get();
                    if (!empty($existing)) {
                        $error = 'Username is already taken.';
                    } else {
                        // Create the user
                        $xdb->insert([
                            'username' => $username,
                            'password' => password_hash($password, PASSWORD_DEFAULT),
                            'role' => 'registered',
                            'created_at' => time()
                        ]);
                        $success = 'Account created successfully! You can now log in.';
                    }
                } catch (\Exception $e) {
                    $error = 'Registration failed: ' . $e->getMessage();
                }
            }
        }

        $blade = \SPPMod\Drishyam\SPPBlade::getInstance();
        return $blade->renderInstance('register', [
            'app_name' => 'SPPDocs',
            'base_url' => \SPP\App::getBaseUrl('SPPDocs'),
            'error' => $error,
            'success' => $success
        ]);
    }

    public function login()
    {
        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $redirect = trim($_POST['redirect'] ?? '');

            if (!empty($username) && !empty($password)) {
                try {
                    $authenticatedUser = null;
                    
                    // 1. Try XDB Authentication first
                    if (function_exists('get_xdb')) {
                        $xdb = get_xdb('auth', 'users');
                        $users = $xdb->where('username', $username)->get();
                        
                        if (!empty($users)) {
                            $userRow = array_values($users)[0]; // Get first match
                            if (password_verify($password, $userRow['password'])) {
                                $authenticatedUser = (object)[
                                    'id' => $userRow['id'] ?? $username,
                                    'username' => $username,
                                    'role' => $userRow['role'] ?? 'registered'
                                ];
                            }
                        }
                    }
                    
                    // 2. Fallback to Demo Credentials (if not found in XDB)
                    if (!$authenticatedUser && $username === 'admin' && ($password === 'admin' || $password === 'password')) {
                        $authenticatedUser = (object)[
                            'id' => 'admin',
                            'username' => 'admin',
                            'role' => 'admin'
                        ];
                    }

                    if ($authenticatedUser) {
                        \SPPMod\SPPAuth\SPPAuth::guard('web')->login($authenticatedUser);

                        // Also set SPPDocs session variables for admin panel compatibility
                        if (session_status() === PHP_SESSION_NONE) {
                            session_start();
                        }
                        $_SESSION['sppdocs_admin_auth'] = true;
                        $_SESSION['sppdocs_user'] = $authenticatedUser->username;
                        $_SESSION['sppdocs_role'] = $authenticatedUser->role;
                        $_SESSION['sppdocs_csrf'] = bin2hex(random_bytes(32));

                        // Redirect back to calling page, or dashboard if none specified
                        if (!empty($redirect) && str_starts_with($redirect, '/')) {
                            header('Location: ' . $redirect);
                        } else {
                            header('Location: ' . \SPP\App::url('home', 'SPPDocs'));
                        }
                        exit;
                    } else {
                        $error = 'Invalid username or password.';
                    }
                } catch (\Exception $e) {
                    $error = 'Auth error: ' . $e->getMessage();
                }
            }
        }

        $blade = \SPPMod\Drishyam\SPPBlade::getInstance();
        return $blade->renderInstance('login', [
            'app_name' => 'SPPDocs',
            'base_url' => \SPP\App::getBaseUrl('SPPDocs'),
            'error' => $error
        ]);
    }

    public function logout()
    {
        if (class_exists('\SPPMod\SPPAuth\SPPAuth')) {
            \SPPMod\SPPAuth\SPPAuth::guard('web')->logout();
        }
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        unset($_SESSION['sppdocs_admin_auth'], $_SESSION['sppdocs_user'], $_SESSION['sppdocs_role']);
        header('Location: ' . \SPP\App::url('home', 'SPPDocs'));
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

        // API login with XDB support
        $authenticated = false;
        
        if (function_exists('get_xdb')) {
            $xdb = get_xdb('auth', 'users');
            $users = $xdb->where('username', $username)->get();
            if (!empty($users)) {
                $userRow = array_values($users)[0];
                if (password_verify($password, $userRow['password'])) {
                    $authenticated = true;
                }
            }
        }
        
        if (!$authenticated && $username === 'admin' && $password === 'admin') {
            $authenticated = true;
        }

        if ($authenticated) {
            echo json_encode([
                'status' => 'ok',
                'token' => bin2hex(random_bytes(32)),
                'user' => ['id' => 1, 'username' => $username]
            ]);
        } else {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Invalid credentials']);
        }
    }
}