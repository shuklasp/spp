<?php
namespace SPPMod\SPPDeploy\Deployer;

class TargetConnection
{
    private string $targetUrl;
    private string $token;
    private ?string $sslCert;
    private ?string $sslKey;
    private ?string $sslPass;

    public function __construct(string $targetUrl, string $token, ?string $sslCert = null, ?string $sslKey = null, ?string $sslPass = null)
    {
        $this->targetUrl = rtrim($targetUrl, '/');
        $this->token = $token;
        $this->sslCert = $sslCert;
        $this->sslKey = $sslKey;
        $this->sslPass = $sslPass;
    }

    public static function getDefaultEnvironment(): string
    {
        $confFile = SPP_BASE_DIR . '/.sppdeploy.yml';
        if (file_exists($confFile)) {
            $conf = @yaml_parse_file($confFile);
            if (isset($conf['default_environment']) && is_string($conf['default_environment']) && $conf['default_environment'] !== '') {
                return $conf['default_environment'];
            }
        }
        return 'production';
    }

    public static function resolve(string $targetOrAlias, string $token = 'default_cli_key'): self
    {
        if ($targetOrAlias === '' || $targetOrAlias === 'default') {
            $targetOrAlias = self::getDefaultEnvironment();
        }

        $url = $targetOrAlias;
        $resolvedToken = $token;
        $sslCert = null;
        $sslKey = null;
        $sslPass = null;

        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            $confFile = SPP_BASE_DIR . '/.sppdeploy.yml';
            if (file_exists($confFile)) {
                $conf = @yaml_parse_file($confFile);
                if (isset($conf['environments'][$url])) {
                    $env = $conf['environments'][$url];
                    if (isset($env['url']))
                        $url = $env['url'];
                    if (isset($env['token']))
                        $resolvedToken = $env['token'];
                    if (isset($env['ssl_cert']))
                        $sslCert = $env['ssl_cert'];
                    if (isset($env['ssl_key']))
                        $sslKey = $env['ssl_key'];
                    if (isset($env['ssl_passphrase']))
                        $sslPass = $env['ssl_passphrase'];
                }
            }
        }

        if ($resolvedToken === 'default_cli_key') {
            $configured = \SPPMod\SPPDeploy\SPPDeploy::configuredToken();
            if ($configured !== '')
                $resolvedToken = $configured;
        }

        return new self($url, $resolvedToken, $sslCert, $sslKey, $sslPass);
    }

    public function getDiff(array $hashes): array
    {
        return $this->request('/diff', 'POST', ['hashes' => $hashes]);
    }

    public function getHealth(): array
    {
        return $this->request('/health', 'GET');
    }

    public function getEnvKeys(): array
    {
        return $this->request('/env-keys', 'GET');
    }

    public function deploy(array $payload): array
    {
        return $this->request('/_sppdeploy/deploy', 'POST', $payload);
    }

    public function uploadChunk(string $sessionId, string $chunkData, int $index, bool $isLast, array $payloadData = []): array
    {
        $data = [
            'session_id' => $sessionId,
            'chunk_data' => $chunkData,
            'index' => $index,
            'is_last' => $isLast
        ];
        if ($isLast) {
            $data['payload_data'] = $payloadData;
        }
        return $this->request('/chunk', 'POST', $data);
    }

    public function triggerRollback(string $id): array
    {
        return $this->request('/_sppdeploy/rollback', 'POST', ['id' => $id]);
    }

    public function getHistory(): array
    {
        return $this->request('/_sppdeploy/history', 'GET');
    }

    public function getBackups(): array
    {
        return $this->request('/_sppdeploy/backups', 'GET');
    }

    public function cleanupBackups(int $keep): array
    {
        return $this->request('/_sppdeploy/cleanup', 'POST', ['keep' => $keep]);
    }

    public function setMaintenanceMode(string $state): array
    {
        return $this->request('/_sppdeploy/maintenance', 'POST', ['state' => $state]);
    }

    public function pushEnvKey(string $key, string $value): array
    {
        return $this->request('/_sppdeploy/env/push', 'POST', ['key' => $key, 'value' => $value]);
    }

    public function getExport(): array
    {
        return $this->request('/export', 'GET');
    }

    public function getLogs(int $offset = -1, int $lines = 100): array
    {
        $params = [];
        if ($offset >= 0)
            $params['offset'] = $offset;
        if ($lines > 0 && $offset < 0)
            $params['lines'] = $lines;

        $qs = http_build_query($params);
        $endpoint = '/_sppdeploy/logs' . ($qs ? '?' . $qs : '');
        return $this->request($endpoint, 'GET');
    }

    public function runCommand(string $command): array
    {
        return $this->request('/_sppdeploy/run', 'POST', ['command' => $command]);
    }

    private function request(string $endpoint, string $method = 'GET', array $data = []): array
    {
        // Rewrite the internal _sppdeploy pseudo-paths to query parameters for the target kernel
        $url = $this->targetUrl;
        $separator = (strpos($url, '?') === false) ? '?' : '&';
        $url .= $separator . '_sppdeploy=1&path=' . urlencode($endpoint);

        $ch = curl_init($url);

        $jsonPayload = empty($data) ? '' : json_encode($data);
        $signature = hash_hmac('sha256', $jsonPayload, $this->token);

        $headers = [
            'Content-Type: application/json',
            'X-Deploy-Token: ' . $this->token,
            'X-Signature: ' . $signature
        ];

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);

        if ($this->sslCert) {
            curl_setopt($ch, CURLOPT_SSLCERT, $this->sslCert);
        }
        if ($this->sslKey) {
            curl_setopt($ch, CURLOPT_SSLKEY, $this->sslKey);
        }
        if ($this->sslPass) {
            curl_setopt($ch, CURLOPT_KEYPASSWD, $this->sslPass);
        }

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        }

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new \Exception('CURL Error: ' . curl_error($ch));
        }

        curl_close($ch);

        $decoded = json_decode($response, true);
        if (!$decoded) {
            throw new \Exception('Invalid JSON response from target: ' . $response);
        }

        return $decoded;
    }

    public static function acquireDeploymentLock(): bool
    {
        $lockFile = SPP_BASE_DIR . '/var/lock/sppdeploy.lock';
        if (!is_dir(dirname($lockFile))) {
            @mkdir(dirname($lockFile), 0777, true);
        }

        if (file_exists($lockFile)) {
            $lockTime = (int)file_get_contents($lockFile);
            if (time() - $lockTime < 60) {
                throw new \RuntimeException("Deployment in progress: Mutex lock acquired by another deployment process.");
            }
        }

        @file_put_contents($lockFile, time());
        return true;
    }

    public static function releaseDeploymentLock(): void
    {
        $lockFile = SPP_BASE_DIR . '/var/lock/sppdeploy.lock';
        @unlink($lockFile);
    }
}
