<?php
define('SPP_NO_APP', true);
require 'spp/core/class.app.php';
\SPP\App::bootstrap(true);

\SPP\Scheduler::withContext('Samvaad', function() {
    $q = 'backend-showcase';
    // Use Reflection to call private findPageInAttributes
    $method = new ReflectionMethod(\SPPMod\SPPRouter\SPPRouter::class, 'findPageInAttributes');
    $method->setAccessible(true);
    
    $result = $method->invoke(null, $q, 'Samvaad');
    echo "RESULT FOR backend-showcase:\n";
    print_r($result);
    
    // Also, let's check what the cache file says!
    $cacheFile = SPP_BASE_DIR . '/var/cache/routes_Samvaad.php';
    echo "Cache file path: $cacheFile\n";
    if (file_exists($cacheFile)) {
        echo "Cache file contents:\n";
        print_r(include $cacheFile);
    } else {
        echo "Cache file does not exist!\n";
    }
});
