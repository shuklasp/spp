<?php
// Scratch script to test SPP Integration Module

// Mock SPP Autoloader/Bootstrap
require_once __DIR__ . '/spp/modules/spp/sppintegrations/class.sppintegrations.php';
require_once __DIR__ . '/spp/modules/contrib/appdrivers/class.appdrivers.php';

use SPPMod\SPPIntegrations\IntegrationFactory;

echo "Registered Drivers:\n";
print_r(IntegrationFactory::getRegisteredDrivers());

echo "\nInstantiating phpBB driver...\n";
try {
    $driver = IntegrationFactory::getDriver('phpbb', ['base_url' => 'http://localhost/phpbb']);
    echo "Successfully instantiated: " . get_class($driver) . "\n";
    echo "Is Subclass of AbstractDriver: " . (is_subclass_of($driver, \SPPMod\SPPIntegrations\AbstractDriver::class) ? 'Yes' : 'No') . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
