<?php
require_once('spp/sppinit.php');
echo "SPP_APP_DIR: " . SPP_APP_DIR . "\n<br>";
echo "APP_BASE_DIR: " . APP_BASE_DIR . "\n<br>";
$context = 'spp_docs';
$template = realpath(APP_BASE_DIR . "/resources/$context/views/node.blade.php");
echo "Template: " . var_export($template, true) . "\n<br>";
