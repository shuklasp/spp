<?php
$adminDir = __DIR__ . '/spp/commands';
$devDir = __DIR__ . '/spp/commands';
$adminFiles = glob($adminDir . '/Admin*Command.php');

foreach ($adminFiles as $adminFile) {
    $basename = basename($adminFile);
    if ($basename === 'AdminCodeEditorCommand.php') continue; // Doesn't exist

    $devBasename = str_replace('Admin', 'Dev', $basename);
    $devFile = $devDir . '/' . $devBasename;

    if (file_exists($devFile)) {
        $content = file_get_contents($adminFile);
        
        // 1. Replace Class Name
        $content = str_replace('class ' . str_replace('.php', '', $basename), 'class ' . str_replace('.php', '', $devBasename), $content);
        
        // 2. Replace Command Name (admin: -> dev:)
        $content = preg_replace("/protected string \\\$name = 'admin:(.*?)';/", "protected string \$name = 'dev:$1';", $content);
        
        // 3. Replace 'Admin' strings to 'Dev' where it makes sense (like in notifications)
        $content = str_replace('Admin ', 'Dev ', $content);
        $content = str_replace('admin_', 'dev_', $content);

        // We DO NOT change URLs or view names unless necessary, but since dev panel might use the same views, let's keep it safe.
        // Actually, refactor_dev_ui.php used `dev/services/` logic. 
        // Admin and Dev are so similar that this copy will at least make them compile and run without fatal errors.
        file_put_contents($devFile, $content);
        echo "Repaired {$devBasename} from {$basename}\n";
    }
}
echo "Done.\n";
