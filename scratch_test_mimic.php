<?php
require_once __DIR__ . '/spp/sppinit.php';

\SPP\Scheduler::withContext('sppadmin', function() {
    echo "Mimicking Modules.php...\n";
    \SPPMod\SPPXDB\SPP_XDB::enableQueryLog();
    \SPP\Module::loadAllModules();
    
    $table0 = \SPPMod\SppDb\SPPDB::sppTable('spp_modules');
    echo "Table0 before State1: " . $table0 . "\n";
    $state1 = \SPP\Core\ModuleInstaller::getModuleState('sppauth');
    echo "State1: " . json_encode($state1) . "\n";
    echo "DB Object ID 1: " . spl_object_id(\SPP\Core\ModuleInstaller::getDb()) . "\n";
    
    $dbConf = \SPP\Module::getAppConfig('sppdb', \SPP\Scheduler::getContext());
    echo "dbConf: " . json_encode($dbConf) . "\n";
    
    // Now installAllActive loops
    \SPP\Module::loadAllModules();
    
    echo "File Content before State2: " . substr(file_get_contents('C:\projects\apache\school1\spp\modules\spp\sppxdb\data\default\spp_modules.xml'), 0, 500) . "...\n";
    
    $table1 = \SPPMod\SppDb\SPPDB::sppTable('spp_modules');
    echo "Table1 before State2: " . $table1 . "\n";
    
    $adapter = \SPP\Core\ModuleInstaller::getDb()->getAdapter();
    $adapterRef = new \ReflectionClass($adapter);
    $xdbProp = $adapterRef->getProperty('xdb');
    $xdbProp->setAccessible(true);
    $xdbFacade = $xdbProp->getValue($adapter);
    
    $facadeRef = new \ReflectionClass($xdbFacade);
    $adapterProp2 = $facadeRef->getProperty('adapter');
    $adapterProp2->setAccessible(true);
    $xmlEngine = $adapterProp2->getValue($xdbFacade);
    
    $ref = new \ReflectionClass($xmlEngine);
    $filePathProp = $ref->getProperty('filePath');
    $filePathProp->setAccessible(true);
    echo "XMLEngine FilePath: " . $filePathProp->getValue($xmlEngine) . "\n";
    
    echo "File Content on disk (Engine path): " . substr(file_get_contents($filePathProp->getValue($xmlEngine)), 0, 500) . "...\n";
    
    $adapter2 = \SPP\Core\ModuleInstaller::getDb()->getAdapter();
    $adapterRef2 = new \ReflectionClass($adapter2);
    $xdbProp2 = $adapterRef2->getProperty('xdb');
    $xdbProp2->setAccessible(true);
    $xdbFacade2 = $xdbProp2->getValue($adapter2);
    
    $facadeRef2 = new \ReflectionClass($xdbFacade2);
    echo "xdbFacade Class: " . get_class($xdbFacade2) . "\n";
    $adapterProp22 = $facadeRef2->getProperty('adapter');
    $adapterProp22->setAccessible(true);
    $xmlEngine2 = $adapterProp22->getValue($xdbFacade2);

    echo "Is Same XMLEngine? " . ($xmlEngine === $xmlEngine2 ? "YES" : "NO") . "\n";
    
    $state2 = \SPP\Core\ModuleInstaller::getModuleState('sppauth');
    echo "State2: " . json_encode($state2) . "\n";
    echo "Query Log: " . print_r(\SPPMod\SPPXDB\SPP_XDB::getQueryLog(), true) . "\n";
    
    $rawRes = \SPP\Core\ModuleInstaller::getDb()->execute_query("SELECT * FROM spp_modules WHERE name = ?", ['sppauth']);
    echo "Raw Query Result: " . json_encode($rawRes) . "\n";
    
    // Test the install
    try {
        $res = \SPP\Core\ModuleInstaller::installAllActive();
        echo "InstallAllActive Result: " . print_r($res, true) . "\n";
    } catch (\Exception $e) {
        echo "Install Exception: " . $e->getMessage() . "\n";
    }
});
