<?php
$_SERVER['REQUEST_URI'] = '/school1/lekhak/api/sppmigrate/sender/diff';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_HOST'] = 'localhost';
$_POST = [
    'target_url' => 'http://localhost/school1/lekhak',
    'api_key' => 'test'
];
require 'index.php';
