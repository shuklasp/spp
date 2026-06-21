<?php
$start = microtime(true);
function tlog($msg) {
    global $start;
    echo number_format(microtime(true) - $start, 4) . "s: $msg\n";
}

  define('SPP_DEBUG', false);
  require 'c:/projects/apache/school1/spp/sppinit.php';
  tlog("After sppinit");

