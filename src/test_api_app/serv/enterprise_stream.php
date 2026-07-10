<?php
/**
 * Enterprise SSE Stream Service — Samvaad
 * Handled via StreamDispatcher
 */

$events = [
    ['type' => 'INSERT', 'table' => 'users', 'id' => 1042],
    ['type' => 'UPDATE', 'table' => 'orders', 'id' => 884],
    ['type' => 'DELETE', 'table' => 'sessions', 'id' => 'abc123xyz'],
    ['type' => 'INSERT', 'table' => 'audit_logs', 'id' => 9912],
    ['type' => 'UPDATE', 'table' => 'inventory', 'id' => 45]
];

// If using StreamDispatcher, $sseEmit is injected
if (isset($sseEmit) && is_callable($sseEmit)) {
    // StreamDispatcher already sent headers and the start event.
    
    // Simulate streaming for 100 iterations
    for ($i = 0; $i < 100; $i++) {
        if (connection_aborted()) break;
        
        $event = $events[array_rand($events)];
        $payload = [
            'timestamp' => date('H:i:s'),
            'operation' => $event['type'],
            'table' => $event['table'],
            'record_id' => $event['id']
        ];
        
        $sseEmit('progress', $payload);
        
        usleep(400000); // 400ms delay between events
    }
    
    // StreamDispatcher will automatically emit 'complete' when this file ends.
} else {
    // Fallback if accessed directly (shouldn't happen with __spa_stream, but just in case)
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');

    while (ob_get_level()) { ob_end_flush(); }

    echo "event: start
";
    echo "data: {\"message\": \"Connected to CDC Stream\"}

";
    flush();

    for ($i = 0; $i < 100; $i++) {
        if (connection_aborted()) break;
        
        $event = $events[array_rand($events)];
        $payload = [
            'timestamp' => date('H:i:s'),
            'operation' => $event['type'],
            'table' => $event['table'],
            'record_id' => $event['id']
        ];
        
        echo "event: progress
";
        echo "data: " . json_encode($payload) . "

";
        flush();
        
        usleep(400000);
    }

    echo "event: complete
";
    echo "data: {\"status\": \"success\"}

";
    flush();
}
