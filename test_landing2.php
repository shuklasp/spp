<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['q'] = 'lekhak/admin/landing';

try {
    require 'c:/projects/apache/school1/index.php';
} catch (\Throwable $e) {
    echo "Exception Caught: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
