<?php
namespace SPPMod\Contrib\AppDrivers;

use SPPMod\SPPIntegrations\AbstractDriver;

/**
 * Class WordPressDriver
 * 
 * Integrates with WordPress using its built-in REST API.
 */
class WordPressDriver extends AbstractDriver
{
    protected function initialize(): void
    {
        if (!isset($this->config['base_url'])) {
            throw new \Exception("WordPress driver requires a 'base_url' in config.");
        }
    }

    private function getHeaders(): array
    {
        $headers = ['Content-Type: application/json'];
        
        // Application Passwords or Basic Auth plugin
        if (isset($this->config['username']) && isset($this->config['password'])) {
            $headers[] = 'Authorization: Basic ' . base64_encode($this->config['username'] . ':' . $this->config['password']);
        } elseif (isset($this->config['token'])) {
            $headers[] = 'Authorization: Bearer ' . $this->config['token'];
        }

        return $headers;
    }

    public function syncUser(array $userData): bool
    {
        if (isset($this->config['local_path'])) {
            return $this->syncUserNative($userData);
        }

        $endpoint = $this->config['base_url'] . '/wp-json/wp/v2/users';
        
        $payload = [
            'username' => $userData['username'] ?? '',
            'email'    => $userData['email'] ?? '',
            'password' => bin2hex(random_bytes(10)), // WP requires a password, generate random if syncing
            'roles'    => ['subscriber']
        ];

        $response = $this->makeHttpRequest($endpoint, 'POST', $payload, $this->getHeaders());
        
        // If user already exists, WP returns 400. In a real integration, we might want to catch that and do a PUT instead.
        return $response['success'];
    }

    private function syncUserNative(array $userData): bool
    {
        $path = $this->config['local_path'];
        $jsonPayload = escapeshellarg(json_encode($userData));
        $script = "
            require_once '{$path}/wp-load.php';
            \$data = json_decode({$jsonPayload}, true);
            if (!username_exists(\$data['username']) && !email_exists(\$data['email'])) {
                wp_insert_user([
                    'user_login' => \$data['username'],
                    'user_email' => \$data['email'],
                    'user_pass'  => wp_generate_password(),
                    'role'       => 'subscriber'
                ]);
                echo 'SUCCESS';
            } else {
                echo 'EXISTS';
            }
        ";
        $output = shell_exec('php -r ' . escapeshellarg($script));
        return strpos($output, 'SUCCESS') !== false || strpos($output, 'EXISTS') !== false;
    }

    public function loginUser(array $userData): bool
    {
        if (!isset($this->config['local_path'])) {
            return false; // Can only do magical SSO with local path
        }
        
        $path = $this->config['local_path'];
        $username = escapeshellarg($userData['username']);
        // We write the auth cookie directly using WP's hashing
        $script = "
            require_once '{$path}/wp-load.php';
            \$user = get_user_by('login', {$username});
            if (\$user) {
                wp_set_auth_cookie(\$user->ID, true);
                echo 'SUCCESS';
            }
        ";
        $output = shell_exec('php -r ' . escapeshellarg($script));
        return strpos($output, 'SUCCESS') !== false;
    }

    public function fetchData(string $endpoint): array
    {
        $url = $this->config['base_url'] . '/wp-json/wp/v2/' . ltrim($endpoint, '/');
        $response = $this->makeHttpRequest($url, 'GET', [], $this->getHeaders());
        return $response['success'] ? $response['data'] : [];
    }

    public function pushEvent(string $eventName, array $payload): bool
    {
        // Custom REST endpoint in WP to receive webhooks
        $endpoint = $this->config['base_url'] . '/wp-json/spp/v1/webhook';
        
        $response = $this->makeHttpRequest($endpoint, 'POST', [
            'event' => $eventName,
            'data' => $payload
        ], $this->getHeaders());
        
        return $response['success'];
    }
}
