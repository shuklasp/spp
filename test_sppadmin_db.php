<?php
$authContext = 'sppadmin';
require 'spp/sppinit.php';
try { \SPP\Scheduler::getProcObj($authContext); } catch (\Exception $e) { new \SPP\App($authContext); }
\SPP\Scheduler::setContext($authContext);

$db = new \SPPMod\SPPDB\SPPDB();
$adapter = (new \ReflectionClass($db))->getProperty('adapter')->getValue($db);
$xdb = (new \ReflectionClass($adapter))->getProperty('xdb')->getValue($adapter);
$engine = (new \ReflectionClass($xdb))->getProperty('adapter')->getValue($xdb);
$pdo = (new \ReflectionClass($engine))->getProperty('pdo')->getValue($engine);
echo "PDO Path: " . $engine->dbPath . "<br>\n";
