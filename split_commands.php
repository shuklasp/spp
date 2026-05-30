<?php

$sppPhp = file_get_contents(__DIR__ . '/spp/spp.php');

$start = strpos($sppPhp, 'switch ($command) {');
if ($start === false) {
    die("Switch not found\n");
}

$switchBlock = substr($sppPhp, $start);

// Match each case block
preg_match_all('/case \'([a-z0-9:]+)\':\s*(.*?)\s*break;/s', $switchBlock, $matches, PREG_SET_ORDER);

$commandsDir = __DIR__ . '/spp/commands';
if (!is_dir($commandsDir)) mkdir($commandsDir, 0777, true);

foreach ($matches as $match) {
    $commandName = $match[1];
    $commandBody = $match[2];

    if (strpos($commandBody, 'has been migrated') !== false) {
        continue;
    }

    // Convert command name to Class Name (e.g., auth:user:list -> AuthUserListCommand)
    $parts = explode(':', $commandName);
    $className = '';
    foreach ($parts as $p) {
        $className .= ucfirst($p);
    }
    $className .= 'Command';

    // Format body
    $lines = explode("\n", $commandBody);
    $indentedBody = implode("\n        ", $lines);

    $classContent = <<<PHP
<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class {$className} implements Command
{
    public function execute(array \$args): void
    {
        \$command = \$args[1] ?? '';
        {$indentedBody}
    }

    public function getName(): string
    {
        return '{$commandName}';
    }

    public function getDescription(): string
    {
        return 'Legacy port of {$commandName}';
    }
}
PHP;

    file_put_contents($commandsDir . '/' . $className . '.php', $classContent);
    echo "Generated {$className}.php for {$commandName}\n";
}
