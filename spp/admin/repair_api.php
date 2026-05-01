<?php
$path = 'c:\projects\apache\school1\spp\admin\api.php';
$content = file_get_contents($path);

// 1. Add compile_registry and migration cases before toggle_module
$newCases = "
        case 'compile_registry':
            try {
                \$compiler = new \\SPP\\Core\\ModuleCompiler();
                \$compiler->compile('default');
                sendResponse(true, [], 'Module registry cache rebuilt successfully.');
            } catch (\\Exception \$e) {
                sendResponse(false, [], 'Compilation failed: ' . \$e->getMessage());
            }
            break;

        case 'run_migrations':
            try {
                \$vm = new \\SPP\\Core\\VersionManager();
                \$log = \$vm->syncAll();
                sendResponse(true, ['log' => \$log], 'Migrations executed successfully.');
            } catch (\\Exception \$e) {
                sendResponse(false, [], 'Migration failed: ' . \$e->getMessage());
            }
            break;

        case 'get_migration_status':
            try {
                \$vm = new \\SPP\\Core\\VersionManager();
                \$status = \$vm->getRegistry();
                sendResponse(true, ['registry' => \$status]);
            } catch (\\Exception \$e) {
                sendResponse(false, [], 'Failed to fetch migration status.');
            }
            break;
";

$content = str_replace("case 'toggle_module':", $newCases . "        case 'toggle_module':", $content);

// 2. Add auto-compile to toggle_module
$toggleTarget = "\$updatedFiles = \\SPP\\Module::toggleModuleStatus(\$modname, \$newStatus);";
$toggleFixed = "\$updatedFiles = \\SPP\\Module::toggleModuleStatus(\$modname, \$newStatus);
                // Orion: Auto-recompile registry on status change
                try {
                    \$compiler = new \\SPP\\Core\\ModuleCompiler();
                    \$compiler->compile('default');
                } catch (\\Exception \$e) {
                    // Log error but don't fail the toggle
                    error_log('Orion Auto-compile failed: ' . \$e->getMessage());
                }";

$content = str_replace($toggleTarget, $toggleFixed, $content);

file_put_contents($path, $content);
echo "Updated Admin API for Orion integration\n";
