<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'spp/sppinit.php';
try { \SPP\Scheduler::getProcObj('sppadmin'); } catch (\Exception $e) { new \SPP\App('sppadmin'); }
\SPP\Scheduler::setContext('sppadmin');

$settings = \SPP\App::getGlobalSettings();
var_dump($settings);

echo "SuperAdmin setting: " . ($settings['admin_username'] ?? 'not set') . "\n";
