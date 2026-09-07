@php
    $method = strtoupper($params['method'] ?? 'GET');
    $path = $params['path'] ?? '/api';
    $summary = $params['summary'] ?? ($params['title'] ?? '');
    $description = $params['description'] ?? '';
    $serverUrl = $params['server'] ?? '';

    $endpoint = [
        'method' => $method,
        'path' => $path,
        'summary' => $summary,
        'description' => $description,
        'server_url' => $serverUrl,
        'parameters' => [],
        'request_body' => null,
    ];
@endphp

@spppartial('partials/api_endpoint_card.blade.php', ['endpoint' => $endpoint])