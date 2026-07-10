<?php
$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\nX-Requested-With: XMLHttpRequest\r\n",
        'content' => json_encode(['name' => 'enterprise.live', 'action' => 'call_service'])
    ]
]);
$result = @file_get_contents('http://localhost/school1/samvaad?action=call_service', false, $context);
if ($result === false) {
    echo "ERROR: " . error_get_last()['message'] . "\n";
} else {
    echo $result;
}
