<?php
$_SERVER['REQUEST_URI'] = '/school1/lekhak/sppadmin/migrate';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';
require 'spp/sppinit.php';

var_dump([
    'isEnabled' => \SPP\Module::isEnabled('sppmigrate'),
    'class_exists' => class_exists('\SPPMod\SPPMigrate\SPPMigrate'),
    'isMigrateRequest' => class_exists('\SPPMod\SPPMigrate\SPPMigrate') ? \SPPMod\SPPMigrate\SPPMigrate::isMigrateRequest() : false
]);
