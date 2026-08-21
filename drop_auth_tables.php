<?php
$pdo = new PDO('sqlite:c:/projects/apache/school1/var/db/default.sqlite');
$tables = ['spp_users', 'spp_roles', 'spp_rights', 'spp_userroles', 'spp_roleright', 'spp_loginrec', 'spp_remember_tokens', 'spp_login_attempts', 'spp_sppgroup', 'spp_sppgroupmember'];
foreach ($tables as $t) {
    $pdo->exec("DROP TABLE IF EXISTS $t");
}
echo "Dropped tables.\n";
