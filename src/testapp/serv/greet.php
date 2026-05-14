<?php
// Standalone service executing inside self-contained application boundary
$response = [
    'status' => 'ok',
    'message' => 'Greetings from self-contained application service!',
    'data' => ['app' => 'testapp', 'timestamp' => time()]
];
