<?php
require_once __DIR__ . '/spp/sppinit.php';

use SPPMod\SPPEntity\SppEntityQuery;

// Test entity with dynamic fields
$query = SppEntityQuery::forEntity('LekhakNode')
    ->where('status', 'published')
    ->whereDynamic('author_name', 'like', '%John%')
    ->orderBy('created_at', 'DESC')
    ->limit(10);

echo "SQL: " . $query->toSql() . "\n";
print_r($query->getBindings());

try {
    $results = $query->get();
    echo "Results count: " . count($results) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
