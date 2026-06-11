<?php
try {
    eval('class A {');
} catch (\Throwable $e) {
    echo "CAUGHT PARSE ERROR: " . $e->getMessage() . "\n";
}
