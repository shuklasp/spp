<?php
namespace SPPMod\SPPLive;

class WebsocketLiveEngine implements LiveEngineInterface {
    private $broadcasterUrl;

    public function __construct() {
        $this->broadcasterUrl = \SPP\Config\YamlLoader::get('spplive', 'websocket_url') ?: 'http://127.0.0.1:8080/broadcast';
    }

    public function isAvailable(): bool {
        // Attempt to check if broadcaster is alive (e.g., via a ping or config check)
        // For performance, we assume it's available if configured, or you could do a quick socket check
        return !empty(\SPP\Config\YamlLoader::get('spplive', 'websocket_url'));
    }

    public function emit(string $componentId, string $event, array $params = []): void {
        $payload = json_encode([
            'target' => $componentId,
            'name' => $event,
            'params' => $params
        ]);

        // Push to websocket broadcaster via simple HTTP POST
        if (function_exists('curl_init')) {
            $ch = curl_init($this->broadcasterUrl);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, 500); // 500ms timeout so we don't block
            $secret = \SPP\Config\YamlLoader::get('spplive', 'websocket_secret') ?: 'default_insecure_secret';
            $signature = hash_hmac('sha256', $payload, $secret);

            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($payload),
                'X-SPP-Live-Signature: ' . $signature
            ]);
            curl_exec($ch);
            curl_close($ch);
        }
    }

    public function flush(): array {
        // Websockets stream proactively; no manual flush needed.
        return [];
    }
}
