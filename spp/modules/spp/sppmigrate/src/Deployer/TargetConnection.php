<?php
namespace SPPMod\SPPMigrate\Deployer;

class TargetConnection {
    private string $targetUrl;
    private string $apiKey;

    public function __construct(string $targetUrl, string $apiKey) {
        $this->targetUrl = rtrim($targetUrl, '/');
        $this->apiKey = $apiKey;
    }

    public function ping(): array {
        return $this->request('/api/sppmigrate/ping');
    }

    public function getDiff(array $localHashes): array {
        return $this->request('/api/sppmigrate/diff', 'POST', ['hashes' => $localHashes]);
    }

    public function deploy(array $payload): array {
        return $this->request('/api/sppmigrate/deploy', 'POST', $payload);
    }

    private function request(string $endpoint, string $method = 'GET', array $data = []): array {
        $ch = curl_init($this->targetUrl . $endpoint);
        
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ];

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            throw new \Exception("CURL Error: " . $error);
        }

        $decoded = json_decode($response, true);
        if (!$decoded) {
            throw new \Exception("Invalid JSON response from target: " . $response);
        }

        return $decoded;
    }
}
