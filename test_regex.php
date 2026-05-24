<?php
$path = '/school1/lekhak/admin/sankhyaki';

if (preg_match('#/admin/sankhyaki/?$#', $path)) {
    echo "MATCHED\n";
} else {
    echo "NOT MATCHED\n";
}
