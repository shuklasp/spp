<?php
$d=json_decode(file_get_contents('c:\projects\apache\school1\src\ptable\data\master_elements.json'), true);
echo substr($d['U']['sections']['Applications'], 0, 500);
