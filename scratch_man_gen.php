<?php
define('SPP_BASE_DIR', __DIR__);
require_once __DIR__ . '/spp/global.php';

$commands = \SPP\CLI\CommandManager::discover();
$cmd = $commands['cache:clear'];

$ref = new ReflectionClass($cmd);
$source = file_get_contents($ref->getFileName());

// Simple analysis
$options = [];
preg_match_all("/str_starts_with\(\\$[a-zA-Z0-9_]+,\s*'(--[^']+)'\)/", $source, $matches1);
preg_match_all("/\\$[a-zA-Z0-9_]+\s*===\s*'(--[^']+)'/", $source, $matches2);

$options = array_merge($matches1[1] ?? [], $matches2[1] ?? []);
$options = array_unique($options);

$underTheHood = [];
preg_match_all("/([A-Z][a-zA-Z0-9_]*::[a-zA-Z0-9_]+)\(/", $source, $calls);
if (!empty($calls[1])) {
    $underTheHood = array_unique($calls[1]);
}

echo "Command: " . $cmd->getName() . "\n";
echo "Options: " . implode(", ", $options) . "\n";
echo "Under the hood: " . implode(", ", $underTheHood) . "\n";
