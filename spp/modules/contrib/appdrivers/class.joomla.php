<?php
namespace SPPMod\Contrib\AppDrivers;

use SPPMod\SPPIntegrations\AbstractDriver;

/**
 * Class JoomlaDriver
 * 
 * Integrates with Joomla using its core Web Services API (Joomla 4+).
 */
class JoomlaDriver extends AbstractDriver
{
    protected function initialize(): void
    {
        if (!isset($this->config['base_url'])) {
            throw new \Exception("Joomla driver requires a 'base_url' in config.");
        }
        if (!isset($this->config['token'])) {
            throw new \Exception("Joomla driver requires a Super User API 'token' in config.");
        }
    }

    private function getHeaders(): array
    {
        return [
            'Content-Type: application/json',
            'X-Joomla-Token: ' . $this->config['token']
        ];
    }

    public function syncUser(array $userData): bool
    {
        if (isset($this->config['local_path'])) {
            return $this->syncUserNative($userData);
        }

        $endpoint = $this->config['base_url'] . '/api/index.php/v1/users';
        
        $payload = [
            'name'     => $userData['name'] ?? $userData['username'],
            'username' => $userData['username'] ?? '',
            'email'    => $userData['email'] ?? '',
            'password' => bin2hex(random_bytes(10)),
            'groups'   => [2] // Registered user group
        ];

        $response = $this->makeHttpRequest($endpoint, 'POST', $payload, $this->getHeaders());
        return $response['success'];
    }

    private function syncUserNative(array $userData): bool
    {
        $path = $this->config['local_path'];
        $jsonPayload = escapeshellarg(json_encode($userData));
        $script = "
            define('_JEXEC', 1);
            define('JPATH_BASE', '{$path}');
            require_once JPATH_BASE . '/includes/defines.php';
            require_once JPATH_BASE . '/includes/framework.php';
            \$app = JFactory::getApplication('site');

            \$data = json_decode({$jsonPayload}, true);
            
            \$user = new JUser();
            \$user->load(['username' => \$data['username']]);
            if (!\$user->id) {
                \$user->name = \$data['name'] ?? \$data['username'];
                \$user->username = \$data['username'];
                \$user->email = \$data['email'];
                \$user->password = bin2hex(random_bytes(10));
                \$user->groups = [2];
                if (\$user->save()) {
                    echo 'SUCCESS';
                }
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
            return false;
        }

        $path = $this->config['local_path'];
        $username = escapeshellarg($userData['username']);
        $script = "
            define('_JEXEC', 1);
            define('JPATH_BASE', '{$path}');
            require_once JPATH_BASE . '/includes/defines.php';
            require_once JPATH_BASE . '/includes/framework.php';
            \$app = JFactory::getApplication('site');

            \$user = new JUser();
            \$user->load(['username' => {$username}]);
            
            if (\$user->id) {
                // Joomla login forcing
                \$app->login(['username' => \$user->username], ['silent' => true]);
                echo 'SUCCESS';
            }
        ";
        $output = shell_exec('php -r ' . escapeshellarg($script));
        return strpos($output, 'SUCCESS') !== false;
    }

    public function fetchData(string $endpoint): array
    {
        $url = $this->config['base_url'] . '/api/index.php/v1/' . ltrim($endpoint, '/');
        $response = $this->makeHttpRequest($url, 'GET', [], $this->getHeaders());
        return $response['success'] ? $response['data'] : [];
    }

    public function pushEvent(string $eventName, array $payload): bool
    {
        // Custom component endpoint for Joomla to receive events
        $endpoint = $this->config['base_url'] . '/api/index.php/v1/spp_webhook';
        
        $response = $this->makeHttpRequest($endpoint, 'POST', [
            'event' => $eventName,
            'data' => $payload
        ], $this->getHeaders());
        
        return $response['success'];
    }
}
