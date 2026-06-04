<?php

namespace SPPMod\SPPXDB;

/**
 * Class XdbController
 * Provides RESTful access to XML Database tables.
 */
class XdbController
{
    protected $xdb;

    public function handleRequest($dbName, $tableName)
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $this->xdb = new SPP_XDB($dbName, $tableName);

        switch ($method) {
            case 'GET':
                $where = $_GET['where'] ?? null;
                $results = $this->xdb->querySQL("SELECT * FROM $tableName" . ($where ? " WHERE $where" : ""));
                return $results;

            case 'POST':
                $data = json_decode(file_get_contents('php://input'), true);
                if ($this->xdb->insert($data)) {
                    return ['status' => 'success', 'id' => $this->xdb->lastInsertId()];
                }
                return ['error' => 'Insert failed'];

            case 'PUT':
                $data = json_decode(file_get_contents('php://input'), true);
                $where = $_GET['where'] ?? null;
                if (!$where) {
                    return ['error' => 'WHERE clause required for update'];
                }
                if ($this->xdb->update($data, $where)) {
                    return ['status' => 'success'];
                }
                return ['error' => 'Update failed'];

            case 'DELETE':
                $where = $_GET['where'] ?? null;
                if (!$where) {
                    return ['error' => 'WHERE clause required for delete'];
                }
                if ($this->xdb->delete($where)) {
                    return ['status' => 'success'];
                }
                return ['error' => 'Delete failed'];
        }
        return ['error' => 'Method not supported'];
    }
}
