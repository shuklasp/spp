<?php
require_once __DIR__ . '/spp/sppinit.php';
\SPP\Scheduler::withContext('sppadmin', function() {
    try {
        $results = \SPP\Core\ModuleInstaller::installAllActive();
        print_r($results);
    } catch (\Exception $e) {
        echo "Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString();
    }
});
