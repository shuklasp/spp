<?php
define('SPP_BASE_DIR', __DIR__);
require_once __DIR__ . '/spp/spp.php';

$commands = \SPP\CLI\CommandManager::discover();
$list = [];
foreach ($commands as $name => $cmd) {
    $ref = new \ReflectionClass($cmd);
    $list[$name] = $ref->getFileName();
}
file_put_contents('commands_list.json', json_encode($list, JSON_PRETTY_PRINT));
echo "Saved " . count($list) . " commands to commands_list.json\n";
