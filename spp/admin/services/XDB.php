<?php
/**
 * XDB Management Service Group for SPP Admin
 */

require_once SPP_BASE_DIR . '/modules/spp/sppxdb/class.sppxdb.php';

function live_XDB_ListDB($la, $params) {
    $xdb = new \SPPMod\SPPXDB\SPP_XDB();
    $la->setData(['databases' => $xdb->listDatabases()]);
}

function live_XDB_ListTables($la, $params) {
    $dbname = $params['dbname'] ?? 'default';
    $xdb = new \SPPMod\SPPXDB\SPP_XDB($dbname);
    $la->setData(['tables' => $xdb->listTables()]);
}

function live_XDB_GetTableData($la, $params) {
    $dbname = $params['dbname'] ?? 'default';
    $table = $params['table'] ?? null;
    if (!$table) return $la->setStatus('error')->notify("Table name required.");
    
    $xdb = new \SPPMod\SPPXDB\SPP_XDB($dbname, $table);
    $data = $xdb->querySQL("SELECT * FROM $table LIMIT 100");
    $la->setData(['rows' => $data]);
}

function live_XDB_GetTableColumns($la, $params) {
    $dbname = $params['dbname'] ?? 'default';
    $table = $params['table'] ?? null;
    if (!$table) return $la->setStatus('error')->notify("Table name required.");
    
    $xdb = new \SPPMod\SPPXDB\SPP_XDB($dbname);
    $la->setData(['columns' => $xdb->getTableColumns($table)]);
}

function live_XDB_RunQuery($la, $params) {
    $dbname = $params['dbname'] ?? 'default';
    $sql = $params['sql'] ?? '';
    if (!$sql) return $la->setStatus('error')->notify("SQL or XPath query required.");
    
    $xdb = new \SPPMod\SPPXDB\SPP_XDB($dbname);
    try {
        if (strpos(trim($sql), '/') === 0) {
            $results = $xdb->queryX($sql);
        } else {
            $results = $xdb->querySQL($sql);
        }
        $la->setData(['results' => $results]);
    } catch (\Exception $e) {
        $la->setStatus('error')->notify($e->getMessage());
    }
}

function live_XDB_SaveRecord($la, $params) {
    $dbname = $params['dbname'] ?? 'default';
    $table = $params['table'] ?? '';
    $data = $params['data'] ?? [];
    $id = $params['id'] ?? null;
    if (!$table || empty($data)) return $la->setStatus('error')->notify("Table and data required.");
    
    $xdb = new \SPPMod\SPPXDB\SPP_XDB($dbname, $table);
    $res = $id ? $xdb->update($data, "id = ?", [$id]) : $xdb->insert($data);
    $la->notify($res ? "Record saved." : "Save failed.");
}

function live_XDB_DeleteRecord($la, $params) {
    $dbname = $params['dbname'] ?? 'default';
    $table = $params['table'] ?? '';
    $id = $params['id'] ?? null;
    if (!$table || !$id) return $la->setStatus('error')->notify("Table and ID required.");
    
    $xdb = new \SPPMod\SPPXDB\SPP_XDB($dbname, $table);
    $res = $xdb->delete("id = ?", [$id]);
    $la->notify($res ? "Record deleted." : "Delete failed.");
}
