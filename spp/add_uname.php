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
        try {
            $db->exec("ALTER TABLE spp_users ADD COLUMN uname VARCHAR(50) DEFAULT ''");
            echo "Added uname to $file\n";
        } catch (Exception $e) {
            echo "Could not add uname to $file: " . $e->getMessage() . "\n";
        }
    }
}
