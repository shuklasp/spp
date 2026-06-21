<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$_SERVER['REQUEST_URI'] = '/school1/lekhak/admin';
$_GET['q'] = 'lekhak/admin';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SCRIPT_NAME'] = '/school1/index.php';

require_once 'vendor/autoload.php';
require_once 'spp/sppinit.php';
\SPP\Core\MiddlewareKernel::boot();
$ref = new ReflectionClass(\SPP\Core\MiddlewareKernel::class);
$prop = $ref->getProperty('middleware');
$prop->setAccessible(true);
var_dump($prop->getValue());
