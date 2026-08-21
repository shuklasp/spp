<?php
$json = json_decode(file_get_contents('c:\projects\apache\school1\src\ptable\data\master_elements.json'), true);
$php = '<?php return ' . var_export($json, true) . ';';
file_put_contents('c:\projects\apache\school1\src\ptable\data\master_elements.php', $php);
echo "Compiled successfully.\n";
