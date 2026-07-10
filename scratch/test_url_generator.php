<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../spp/sppinit.php';

// Force SPPApp Context


echo "Base URL: " . \SPP\App::getBaseUrl() . "\n";
echo "Has Rewriting: " . (\SPP\App::hasUrlRewriting() ? 'YES' : 'NO') . "\n";
echo "URL Output for 'dashboard': " . \SPP\App::url('dashboard') . "\n";

// Let's test blade rendering manually
$blade = new \SPPMod\Drishyam\SPPBlade();
echo "Testing Blade Output:\n";
try {
    file_put_contents(__DIR__ . '/test_blade_url.blade.php', "Link: <a href=\"@url('about')\">About</a>\nLink2: <a href=\"@sppurl('contact')\">Contact</a>");
    echo $blade->render('test_blade_url', [], __DIR__);
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
