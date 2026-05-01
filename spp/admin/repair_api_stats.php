<?php
$path = 'c:\projects\apache\school1\spp\admin\api.php';
$content = file_get_contents($path);

// 1. Update get_system_info handler to include Orion engine stats
$target = "case 'get_system_info':";
$orionStats = "
        case 'get_system_info':
            // Orion: Collect engine stats
            \$cacheFile = SPP_APP_DIR . '/var/cache/modules_default.php';
            \$orion = [
                'cache_exists' => file_exists(\$cacheFile),
                'cache_time' => file_exists(\$cacheFile) ? date('Y-m-d H:i:s', filemtime(\$cacheFile)) : 'None',
                'cache_size' => file_exists(\$cacheFile) ? round(filesize(\$cacheFile) / 1024, 2) . ' KB' : '0 KB',
                'registry_file' => SPP_APP_DIR . '/var/module_versions.yml',
                'registry_exists' => file_exists(SPP_APP_DIR . '/var/module_versions.yml')
            ];
";

// Use a unique marker in the file to insert after
$content = str_replace("case 'get_system_info':", $orionStats, $content);

// Update the sendResponse inside get_system_info to include $orion
$oldResponse = "sendResponse(true, [
                'spp_version' => \\SPP\\Module::VERSION,
                'php_version' => PHP_VERSION,";

$newResponse = "sendResponse(true, [
                'orion' => \$orion,
                'spp_version' => \\SPP\\Module::VERSION,
                'php_version' => PHP_VERSION,";

$content = str_replace($oldResponse, $newResponse, $content);

file_put_contents($path, $content);
echo "Injected Orion stats into get_system_info\n";
