<?php
namespace SPPMod\SPPDeploy;

class SPPDeploy extends \SPP\Module
{
    public static function isDeployRequest(): bool
    {
        // Intercept global requests with ?_sppdeploy=1 OR paths containing /_sppdeploy/
        if (isset($_GET['_sppdeploy']) || strpos($_SERVER['REQUEST_URI'], '/_sppdeploy/') !== false) {
            return true;
        }
        return false;
    }

    public static function handle(): void
    {
        $path = $_GET['path'] ?? '/deploy';
        if (strpos($_SERVER['REQUEST_URI'], '/_sppdeploy/') !== false) {
            $parts = explode('/_sppdeploy/', $_SERVER['REQUEST_URI']);
            $rawPath = $parts[1] ?? '';
            if (($pos = strpos($rawPath, '?')) !== false) {
                $rawPath = substr($rawPath, 0, $pos);
            }
            $path = '/' . trim($rawPath, '/');
        }

        \SPPMod\SPPDeploy\Api\Receiver::handle($path);
    }

    public static function requireAuth(): void
    {
        // Extract headers from either $_SERVER or getallheaders() (supports Apache/Nginx FastCGI)
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $headerMap = [];
        foreach ($headers as $k => $v) {
            $headerMap[strtolower($k)] = $v;
        }

        $provided = $_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? ($headerMap['x-deploy-token'] ?? '');
        $signature = $_SERVER['HTTP_X_SIGNATURE'] ?? ($headerMap['x-signature'] ?? '');

        if (!$provided) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized deployment request', 'debug' => 'Missing X-Deploy-Token header']);
            exit;
        }

        $validTokens = self::getValidTokens();
        if (empty($validTokens)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized deployment request', 'debug' => 'No deployment tokens configured on target server']);
            exit;
        }

        $matchedToken = null;
        foreach ($validTokens as $token) {
            if (hash_equals($token, $provided)) {
                $matchedToken = $token;
                break;
            }
        }

        if (!$matchedToken) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized deployment request', 'debug' => 'Token Auth Failed. Provided token not recognized by target.']);
            exit;
        }

        $payload = file_get_contents('php://input') ?: '';
        $expectedSignature = hash_hmac('sha256', $payload, $matchedToken);

        if (!hash_equals($expectedSignature, $signature)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized deployment request', 'debug' => "HMAC Mismatch. Expected: {$expectedSignature}, Provided: {$signature}"]);
            exit;
        }
    }

    public static function getValidTokens(): array
    {
        $tokens = [];
        $env = getenv('SPP_DEPLOY_TOKEN');
        if ($env && $env !== 'spp_deploy_token_placeholder') {
            $tokens[] = trim($env);
        }

        $confFile = self::getConfigFile();
        if ($confFile && file_exists($confFile)) {
            $conf = self::parseYamlFile($confFile);
            if (!empty($conf['token']) && $conf['token'] !== 'spp_deploy_token_placeholder') {
                $tokens[] = trim((string)$conf['token']);
            }
            if (!empty($conf['environments']) && is_array($conf['environments'])) {
                foreach ($conf['environments'] as $envData) {
                    if (is_array($envData) && !empty($envData['token']) && $envData['token'] !== 'spp_deploy_token_placeholder') {
                        $tokens[] = trim((string)$envData['token']);
                    }
                }
            }
        }

        return array_values(array_unique(array_filter($tokens)));
    }

    public static function configuredToken(): string
    {
        $tokens = self::getValidTokens();
        return $tokens[0] ?? '';
    }

    public static function getConfigFile(): ?string
    {
        $candidates = [
            defined('SPP_APP_DIR') ? SPP_APP_DIR . '/.sppdeploy.local.yml' : null,
            defined('SPP_BASE_DIR') ? SPP_BASE_DIR . '/.sppdeploy.local.yml' : null,
            getcwd() . '/.sppdeploy.local.yml',
            defined('SPP_APP_DIR') ? SPP_APP_DIR . '/.sppdeploy.yml' : null,
            defined('SPP_BASE_DIR') ? SPP_BASE_DIR . '/.sppdeploy.yml' : null,
            getcwd() . '/.sppdeploy.yml',
            dirname(__DIR__, 4) . '/.sppdeploy.yml',
            dirname(__DIR__, 5) . '/.sppdeploy.yml'
        ];

        foreach ($candidates as $file) {
            if ($file && file_exists($file)) {
                return $file;
            }
        }
        return null;
    }

    public static function parseYamlFile(string $file): array
    {
        if (function_exists('yaml_parse_file')) {
            $parsed = @yaml_parse_file($file);
            if (is_array($parsed)) {
                return $parsed;
            }
        }
        if (class_exists('\\Symfony\\Component\\Yaml\\Yaml')) {
            try {
                $parsed = \Symfony\Component\Yaml\Yaml::parse(file_get_contents($file));
                if (is_array($parsed)) {
                    return $parsed;
                }
            } catch (\Throwable $e) {
            }
        }

        $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $data = [];
        foreach ($lines as $line) {
            if (preg_match('/^([a-zA-Z0-9_\-]+):\s*["\']?(.*?)["\']?\s*$/', trim($line), $m)) {
                $data[$m[1]] = trim($m[2]);
            }
        }
        return $data;
    }
}
