<?php
$f = "spp/modules/spp/sppauth/class.webguard.php";
$c = file_get_contents($f);
$c = str_replace(
    "// Fail silently, assume logged out if DB check fails\n                    \$this->logout();\n                    return false;",
    "// Fail silently, assume loginrec is missing, DO NOT LOG OUT\n",
    $c
);
file_put_contents($f, $c);
