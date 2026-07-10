<?php
$f = 'c:\projects\apache\school1\spp\modules\spp\sppview\class.livecomponent.php';
$c = file_get_contents($f);
$c = rtrim($c);
$c .= "\n}\n";
file_put_contents($f, $c);
echo "Added brace!\n";
