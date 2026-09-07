<?php
namespace SPPMod\Parikshak;

/**
 * Trait InteractsWithApi
 * Provides helpers to simulate HTTP requests in tests without a real web server.
 */
trait InteractsWithApi
{
    protected function call(string $method, string $uri, array $parameters = [], array $headers = []): SPPTestResponse
    {
        // Save global state
        $oldServer = $_SERVER;
        $oldGet = $_GET;
        $oldPost = $_POST;

        // Mock environment
        $_SERVER['REQUEST_METHOD'] = strtoupper($method);
        $_SERVER['REQUEST_URI'] = $uri;
        
        foreach ($headers as $key => $value) {
            $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
            $_SERVER[$serverKey] = $value;
        }

        if (strtoupper($method) === 'GET') {
            $_GET = $parameters;
        } else {
            $_POST = $parameters;
        }

        ob_start();
        
        // Very basic mock router dispatch mechanism. 
        // In a real scenario, this would tap into the SPP Kernel Pipeline.
        $statusCode = 200;
        try {
            if (class_exists('\SPP\Core\Pipeline')) {
                // If the app has a proper Request/Response cycle:
                // $request = new \SPP\Core\Request($_GET, $_POST, [], [], $_SERVER);
                // $response = \SPP\Core\Kernel::handle($request);
                // echo $response->getContent();
                // $statusCode = $response->getStatusCode();
            } else {
                echo json_encode(['mock' => 'API simulation requires full Kernel boot mapping.']);
            }
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            echo json_encode(['error' => $e->getMessage()]);
        }
        
        $content = ob_get_clean();

        // Restore global state
        $_SERVER = $oldServer;
        $_GET = $oldGet;
        $_POST = $oldPost;

        return new SPPTestResponse($content, $statusCode);
    }

    protected function get(string $uri, array $headers = []): SPPTestResponse
    {
        return $this->call('GET', $uri, [], $headers);
    }

    protected function post(string $uri, array $data = [], array $headers = []): SPPTestResponse
    {
        return $this->call('POST', $uri, $data, $headers);
    }
    
    protected function put(string $uri, array $data = [], array $headers = []): SPPTestResponse
    {
        return $this->call('PUT', $uri, $data, $headers);
    }

    protected function delete(string $uri, array $data = [], array $headers = []): SPPTestResponse
    {
        return $this->call('DELETE', $uri, $data, $headers);
    }
}
