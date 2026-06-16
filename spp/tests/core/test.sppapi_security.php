<?php

namespace SPPMod\Parikshak\Tests;

use SPPMod\Parikshak\SPPTestCase;

class SPPAPI_SecurityTest extends SPPTestCase
{
    private string $apiEntry;

    public function setUp(): void
    {
        $this->apiEntry = SPP_APP_DIR . '/admin/api.php';
    }

    private function simulateRequest(string $method, string $queryString, array $headers = [], string $body = ''): array
    {
        $cmd = "php -r \"";
        $cmd .= "\$_SERVER['REQUEST_METHOD'] = '{$method}'; ";
        $cmd .= "parse_str('{$queryString}', \$_GET); ";
        $cmd .= "\$_REQUEST = array_merge(\$_REQUEST, \$_GET); ";
        $cmd .= "\$_SERVER['QUERY_STRING'] = '{$queryString}'; ";
        
        if (!empty($body)) {
            $b64 = base64_encode($body);
            $cmd .= "\$bodyParams = json_decode(base64_decode('{$b64}'), true) ?: []; ";
            $cmd .= "\$_REQUEST = array_merge(\$_REQUEST, \$bodyParams); ";
        }
        
        foreach ($headers as $key => $val) {
            $cmd .= "\$_SERVER['HTTP_" . str_replace('-', '_', strtoupper($key)) . "'] = '{$val}'; ";
        }
        
        $cmd .= "\$_SERVER['SERVER_PORT'] = 80; ";
        $cmd .= "\$_SERVER['HTTP_HOST'] = 'localhost'; ";
        $cmd .= "\$_SERVER['REMOTE_ADDR'] = '127.0.0.1'; ";
        $cmd .= "define('SPP_NO_EXIT', true); "; // In case the framework supports disabling exit
        $cmd .= "ob_start(); ";
        $cmd .= "try { ";
        $cmd .= "  chdir(dirname('{$this->apiEntry}')); ";
        $cmd .= "  require '{$this->apiEntry}'; ";
        $cmd .= "} catch (\\Throwable \$e) { echo \$e->getMessage(); } ";
        $cmd .= "\$out = ob_get_clean(); ";
        $cmd .= "echo json_encode(['code' => http_response_code(), 'output' => \$out]); ";
        $cmd .= "\"";

        $output = shell_exec($cmd);
        $decoded = json_decode($output, true);
        if ($decoded && isset($decoded['status'])) {
            return $decoded;
        }
        return json_decode($output, true) ?: ['code' => 500, 'output' => $output];
    }

    public function testUnauthenticatedGetExposureIsBlocked()
    {
        $res = $this->simulateRequest('GET', 'entity=User&__api=1');
        $this->assertTrue(
            (isset($res['code']) && $res['code'] === 401) || 
            (isset($res['message']) && strpos($res['message'], 'Unauthorized') !== false) ||
            (isset($res['output']) && strpos($res['output'], 'Unauthorized') !== false),
            "Unauthenticated GET request should be blocked with 401."
        );
    }

    public function testApiDocumentationExposureIsBlocked()
    {
        $res = $this->simulateRequest('GET', 'entity=docs&__api=1');
        $this->assertTrue(
            (isset($res['code']) && $res['code'] === 401) || 
            (isset($res['message']) && strpos($res['message'], 'Unauthorized') !== false) ||
            (isset($res['output']) && strpos($res['output'], 'Unauthorized') !== false),
            "Unauthenticated API Documentation should be blocked with 401."
        );
    }

    public function testCsrfProtectionOnComponentAction()
    {
        $res = $this->simulateRequest('POST', '__svc=component_action', [], '{"component":"Test","method":"test"}');
        $this->assertTrue(
            (isset($res['code']) && $res['code'] === 403) || 
            (isset($res['message']) && strpos($res['message'], 'CSRF Protection') !== false) ||
            (isset($res['output']) && strpos($res['output'], 'CSRF Protection') !== false),
            "Component action without X-SPP-Ajax header should be blocked with 403."
        );
    }

    public function testCsrfProtectionOnService()
    {
        $res = $this->simulateRequest('POST', '__svc=test_service', [], '{"test":"1"}');
        
        $this->assertTrue(
            (isset($res['code']) && $res['code'] === 403) || 
            (isset($res['message']) && strpos($res['message'], 'CSRF Protection') !== false) ||
            (isset($res['output']) && strpos($res['output'], 'CSRF Protection') !== false),
            "Service invocation without X-SPP-Ajax header should be blocked with 403."
        );
    }

    public function testTransportIntegrityEnforced()
    {
        $res = $this->simulateRequest('POST', '__svc=component_action', ['X-SPP-Ajax' => '1'], '{"component": "Test", "method": "test"}');
        
        $this->assertTrue(
            (isset($res['code']) && $res['code'] === 403) || 
            (isset($res['message']) && strpos($res['message'], 'Transport Integrity Failure') !== false) ||
            (isset($res['output']) && strpos($res['output'], 'Transport Integrity Failure') !== false),
            "Tampered or unsigned payload should fail Transport Integrity checks."
        );
    }
}
