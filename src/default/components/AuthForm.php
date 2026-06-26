<?php
namespace App\Default\Components;

use SPPMod\SPPAuth\SPPUser;

class AuthForm
{
    private array $props = [];

    public function __construct(array $props = [])
    {
        $this->props = $props;
    }

    private function getProp(string $key, $default = null)
    {
        return $this->props[$key] ?? $default;
    }
    public function render(): string
    {
        $mode = $this->getProp('mode', 'login');
        $error = $this->getProp('error', '');
        $success = $this->getProp('success', '');

        $isPost = $_SERVER['REQUEST_METHOD'] === 'POST';

        if ($isPost) {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $email = $_POST['email'] ?? '';
            $mode = $_POST['mode'] ?? $mode;

            if ($mode === 'register') {
                try {
                    SPPUser::saveUserInfo([
                        'username' => $username,
                        'email' => $email,
                        'password' => $password,
                        'status' => 'active'
                    ]);
                    $success = "Registration successful! You can now login.";
                    $mode = 'login';
                } catch (\Exception $e) {
                    $error = "Registration failed: " . $e->getMessage();
                }
            } else {
                if (SPPUser::verifyUserPassword($username, $password)) {
                    $success = "Login successful!";
                    $user = new SPPUser($username);
                    \SPPMod\SPPAuth\SPPAuth::guard('web')->login($user);
                    header("Location: " . \SPP\App::getBaseUrl('default') . "/dashboard");
                    exit;
                } else {
                    $error = "Invalid username or password.";
                }
            }
        }

        $title = $mode === 'login' ? 'Login to your account' : 'Register a new account';
        $toggleText = $mode === 'login' ? "Don't have an account? Register" : "Already have an account? Login";
        $toggleMode = $mode === 'login' ? 'register' : 'login';

        $html = "
            <div class=\"container py-5 h-100\">
                <div class=\"row d-flex justify-content-center align-items-center h-100\">
                    <div class=\"col-12 col-md-8 col-lg-6 col-xl-5\">
                        <div class=\"card shadow-2-strong\" style=\"border-radius: 1rem;\">
                            <div class=\"card-body p-5 text-center\">

                                <h3 class=\"mb-5\">{$title}</h3>

                                " . ($error ? "<div class=\"alert alert-danger\">{$error}</div>" : "") . "
                                " . ($success ? "<div class=\"alert alert-success\">{$success}</div>" : "") . "

                                <form method=\"POST\" action=\"\">
                                    <input type=\"hidden\" name=\"mode\" value=\"{$mode}\" />
                                    <div class=\"form-outline mb-4\">
                                        <input type=\"text\" name=\"username\" id=\"username\" class=\"form-control form-control-lg\" required />
                                        <label class=\"form-label\" for=\"username\">Username</label>
                                    </div>

                                    " . ($mode === 'register' ? "
                                    <div class=\"form-outline mb-4\">
                                        <input type=\"email\" name=\"email\" id=\"email\" class=\"form-control form-control-lg\" required />
                                        <label class=\"form-label\" for=\"email\">Email</label>
                                    </div>
                                    " : "") . "

                                    <div class=\"form-outline mb-4\">
                                        <input type=\"password\" name=\"password\" id=\"password\" class=\"form-control form-control-lg\" required />
                                        <label class=\"form-label\" for=\"password\">Password</label>
                                    </div>

                                    <button class=\"btn btn-primary btn-lg btn-block\" type=\"submit\">" . ucfirst($mode) . "</button>
                                </form>

                                <hr class=\"my-4\">

                                <a href=\"?mode={$toggleMode}\">{$toggleText}</a>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        ";

        return $html;
    }
}
