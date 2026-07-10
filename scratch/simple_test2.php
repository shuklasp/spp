<?php
$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\nX-Requested-With: XMLHttpRequest\r\nX-SPP-Ajax: 1\r\n",
        'content' => json_encode(['name' => 'enterprise.live']),
        'ignore_errors' => true // allow reading 403 response body
    ]
]);
$result = file_get_contents('http://localhost/school1/samvaad?action=service', false, $context);
echo $result;
