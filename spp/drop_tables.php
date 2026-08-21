<?php
$dbs = [
    'c:/projects/apache/school1/var/db/default.sqlite',
    'c:/projects/apache/school1/var/db/lekhak.sqlite',
    'c:/projects/apache/school1/var/db/merged_fix_verified.sqlite',
    'c:/projects/apache/school1/var/db/school.sqlite',
    'c:/projects/apache/school1/spp/var/data/spplive.sqlite'
];

foreach ($dbs as $file) {
    if (file_exists($file) && filesize($file) > 0) {
        $db = new PDO("sqlite:$file");
        $db->exec('DROP TABLE IF EXISTS spp_users');
        $db->exec('DROP TABLE IF EXISTS login_attempts');
        echo "Dropped from $file\n";
    }
}
