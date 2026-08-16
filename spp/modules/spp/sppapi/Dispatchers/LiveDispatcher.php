<?php
declare(strict_types=1);

namespace SPPMod\SPPAPI\Dispatchers;

use SPPMod\SPPAPI\SPPAjax;
use SPPMod\SPPView\LiveComponent;
use ReflectionClass;

class LiveDispatcher
{
    public static function dispatch(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);

        // CSRF Protection
        $hasCustomHeader = (isset($_SERVER['HTTP_X_SPP_AJAX']) && $_SERVER['HTTP_X_SPP_AJAX'] === '1') ||
            (isset($_SERVER['X-SPP-Ajax']) && $_SERVER['X-SPP-Ajax'] === '1');
        if (!$hasCustomHeader) {
            SPPAjax::respond('error', ['message' => 'CSRF Protection: Missing X-SPP-Ajax header.'], 403);
        }

        if (isset($input['components']) && is_array($input['components'])) {
            $results = [];
            foreach ($input['components'] as $componentData) {
                try {
                    $results[] = self::processComponent($componentData);
                } catch (\Exception $e) {
                    $code = $e->getCode();
                    if ($code === 401) {
                        SPPAjax::respond('error', ['message' => $e->getMessage()], 401);
                    }
                    $results[] = ['error' => $e->getMessage()];
                }
            }
            SPPAjax::respond('ok', ['results' => $results]);
        } else {
            try {
                $result = self::processComponent($input);
                SPPAjax::respond('ok', ['result' => $result]);
            } catch (\Exception $e) {
                $code = $e->getCode();
                if ($code === 401) {
                    SPPAjax::respond('error', ['message' => $e->getMessage()], 401);
                }
                SPPAjax::respond('error', ['message' => 'LiveComponent Error: ' . $e->getMessage()]);
            }
        }
    }

    private static function processComponent(array $input): array
    {
        $compClass = $input['component'] ?? null;
        $state = $input['state'] ?? [];
        $checksum = $input['checksum'] ?? '';
        $updates = $input['updates'] ?? [];
        $method = $input['method'] ?? null;
        $params = $input['params'] ?? [];

        if (!$compClass || !class_exists($compClass)) {
            throw new \Exception('Invalid or missing component class.');
        }

        $refClass = new ReflectionClass($compClass);
        $isPublic = !empty($refClass->getAttributes('SPPMod\SPPView\Attributes\AllowGuest'));

        if (!$isPublic) {
            if (!\SPPMod\SPPAPI\SPPAPI::checkAuth()) {
                throw new \Exception('Unauthorized component execution.', 401);
            }
        }

        return LiveComponent::handleRequest($compClass, $state, $updates, $checksum, $method, $params, ['global']);
    }
}
