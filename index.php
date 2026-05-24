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


    if ($svc === 'drishyam:list') {
        header('Content-Type: application/json; charset=utf-8');
        $list = [];
        $app = class_exists('\SPP\App', false) ? \SPP\App::getApp() : null;
        $themeDir = ($app ? $app->getAppSrcDir() : SPP_APP_DIR . '/src/' . $context) . '/resources/themes';
        if (is_dir($themeDir)) {
            foreach (scandir($themeDir) as $item) {
                if ($item === '.' || $item === '..') continue;
                $path = $themeDir . '/' . $item;
                if (is_dir($path)) {
                    $isDrishyam = file_exists($path . '/theme.yml');
                    $isDrupal = !empty(glob($path . '/*.info.yml'));
                    $isWordPress = file_exists($path . '/style.css');
                    if ($isDrishyam || $isDrupal || $isWordPress) {
                        $title = ucfirst($item);
                        $ver = '1.0.0';
                        $desc = 'Custom standalone module environment.';
                        $icon = '📦';
                        $type = 'site';
                        if ($isDrishyam) {
                            $parsed = @\Symfony\Component\Yaml\Yaml::parseFile($path . '/theme.yml');
                            if (is_array($parsed)) {
                                $title = $parsed['name'] ?? $title;
                                $desc = $parsed['description'] ?? $desc;
                                $type = $parsed['type'] ?? $type;
                                $icon = $parsed['icon'] ?? '🔮';
                            }
                        } elseif ($isDrupal) {
                            $infoFiles = glob($path . '/*.info.yml');
                            $parsed = @\Symfony\Component\Yaml\Yaml::parseFile($infoFiles[0]);
                            if (is_array($parsed)) {
                                $title = $parsed['name'] ?? $title;
                                $desc = $parsed['description'] ?? $desc;
                                $ver = $parsed['version'] ?? $ver;
                                $icon = '💧';
                            }
                        } elseif ($isWordPress) {
                            $content = @file_get_contents($path . '/style.css', false, null, 0, 1024);
                            if ($content && preg_match('/Theme Name:\s*(.*)/i', $content, $m)) $title = trim($m[1]);
                            if ($content && preg_match('/Description:\s*(.*)/i', $content, $m)) $desc = trim($m[1]);
                            $icon = '📝';
                        }
                        if ($item === 'eduxpro') $icon = '💧';
                        $list[] = [
                            'id' => $item,
                            'title' => $title,
                            'ver' => $ver,
                            'type' => $type,
                            'desc' => strip_tags($desc),
                            'icon' => $icon
                        ];
                    }
                }
            }
        }
        echo json_encode($list);
        exit(0);
    }

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

    $activeProc = \SPP\Scheduler::getActiveProc();
    if (method_exists($activeProc, 'handle')) {
        $activeProc->handle($request);
        return;
    }

    \SPPMod\SPPView\ViewPage::processForms();
    \SPPMod\SPPView\ViewPage::showPage();
});
