<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once('vendor/autoload.php');
require_once('spp/sppinit.php');

// 1. Context Sync: Ensure 'q' is relative to the detected application base
$q = $_GET['q'] ?? '';
$context = \SPP\Scheduler::getContext();
if ($context !== '') {
    $baseUrl = trim(\SPP\App::getAppConf('base_url') ?: '/' . $context, '/');
    $qPath = trim($q, '/');
    if ($baseUrl !== '' && (str_starts_with($qPath, $baseUrl . '/') || $qPath === $baseUrl)) {
        $_GET['q'] = ltrim(substr($qPath, strlen($baseUrl)), '/');
    }
}

require_once('global.php');

\SPP\Core\MiddlewareKernel::run(function($request) {
    $context = \SPP\Scheduler::getContext() ?: 'default';
    \SPP\Scheduler::setContext($context);
    $appBaseUri = defined('APP_BASE_URI') ? APP_BASE_URI : '';
    $appAsset = function (string $path) use ($appBaseUri): string {
        return rtrim($appBaseUri, '/') . '/' . ltrim($path, '/');
    };

    // Register core scripts INSIDE the kernel
    \SPPMod\SPPView\ViewPage::addCssIncludeFile($appAsset('res/spp/css/spp.css'));
    \SPPMod\SPPView\ViewPage::addJsIncludeFile($appAsset('res/spp/js/spp.js'));
    \SPPMod\SPPView\ViewPage::addJsIncludeFile($appAsset('res/spp/js/sppvalidations.js'));
    \SPPMod\SPPView\ViewPage::addJsIncludeFile($appAsset('res/spp/js/monaco.js'));


    // Intercept native Hot Module Replacement (HMR) / Live-Reload state check requests
    $svc = $_GET['__svc'] ?? $_POST['__svc'] ?? '';



    if (class_exists('\SPPMod\SPPMigrate\SPPMigrate') && \SPPMod\SPPMigrate\SPPMigrate::isMigrateRequest()) {
        \SPPMod\SPPMigrate\SPPMigrate::handle();
        return;
    }

    if (\SPP\Module::isEnabled('sppapi') && class_exists('\SPPMod\SPPAPI\SPPAPI') && \SPPMod\SPPAPI\SPPAPI::isApiRequest()) {
        \SPPMod\SPPAPI\SPPAPI::handle();
        return;
    }

    if (\SPP\Module::isEnabled('sppajax') && \SPPMod\SPPAjax\SPPAjax::isAjaxRequest()) {
        \SPPMod\SPPAjax\SPPAjax::handle();
        return;
    }

    // SPP DX: AutoApiRouter injection for generic headless REST APIs
    $qPath = $_GET['q'] ?? '';
    if (str_starts_with($qPath, 'api/v1/')) {
        require_once SPP_BASE_DIR . '/core/AutoApiRouter.php';
        \SPP\Core\AutoApiRouter::handle();
        return;
    }

    $activeProc = \SPP\Scheduler::getActiveProc();
    if (method_exists($activeProc, 'handle')) {
        $activeProc->handle($request);
        return;
    }

    \SPPMod\SPPView\ViewPage::processForms();
    \SPPMod\SPPView\ViewPage::showPage();
});
