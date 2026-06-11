<?php
$base = 'c:/projects/apache/school1/spp';
$core = "$base/core";
$modules = "$base/modules/spp";

$moves = [
    // Cache
    "$core/class.cache.php" => "$modules/sppcache/src/SPPCacheManager.php",
    "$core/class.filecache.php" => "$modules/sppcache/src/FileCacheDriver.php",
    "$core/class.rediscache.php" => "$modules/sppcache/src/RedisCacheDriver.php",

    // Queue
    "$core/class.queue.php" => "$modules/sppqueue/src/SPPQueue.php",
    "$core/int.sppjob.php" => "$modules/sppqueue/src/SPPJobInterface.php",

    // Logger
    "$core/class.applogger.php" => "$modules/spplogger/src/AppLogger.php",
    "$core/class.psrloggeradapter.php" => "$modules/spplogger/src/PsrLoggerAdapter.php",
    "$core/class.requestlogger.php" => "$modules/spplogger/src/RequestLogger.php",

    // Storage
    "$core/class.storage.php" => "$modules/sppstorage/src/SPPStorage.php",

    // Workflow
    "$core/class.workflowmanager.php" => "$modules/sppworkflow/src/SPPWorkflowManager.php",
];

// Ensure dirs exist
@mkdir("$modules/sppcache/src", 0777, true);
@mkdir("$modules/sppqueue/src", 0777, true);
@mkdir("$modules/spplogger/src", 0777, true);
@mkdir("$modules/sppstorage/src", 0777, true);
@mkdir("$modules/sppworkflow/src", 0777, true);

foreach ($moves as $src => $dest) {
    if (file_exists($src)) {
        // Read content and rewrite namespace to the new module namespace?
        // Wait, if we keep the original class name and just move it, does it need a namespace change?
        // The classes in core currently don't use namespaces, they are SPP\Core\... or just global.
        // Actually, if we look at `class.cache.php`, it might be `namespace SPP\Core;`
        // If it's moved to a module, does the autoloader still find it if the namespace is `SPP\Core`?
        // The module autoloader expects `SPPMod\ModuleName\...`.
        // Let's just move them and then we can update the namespaces using another script or IDE.
        rename($src, $dest);
        echo "Moved $src to $dest\n";
    } else {
        echo "Warning: $src does not exist\n";
    }
}
