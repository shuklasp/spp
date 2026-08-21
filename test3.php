<?php
$j = json_decode(file_get_contents('ptable_data.json'), true);
$z = array_filter($j['elements'], function($e) { return $e['symbol'] === 'Zn'; });
print_r(current($z));
