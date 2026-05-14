<?php
$db = new \PDO('sqlite:spp/var/db/lekhak.db');
$s = $db->query("SELECT * FROM users WHERE username='admin'")->fetch(PDO::FETCH_ASSOC);
print_r($s);
