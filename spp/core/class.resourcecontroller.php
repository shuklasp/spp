<?php

namespace SPP\Core;

use SPP\Exceptions\EntityNotFoundException;

/**
 * Class ResourceController
 * Provides a base for RESTful resource management in SPP.
 */
abstract class ResourceController
{
    protected string $entityClass;

    public function __construct()
    {
        if (empty($this->entityClass)) {
            // Try to guess from controller name (e.g. UserController -> User)
            $className = (new \ReflectionClass($this))->getShortName();
            $entityName = str_replace('Controller', '', $className);
            $this->entityClass = "\\App\\Default\\Entities\\$entityName";
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index($args)
    {
        $entities = ($this->entityClass)::find_all();
        return [
            'view' => 'index',
            'data' => [
                'items' => $entities,
                'entityName' => basename(str_replace('\\', '/', $this->entityClass))
            ]
        ];
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store($args)
    {
        $data = $_POST;
        $entity = new $this->entityClass();
        $entity->setValues($data);
        $id = $entity->save();

        return [
            'success' => true,
            'id' => $id,
            'message' => 'Resource created successfully.'
        ];
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $entity = new $this->entityClass($id);
        return [
            'view' => 'show',
            'data' => ['item' => $entity]
        ];
    }

    /**
     * Update the specified resource in storage.
     */
    public function update($id, $args)
    {
        $data = $_POST;
        $entity = new $this->entityClass($id);
        $entity->setValues($data);
        $entity->save();

        return [
            'success' => true,
            'message' => 'Resource updated successfully.'
        ];
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $db = \SPP\DB::getInstance();
        $entity = new $this->entityClass($id);
        
        if (method_exists($entity, 'delete')) {
            $entity->delete();
        } else {
            $table = method_exists($entity, 'getTable') ? $entity->getTable() : strtolower((new \ReflectionClass($entity))->getShortName()) . 's';
            $idField = method_exists($entity, 'getMetadata') ? $entity->getMetadata('id_field') : 'id';
            $sql = "DELETE FROM %tab% WHERE {$idField} = ?";
            $db->exec_squery($sql, $table, [$id]);
        }

        return [
            'success' => true,
            'message' => 'Resource deleted successfully.'
        ];
    }
}
