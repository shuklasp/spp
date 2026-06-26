<?php

require_once('vendor/autoload.php');
require_once('spp/sppinit.php');

// 1. Context Sync: Ensure 'q' is relative to the detected application base
if (file_exists(__DIR__ . '/.maintenance')) {
    http_response_code(503);
    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>503 Service Unavailable</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { 
            --bg: #09090b; 
            --surface: rgba(24, 24, 27, 0.7); 
            --primary: #f59e0b; 
            --text: #f4f4f5; 
            --muted: #a1a1aa; 
            --border: rgba(255,255,255,0.08);
            --glow: rgba(245, 158, 11, 0.15);
        }
        body { 
            margin: 0; 
            background: var(--bg); 
            background-image: radial-gradient(circle at center, var(--glow), transparent 600px);
            color: var(--text); 
            font-family: "Inter", sans-serif; 
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            text-align: center;
        }
        .container { 
            max-width: 600px; 
            padding: 50px; 
            background: var(--surface); 
            backdrop-filter: blur(12px);
            border-radius: 20px; 
            border: 1px solid var(--border);
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); 
        }
        .icon { font-size: 64px; margin-bottom: 20px; }
        .message { font-size: 24px; font-weight: 600; margin: 20px 0; color: var(--primary); }
        .desc { color: var(--muted); line-height: 1.6; }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🚧</div>
        <div class="message">System Maintenance</div>
        <div class="desc">' . htmlspecialchars(file_get_contents(__DIR__ . '/.maintenance') ?: 'We are currently performing a scheduled deployment. The system will be back online shortly.') . '</div>
    </div>
</body>
</html>';
    exit;
}
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

\SPP\Core\MiddlewareKernel::run(function ($request) {
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



    // Intercept Deployment Requests globally (bypasses any App)
    if (class_exists('\SPPMod\SPPDeploy\SPPDeploy') && \SPPMod\SPPDeploy\SPPDeploy::isDeployRequest()) {
        \SPPMod\SPPDeploy\SPPDeploy::handle();
        return;
    }

    if (\SPP\Module::isEnabled('sppapi') && class_exists('\SPPMod\SPPAPI\SPPAPI') && \SPPMod\SPPAPI\SPPAPI::isApiRequest()) {
        \SPPMod\SPPAPI\SPPAPI::handle();
        return;
    }

    if (\SPP\Module::isEnabled('sppajax') && class_exists('\SPPMod\SPPAPI\SPPAjax') && \SPPMod\SPPAPI\SPPAjax::isAjaxRequest()) {
        \SPPMod\SPPAPI\SPPAjax::handle();
        return;
    }

    // SPP DX: AutoApiRouter injection for generic headless REST APIs
    $qPath = $_GET['q'] ?? '';

    // Route SCIM API endpoints
    if (str_starts_with($qPath, 'scim/v2/')) {
        require_once SPP_BASE_DIR . '/modules/spp/sppauth/class.scim_handler.php';
        $handler = new \SPPMod\SPPAuth\SCIMHandler();
        $endpoint = substr($qPath, 8); // remove 'scim/v2/'
        $parts = explode('/', $endpoint);
        $resource = $parts[0] ?? '';
        $payload = json_decode(file_get_contents('php://input'), true) ?: [];
        if (isset($parts[1])) {
            $payload['id'] = $parts[1]; // pass ID in payload for updates
        }
        $handler->handleRequest($_SERVER['REQUEST_METHOD'], $resource, $payload);
        return;
    }

    // Route OAuth 2.0 endpoints
    if (str_starts_with($qPath, 'oauth/')) {
        require_once SPP_BASE_DIR . '/modules/spp/sppauth/class.oauth_server.php';
        $server = new \SPPMod\SPPAuth\OAuthServer();
        if ($qPath === 'oauth/authorize') {
            $clientId = $_GET['client_id'] ?? '';
            $redirectUri = $_GET['redirect_uri'] ?? '';
            $state = $_GET['state'] ?? '';
            $server->authorize($clientId, $redirectUri, $state);
            return;
        }
        if ($qPath === 'oauth/token') {
            $clientId = $_POST['client_id'] ?? '';
            $clientSecret = $_POST['client_secret'] ?? '';
            $code = $_POST['code'] ?? '';
            $server->issueToken($clientId, $clientSecret, $code);
            return;
        }
    }

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
