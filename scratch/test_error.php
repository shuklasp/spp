<?php
define('SPP_BASE_DIR', 'c:\projects\apache\school1');
define('SPP_APP_DIR', 'c:\projects\apache\school1');
require 'c:\projects\apache\school1\spp\sppinit.php';
require 'c:\projects\apache\school1\spp\admin\services\Core.php';
class LA {
    public function setData($d) { print_r($d); }
    public function error($m) { echo "ERROR: $m\n"; }
    public function notify($m, $t) { echo "NOTIFY: $m\n"; }
}
$la = new LA();
try {
    live_Core_GetSystemInfo($la, []);
} catch (Throwable $e) {
    echo "Caught Exception: " . $e->getMessage() . " on line " . $e->getFile() . ':' . $e->getLine() . "\n";
}
