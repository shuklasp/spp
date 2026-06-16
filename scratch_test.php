<?php
require 'spp/sppinit.php';
\SPP\Module::setConfig('dbtype', 'sqlite', 'sppdb');
\SPP\Module::setConfig('sqlite_path', ':memory:', 'sppdb');
try {
    $manager = new \SPPMod\Sppdb\Migration\SPPMigrationManager('default');
    $reflection = new \ReflectionClass($manager);
    $method = $reflection->getMethod('ensureMigrationsTable');
    $method->setAccessible(true);
    $method->invoke($manager);
    
    $prop = $reflection->getProperty('db');
    $prop->setAccessible(true);
    $db = $prop->getValue($manager);
    $stmt = $db->getPDO()->query('SELECT name FROM sqlite_master WHERE type="table"');
    var_dump($stmt->fetchAll(\PDO::FETCH_ASSOC));
    
    echo "SUCCESS\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
