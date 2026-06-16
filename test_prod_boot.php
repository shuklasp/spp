<?php
define('SPP_DEBUG', false);
require 'spp/sppinit.php';
$app = \SPP\App::getApp('default');

if (file_exists('var/cache/modules_default.php')) {
    echo "Cache generated successfully.\n";
} else {
    echo "Cache NOT generated. Wait, the boot doesn't write it automatically in prod?\n";
}
