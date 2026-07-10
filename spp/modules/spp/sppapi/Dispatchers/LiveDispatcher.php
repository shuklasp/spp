<?php
declare(strict_types=1);

namespace SPPMod\SPPAPI\Dispatchers;

use SPPMod\SPPAPI\SPPAjax;
use SPPMod\SPPView\LiveComponent;

class LiveDispatcher
{
    public static function dispatch(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);

        $compClass = $input['component'] ?? null;
        $state = $input['state'] ?? [];
        $checksum = $input['checksum'] ?? '';
        $updates = $input['updates'] ?? [];
        $method = $input['method'] ?? null;
        $params = $input['params'] ?? [];

        if (!$compClass || !class_exists($compClass)) {
            SPPAjax::respond('error', ['message' => 'Invalid or missing component class.']);
        }

        // CSRF Protection
        $hasCustomHeader = (isset($_SERVER['HTTP_X_SPP_AJAX']) && $_SERVER['HTTP_X_SPP_AJAX'] === '1') ||
            (isset($_SERVER['X-SPP-Ajax']) && $_SERVER['X-SPP-Ajax'] === '1');
        if (!$hasCustomHeader) {
            SPPAjax::respond('error', ['message' => 'CSRF Protection: Missing X-SPP-Ajax header.'], 403);
        }

        // SPA Native Auth interceptor protecting endpoint dynamically
        // Note: Global auth check is disabled to support public Live Components.
        // Components should manage their own authorization logic using #[Locked] or custom attributes.
        /*
        if (!\SPPMod\SPPAPI\SPPAPI::checkAuth()) {
            SPPAjax::respond('error', ['message' => 'Unauthorized component execution.'], 401);
        }
        */

        try {
            $result = LiveComponent::handleRequest($compClass, $state, $updates, $checksum, $method, $params, ['global']);

            SPPAjax::respond('ok', [
                'result' => $result
            ]);
        } catch (\Exception $e) {
            SPPAjax::respond('error', ['message' => 'LiveComponent Error: ' . $e->getMessage()]);
        }
    }
}
