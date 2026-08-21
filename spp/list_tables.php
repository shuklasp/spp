<?php
$db = new PDO('sqlite:c:/projects/apache/school1/spp/etc/data/spp.sqlite');
foreach($db->query("SELECT name FROM sqlite_master WHERE type='table'") as $r) {
    echo $r[0]."\n";
}
