<?php
declare(strict_types=1);

namespace SPPMod\SPPAPI\Dispatchers;

use SPPMod\SppApi\SPPAjax;

class ServiceDispatcher
{
    public static function enforceRequestGuards(): void
    {
        $hasCustomHeader = (isset($_SERVER['HTTP_X_SPP_AJAX']) && $_SERVER['HTTP_X_SPP_AJAX'] === '1') ||
                           (isset($_SERVER['X-SPP-Ajax']) && $_SERVER['X-SPP-Ajax'] === '1');
        if (!$hasCustomHeader) {
            SPPAjax::respond('error', ['message' => 'CSRF Protection: Missing X-SPP-Ajax header.'], 403);
        }

        if (!SPPAjax::verifyTransportIntegrity()) {
            SPPAjax::respond('error', ['message' => 'Transport Integrity Failure: Payload tampered or signature missing.'], 403);
        }
    }

    public static function dispatch(string $action, array $params = []): void
    {
        $serviceFile = null;
        $funcName = null;

        self::enforceRequestGuards();

        // 1. Check registry first
        $svc = SPPAjax::findService($action);
        if ($svc) {
            self::resolveAndExecute($action, $params);
            return;
        }

        // 2. Fallback: Dynamic standalone script discovery
        $context = \SPP\Scheduler::getContext();
        $srcPath = \SPP\App::getAppConf('src_path', $context) ?: ('src/' . $context);
        $servicesPath = \SPP\App::getAppConf('services_path', $context) ?: (rtrim($srcPath, '/') . '/services');
        $servicesDir = SPP_APP_DIR . '/' . ltrim($servicesPath, '/');
        
        $standaloneFile = $servicesDir . '/' . preg_replace('/[^a-zA-Z0-9_\-]/', '', $action) . '.php';

        if (file_exists($standaloneFile)) {
            $serviceFile = $standaloneFile;
        }

        if (!$serviceFile) {
            // Check SPA pattern fallback
            $servDir = \SPP\Module::getConfig('spa_service_dir', 'sppajax') ?: '/src/serv';
            $servFile = SPP_APP_DIR . $servDir . '/' . preg_replace('/[^a-zA-Z0-9_\-]/', '', $action) . '.php';

            if (file_exists($servFile)) {
                $serviceFile = $servFile;
            }
        }

        if ($serviceFile && file_exists($serviceFile)) {
            $realBase = realpath(dirname($serviceFile));
            $realFile = realpath($serviceFile);

            if ($realFile === false || !str_starts_with($realFile, $realBase)) {
                SPPAjax::respond('error', ['message' => 'Forbidden.'], 403);
            }

            // Execute the service script — it must set $response array
            $response = [];
            include $realFile;

            SPPAjax::respond($response['status'] ?? 'ok', $response['data'] ?? [], $response['message'] ?? '');
            return;
        }

        SPPAjax::respond('error', ['message' => "Service script not found."], 404);
    }

    public static function resolveAndExecute(string $action, array $params = []): void
    {
        $serviceFile = null;
        $funcName = null;

        $svc = SPPAjax::findService($action);
        if ($svc) {
            // Polyglot Service Check
            if (isset($svc['runtime']) && isset($svc['target'])) {
                $args = array_merge($params, json_decode(file_get_contents('php://input'), true) ?: []);
                $res = \SPP\PolyglotBridge::call($svc['runtime'], $svc['target'], $svc['method'] ?? 'main', $args);
                if ($res['success']) {
                    $la = new \SPPMod\SppApi\LiveAction();
                    $data = $res['data'] ?? [];
                    if (isset($data['status'])) {
                        $la->setStatus($data['status']);
                        unset($data['status']);
                    }
                    if (isset($data['message'])) {
                        $la->notify($data['message']);
                        unset($data['message']);
                    }
                    $la->setData($data);
                    $la->send();
                    exit;
                } else {
                    SPPAjax::respond('error', ['message' => 'Polyglot Service Error: ' . ($res['error'] ?? 'Unknown')], 500);
                }
            }

            $serviceFile = $svc['script'];
            $funcName = $svc['method'] ?? null;
            if ($funcName && ($funcName === 'POST' || $funcName === 'ANY' || $funcName === 'GET')) {
                $funcName = null;
            }

            $serviceFile = self::resolveSafeServiceFile($serviceFile);

            if ($serviceFile && file_exists($serviceFile)) {
                require_once $serviceFile;
            }
        }

        if (!$serviceFile) {
            SPPAjax::respond('error', ['message' => "Service script for '{$action}' not found."], 404);
        }

        if ($funcName && function_exists($funcName)) {
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $args = array_merge($params, $input);
            $la = new \SPPMod\SppApi\LiveAction();
            $result = call_user_func($funcName, $la, $args);
            
            if ($result instanceof \SPPMod\SppApi\LiveAction) {
                $result->send();
            }
            if (!empty($la->getInstructions()) || !empty($la->getData()) || $la->getStatus() !== 'ok') {
                $la->send();
            }

            SPPAjax::respond('ok', ['result' => $result]);
        } else {
            // Ensure no stray output
            ob_start();
            $result = include $serviceFile;
            $output = ob_get_clean();

            if ($result instanceof \SPPMod\SppApi\LiveAction) {
                $result->send();
            }
            
            SPPAjax::respond('ok', ['result' => $result, 'output' => $output]);
        }
    }

    private static function resolveSafeServiceFile(string $serviceFile): ?string
    {
        if (!str_starts_with($serviceFile, '/') && !str_contains($serviceFile, ':')) {
            $serviceFile = SPP_APP_DIR . '/' . ltrim($serviceFile, '/');
        }

        $realFile = realpath($serviceFile);
        if ($realFile === false || !is_file($realFile)) {
            return null;
        }

        $allowedRoots = [
            SPP_APP_DIR . DIRECTORY_SEPARATOR . 'src',
            SPP_APP_DIR . DIRECTORY_SEPARATOR . 'apps',
            SPP_APP_DIR . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'apps',
            SPP_BASE_DIR . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'services',
            SPP_BASE_DIR . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'spp' . DIRECTORY_SEPARATOR . 'sppreport' . DIRECTORY_SEPARATOR . 'services',
        ];

        foreach ($allowedRoots as $root) {
            $realRoot = realpath($root);
            if ($realRoot && str_starts_with($realFile, $realRoot . DIRECTORY_SEPARATOR)) {
                return $realFile;
            }
        }

        SPPAjax::respond('error', ['message' => 'Forbidden service path.'], 403);
    }
}
