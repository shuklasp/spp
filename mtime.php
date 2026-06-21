<?php
$reg = require 'var/cache/modules_lekhak.php';
echo 'Cache mtime: ' . $reg['manifest_mtime'] . PHP_EOL;
echo 'File mtime: ' . filemtime('spp/etc/modules.yml') . PHP_EOL;
