<?php
$j = json_decode(file_get_contents('src/ptable/data/master_elements.json'), true);
echo substr($j['Zn']['sections']['Physical properties'] ?? '', 0, 2000);
