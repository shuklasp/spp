<?php

/**
 * SPPDB IDE Helper Generator
 * Run this script via CLI to generate an _ide_helper.php file in your project root.
 * This file provides perfect IDE autocompletion for your SPPEntity classes.
 */

define('SPP_BASE_DIR', dirname(__DIR__));
require_once dirname(__DIR__) . '/spp.php';

$projectRoot = dirname(__DIR__, 2);
$outputFile = $projectRoot . '/_ide_helper.php';

echo "--- Generating IDE Helper ---\n";

// Recursive function to get all php files in a directory
function getPhpFiles($dir) {
    $files = [];
    if (!is_dir($dir)) return $files;
    
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
    return $files;
}

// Find all classes that extend SPPEntity
$allClasses = get_declared_classes();
// We also need to require project files to ensure classes are loaded if not using composer autoload
$appPath = $projectRoot . '/app'; // Assuming standard app folder, but we will scan projectRoot except vendor
$phpFiles = getPhpFiles($projectRoot);

foreach ($phpFiles as $file) {
    if (strpos($file, 'vendor') !== false || strpos($file, 'spp\\lib') !== false) {
        continue; // Skip vendor and libs
    }
    try {
        // Suppress output and errors during inclusion to avoid clutter
        @include_once $file;
    } catch (\Throwable $e) {
        // Skip files with errors
    }
}

$allClasses = get_declared_classes();
$entityClasses = [];

foreach ($allClasses as $class) {
    if (is_subclass_of($class, '\\SPPMod\\SPPEntity\\SPPEntity')) {
        $entityClasses[] = $class;
    }
}

$output = "<?php\n\n/**\n * A helper file for your IDE to provide autocompletion.\n * This file should not be included in your application execution.\n */\n\n";

foreach ($entityClasses as $class) {
    $reflection = new ReflectionClass($class);
    $namespace = $reflection->getNamespaceName();
    $shortName = $reflection->getShortName();
    
    $metadata = $class::getMetadata();
    $attributes = $metadata['attributes'] ?? [];
    
    if ($namespace) {
        $output .= "namespace {$namespace} {\n";
    } else {
        $output .= "namespace {\n";
    }
    
    $output .= "    /**\n";
    
    // Add properties
    foreach ($attributes as $field => $typeDef) {
        // Simple type inference from SQL type
        $typeStr = strtolower($typeDef);
        $phpType = 'string';
        if (strpos($typeStr, 'int') !== false) {
            $phpType = 'int';
        } elseif (strpos($typeStr, 'float') !== false || strpos($typeStr, 'decimal') !== false || strpos($typeStr, 'double') !== false) {
            $phpType = 'float';
        } elseif (strpos($typeStr, 'tinyint(1)') !== false || strpos($typeStr, 'bool') !== false) {
            $phpType = 'bool';
        }
        
        $output .= "     * @property {$phpType} \${$field}\n";
    }
    
    $output .= "     */\n";
    $output .= "    class {$shortName} extends \\SPPMod\\SPPEntity\\SPPEntity {}\n";
    $output .= "}\n\n";
}

file_put_contents($outputFile, $output);

echo "IDE Helper written to: {$outputFile}\n";
echo "Done.\n";
