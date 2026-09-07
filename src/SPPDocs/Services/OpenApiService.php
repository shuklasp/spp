<?php

namespace App\SPPDocs\Services;

use Symfony\Component\Yaml\Yaml;

/**
 * OpenApiService
 * Comprehensive OpenAPI 3.0 / 3.1 parser, interactive API test runner,
 * and polyglot code snippet generator.
 */
class OpenApiService
{
    public static function getOpenApiDir(string $projectId): string
    {
        $dir = dirname(SPP_BASE_DIR) . '/docs/' . $projectId . '/openapi';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir;
    }

    public static function listSpecs(string $projectId): array
    {
        $dir = self::getOpenApiDir($projectId);
        $specs = [];

        if (is_dir($dir)) {
            $files = glob($dir . '/*.{json,yaml,yml}', GLOB_BRACE) ?: [];
            foreach ($files as $file) {
                $basename = basename($file);
                $name = pathinfo($basename, PATHINFO_FILENAME);
                $ext = strtolower(pathinfo($basename, PATHINFO_EXTENSION));

                $meta = [
                    'file' => $basename,
                    'name' => $name,
                    'format' => $ext,
                    'title' => ucfirst(str_replace(['_', '-'], ' ', $name)),
                    'version' => '1.0.0',
                    'description' => '',
                    'endpoint_count' => 0,
                    'size' => filesize($file),
                    'modified_at' => filemtime($file),
                ];

                try {
                    $parsed = self::parseFile($file);
                    if ($parsed && is_array($parsed)) {
                        $meta['title'] = $parsed['info']['title'] ?? $meta['title'];
                        $meta['version'] = $parsed['info']['version'] ?? $meta['version'];
                        $meta['description'] = $parsed['info']['description'] ?? '';
                        $meta['endpoint_count'] = self::countEndpoints($parsed);
                    }
                } catch (\Throwable $e) {}

                $specs[$name] = $meta;
            }
        }

        ksort($specs);
        return $specs;
    }

    public static function getSpec(string $projectId, string $name): ?array
    {
        $dir = self::getOpenApiDir($projectId);
        foreach (['json', 'yaml', 'yml'] as $ext) {
            $file = $dir . '/' . $name . '.' . $ext;
            if (file_exists($file)) {
                return self::parseFile($file);
            }
        }
        return null;
    }

    public static function saveSpec(string $projectId, string $name, string $content): bool
    {
        $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', strtolower($name));
        if (empty($safeName)) return false;

        $dir = self::getOpenApiDir($projectId);
        // Detect format
        $isJson = false;
        $trimmed = trim($content);
        if (str_starts_with($trimmed, '{') && str_ends_with($trimmed, '}')) {
            $decoded = json_decode($trimmed, true);
            if ($decoded !== null) $isJson = true;
        }

        $filename = $dir . '/' . $safeName . ($isJson ? '.json' : '.yaml');
        return (bool)@file_put_contents($filename, $content, LOCK_EX);
    }

    public static function deleteSpec(string $projectId, string $name): bool
    {
        $dir = self::getOpenApiDir($projectId);
        $deleted = false;
        foreach (['json', 'yaml', 'yml'] as $ext) {
            $file = $dir . '/' . $name . '.' . $ext;
            if (file_exists($file)) {
                if (@unlink($file)) $deleted = true;
            }
        }
        return $deleted;
    }

    public static function parseEndpoints(array $spec): array
    {
        $endpoints = [];
        $paths = $spec['paths'] ?? [];
        $serverUrl = rtrim($spec['servers'][0]['url'] ?? '', '/');

        $methods = ['get', 'post', 'put', 'patch', 'delete', 'options', 'head'];

        foreach ($paths as $path => $pathItem) {
            if (!is_array($pathItem)) continue;

            $commonParams = $pathItem['parameters'] ?? [];

            foreach ($methods as $m) {
                if (!isset($pathItem[$m]) || !is_array($pathItem[$m])) continue;

                $op = $pathItem[$m];
                $methodUpper = strtoupper($m);
                $opId = $op['operationId'] ?? ($m . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $path));
                $tags = !empty($op['tags']) ? $op['tags'] : ['General'];
                $summary = $op['summary'] ?? ($methodUpper . ' ' . $path);
                $description = $op['description'] ?? '';

                // Merge common and operation parameters
                $rawParams = array_merge($commonParams, $op['parameters'] ?? []);
                $parameters = [];
                foreach ($rawParams as $p) {
                    if (is_array($p) && isset($p['name'])) {
                        $parameters[] = [
                            'name' => $p['name'],
                            'in' => $p['in'] ?? 'query', // query, path, header
                            'required' => !empty($p['required']),
                            'description' => $p['description'] ?? '',
                            'schema' => $p['schema'] ?? [],
                            'example' => $p['example'] ?? ($p['schema']['default'] ?? ''),
                        ];
                    }
                }

                // Parse request body
                $requestBody = null;
                $requestSample = '';
                if (!empty($op['requestBody']['content'])) {
                    $contentTypes = array_keys($op['requestBody']['content']);
                    $firstCt = $contentTypes[0] ?? 'application/json';
                    $schema = $op['requestBody']['content'][$firstCt]['schema'] ?? [];
                    $example = $op['requestBody']['content'][$firstCt]['example'] ?? null;
                    if ($example !== null) {
                        $requestSample = is_string($example) ? $example : json_encode($example, JSON_PRETTY_PRINT);
                    } elseif (!empty($schema['properties'])) {
                        $sampleObj = [];
                        foreach ($schema['properties'] as $propKey => $propDef) {
                            $sampleObj[$propKey] = $propDef['example'] ?? ($propDef['default'] ?? ($propDef['type'] ?? 'string'));
                        }
                        $requestSample = json_encode($sampleObj, JSON_PRETTY_PRINT);
                    }
                    $requestBody = [
                        'content_type' => $firstCt,
                        'required' => !empty($op['requestBody']['required']),
                        'sample' => $requestSample,
                    ];
                }

                // Parse responses
                $responses = [];
                foreach (($op['responses'] ?? []) as $code => $resp) {
                    $responses[$code] = [
                        'description' => $resp['description'] ?? '',
                        'sample' => '',
                    ];
                    if (!empty($resp['content']['application/json']['example'])) {
                        $responses[$code]['sample'] = json_encode($resp['content']['application/json']['example'], JSON_PRETTY_PRINT);
                    }
                }

                $endpoints[] = [
                    'id' => $opId,
                    'method' => $methodUpper,
                    'path' => $path,
                    'server_url' => $serverUrl,
                    'tags' => $tags,
                    'summary' => $summary,
                    'description' => $description,
                    'parameters' => $parameters,
                    'request_body' => $requestBody,
                    'responses' => $responses,
                ];
            }
        }

