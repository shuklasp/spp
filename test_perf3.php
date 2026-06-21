<?php
$start = microtime(true);
function tlog($msg) {
    global $start;
    echo number_format(microtime(true) - $start, 4) . "s: $msg\n";
}

require 'c:/projects/apache/school1/spp/sppinit.php';
tlog("After sppinit");

$s1 = microtime(true);
$settings = \SPP\App::getGlobalSettings();
$e1 = microtime(true);
tlog("getGlobalSettings took " . ($e1 - $s1));

$s2 = microtime(true);
$app = \SPP\App::getApp('lekhak');
$e2 = microtime(true);
tlog("getApp took " . ($e2 - $s2));

