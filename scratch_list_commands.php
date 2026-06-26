<?php
require_once 'spp/global.php';
$commands = \SPP\CLI\CommandManager::discover();
echo count($commands) . " commands found.\n";
foreach($commands as $name => $cmd) {
    $ref = new ReflectionClass($cmd);
    echo "$name: " . $ref->getFileName() . "\n";
}
