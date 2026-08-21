<?php
$dir = __DIR__ . '/spp/commands';
$files = glob($dir . '/*.php');

$zeroIndexedCommands = [
    'AskCommand.php',
    'ConfigCommand.php',
    'ConfigSyncCommand.php',
    'ExtDisableCommand.php',
    'ExtEnableCommand.php',
    'IntegrationRestoreCommand.php',
    'IntegrationSeedCommand.php'
];

// Some exceptions to manually review
$exceptions = [
    'MakePolyglotCommand.php', 
    'DeleteAppCommand.php', 
    'DbSyncCommand.php', 
    'CompileRegistryCommand.php',
    'SysStatusCommand.php'
];

foreach ($files as $file) {
    $basename = basename($file);
    if (in_array($basename, $exceptions)) {
        continue;
    }

    $content = file_get_contents($file);
    $newContent = $content;

    if (in_array($basename, $zeroIndexedCommands)) {
        // Replace $args[0], $args[1], $args[2] -> getArgument(0), getArgument(1), getArgument(2)
        $newContent = preg_replace('/\$args\[0\]/', '$this->getArgument($args, 0)', $newContent);
        $newContent = preg_replace('/\$args\[1\]/', '$this->getArgument($args, 1)', $newContent);
        $newContent = preg_replace('/\$args\[2\]/', '$this->getArgument($args, 2)', $newContent);
        $newContent = preg_replace('/\$args\[3\]/', '$this->getArgument($args, 3)', $newContent);
    } else {
        // Standard commands where $args[2] is the first argument
        $newContent = preg_replace('/\$args\[2\]/', '$this->getArgument($args, 0)', $newContent);
        $newContent = preg_replace('/\$args\[3\]/', '$this->getArgument($args, 1)', $newContent);
        $newContent = preg_replace('/\$args\[4\]/', '$this->getArgument($args, 2)', $newContent);
        $newContent = preg_replace('/\$args\[5\]/', '$this->getArgument($args, 3)', $newContent);
    }

    if ($newContent !== $content) {
        file_put_contents($file, $newContent);
        echo "Refactored {$basename}\n";
    }
}
