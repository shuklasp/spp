<?php

require_once 'spp/sppinit.php';

use SPPMod\Parikshak\Parikshak;
use SPPMod\SPPDB\SPPDB;
use SPPMod\SPPEntity\SPPEntity;

echo "Parikshak + Entity Validation Integration Test\n";
echo "==============================================\n\n";

// 1. Create a dynamic entity with validation rules
class ParikshakTestUser extends SPPEntity {
    
    public static function defineTestMetadata() {
        self::$_metadata[static::class] = [
            'table' => 'parikshak_test_users',
            'id_field' => 'id',
            'sequence' => 'parikshak_test_users_seq',
            'attributes' => [
                'username' => 'varchar(50)',
                'email' => 'varchar(100)',
                'age' => 'int'
            ],
            'validation' => [
                'username' => ['required', 'min:3'],
                'email' => ['required', 'email'],
                'age' => ['required']
            ]
        ];
    }
}

ParikshakTestUser::defineTestMetadata();
// ParikshakTestUser::install(); // Will be done by Parikshak's shadow table setup

// 2. Run Parikshak on this entity
$db = new SPPDB();
$engine = new Parikshak($db);

echo "Running Validation Scenario for ParikshakTestUser...\n";
$engine->testEntity(ParikshakTestUser::class, 'lekhak');
$results = $engine->getResults();
$report = $results['entities'][0];

echo "\nScenario Results:\n";
foreach ($report['scenarios'] as $s) {
    if ($s['name'] === 'Validation Metadata Invariants') {
        echo "  [Scenario] " . $s['name'] . ": " . strtoupper($s['status']) . "\n";
        if (isset($s['log'])) {
            foreach ($s['log'] as $msg) echo "    - $msg\n";
        }
        if ($s['status'] === 'failed') echo "    !! ERROR: " . $s['error'] . "\n";
    }
}

echo "\nEntity Status: " . strtoupper($report['status']) . "\n";

echo "\nTests Completed.\n";
