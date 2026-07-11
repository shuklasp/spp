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

require_once('global_v3.php');

try {
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

        // Strict API Contract: If __svc is requested but CSRF/Ajax validation fails, reject immediately.
        // Exception: live_sse (EventSource cannot send custom headers)
        if ($svc !== '' && $svc !== 'live_sse') {
            if (!class_exists('\SPPMod\SPPAPI\SPPAjax') || !\SPPMod\SPPAPI\SPPAjax::isAjaxRequest()) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'error',
                    'success' => false,
                    'message' => 'CSRF Protection: Missing X-SPP-Ajax header or invalid payload signature.'
                ]);
                exit;
            }
        }

        if (\SPP\Module::isEnabled('sppapi') && class_exists('\SPPMod\SPPAPI\SPPAjax') && (\SPPMod\SPPAPI\SPPAjax::isAjaxRequest() || $svc === 'live_sse')) {
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

        // Route asset requests
        if (str_starts_with($qPath, 'sppasset/')) {
            require_once SPP_BASE_DIR . '/core/AssetRouter.php';
            \SPP\Core\AssetRouter::handle($qPath);
            return;
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
} catch (\SPP\MeshPassthroughException $e) {
    // Escape Hatch for Mesh Legacy Virtualization (Scope Bleed Mitigation)
    // We catch the exception at the absolute global scope and perform the legacy require here.
    $_SERVER['SCRIPT_NAME'] = $e->getScriptName();
    $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
    $_SERVER['SCRIPT_FILENAME'] = $e->getScriptFilename();
    
    if ($e->hasUiMesh()) {
        ob_start();
    }
    
    // A La Carte Feature: Security Headers
    if ($e->hasSecurityHeaders() && !headers_sent()) {
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    
    // A La Carte Feature: Telemetry
    if ($e->hasTelemetry() && class_exists('\SPPMod\SPPReport\W3CTraceContext')) {
        \SPPMod\SPPReport\W3CTraceContext::startTrace('MeshPassthrough:' . basename($e->getLegacyEntryFile()));
    }
    
    $legacyEntryFile = $e->getLegacyEntryFile();
    $realLegacyPath = realpath($legacyEntryFile);
    
    // Security: Prevent LFI / Directory Traversal by ensuring the file exists and is within the application directory
    if ($realLegacyPath === false || !is_file($realLegacyPath) || !str_starts_with($realLegacyPath, realpath(SPP_APP_DIR))) {
        http_response_code(403);
        die("Security Exception: Invalid Mesh Passthrough entry file.");
    }
    $legacyEntryFile = $realLegacyPath;
    
    // CWD Bleed Mitigation: Force PHP to pivot its working directory to the legacy app's folder
    chdir(dirname($legacyEntryFile));
    
    // Error Handler Bleed Mitigation: Strip SPP's global error catching perimeter
    restore_error_handler();
    restore_exception_handler();
    
    // Execute legacy app in true global scope
    require $legacyEntryFile;
    
    if ($e->hasUiMesh()) {
        $output = ob_get_clean();
        echo "<div style='background: #000; color: #fff; padding: 10px; font-family: sans-serif;'>SPP WebOS Mesh Header</div>" . $output;
    }
    exit;
}
