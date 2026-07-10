<?php
namespace SPPMod\Contrib\AppDrivers;

use SPPMod\SPPIntegrations\AbstractDriver;

/**
 * Class CourseraDriver
 * 
 * Integrates with Coursera using its Enterprise/Partner REST APIs.
 */
class CourseraDriver extends AbstractDriver
{
    protected function initialize(): void
    {
        if (!isset($this->config['token'])) {
            throw new \Exception("Coursera driver requires a Bearer 'token' in config.");
        }
        $this->config['base_url'] = 'https://api.coursera.org/api';
    }

    private function getHeaders(): array
    {
        return [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->config['token']
        ];
    }

    public function syncUser(array $userData): bool
    {
        // Coursera typically manages users via enterprise invitations
        $endpoint = $this->config['base_url'] . '/enterpriseInvitations.v1';
        
        $payload = [
            'programId' => $this->config['program_id'] ?? '',
            'email' => $userData['email'] ?? '',
            'externalId' => $userData['id'] ?? ''
        ];

        $response = $this->makeHttpRequest($endpoint, 'POST', $payload, $this->getHeaders());
        return $response['success'];
    }

    public function fetchData(string $endpoint): array
    {
        $url = $this->config['base_url'] . '/' . ltrim($endpoint, '/');
        $response = $this->makeHttpRequest($url, 'GET', [], $this->getHeaders());
        return $response['success'] ? $response['data'] : [];
    }

    public function pushEvent(string $eventName, array $payload): bool
    {
        // Coursera APIs are generally pull-based, but we can simulate pushing to a generic endpoint if available
        return false;
    }
}
