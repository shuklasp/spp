<?php
namespace SPPMod\Contrib\AppDrivers;

use SPPMod\SPPIntegrations\AbstractDriver;

class MagentoDriver extends AbstractDriver
{
    public function syncUser(array $userData): bool
    {
        // Magento 2 REST API uses /rest/V1/customers
        $endpoint = rtrim($this->config['base_url'] ?? '', '/') . '/rest/V1/customers';
        
        $headers = [
            'Authorization: Bearer ' . ($this->config['token'] ?? ''),
            'Content-Type: application/json'
        ];

        $payload = [
            'customer' => [
                'email'     => $userData['email'] ?? '',
                'firstname' => $userData['firstname'] ?? 'Customer',
                'lastname'  => $userData['lastname'] ?? 'User',
                'website_id' => 1
            ],
            'password' => 'TempPassword123!' // Magento requires complex passwords
        ];

        $response = $this->makeHttpRequest($endpoint, 'POST', $payload, $headers);
        return $response['success'];
    }

    public function fetchData(string $endpoint): array
    {
        $url = rtrim($this->config['base_url'] ?? '', '/') . '/rest/V1/' . ltrim($endpoint, '/');
        $headers = ['Authorization: Bearer ' . ($this->config['token'] ?? '')];
        $response = $this->makeHttpRequest($url, 'GET', [], $headers);
        return $response['success'] ? $response['data'] : [];
    }

    public function pushEvent(string $eventName, array $payload): bool
    {
        // Pushing custom events might require a custom Magento module to accept them.
        return false; 
    }
}
