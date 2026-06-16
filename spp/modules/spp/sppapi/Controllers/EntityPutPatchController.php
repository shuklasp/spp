<?php
declare(strict_types=1);

namespace SPPMod\SPPAPI\Controllers;

use SPPMod\SPPAPI\SPPApiResponse;

class EntityPutPatchController
{
    public static function handle(string $entityName, string $classMap, ?string $id): void
    {
        if (empty($_SERVER['HTTP_X_SPP_API']) || $_SERVER['HTTP_X_SPP_API'] !== '1') {
            SPPApiResponse::error('CSRF Protection: Missing X-SPP-API header.', 403);
        }
        if (!$id) {
            SPPApiResponse::error('ID required for update.', 400);
        }

        $entity = new $classMap($id);
        if (!$entity->getId()) {
            SPPApiResponse::error('Entity not found.', 404);
        }
        if (method_exists($entity, 'checkAccess') && !$entity->checkAccess('update')) {
            SPPApiResponse::error('Access denied.', 403);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            SPPApiResponse::error('Invalid JSON payload.', 400);
        }

        EntityPostController::fillEntity($entity, $classMap, $input);

        if (method_exists($entity, 'save')) {
            $entity->save();
        }

        $resourceClass = EntityGetController::resolveResourceClass($entityName);
        $data = $entity->jsonSerialize();
        if ($resourceClass) {
            $resObj = new $resourceClass();
            $resObj->resource = $entity;
            $data = $resObj->toArray(null);
        }

        SPPApiResponse::success($data);
    }
}
