<?php

namespace SPPMod\SPPView;

/**
 * Class SPPRequest
 * Unified HTTP request abstraction handling form-encoded data, raw JSON payloads,
 * headers, method inspection, and input filtering.
 */
class SPPRequest extends \SPP\SPPObject
{
    protected array $data = [];
    protected array $queryData = [];
    protected array $postData = [];
    protected array $jsonPayload = [];
    protected string $request_url = '';
    protected string $request_method = 'GET';

    public function __construct(?array $data = null)
    {
        $this->request_method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->queryData = $_GET ?? [];
        $this->postData = $_POST ?? [];

        // Parse JSON payload if Content-Type is application/json
        $contentType = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');
        if (str_contains(strtolower($contentType), 'application/json')) {
            $raw = file_get_contents('php://input');
            if ($raw !== false && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $this->jsonPayload = $decoded;
                }
            }
        }

        if ($data !== null) {
            $this->data = $data;
        } else {
            $this->data = array_merge($this->queryData, $this->postData, $this->jsonPayload);
        }

        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $this->request_url = "{$scheme}://{$host}{$uri}";
    }

    /**
     * Retrieve all input data (query parameters, POST body, and JSON payload).
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * Backward-compatible alias for all().
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Retrieve a specific input value by key, with optional fallback default.
     */
    public function input(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->data;
        }
        return $this->data[$key] ?? $default;
    }

    /**
     * Check if an input field is present.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * Retrieve a subset of the input containing only the specified keys.
     */
    public function only(array $keys): array
    {
        $res = [];
        foreach ($keys as $k) {
            if (array_key_exists($k, $this->data)) {
                $res[$k] = $this->data[$k];
            }
        }
        return $res;
    }

    /**
     * Retrieve all input except the specified keys.
     */
    public function except(array $keys): array
    {
        $res = $this->data;
        foreach ($keys as $k) {
            unset($res[$k]);
        }
        return $res;
    }

    /**
     * Retrieve query parameter ($_GET) value.
     */
    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->queryData;
        }
        return $this->queryData[$key] ?? $default;
    }

    /**
     * Retrieve POST body parameter value.
     */
    public function post(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->postData;
        }
        return $this->postData[$key] ?? $default;
    }

    /**
     * Retrieve an HTTP request header.
     */
    public function header(string $name, ?string $default = null): ?string
    {
        $headerKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (isset($_SERVER[$headerKey])) {
            return $_SERVER[$headerKey];
        }
        if (isset($_SERVER[strtoupper($name)])) {
            return $_SERVER[strtoupper($name)];
        }
        return $default;
    }

    /**
     * Get the HTTP request method (e.g. GET, POST, PUT, DELETE).
     */
    public function method(): string
    {
        return $this->request_method;
    }

    /**
     * Backward-compatible alias for method().
     */
    public function getRequestMethod(): string
    {
        return $this->request_method;
    }

    /**
     * Get the full request URL.
     */
    public function getRequestUrl(): string
    {
        return $this->request_url;
    }

    /**
     * Client IP address.
     */
    public function ip(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Determine if the request accepts or sends JSON.
     */
    public function isJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $contentType = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');
        $isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';

        return str_contains($accept, 'application/json') ||
               str_contains($contentType, 'application/json') ||
               $isAjax;
    }

    /**
     * Determine if request is dispatched by HTMX.
     */
    public function isHtmx(): bool
    {
        return isset($_SERVER['HTTP_HX_REQUEST']) && $_SERVER['HTTP_HX_REQUEST'];
    }

    public function __get($propname)
    {
        return $this->data[$propname] ?? null;
    }

    public function generateFromJson($jason_data)
    {
        $this->data = $jason_data;
    }
}

if (!class_exists('\\SPP\\SPPRequest', false)) {
    class_alias(SPPRequest::class, '\\SPP\\SPPRequest');
}
