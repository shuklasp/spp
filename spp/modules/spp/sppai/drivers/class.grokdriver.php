<?php
namespace SPPMod\SPPAI;

/**
 * GrokDriver for xAI.
 */
class GrokDriver implements AIDriverInterface
{
    private string $apiKey;
    private string $model = 'grok-2';
    private string $baseUrl = 'https://api.x.ai/v1/chat/completions';

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
        return ['grok-2', 'grok-2-latest', 'grok-beta'];
    }

    public function complete(string $prompt, array $options = []): string
    {
        return $this->chat([['role' => 'user', 'content' => $prompt]], $options);
    }

    public function chat(array $messages, array $options = []): string
    {
        if (empty($this->apiKey)) return "Error: xAI Grok API Key not configured.";

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
            'Authorization: Bearer ' . $this->apiKey
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        return $data['choices'][0]['message']['content'] ?? "Error: Invalid response from xAI API.";
    }

    public function embed(string $text): array
    {
        return [];
    }

    public function callTool(string $prompt, array $tools, array $options = []): array|string
    {
        $messages = [['role' => 'user', 'content' => $prompt]];
        $formattedTools = [];
        foreach ($tools as $tool) {
            $formattedTools[] = [
                'type' => 'function',
                'function' => [
                    'name' => $tool['name'],
                    'description' => $tool['description'] ?? '',
                    'parameters' => $tool['parameters'] ?? ['type' => 'object', 'properties' => []]
                ]
            ];
        }

        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'tools' => $formattedTools,
            'tool_choice' => 'auto'
        ];

        $ch = curl_init($this->baseUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        $toolCalls = $data['choices'][0]['message']['tool_calls'] ?? null;
        if ($toolCalls) {
            $invocations = [];
            foreach ($toolCalls as $call) {
                $invocations[] = [
                    'name' => $call['function']['name'],
                    'arguments' => json_decode($call['function']['arguments'] ?? '{}', true)
                ];
            }
            return $invocations;
        }

        return $data['choices'][0]['message']['content'] ?? '';
    }

    public function structured(string $prompt, array $jsonSchema, array $options = []): array|string
    {
        $messages = [
            ['role' => 'system', 'content' => 'You are a precise data assistant. Output your final answer ONLY as a valid JSON object matching the requested schema.'],
            ['role' => 'user', 'content' => "Prompt: {$prompt}\n\nRequired JSON Schema:\n" . json_encode($jsonSchema)]
        ];

        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'response_format' => ['type' => 'json_object']
        ];

        $ch = curl_init($this->baseUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        $content = $data['choices'][0]['message']['content'] ?? '{}';
        return json_decode($content, true) ?: $content;
    }
}
