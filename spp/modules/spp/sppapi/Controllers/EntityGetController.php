<?php
declare(strict_types=1);

namespace SPPMod\SPPAPI\Controllers;

use SPPMod\SPPAPI\SPPApiResponse;
use SPPMod\SPPAPI\SPPPaginator;

class EntityGetController
{
    public static function handle(string $entityName, string $classMap, ?string $id): void
    {
        $resourceClass = self::resolveResourceClass($entityName);

        if ($id) {
            $entity = new $classMap($id);
            if (!$entity->getId()) {
                SPPApiResponse::error('Entity not found.', 404);
            }
            if (method_exists($entity, 'checkAccess') && !$entity->checkAccess('view')) {
                SPPApiResponse::error('Access denied.', 403);
            }

            $data = $entity->jsonSerialize();
            if ($resourceClass) {
                $resObj = new $resourceClass();
                $resObj->resource = $entity;
                $data = $resObj->toArray(null);
            }

            SPPApiResponse::success($data);
        } else {
            $page = (int)($_GET['page'] ?? 1);
            $perPage = (int)($_GET['per_page'] ?? 50);
            
            // Fallback for older limit/offset queries
            if (isset($_GET['limit']) && !isset($_GET['per_page'])) {
                $perPage = (int)$_GET['limit'];
                if (isset($_GET['offset'])) {
                    $page = (int)ceil(((int)$_GET['offset'] / max(1, $perPage)) + 1);
                }
            }

            $instance = new $classMap();
            if ($classMap === "\\SPPMod\\SPPEntity\\SPPEntity") {
                $instance->setTable(\SPPMod\SppDb\SPPEntity::getMetadata('table'));
            }

            $table = $instance->getTable();
            $idField = \SPPMod\SppDb\SPPEntity::getMetadata('id_field');
            if (!$idField) {
                $idField = 'id';
            }

            $query = "SELECT $idField FROM $table";
            $pagination = SPPPaginator::paginateQuery($query, $page, $perPage);

            $entities = [];
            foreach ($pagination['items'] as $row) {
                $entity = new $classMap($row[$idField]);
                if ($classMap === "\\SPPMod\\SPPEntity\\SPPEntity") {
                    $entity->setTable($table);
                }
                if (!method_exists($entity, 'checkAccess') || $entity->checkAccess('view')) {
                    $entities[] = $entity;
                }
            }

            if ($resourceClass) {
                $data = $resourceClass::collection($entities);
            } else {
                $data = array_map(fn($e) => $e->jsonSerialize(), $entities);
            }

            SPPApiResponse::paginate($data, $pagination['total'], $pagination['per_page'], $pagination['current_page']);
        }
    }

    public static function resolveResourceClass(string $entityName): ?string
    {
        $appContext = class_exists('\SPP\Scheduler') ? \SPP\Scheduler::getContext() : 'default';
        $potentialResource1 = "\\App\\" . ucfirst($appContext) . "\\Resources\\" . ucfirst($entityName) . "Resource";
        $potentialResource2 = "\\SPPMod\\SPPAPI\\Resources\\" . ucfirst($entityName) . "Resource";

        if (class_exists($potentialResource1)) {
            return $potentialResource1;
        } elseif (class_exists($potentialResource2)) {
            return $potentialResource2;
        }

        return null;
    }
}
