<?php
$f = 'c:\projects\apache\school1\spp\modules\spp\sppview\class.livecomponent.php';
$c = file_get_contents($f);
$c = str_replace("\x00", '', $c); // Remove UTF-16 NUL bytes
// Also remove trailing spaces, newlines, and any trailing } just in case
$c = rtrim($c, "}\r\n\t ");
$c .= "\n}\n";
file_put_contents($f, $c);
echo "Fixed!\n";
