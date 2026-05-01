<?php
require_once 'spp/sppinit.php';
echo "APP_BASE_URI: " . (defined('APP_BASE_URI') ? APP_BASE_URI : 'NOT DEFINED') . "\n";
echo "Context: " . \SPP\Scheduler::getContext() . "\n";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'NOT SET') . "\n";
echo "DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'NOT SET') . "\n";
echo "SPP_APP_DIR: " . SPP_APP_DIR . "\n";
