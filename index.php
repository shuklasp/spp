<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once('vendor/autoload.php');
require_once('spp/sppinit.php');

// 1. Context Sync: Ensure 'q' is relative to the detected application base
$q = $_GET['q'] ?? '';
$context = \SPP\Scheduler::getContext();
if ($context !== 'default') {
    $baseUrl = trim(\SPP\App::getAppConf('base_url'), '/');
    $qPath = trim($q, '/');
    if ($baseUrl !== '' && (str_starts_with($qPath, $baseUrl . '/') || $qPath === $baseUrl)) {
        $_GET['q'] = ltrim(substr($qPath, strlen($baseUrl)), '/');
    }
}

require_once('global.php');

\SPP\Core\MiddlewareKernel::run(function($request) {
    \SPP\Scheduler::setContext('lekhak');
    $appBaseUri = defined('APP_BASE_URI') ? APP_BASE_URI : '';
    $appAsset = function (string $path) use ($appBaseUri): string {
        return rtrim($appBaseUri, '/') . '/' . ltrim($path, '/');
    };

    // Register core scripts INSIDE the kernel
    \SPPMod\SPPView\ViewPage::addCssIncludeFile($appAsset('res/spp/css/spp.css'));
    \SPPMod\SPPView\ViewPage::addJsIncludeFile($appAsset('res/spp/js/spp.js'));
    \SPPMod\SPPView\ViewPage::addJsIncludeFile($appAsset('res/spp/js/sppvalidations.js'));

    if (\SPP\Module::isEnabled('sppux') && class_exists('\SPPMod\SPPUX\SPPUX')) {
        \SPPMod\SPPUX\SPPUX::boot();
    }

    if (\SPP\Module::isEnabled('sppapi') && class_exists('\SPPMod\SPPAPI\SPPAPI') && \SPPMod\SPPAPI\SPPAPI::isApiRequest()) {
        \SPPMod\SPPAPI\SPPAPI::handle();
        return;
    }

    if (\SPP\Module::isEnabled('sppajax') && \SPPMod\SPPAjax\SPPAjax::isAjaxRequest()) {
        \SPPMod\SPPAjax\SPPAjax::handle();
        return;
    }

    \SPPMod\SPPView\ViewPage::processForms();
    
    $activeProc = \SPP\Scheduler::getActiveProc();
    if (method_exists($activeProc, 'handle')) {
        file_put_contents(SPP_APP_DIR . '/var/logs/spp_debug.log', "INDEX.PHP DEBUG: activeProc is " . get_class($activeProc) . ". App getName() is " . \SPP\App::getApp()->getName() . "\n", FILE_APPEND);
        $activeProc->handle($request);
        return;
    }

    \SPPMod\SPPView\ViewPage::showPage();
});
