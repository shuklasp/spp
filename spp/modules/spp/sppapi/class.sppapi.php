<?php
declare(strict_types=1);

namespace SPPMod\SPPAPI;

/**
 * SPPAPI Dynamic Generator Engine
 */
class SPPAPI extends \SPP\SPPObject
{
    /** @var callable|null */
    private static $authValidator = null;

    public static function setAuthValidator(callable $callback): void
    {
        self::$authValidator = $callback;
    }

    public static function checkAuth(): bool
    {
        if (is_callable(self::$authValidator)) {
            return call_user_func(self::$authValidator);
        }
        // Default to false securely if no provider is registered
        return false;
    }

    public static function handle(): void
    {
        // Enforce API Rate Limiting via Event-Driven Middleware
        if (class_exists('\\SPP\\SPPEvent')) {
            \SPP\SPPEvent::fireEvent('api.request.start', new \SPP\EventParams());
        }

        header('Content-Type: application/json');

        if (!self::isApiRequest()) {
            return;
        }

        if (!self::authenticateRequest()) {
            SPPApiResponse::error('Unauthorized. Bearer token missing or invalid.', 401);
        }

        $entityName = $_GET['entity'] ?? null;

        if ($entityName === 'docs') {
            require_once __DIR__ . '/class.apidoccontroller.php';
            ApiDocController::render();
            exit;
        }
        
        if (!$entityName) {
            SPPApiResponse::error('API parameter required.', 400);
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $classMap = self::resolveEntityClass($entityName);

        // ENFORCE API ACCESS: Ensure the entity has explicitly enabled API exposure
        $isApiEnabled = false;
        if (method_exists($classMap, 'getMetadata')) {
            $isApiEnabled = $classMap::getMetadata('enable_api');
        } elseif ($classMap === "\\SPPMod\\SPPEntity\\SPPEntity") {
            $isApiEnabled = \SPPMod\SppDb\SPPEntity::getMetadata('enable_api');
        }
        
        if (!$isApiEnabled) {
            SPPApiResponse::error('API access is not enabled for this entity.', 403);
        }

        $id = $_GET['id'] ?? null;

        try {
            $pipeline = new \SPPMod\SPPAPI\Middleware\Pipeline();
            $pipeline->send(null)
                     ->through([
                         \SPPMod\SPPAPI\Middleware\ApiAuthMiddleware::class
                     ])
                     ->then(function ($request) use ($method, $entityName, $classMap, $id) {
                         switch ($method) {
                             case 'GET':
                                 Controllers\EntityGetController::handle($entityName, $classMap, $id);
                                 break;
                             case 'POST':
                                 Controllers\EntityPostController::handle($entityName, $classMap);
                                 break;
                             case 'PUT':
                             case 'PATCH':
                                 Controllers\EntityPutPatchController::handle($entityName, $classMap, $id);
                                 break;
                             case 'DELETE':
                                 Controllers\EntityDeleteController::handle($entityName, $classMap, $id);
                                 break;
                             default:
                                 SPPApiResponse::error('Method not allowed.', 405);
                         }
                     });
        } catch (\SPP\Core\SPPException $e) {
            SPPApiResponse::error($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            SPPApiResponse::error('Internal Server Error.', 500);
        }
    }

    private static function resolveEntityClass(string $entityName): string
    {
        $classMap = $entityName;

        if (!class_exists($classMap)) {
            $appContext = class_exists('\SPP\Scheduler') ? \SPP\Scheduler::getContext() : 'default';
            $fallback = "\\App\\" . ucfirst($appContext) . "\\Entities\\" . ucfirst($entityName);
            if (class_exists($fallback)) {
                $classMap = $fallback;
            } elseif (!\SPPMod\SppDb\SPPEntity::entityExists($entityName)) {
                SPPApiResponse::error("Entity '{$entityName}' not found.", 404);
            } else {
                $classMap = "\\SPPMod\\SPPEntity\\SPPEntity";
                try {
                    $reflection = new \ReflectionMethod($classMap, 'loadEntityConfig');
                    $reflection->setAccessible(true);
                    $reflection->invoke(null, $entityName);
                } catch (\Exception $e) {
                    // Allowed to fail if generic load fails
                }
            }
        }
        
        if (!is_subclass_of($classMap, '\SPPMod\SppDb\SPPEntity') && $classMap !== '\SPPMod\SPPEntity\SPPEntity' && !is_subclass_of($classMap, '\SPPMod\SPPEntity\SPPEntity')) {
            if (!class_exists($classMap) || !is_subclass_of($classMap, '\SPPMod\SppDb\SPPEntity')) {
                if (!is_subclass_of($classMap, 'SPPDB_Entity') && $classMap !== "\\SPPMod\\SPPEntity\\SPPEntity") {
                    SPPApiResponse::error('Invalid Entity Class.', 400);
                }
            }
        }

        return $classMap;
    }

    public static function isApiRequest(): bool
    {
        return (isset($_GET['__api']) && $_GET['__api'] === '1')
            || (isset($_SERVER['HTTP_X_SPP_API']) && $_SERVER['HTTP_X_SPP_API'] === '1');
    }

    private static function authenticateRequest(): bool
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $authHeader = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['Authorization'] ?? '';
        if (!preg_match('/Bearer\s+(\S+)/', $authHeader, $matches)) {
            return false;
        }

        $params = new \SPP\EventParams([
            'token' => $matches[1],
            'is_valid' => false,
        ]);
        if (class_exists('\\SPP\\SPPEvent')) {
            \SPP\SPPEvent::fireEvent('api.auth.verify_token', $params);
        }

        return (bool) $params->get('is_valid');
    }
}
