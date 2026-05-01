<?php
$f = fopen('c:/projects/apache/school1/spp/core/class.module.php', 'r');
for($i=0;$i<363;$i++) fgets($f);
$line = fgets($f);
echo bin2hex($line);
