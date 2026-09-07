<?php

namespace SPPMod\SPPAI;

/**
 * GeminiDriver for Google Generative AI.
 */
class GeminiDriver implements AIDriverInterface
{
    private string $apiKey;
    private string $model = 'gemini-1.5-pro';
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/';

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
        return ['gemini-1.5-pro', 'gemini-1.5-flash', 'gemini-pro', 'gemini-pro-vision'];
    }

    public function complete(string $prompt, array $options = []): string
    {
        $payload = [
            'contents' => [
                ['parts' => [['text' => $prompt]]]
            ],
            'generationConfig' => [
                'temperature' => $options['temperature'] ?? 0.7,
                'maxOutputTokens' => $options['max_tokens'] ?? 2048
            ]
        ];

        return $this->request($payload);
    }

    public function chat(array $messages, array $options = []): string
    {
        $contents = [];
        foreach ($messages as $msg) {
            $contents[] = [
                'role' => $msg['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $msg['content']]]
            ];
        }

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => $options['temperature'] ?? 0.7
            ]
        ];

        return $this->request($payload);
    }

    public function embed(string $text): array
    {
        // Placeholder for Gemini Embedding API
        return [0.1, 0.2, 0.3];
    }

    private function request(array $payload): string
    {
        if (empty($this->apiKey)) {
            return "Error: Gemini API Key not configured.";
        }

        $url = $this->baseUrl . $this->model . ":generateContent?key=" . $this->apiKey;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        return $data['candidates'][0]['content']['parts'][0]['text'] ?? "Error: Invalid response from Gemini API.";
    }

    public function callTool(string $prompt, array $tools, array $options = []): array|string
    {
        $functionDeclarations = [];
        foreach ($tools as $tool) {
            $functionDeclarations[] = [
                'name' => $tool['name'],
                'description' => $tool['description'] ?? '',
                'parameters' => $tool['parameters'] ?? ['type' => 'object', 'properties' => []]
            ];
        }

        $payload = [
            'contents' => [['parts' => [['text' => $prompt]]]],
            'tools' => [['functionDeclarations' => $functionDeclarations]]
        ];

        if (empty($this->apiKey)) {
            return "Error: Gemini API Key not configured.";
        }
        $url = $this->baseUrl . $this->model . ":generateContent?key=" . $this->apiKey;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        $calls = $data['candidates'][0]['content']['parts'] ?? [];
        $invocations = [];
        foreach ($calls as $part) {
            if (isset($part['functionCall'])) {
                $invocations[] = [
                    'name' => $part['functionCall']['name'],
                    'arguments' => $part['functionCall']['args'] ?? []
                ];
            }
        }
        if (!empty($invocations)) {
            return $invocations;
        }

        return $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    }

    public function structured(string $prompt, array $jsonSchema, array $options = []): array|string
    {
        $payload = [
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'responseSchema' => $jsonSchema
            ]
        ];

        $resText = $this->request($payload);
        return json_decode(trim($resText), true) ?: $resText;
    }
}
