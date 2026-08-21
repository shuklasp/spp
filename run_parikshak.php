<?php
require 'spp/sppinit.php';
$p = new \SPPMod\Parikshak\Parikshak();
$p->testEntity('App\\Default\\Entities\\Testentity', 'default');
print_r($p->getResults());
