<?php
$start = microtime(true);
$res = file_get_contents('http://[::1]/school1/index.php');
echo "Took " . (microtime(true) - $start) . " seconds.\n";
