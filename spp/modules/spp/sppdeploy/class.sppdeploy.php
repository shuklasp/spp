<?php
namespace SPPMod\SPPDeploy;

class SPPDeploy
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
        $expected = self::configuredToken();
        $provided = $_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? '';
        
        if (!$expected || $expected === 'spp_deploy_token_placeholder' || !$provided || !hash_equals($expected, $provided)) {
            $dbg = "Token Auth Failed. Expected: '{$expected}', Provided: '{$provided}'";
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized deployment request', 'debug' => $dbg]);
            exit;
        }

        $signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
        $payload = file_get_contents('php://input') ?: '';
        $expectedSignature = hash_hmac('sha256', $payload, $expected);

        if (!hash_equals($expectedSignature, $signature)) {
            $dbg = "HMAC Mismatch. Expected: {$expectedSignature}, Provided: {$signature}";
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized deployment request', 'debug' => $dbg]);
            exit;
        }
    }

    public static function configuredToken(): string
    {
        $env = getenv('SPP_DEPLOY_TOKEN');
        if ($env) return $env;

        $confFile = SPP_BASE_DIR . '/.sppdeploy.yml';
        if (file_exists($confFile)) {
            $content = file_get_contents($confFile);
            if (preg_match('/^token:\s*["\']?(.*?)["\']?\s*$/m', $content, $matches)) {
                return trim($matches[1]);
            }
        }
        return '';
    }
}
