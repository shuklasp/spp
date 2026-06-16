<?php
declare(strict_types=1);

namespace SPPMod\SPPAPI\Dispatchers;

use SPPMod\SppApi\SPPAjax;

class IntentDispatcher
{
    public static function dispatch(): void
    {
        // CSRF Protection
        $hasCustomHeader = (isset($_SERVER['HTTP_X_SPP_AJAX']) && $_SERVER['HTTP_X_SPP_AJAX'] === '1') || 
                           (isset($_SERVER['X-SPP-Ajax']) && $_SERVER['X-SPP-Ajax'] === '1');
        if (!$hasCustomHeader) {
            SPPAjax::respond('error', ['message' => 'CSRF Protection: Missing X-SPP-Ajax header.'], 403);
        }

        // SPA Native Auth interceptor
        if (!\SPPMod\SPPAPI\SPPAPI::checkAuth()) {
            SPPAjax::respond('error', ['message' => 'Unauthorized. AI execution requires an active session.'], 401);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $intent = $input['intent'] ?? $_REQUEST['intent'] ?? null;
        $schema = $input['schema'] ?? $_REQUEST['schema'] ?? [];

        if (!$intent) {
            SPPAjax::respond('error', ['message' => 'Intent query prompt string required.'], 400);
        }

        $aiResult = \SPPMod\SPPAI\SPPAI::structured($intent, $schema);
        SPPAjax::appendMerkleLineage('intent_morph', is_array($aiResult) ? $aiResult : ['result' => $aiResult]);
        SPPAjax::respond('ok', ['synthesized' => $aiResult]);
    }
}
