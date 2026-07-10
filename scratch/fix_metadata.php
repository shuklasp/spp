<?php
$f = 'spp/modules/spp/sppdb/class.sppentity.php';
$content = file_get_contents($f);
$content = str_replace('self::getMetadata(', 'static::getMetadata(', $content);
file_put_contents($f, $content);
echo "Done.";
