<?php
$base = 'c:/projects/apache/school1/spp/modules/spp';

function mergeDirs($srcDir, $destDir) {
    if (!is_dir($srcDir)) return;
    if (!is_dir($destDir)) mkdir($destDir, 0777, true);
    
    $dir = opendir($srcDir);
    while (($file = readdir($dir)) !== false) {
        if ($file === '.' || $file === '..') continue;
        
        $srcFile = $srcDir . '/' . $file;
        $destFile = $destDir . '/' . $file;
        
        if (is_dir($srcFile)) {
            mergeDirs($srcFile, $destFile);
        } else {
            // If it's config.yml, don't overwrite if it exists, maybe we can combine them later
            if ($file === 'config.yml' && file_exists($destFile)) {
                echo "Skipped config.yml merge for $destDir\n";
                continue;
            }
            if (file_exists($destFile)) {
                echo "File conflict: $destFile\n";
                continue;
            }
            rename($srcFile, $destFile);
            echo "Moved $srcFile to $destFile\n";
        }
    }
    closedir($dir);
    // Try to remove empty src dir
    @rmdir($srcDir);
}

// 1. sppinterdb -> sppdb
mergeDirs("$base/sppinterdb", "$base/sppdb");

// 2. sppentity -> sppdb
mergeDirs("$base/sppentity", "$base/sppdb");

// 3. sppajax -> sppapi
mergeDirs("$base/sppajax", "$base/sppapi");

// 4. sppblade -> drishyam
mergeDirs("$base/sppblade", "$base/drishyam");

// 5. sppux -> drishyam
mergeDirs("$base/sppux", "$base/drishyam");

echo "Merge complete.\n";
