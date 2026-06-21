<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$_SERVER['REQUEST_URI'] = '/school1/lekhak/admin/login';
$_SERVER['SCRIPT_NAME'] = '/school1/lekhak/index.php';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';

require_once 'index.php';
