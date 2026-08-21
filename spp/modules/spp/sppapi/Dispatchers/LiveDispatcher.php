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

        // --- BACKEND HARDENING: Idempotency Engine ---
        // Prevent concurrent execution of mutations (methods) via malicious curl or rapid race conditions.
        // We do NOT block sync-only requests ($method === null).
        $lockAcquired = false;
        $lockKey = null;
        
        if ($method !== null) {
            // Generate a unique fingerprint for this specific mutation attempt
            $payloadHash = md5(json_encode([$compClass, $checksum, $method, $params]));
            $lockKey = 'spp_live_mutex_' . $payloadHash;
            
            // Fast in-memory APcU lock (or fallback to basic session/file lock)
            if (function_exists('apcu_add')) {
                if (!apcu_add($lockKey, 1, 5)) { // 5-second lock
                    throw new \Exception('Mutation blocked. Duplicate request currently executing.', 409);
                }
                $lockAcquired = true;
            } else {
                // Fallback lock for shared hosting (Session based)
                if (session_status() === PHP_SESSION_NONE) {
                    @session_start();
                }
                if (isset($_SESSION[$lockKey]) && $_SESSION[$lockKey] > (time() - 5)) {
                    throw new \Exception('Mutation blocked. Duplicate request currently executing.', 409);
                }
                $_SESSION[$lockKey] = time();
                $lockAcquired = true;
            }
        }

        try {
            $refClass = new ReflectionClass($compClass);
            $isPublic = !empty($refClass->getAttributes('SPPMod\SPPView\Attributes\AllowGuest'));

            if (!$isPublic) {
                if (!\SPPMod\SPPAPI\SPPAPI::checkAuth()) {
                    throw new \Exception('Unauthorized component execution.', 401);
                }
            }

            return LiveComponent::handleRequest($compClass, $state, $updates, $checksum, $method, $params, ['global']);
        } finally {
            // Release the idempotency lock
            if ($lockAcquired && $lockKey !== null) {
                if (function_exists('apcu_delete')) {
                    apcu_delete($lockKey);
                } else {
                    unset($_SESSION[$lockKey]);
                }
            }
        }
    }
}
