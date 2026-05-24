<?php
require 'spp/sppinit.php';
require 'src/lekhak/modules/sankhyaki/module.php';
require 'src/lekhak/modules/sankhyaki/src/ConfigManager.php';
require 'src/lekhak/modules/sankhyaki/src/Controller/StatsController.php';

// Fake a few visits by manually calling the hook
$module = new \Lekhak\Modules\Sankhyaki\LekhakModuleSankhyaki();

$_SERVER['REMOTE_ADDR'] = '192.168.1.100';
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)';
$_SERVER['REQUEST_URI'] = '/some-article';
$_SERVER['HTTP_REFERER'] = 'https://www.google.com/search?q=lekhak+cms';
$_SESSION['uid'] = 0;
$module->hook_request_init();

$_SERVER['REMOTE_ADDR'] = '192.168.1.101';
$_SERVER['REQUEST_URI'] = '/about-us';
$_SERVER['HTTP_REFERER'] = 'https://www.bing.com/search?q=best+cms';
$module->hook_request_init();

$_SERVER['REMOTE_ADDR'] = '192.168.1.102';
$_SERVER['REQUEST_URI'] = '/contact';
$_SERVER['HTTP_REFERER'] = 'https://twitter.com/someuser';
$_SESSION['uid'] = 5;
$module->hook_request_init();

echo "Visits logged.\n";

// Test Standalone API
$_SERVER['REQUEST_URI'] = '/api/sankhyaki/stats';
ob_start();
$module->hook_request_init(); // This should trigger the exit; and output json
$out = ob_get_clean();
echo "Standalone API Output:\n" . $out . "\n";
