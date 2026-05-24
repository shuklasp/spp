<?php
require 'spp/sppinit.php';
require 'src/lekhak/modules/sankhyaki/module.php';
require 'src/lekhak/modules/sankhyaki/src/DeviceParser.php';
require 'src/lekhak/modules/sankhyaki/src/GeoLocator.php';
require 'src/lekhak/modules/sankhyaki/src/ConfigManager.php';
require 'src/lekhak/modules/sankhyaki/src/Controller/StatsController.php';

$module = new \Lekhak\Modules\Sankhyaki\LekhakModuleSankhyaki();

// Test Advanced Tracking
$_SERVER['REMOTE_ADDR'] = '8.8.8.8'; // Should geolocate to US
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36';
$_SERVER['REQUEST_URI'] = '/promo-page?utm_source=twitter&utm_campaign=summer_sale&utm_medium=social';
$_SERVER['HTTP_REFERER'] = 'https://twitter.com/';
$_GET['utm_source'] = 'twitter';
$_GET['utm_medium'] = 'social';
$_GET['utm_campaign'] = 'summer_sale';
$_SESSION['uid'] = 0;

$module->hook_request_init();
echo "Advanced visit logged.\n";

// Test iPhone visit
$_SERVER['REMOTE_ADDR'] = '142.250.190.46'; // Google IP (US)
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.5 Mobile/15E148 Safari/604.1';
$_SERVER['REQUEST_URI'] = '/home';
$_GET = []; // reset UTM
$module->hook_request_init();
echo "Mobile visit logged.\n";

// Test Ping (Time on Page update)
$_SERVER['REQUEST_URI'] = '/api/sankhyaki/ping';
$_SERVER['REQUEST_METHOD'] = 'POST';
$pingData = json_encode([
    'session_id' => session_id() ?: md5('142.250.190.46' . $_SERVER['HTTP_USER_AGENT']), // Match the second visit
    'url' => '/home',
    'time_on_page' => 120
]);

// Since the module reads from php://input, we can't easily mock that directly in a generic PHP script without stream wrappers.
// But we can check StatsController output instead.

$c = new \Lekhak\Modules\Sankhyaki\Controller\StatsController();
echo $c->getStats();
