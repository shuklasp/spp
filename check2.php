<?php
$d = json_decode(file_get_contents('c:\projects\apache\school1\ptable_data.json'), true);
print_r(array_keys($d['elements'][0]));
