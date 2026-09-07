<?php

namespace SPPMod\SPPAI;

/**
 * SarvamDriver for Sarvam AI (Indic Focus).
 */
class SarvamDriver implements AIDriverInterface
{
    private string $apiKey;
    private string $model = 'sarvam-1';
    private string $baseUrl = 'https://api.sarvam.ai/chat/completions';

    public function __construct(array $config = [])
    {
        $this->apiKey = $config['api_key'] ?? '';
        if (isset($config['default_model'])) {
            $this->model = $config['default_model'];
        }
    }

    public function setModel(string $model): AIDriverInterface
    {
        $this->model = $model;
        return $this;
    }

    public function getSupportedModels(): array
    {
        return ['sarvam-1', 'airavata'];
    }

    public function complete(string $prompt, array $options = []): string
    {
        return $this->chat([['role' => 'user', 'content' => $prompt]], $options);
    }

    public function chat(array $messages, array $options = []): string
    {
        if (empty($this->apiKey)) {
            return "Error: Sarvam API Key not configured.";
        }

        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.7
        ];

        $ch = curl_init($this->baseUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'api-subscription-key: ' . $this->apiKey
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        return $data['choices'][0]['message']['content'] ?? "Error: Invalid response from Sarvam API.";
    }

    public function embed(string $text): array
    {
        return [];
    }

    public function callTool(string $prompt, array $tools, array $options = []): array|string
    {
        // Sarvam fallback instruction integration
        $instruction = "You have access to the following tools:\n" . json_encode($tools) . "\nIf a tool should be called, output ONLY a JSON array of objects with 'name' and 'arguments' keys. Otherwise answer normally.";
        $res = $this->complete("{$instruction}\n\nUser: {$prompt}", $options);
        $decoded = json_decode(trim($res), true);
        return is_array($decoded) ? $decoded : $res;
    }

    public function structured(string $prompt, array $jsonSchema, array $options = []): array|string
    {
        $instruction = "Output your response strictly as a JSON object conforming exactly to this schema:\n" . json_encode($jsonSchema);
        $res = $this->complete("{$instruction}\n\nPrompt: {$prompt}", $options);
        return json_decode(trim($res), true) ?: $res;
    }
}
