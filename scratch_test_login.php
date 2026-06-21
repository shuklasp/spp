<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
$_SERVER['REQUEST_URI'] = '/school1/lekhak/admin/login';
require 'vendor/autoload.php';
require 'spp/sppinit.php';
\SPP\Core\MiddlewareKernel::boot();
$app = new \App\Lekhak\LekhakApp('lekhak');
\SPP\Scheduler::regProc($app);
\SPP\Scheduler::setContext('lekhak');
$ctrl = new \App\Lekhak\Serv\AdminController();
echo "calling login...\n";
$out = $ctrl->login();
echo "login returned length: " . strlen($out) . "\n";
var_dump(substr($out, 0, 100));
