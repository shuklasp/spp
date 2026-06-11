<?php
require_once 'c:/projects/apache/school1/spp/sppinit.php';
$classes = get_declared_classes();
foreach ($classes as $c) {
    if (str_contains(strtolower($c), 'sppevent')) {
        echo $c . "\n";
    }
}