        return $endpoints;
    }

    public static function generateSnippets(string $method, string $fullUrl, array $headers = [], ?string $body = null): array
    {
        $method = strtoupper($method);

        // 1. cURL
        $curlLines = ["curl -X {$method} \"{$fullUrl}\""];
        foreach ($headers as $k => $v) {
            $curlLines[] = "  -H \"{$k}: {$v}\"";
        }
        if (!empty($body) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $escaped = str_replace('"', '\"', $body);
            $curlLines[] = "  -d '{$body}'";
        }
        $curlSnippet = implode(" \\\n", $curlLines);

        // 2. JavaScript Fetch
        $jsOptions = [
            'method' => $method,
            'headers' => $headers,
        ];
        if (!empty($body) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $jsOptions['body'] = 'JSON_BODY_PLACEHOLDER';
        }
        $jsJson = json_encode($jsOptions, JSON_PRETTY_PRINT);
        if (!empty($body) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $jsJson = str_replace('"JSON_BODY_PLACEHOLDER"', "JSON.stringify(" . $body . ")", $jsJson);
        }
        $jsSnippet = "fetch(\"{$fullUrl}\", {$jsJson})\n  .then(res => res.json())\n  .then(data => console.log(data))\n  .catch(err => console.error(err));";

        // 3. PHP cURL
        $phpSnippet = "<?php\n\$ch = curl_init();\ncurl_setopt(\$ch, CURLOPT_URL, \"{$fullUrl}\");\ncurl_setopt(\$ch, CURLOPT_RETURNTRANSFER, true);\ncurl_setopt(\$ch, CURLOPT_CUSTOMREQUEST, \"{$method}\");\n";
        if (!empty($headers)) {
            $headerStrs = [];
            foreach ($headers as $k => $v) $headerStrs[] = "\"{$k}: {$v}\"";
            $phpSnippet .= "curl_setopt(\$ch, CURLOPT_HTTPHEADER, [\n    " . implode(",\n    ", $headerStrs) . "\n]);\n";
        }
        if (!empty($body) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $phpSnippet .= "curl_setopt(\$ch, CURLOPT_POSTFIELDS, " . var_export($body, true) . ");\n";
        }
        $phpSnippet .= "\$response = curl_exec(\$ch);\ncurl_close(\$ch);\necho \$response;";

        // 4. Python requests
        $pythonSnippet = "import requests\n\nurl = \"{$fullUrl}\"\n";
        if (!empty($headers)) {
            $pythonSnippet .= "headers = " . json_encode($headers, JSON_PRETTY_PRINT) . "\n";
        } else {
            $pythonSnippet .= "headers = {}\n";
        }
        if (!empty($body) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $pythonSnippet .= "data = " . $body . "\n";
            $pythonSnippet .= "response = requests.request(\"{$method}\", url, headers=headers, json=data)\n";
        } else {
            $pythonSnippet .= "response = requests.request(\"{$method}\", url, headers=headers)\n";
        }
        $pythonSnippet .= "print(response.json())";

        return [
            'curl' => $curlSnippet,
            'javascript' => $jsSnippet,
            'php' => $phpSnippet,
            'python' => $pythonSnippet,
        ];
    }

    private static function parseFile(string $file): ?array
    {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $content = file_get_contents($file);
        if ($ext === 'json') {
            return json_decode($content, true);
        }
        return Yaml::parse($content);
    }

    private static function countEndpoints(array $spec): int
    {
        $count = 0;
        $paths = $spec['paths'] ?? [];
        $methods = ['get', 'post', 'put', 'patch', 'delete', 'options', 'head'];
        foreach ($paths as $pathItem) {
            if (is_array($pathItem)) {
                foreach ($methods as $m) {
                    if (isset($pathItem[$m])) $count++;
                }
            }
        }
        return $count;
    }
}