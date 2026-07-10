<?php
$_SERVER['REQUEST_URI'] = '/'; $_SERVER['REQUEST_METHOD'] = 'GET';
require 'C:/projects/apache/school1/index.php';

$class = '\\App\\Samvaad\\comp\\live_demo';
$comp = new $class();
$ref = new ReflectionClass($comp);
echo $ref->getParentClass()->getFileName();
