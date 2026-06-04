<?php

$path = 'c:\projects\apache\school1\spp\modules\spp\sppview\class.viewtag.php';
$content = file_get_contents($path);

$mangled = "htmlspecialchars((string) \$val, ENT_QUOTES, 'UTF-8')";
$fixed = "htmlspecialchars(is_array(\$val) ? json_encode(\$val) : (string)\$val, ENT_QUOTES, 'UTF-8')";

$content = str_replace($mangled, $fixed, $content);
file_put_contents($path, $content);
echo "Fixed ViewTag attributes\n";
