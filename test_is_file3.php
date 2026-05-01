<?php
require_once('spp/sppinit.php');
echo "Scheduler Context: " . \SPP\Scheduler::getContext() . "\n<br>";
echo "App getName(): " . \SPP\App::getApp()->getName() . "\n<br>";

// Reflect into SPPBlade to see what viewsPath it used
$blade = new \SPPMod\SPPBlade\SPPBlade();
$reflection = new \ReflectionClass($blade);
$prop = $reflection->getProperty('viewsPath');
$prop->setAccessible(true);
echo "Blade viewsPath: " . $prop->getValue($blade) . "\n<br>";
