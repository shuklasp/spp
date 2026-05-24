<?php
$p1 = 'src/lekhak/etc/installed_modules.json'; 
$d = json_decode(file_get_contents($p1), true); 
$d['drupal_test_module'] = 1000; 
file_put_contents($p1, json_encode($d)); 
$p2 = 'src/lekhak/etc/enabled_modules.json'; 
$d2 = json_decode(file_get_contents($p2), true); 
if(!in_array('drupal_test_module', $d2)) { 
    $d2[] = 'drupal_test_module'; 
} 
file_put_contents($p2, json_encode($d2));
