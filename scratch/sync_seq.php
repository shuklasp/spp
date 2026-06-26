<?php
define('SPP_PATH', __DIR__ . '/spp');
require_once 'spp/sppinit.php';
new \SPP\App('lekhak', false, 1);
\SPP\Scheduler::setContext('lekhak');
$db = new \SPPMod\SPPDB\SPPDB();

try {
    // Get max ID from lek_nodes
    $res = $db->query('SELECT MAX(id) as maxid FROM lek_nodes');
    $row = $res->fetch();
    $maxId = (int) ($row['maxid'] ?? 0);
    echo "Max ID in lek_nodes: $maxId\n";

    $seqName = 'lekhaknode_seq';
    $nextVal = $maxId + 1;

    // Update or Insert sequence
    $check = $db->query("SELECT * FROM sequences WHERE seqname='$seqName'");
    if ($check->fetch()) {
        echo "Updating sequence $seqName to $nextVal...\n";
        $db->exec("UPDATE sequences SET seqval=$nextVal WHERE seqname='$seqName'");
    } else {
        echo "Inserting sequence $seqName with $nextVal...\n";
        $db->exec("INSERT INTO sequences (seqname, initval, seqval, incval, lastaccess) VALUES ('$seqName', 1, $nextVal, 1, " . time() . ")");
    }

    echo "Sequence synchronized successfully.\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
