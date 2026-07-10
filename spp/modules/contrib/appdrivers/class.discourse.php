<?php
namespace SPPMod\Contrib\AppDrivers;

use SPPMod\SPPIntegrations\AbstractDriver;

class DiscourseDriver extends AbstractDriver
{
    public function syncUser(array $userData): bool
    {
        // Discourse uses /users for creation via API
        $endpoint = rtrim($this->config['base_url'] ?? '', '/') . '/users';
        
        $headers = [
            'Api-Key: ' . ($this->config['token'] ?? ''),
            'Api-Username: system',
            'Content-Type: application/json'
        ];

        $payload = [
            'name'     => $userData['name'] ?? $userData['username'],
            'email'    => $userData['email'] ?? '',
            'password' => bin2hex(random_bytes(10)),
            'username' => $userData['username'] ?? '',
            'active'   => true,
            'approved' => true
        ];

        $response = $this->makeHttpRequest($endpoint, 'POST', $payload, $headers);
        return $response['success'];
    }

    public function fetchData(string $endpoint): array
    {
        // Must append .json for Discourse API requests if not present
        if (!str_ends_with($endpoint, '.json')) {
            $endpoint .= '.json';
        }

        $url = rtrim($this->config['base_url'] ?? '', '/') . '/' . ltrim($endpoint, '/');
        $headers = [
            'Api-Key: ' . ($this->config['token'] ?? ''),
            'Api-Username: system'
        ];
        
        $response = $this->makeHttpRequest($url, 'GET', [], $headers);
        return $response['success'] ? $response['data'] : [];
    }

    public function pushEvent(string $eventName, array $payload): bool
    {
        // Can be routed to Discourse webhooks if needed
        return false;
    }
}
