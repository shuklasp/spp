<?php
require_once 'spp.php';
$db = new \SPPMod\SPPDB\SPPDB();
print_r($db->getAdapter()->getSchema('spp_users'));
