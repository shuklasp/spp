<?php
require 'spp/sppinit.php';
\SPP\Scheduler::setContext('default');

$p = new \SPPMod\Parikshak\Parikshak();
$p->testEntity('App\\Default\\Entities\\Dummyentity', 'default');
print_r($p->getResults());
