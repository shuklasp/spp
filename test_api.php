<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    echo "ERROR: [$errno] $errstr in $errfile:$errline\n";
    return true;
});
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        echo "FATAL: {$error['message']} in {$error['file']}:{$error['line']}\n";
    }
});

// Mock HTTP environment
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['action'] = 'list_commands';
chdir(__DIR__ . '/spp/admin'); // Important: set CWD to what Apache would use
require 'api.php';
