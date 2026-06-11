<?php

define('SPP_BASE_DIR', dirname(__DIR__));
require_once dirname(__DIR__) . '/spp/spp.php';

// Ensure the required classes are loaded
require_once dirname(__DIR__) . '/spp/modules/spp/sppdb/class.sppseeder.php';
require_once dirname(__DIR__) . '/spp/modules/spp/sppdb/class.sppfactory.php';
require_once dirname(__DIR__) . '/spp/modules/spp/sppdb/class.sppfaker.php';

echo "--- SPPDB Database Seeder ---\n";

$seedersDir = __DIR__ . '/seeders';
if (!is_dir($seedersDir)) {
    mkdir($seedersDir, 0755, true);
    echo "Created seeders directory at {$seedersDir}\n";
    echo "Please create a DatabaseSeeder.php class in the seeders directory.\n";
    exit(0);
}

// Check if DatabaseSeeder exists
$mainSeederFile = $seedersDir . '/DatabaseSeeder.php';
if (!file_exists($mainSeederFile)) {
    echo "Error: DatabaseSeeder.php not found in {$seedersDir}\n";
    echo "Please create a DatabaseSeeder class extending SPPMod\\SPPInterDB\\Seeding\\SPPSeeder.\n";
    exit(1);
}

require_once $mainSeederFile;

if (!class_exists('DatabaseSeeder')) {
    echo "Error: Class DatabaseSeeder not found in {$mainSeederFile}\n";
    exit(1);
}

echo "Running DatabaseSeeder...\n";

try {
    $seeder = new DatabaseSeeder();
    $seeder->run();
    echo "Database seeding completed successfully.\n";
} catch (\Exception $e) {
    echo "Seeding failed: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
