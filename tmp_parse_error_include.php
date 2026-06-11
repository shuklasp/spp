<?php
try {
    include_once __DIR__ . '/tmp_parse_error_file.php';
} catch (\Throwable $e) {
    echo "CAUGHT PARSE ERROR: " . $e->getMessage() . "\n";
}
