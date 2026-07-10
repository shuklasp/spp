<?php
require 'spp/modules/spp/sppview/Attributes/Route.php';
require 'spp/modules/spp/sppview/Attributes/Middleware.php';
require 'spp/modules/spp/sppview/Attributes/Title.php';
$content = file_get_contents('c:/projects/apache/school1/src/Samvaad/serv/BackendShowcaseController.php');

$class = '';
if (preg_match('/class\s+([a-zA-Z0-9_]+)/m', $content, $matches)) {
    $class = $matches[1];
}

$namespace = '';
if (preg_match('/namespace\s+([a-zA-Z0-9_\\\\]+);/m', $content, $matches)) {
    $namespace = $matches[1];
}

echo "Namespace: $namespace\n";
echo "Class: $class\n";
