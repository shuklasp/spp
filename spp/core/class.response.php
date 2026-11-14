<?php

namespace SPP;

/**
 * Class Response
 * Standardizes API responses, output buffer cleansing for pure JSON emissions,
 * and multi-context redirection lifecycles.
 */
class Response implements \Stringable
{
    protected string $content;
    protected int $statusCode;
    protected array $headers;

    public function __construct(string $content = '', int $statusCode = 200, array $headers = [])
    {
        $this->content = $content;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function setStatusCode(int $statusCode): self
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Flash a key-value message to the session for the next request.
     *
     * @param string $key Flash message identifier
     * @param mixed $value Flash message content
     * @return self
     */
    public function with(string $key, mixed $value): self
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
        if (class_exists('\\SPP\\SPPSession')) {
            \SPP\SPPSession::setFlash($key, $value);
        } else {
            $_SESSION['_spp_flashes'][$key] = $value;
        }
        return $this;
    }

    /**
     * Flash input data to the session to repopulate forms via old().
     *
     * @param array|null $data Input data (defaults to $_POST)
     * @return self
     */
    public function withInput(?array $data = null): self
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
        $data = $data ?? ($_POST ?? []);
        $_SESSION['_spp_old_input'] = $data;
        return $this;
    }

    public function __toString(): string
    {
        return $this->content;
    }

    /**
     * Send headers, status code, and emit content without forcing script termination.
     */
    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);
            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }
        }
        echo $this->content;
    }

    /**
     * Create a returnable JSON response instance.
     */
    public static function makeJson($data, int $status = 200, array $headers = []): self
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = array_merge([
            'Content-Type' => 'application/json; charset=utf-8'
        ], $headers);

        return new self($json ?: '{}', $status, $headers);
    }

    /**
     * Create a returnable redirect response instance.
     */
    public static function makeRedirect(string $url, int $status = 302, array $headers = []): self
    {
        $headers = array_merge([
            'Location' => $url
        ], $headers);

        return new self('', $status, $headers);
    }

    /**
     * Clears all open output buffers, enforces strict JSON response headers,
     * outputs the encoded payload data, and securely terminates execution.
     *
     * @param array|object $data The response payload
     * @param int $status HTTP response status code
     * @param bool $terminate Whether to terminate execution (default true for backward compatibility)
     * @return self
     */
    public static function json($data, int $status = 200, bool $terminate = true): self
    {
        $res = self::makeJson($data, $status);

        if (!headers_sent()) {
            if (php_sapi_name() !== 'cli') {
                while (ob_get_level()) {
                    ob_end_clean();
                }
            }
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }

        echo $res->getContent();

        if ($terminate && php_sapi_name() !== 'cli') {
            exit;
        }

        return $res;
    }

    /**
     * Issues an HTTP redirect header and securely terminates execution.
     *
     * @param string $url Target destination URL
     * @param int $status HTTP redirect status code
     * @param bool $terminate Whether to terminate execution (default true for backward compatibility)
     * @return self
     */
    public static function redirect(string $url, int $status = 302, bool $terminate = true): self
    {
        $res = self::makeRedirect($url, $status);

        if (!headers_sent()) {
            http_response_code($status);
            header("Location: " . $url);
        }

        if ($terminate && php_sapi_name() !== 'cli') {
            exit;
        }

        return $res;
    }
}
