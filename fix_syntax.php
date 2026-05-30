<?php
$files = [
    'AuthGroupAssignCommand.php',
    'AuthGroupMemberAddCommand.php',
    'AuthRoleAssignCommand.php',
    'AuthUserAssignCommand.php',
];

foreach ($files as $file) {
    $path = __DIR__ . '/spp/commands/' . $file;
    $content = file_get_contents($path);
    // Remove the stray case statement
    $content = preg_replace('/case \'[a-z0-9:]+\':\s+/', '', $content);
    // Replace $argv with $args
    $content = str_replace('$argv', '$args', $content);
    file_put_contents($path, $content);
    echo "Fixed $file\n";
}

$createApp = __DIR__ . '/spp/commands/CreateAppCommand.php';
$content = file_get_contents($createApp);
$content = str_replace('\$mode', '$mode', $content);
$content = str_replace('\$aiBlueprint', '$aiBlueprint', $content);
file_put_contents($createApp, $content);
echo "Fixed CreateAppCommand.php\n";

$badFiles = [
    'EntEditCommand.php',
    'EntManageCommand.php',
    'ViewPageAddCommand.php',
    'ViewServiceAddCommand.php'
];

foreach ($badFiles as $file) {
    $path = __DIR__ . '/spp/commands/' . $file;
    $content = file_get_contents($path);
    $content = str_replace('$argv', '$args', $content);
    file_put_contents($path, $content);
}
echo "Fixed argv in badFiles\n";

// Now fix the stray brackets in EntEdit and EntManage
// EntManageCommand has an extra } or similar?
// ViewPageAddCommand has an extra 'public' or something?
