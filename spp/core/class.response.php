<?php

namespace SPP;

/**
 * Class Response
 * Standardizes API responses, output buffer cleansing for pure JSON emissions,
 * and multi-context redirection lifecycles.
 */
class Response
{
    /**
     * Clears all open output buffers, enforces strict JSON response headers,
     * outputs the encoded payload data, and securely terminates execution.
     *
     * @param array|object $data The response payload
     * @param int $status HTTP response status code
     */
    public static function json($data, int $status = 200): void
    {
        while (ob_get_level()) {
            ob_end_clean();
        }

        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Issues an HTTP redirect header and securely terminates execution.
     *
     * @param string $url Target destination URL
     * @param int $status HTTP redirect status code
     */
    public static function redirect(string $url, int $status = 302): void
    {
        http_response_code($status);
        header("Location: " . $url);
        exit;
    }
}
