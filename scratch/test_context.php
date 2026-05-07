<?php
$_SERVER['REQUEST_URI'] = '/spp/admin/index.php';
$_SERVER['SCRIPT_NAME'] = '/spp/admin/index.php';
$_SERVER['DOCUMENT_ROOT'] = 'c:\\projects\\apache\\school1';

require_once 'spp/sppinit.php';
// sppinit calls detectAndEnforceContext

echo "Context: " . \SPP\Scheduler::getContext() . "\n";
echo "Session Name: " . \SPP\App::getSessionName() . "\n";
