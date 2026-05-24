<?php
$f = 'src/lekhak/etc/enabled_modules.json'; 
$d = json_decode(file_get_contents($f), true); 
if(!in_array('lekhak_peeche', $d)) { 
    $d[] = 'lekhak_peeche'; 
    file_put_contents($f, json_encode($d, JSON_PRETTY_PRINT)); 
    echo 'Enabled lekhak_peeche'; 
} else {
    echo 'Already enabled';
}
