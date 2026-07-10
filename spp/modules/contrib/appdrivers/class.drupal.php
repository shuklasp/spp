<?php
namespace SPPMod\Contrib\AppDrivers;

use SPPMod\SPPIntegrations\AbstractDriver;

/**
 * Class DrupalDriver
 * 
 * Integrates with Drupal using its core JSON:API or RESTful Web Services.
 */
class DrupalDriver extends AbstractDriver
{
    protected function initialize(): void
    {
        if (!isset($this->config['base_url'])) {
            throw new \Exception("Drupal driver requires a 'base_url' in config.");
        }
    }

    public function syncUser(array $userData): bool
    {
        if (isset($this->config['local_path'])) {
            return $this->syncUserNative($userData);
        }

        $endpoint = $this->config['base_url'] . '/jsonapi/user/user';
        
        $headers = [
            'Accept: application/vnd.api+json',
            'Content-Type: application/vnd.api+json'
        ];

        // Basic auth or Bearer token if provided
        if (isset($this->config['token'])) {
            $headers[] = 'Authorization: Bearer ' . $this->config['token'];
        } elseif (isset($this->config['username']) && isset($this->config['password'])) {
            $headers[] = 'Authorization: Basic ' . base64_encode($this->config['username'] . ':' . $this->config['password']);
        }

        // Drupal JSON:API specific payload structure
        $payload = [
            'data' => [
                'type' => 'user--user',
                'attributes' => [
                    'name' => $userData['username'] ?? '',
                    'mail' => $userData['email'] ?? '',
                    'status' => true
                ]
            ]
        ];

        $response = $this->makeHttpRequest($endpoint, 'POST', $payload, $headers);
        return $response['success'];
    }

    private function syncUserNative(array $userData): bool
    {
        $path = $this->config['local_path'];
        $jsonPayload = escapeshellarg(json_encode($userData));
        $script = "
            use Drupal\Core\DrupalKernel;
            use Symfony\Component\HttpFoundation\Request;
            
            \$autoloader = require_once '{$path}/autoload.php';
            \$request = Request::createFromGlobals();
            \$kernel = DrupalKernel::createFromRequest(\$request, \$autoloader, 'prod');
            \$kernel->boot();

            \$data = json_decode({$jsonPayload}, true);
            
            \$users = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['name' => \$data['username']]);
            if (empty(\$users)) {
                \$user = \Drupal\user\Entity\User::create();
                \$user->setPassword(bin2hex(random_bytes(10)));
                \$user->enforceIsNew();
                \$user->setEmail(\$data['email']);
                \$user->setUsername(\$data['username']);
                \$user->activate();
                \$user->save();
                echo 'SUCCESS';
            }
        ";
        $output = shell_exec('php -r ' . escapeshellarg($script));
        return strpos($output, 'SUCCESS') !== false;
    }

    public function loginUser(array $userData): bool
    {
        if (!isset($this->config['local_path'])) {
            return false;
        }

        $path = $this->config['local_path'];
        $username = escapeshellarg($userData['username']);
        $script = "
            use Drupal\Core\DrupalKernel;
            use Symfony\Component\HttpFoundation\Request;
            
            \$autoloader = require_once '{$path}/autoload.php';
            \$request = Request::createFromGlobals();
            \$kernel = DrupalKernel::createFromRequest(\$request, \$autoloader, 'prod');
            \$kernel->boot();

            \$users = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['name' => {$username}]);
            if (!empty(\$users)) {
                \$user = reset(\$users);
                user_login_finalize(\$user);
                echo 'SUCCESS';
            }
        ";
        $output = shell_exec('php -r ' . escapeshellarg($script));
        return strpos($output, 'SUCCESS') !== false;
    }

    public function fetchData(string $endpoint): array
    {
        $url = $this->config['base_url'] . '/jsonapi/' . ltrim($endpoint, '/');
        
        $headers = ['Accept: application/vnd.api+json'];
        if (isset($this->config['token'])) {
            $headers[] = 'Authorization: Bearer ' . $this->config['token'];
        }

        $response = $this->makeHttpRequest($url, 'GET', [], $headers);
        return $response['success'] ? $response['data'] : [];
    }

    public function pushEvent(string $eventName, array $payload): bool
    {
        // Custom REST endpoint or Webhook receiver in Drupal
        $endpoint = $this->config['base_url'] . '/api/spp/webhook';
        $headers = ['Content-Type: application/json'];
        
        if (isset($this->config['webhook_secret'])) {
            $headers[] = 'X-SPP-Signature: ' . hash_hmac('sha256', json_encode($payload), $this->config['webhook_secret']);
        }

        $response = $this->makeHttpRequest($endpoint, 'POST', ['event' => $eventName, 'data' => $payload], $headers);
        return $response['success'];
    }
}
