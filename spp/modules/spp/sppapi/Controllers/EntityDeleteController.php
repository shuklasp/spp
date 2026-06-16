<?php
declare(strict_types=1);

namespace SPPMod\SPPAPI\Controllers;

use SPPMod\SPPAPI\SPPApiResponse;

class EntityDeleteController
{
    public static function handle(string $entityName, string $classMap, ?string $id): void
    {
        if (empty($_SERVER['HTTP_X_SPP_API']) || $_SERVER['HTTP_X_SPP_API'] !== '1') {
            SPPApiResponse::error('CSRF Protection: Missing X-SPP-API header.', 403);
        }
        if (!$id) {
            SPPApiResponse::error('ID required for deletion.', 400);
        }

        $entity = new $classMap($id);
        if (!$entity->getId()) {
            SPPApiResponse::error('Entity not found.', 404);
        }
        if (method_exists($entity, 'checkAccess') && !$entity->checkAccess('delete')) {
            SPPApiResponse::error('Access denied.', 403);
        }

        if (method_exists($entity, 'delete')) {
            $entity->delete();
        }
        SPPApiResponse::success(null, 'Entity deleted successfully.');
    }
}
