<?php
namespace SPPMod\Parikshak;

/**
 * Trait InteractsWithApi
 * Provides helpers to simulate HTTP requests in tests without a real web server.
 */
trait InteractsWithApi
{
    protected ?object $authenticatedUser = null;
    protected array $defaultHeaders = [];
    protected array $sessionData = [];

    /**
     * Set the currently authenticated user for future requests.
     */
    public function actingAs(object $user, ?string $guard = null): static
    {
        $this->authenticatedUser = $user;
        if (class_exists('\SPPMod\SPPAuth\AuthManager')) {
            \SPPMod\SPPAuth\AuthManager::setUser($user);
        }
        return $this;
    }

    /**
     * Add default headers to be sent with subsequent requests.
     */
    public function withHeaders(array $headers): static
    {
        $this->defaultHeaders = array_merge($this->defaultHeaders, $headers);
        return $this;
    }

    /**
     * Set session variables for subsequent requests.
     */
    public function withSession(array $data): static
    {
        $this->sessionData = array_merge($this->sessionData, $data);
        return $this;
    }

    protected function call(string $method, string $uri, array $parameters = [], array $headers = []): SPPTestResponse
    {
        $method = strtoupper($method);

        // Save global state
        $oldServer = $_SERVER;
        $oldGet = $_GET;
        $oldPost = $_POST;
        $oldSession = $_SESSION ?? [];

        // Apply session state
        if (!empty($this->sessionData)) {
            $_SESSION = array_merge($_SESSION ?? [], $this->sessionData);
        }

        // Mock environment
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;

        $mergedHeaders = array_merge($this->defaultHeaders, $headers);
        foreach ($mergedHeaders as $key => $value) {
            $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
            $_SERVER[$serverKey] = $value;
        }

        if ($method === 'GET') {
            $_GET = $parameters;
        } else {
            $_POST = $parameters;
        }

        ob_start();
        $statusCode = 200;
        $responseHeaders = [];

        try {
            if (class_exists('\SPP\Core\Router\Router')) {
                $result = \SPP\Core\Router\Router::dispatch($method, $uri);
                if (is_string($result)) {
                    echo $result;
                } elseif (is_array($result) || is_object($result)) {
                    header('Content-Type: application/json');
                    $responseHeaders['Content-Type'] = 'application/json';
                    echo json_encode($result);
                }
            } elseif (class_exists('\SPP\Core\Pipeline')) {
                // Pipeline fallback if available
                echo json_encode(['status' => 'ok']);
            } else {
                echo json_encode(['mock' => 'API simulation rendered', 'uri' => $uri, 'method' => $method]);
            }
        } catch (\Throwable $e) {
            $statusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
            echo json_encode(['error' => $e->getMessage()]);
        }
        
        $content = ob_get_clean();

        // Restore global state
        $_SERVER = $oldServer;
        $_GET = $oldGet;
        $_POST = $oldPost;
        $_SESSION = $oldSession;

        return new SPPTestResponse($content, $statusCode, $responseHeaders);
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

    protected function json(string $method, string $uri, array $data = [], array $headers = []): SPPTestResponse
    {
        $headers['Content-Type'] = 'application/json';
        $headers['Accept'] = 'application/json';
        return $this->call($method, $uri, $data, $headers);
    }

    protected function jsonGet(string $uri, array $headers = []): SPPTestResponse
    {
        return $this->json('GET', $uri, [], $headers);
    }

    protected function jsonPost(string $uri, array $data = [], array $headers = []): SPPTestResponse
    {
        return $this->json('POST', $uri, $data, $headers);
    }

    protected function jsonPut(string $uri, array $data = [], array $headers = []): SPPTestResponse
    {
        return $this->json('PUT', $uri, $data, $headers);
    }

    protected function jsonDelete(string $uri, array $data = [], array $headers = []): SPPTestResponse
    {
        return $this->json('DELETE', $uri, $data, $headers);
    }
}
