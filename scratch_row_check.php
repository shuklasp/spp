<?php
require_once 'spp/sppinit.php';
$db = new \SPPMod\SPPDB\SPPDB();
foreach(['roles', 'spp_roles', 'rights', 'spp_rights'] as $t) {
    if ($db->tableExists($t)) {
        $res = $db->execute_query("SELECT count(*) as cnt FROM $t");
        echo "Table '$t': " . $res[0]['cnt'] . " rows\n";
    }
}
