<?php
declare(strict_types=1);

namespace SPPMod\SPPAPI\Dispatchers;

use SPPMod\SPPAPI\SPPAjax;

class ComponentDispatcher
{
    public static function dispatchAction(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $compName = $input['component'] ?? $_REQUEST['component'] ?? null;
        $method = $input['method'] ?? $_REQUEST['method'] ?? null;
        $data = $input['data'] ?? $_REQUEST['data'] ?? [];

        if (!$compName || !$method) {
            SPPAjax::respond('error', ['message' => 'Invalid component action request.']);
        }

        // CSRF Protection
        $hasCustomHeader = (isset($_SERVER['HTTP_X_SPP_AJAX']) && $_SERVER['HTTP_X_SPP_AJAX'] === '1') ||
            (isset($_SERVER['X-SPP-Ajax']) && $_SERVER['X-SPP-Ajax'] === '1');
        if (!$hasCustomHeader) {
            SPPAjax::respond('error', ['message' => 'CSRF Protection: Missing X-SPP-Ajax header.'], 403);
        }

        // Enforce Transport Integrity (Dead Code Activation)
        if (!SPPAjax::verifyTransportIntegrity()) {
            SPPAjax::respond('error', ['message' => 'Transport Integrity Failure: Payload tampered or signature missing.'], 403);
        }

        // SPA Native Auth interceptor protecting endpoint dynamically
        if (!\SPPMod\SPPAPI\SPPAPI::checkAuth()) {
            SPPAjax::respond('error', ['message' => 'Unauthorized component execution.'], 401);
        }

        $compName = preg_replace('/[^a-zA-Z0-9_]/', '', $compName);

        $app = \SPP\Scheduler::getContext();
        $className = "App\\" . ucfirst($app) . "\\Components\\" . $compName;

        if (!class_exists($className)) {
            SPPAjax::respond('error', ['message' => "Component '{$compName}' not found."]);
        }

        $component = new $className();
        if (!method_exists($component, $method)) {
            SPPAjax::respond('error', ['message' => "Method '{$method}' not found in component '{$compName}'."]);
        }

        $ref = new \ReflectionMethod($component, $method);
        if (!$ref->isPublic() || str_starts_with($method, '__')) {
            SPPAjax::respond('error', ['message' => "Method '{$method}' is not callable."]);
        }

        // Execute the action
        $result = $component->$method($data);

        SPPAjax::respond('ok', [
            'result' => $result,
            'state' => $component->getState()
        ]);
    }

    public static function dispatchJS(string $name): void
    {
        header('Content-Type: application/javascript; charset=utf-8');

        // Prevent namespace traversal
        $name = preg_replace('/[^a-zA-Z0-9_]/', '', $name);

        try {
            $app = \SPP\Scheduler::getContext();
            $className = "App\\" . ucfirst($app) . "\\Components\\" . $name;
            echo \SPPMod\SPPView\JSGenerator::generate($className);
        } catch (\Exception $e) {
            echo "// Error generating component JS: " . $e->getMessage();
        }
        exit;
    }
}
