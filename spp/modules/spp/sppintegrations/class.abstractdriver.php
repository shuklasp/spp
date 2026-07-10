<?php
namespace SPPMod\SPPIntegrations;

/**
 * Class AbstractDriver
 * 
 * Provides common HTTP communication utilities for SPP Integration drivers.
 */
abstract class AbstractDriver implements ExternalAppDriverInterface
{
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->initialize();
    }

    /**
     * Hook for child classes to perform initialization
     */
    protected function initialize(): void
    {
    }

    /**
     * Helper to perform robust HTTP requests using cURL
     */
    protected function makeHttpRequest(string $url, string $method = 'GET', array $data = [], array $headers = []): array
    {
        $ch = curl_init();
        
        if ($method === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if ($method !== 'GET' && !empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            $headers[] = 'Content-Type: application/json';
        }

        // W3C Trace Context Telemetry Injection
        if (class_exists('\SPPMod\SPPReport\W3CTraceContext')) {
            $traceId = \SPPMod\SPPReport\W3CTraceContext::getCurrentTraceId();
            if ($traceId) {
                $headers[] = 'traceparent: ' . $traceId;
            }
        }

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'status'  => 0,
                'error'   => $error,
                'data'    => null
            ];
        }

        $decoded = json_decode($response, true);

        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'status'  => $httpCode,
            'error'   => null,
            'data'    => $decoded !== null ? $decoded : $response
        ];
    }

    /**
     * Default implementation of loginUser
     * 
     * Drivers overriding this method should respect $this->config['cookie_domain'] 
     * if cross-domain SSO is required.
     */
    public function loginUser(array $userData): bool
    {
        // By default, assume SSO isn't supported unless overridden
        return false;
    }
}
