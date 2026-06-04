<?php

namespace SPPMod\SPPAI;

/**
 * ClaudeDriver for Anthropic.
 */
class ClaudeDriver implements AIDriverInterface
{
    private string $apiKey;
    private string $model = 'claude-3-5-sonnet-20240620';
    private string $baseUrl = 'https://api.anthropic.com/v1/messages';

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
        return ['claude-3-5-sonnet-20240620', 'claude-3-opus-20240229', 'claude-3-haiku-20240307'];
    }

    public function complete(string $prompt, array $options = []): string
    {
        return $this->chat([['role' => 'user', 'content' => $prompt]], $options);
    }

    public function chat(array $messages, array $options = []): string
    {
        if (empty($this->apiKey)) {
            return "Error: Anthropic API Key not configured.";
        }

        $system = '';
        $filteredMessages = [];
        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $system = $msg['content'];
            } else {
                $filteredMessages[] = $msg;
            }
        }

        $payload = [
            'model' => $this->model,
            'messages' => $filteredMessages,
            'max_tokens' => $options['max_tokens'] ?? 2048,
            'temperature' => $options['temperature'] ?? 0.7
        ];

        if (!empty($system)) {
            $payload['system'] = $system;
        }

        $ch = curl_init($this->baseUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'x-api-key: ' . $this->apiKey,
            'anthropic-version: 2023-06-01'
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        return $data['content'][0]['text'] ?? "Error: Invalid response from Anthropic API.";
    }

    public function embed(string $text): array
    {
        return [];
    }

    public function callTool(string $prompt, array $tools, array $options = []): array|string
    {
        $formattedTools = [];
        foreach ($tools as $tool) {
            $formattedTools[] = [
                'name' => $tool['name'],
                'description' => $tool['description'] ?? '',
                'input_schema' => $tool['parameters'] ?? ['type' => 'object', 'properties' => []]
            ];
        }

        $payload = [
            'model' => $this->model,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'tools' => $formattedTools,
            'max_tokens' => $options['max_tokens'] ?? 2048
        ];

        $ch = curl_init($this->baseUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'x-api-key: ' . $this->apiKey,
            'anthropic-version: 2023-06-01'
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        $contentBlocks = $data['content'] ?? [];
        $invocations = [];
        foreach ($contentBlocks as $block) {
            if (($block['type'] ?? '') === 'tool_use') {
                $invocations[] = [
                    'name' => $block['name'],
                    'arguments' => $block['input'] ?? []
                ];
            }
        }
        if (!empty($invocations)) {
            return $invocations;
        }

        return $contentBlocks[0]['text'] ?? '';
    }

    public function structured(string $prompt, array $jsonSchema, array $options = []): array|string
    {
        $system = "Output your final response strictly as a JSON object conforming exactly to the following JSON Schema. Do not wrap in markdown tags.\n" . json_encode($jsonSchema);
        $payload = [
            'model' => $this->model,
            'system' => $system,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'max_tokens' => $options['max_tokens'] ?? 2048,
            'temperature' => 0.1
        ];

        $ch = curl_init($this->baseUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'x-api-key: ' . $this->apiKey,
            'anthropic-version: 2023-06-01'
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        $text = $data['content'][0]['text'] ?? '{}';
        return json_decode(trim($text), true) ?: $text;
    }
}
