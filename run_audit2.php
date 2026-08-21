<?php
require 'spp/sppinit.php';
\SPP\Scheduler::setContext('default');

$db = new \SPPMod\SPPDB\SPPDB();
\SPPMod\SPPAudit\SPPAudit::install();

$tableName = $db->sppTable('audit_logs');
$data = [
    'entity_type' => 'App\Default\Entities\Dummyentity',
    'entity_id' => '1',
    'action' => 'create',
    'old_values' => null,
    'new_values' => null,
    'user_id' => null,
    'ip_address' => '127.0.0.1',
    'created_at' => date('Y-m-d H:i:s')
];
$fields = implode(', ', array_keys($data));
$placeholders = implode(', ', array_fill(0, count($data), '?'));
$sql = "INSERT INTO {$tableName} ({$fields}) VALUES ({$placeholders})";

try {
    $db->exec_squery($sql, $tableName, array_values($data));
    echo "INSERT SUCCESS!\n";
} catch (\Throwable $e) {
    echo "INSERT ERROR: " . $e->getMessage() . "\n";
}

$audit = $db->exec_squery("SELECT * FROM %tab% WHERE entity_type = ? AND entity_id = ? AND action = 'create' ORDER BY id DESC LIMIT 1", $tableName, [
    'App\Default\Entities\Dummyentity',
    '1'
]);
var_dump($audit);
