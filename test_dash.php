<?php
define('SPP_DEBUG', true);
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = '/school1/index.php';
$_SERVER['REQUEST_URI'] = '/school1/lekhak/admin';
$_GET['q'] = 'lekhak/admin';

require 'c:/projects/apache/school1/spp/sppinit.php';

\SPPMod\SPPAuth\SPPAuth::guard('web')->login((object) ['id' => 'admin', 'username' => 'admin', 'email' => 'admin@lekhak.local']);

require 'c:/projects/apache/school1/index.php';
