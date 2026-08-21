<?php
$d = json_decode(file_get_contents('c:\projects\apache\school1\elements.json'), true);
print_r(array_keys($d[0]));
