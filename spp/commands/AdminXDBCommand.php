<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class AdminXDBCommand extends Command
{
    protected string $name = 'admin:xdb';
    protected string $description = 'Manage Admin XDB operations. Usage: admin:xdb <action> [--payload=...] [--json]';

    public function isHidden(): bool { return true; }

    public function execute(array $args): void
    {
        $action = $this->getArgument($args, 0) ?? 'default';
        $payloadRaw = $this->getOption($args, 'payload', '{}');
        $payload = json_decode($payloadRaw, true) ?: [];

        $methodName = 'handle' . str_replace(' ', '', ucwords(str_replace('_', ' ', $action)));
        if (method_exists($this, $methodName)) {
            $this->$methodName($payload, $args);
        } else {
            $this->json(['success' => false, 'error' => "Unknown action: $action"], $args);
        }
    }

    private function handleListdb(array $payload, array $args): void {

    $xdb = new \SPPMod\SPPXDB\SPP_XDB();
    $this->json(['databases' => $xdb->listDatabases()], $args); return;

    }

    private function handleListtables(array $payload, array $args): void {

    $dbname = $payload['dbname'] ?? 'default';
    $xdb = new \SPPMod\SPPXDB\SPP_XDB($dbname);
    $this->json(['tables' => $xdb->listTables()], $args); return;

    }

    private function handleGettabledata(array $payload, array $args): void {

    $dbname = $payload['dbname'] ?? 'default';
    $table = $payload['table'] ?? null;
    if (!$table) $this->json(['success' => false, 'error' => "Table name required."], $args); return;
        return;
    
    $xdb = new \SPPMod\SPPXDB\SPP_XDB($dbname, $table);
    $data = $xdb->querySQL("SELECT * FROM $table LIMIT 100");
    $this->json(['rows' => $data], $args); return;

    }

    private function handleGettablecolumns(array $payload, array $args): void {

    $dbname = $payload['dbname'] ?? 'default';
    $table = $payload['table'] ?? null;
    if (!$table) $this->json(['success' => false, 'error' => "Table name required."], $args); return;
        return;
    
    $xdb = new \SPPMod\SPPXDB\SPP_XDB($dbname);
    $this->json(['columns' => $xdb->getTableColumns($table)], $args); return;

    }

    private function handleRunquery(array $payload, array $args): void {

    $dbname = $payload['dbname'] ?? 'default';
    $sql = $payload['sql'] ?? '';
    if (!$sql) $this->json(['success' => false, 'error' => "SQL or XPath query required."], $args); return;
        return;
    
    $xdb = new \SPPMod\SPPXDB\SPP_XDB($dbname);
    try {
        if (strpos(trim($sql), '/') === 0) {
            $results = $xdb->queryX($sql);
        } else {
            $results = $xdb->querySQL($sql);
        }
        $this->json(['results' => $results], $args); return;
    } catch (\Exception $e) {
        $this->json(['success' => false, 'error' => $e->getMessage()], $args); return;
    }

    }

    private function handleSaverecord(array $payload, array $args): void {

    $dbname = $payload['dbname'] ?? 'default';
    $table = $payload['table'] ?? '';
    $data = $payload['data'] ?? [];
    $id = $payload['id'] ?? null;
    if (!$table || empty($data)) $this->json(['success' => false, 'error' => "Table and data required."], $args); return;
        return;
    
    $xdb = new \SPPMod\SPPXDB\SPP_XDB($dbname, $table);
    $res = $id ? $xdb->update($data, "id = ?", [$id]) : $xdb->insert($data);
    $this->json(['success' => true, 'message' => $res ? "Record saved." : "Save failed."], $args); return;

    }

    private function handleDeleterecord(array $payload, array $args): void {

    $dbname = $payload['dbname'] ?? 'default';
    $table = $payload['table'] ?? '';
    $id = $payload['id'] ?? null;
    if (!$table || !$id) $this->json(['success' => false, 'error' => "Table and ID required."], $args); return;
        return;
    
    $xdb = new \SPPMod\SPPXDB\SPP_XDB($dbname, $table);
    $res = $xdb->delete("id = ?", [$id]);
    $this->json(['success' => true, 'message' => $res ? "Record deleted." : "Delete failed."], $args); return;

    }

    private function handleMigrate(array $payload, array $args): void {

    $rollback = $payload['rollback'] ?? false;
    $xdb = new \SPPMod\SPPXDB\SPP_XDB();
    $mgr = new \SPPMod\SPPXDB\MigrationManager($xdb);
    try {
        if ($rollback) {
            $count = $mgr->rollback(1);
            $this->notify("Rolled back $count migrations.", $args);
        $this->json(['count' => $count]); return;
        } else {
            $count = $mgr->migrate();
            $this->notify("Executed $count migrations.", $args);
        $this->json(['count' => $count]); return;
        }
    } catch (\Exception $e) {
        $this->json(['success' => false, 'error' => $e->getMessage()], $args); return;
    }

    }

    private function handleSeed(array $payload, array $args): void {

    $xdb = new \SPPMod\SPPXDB\SPP_XDB();
    $mgr = new \SPPMod\SPPXDB\SeederManager($xdb);
    try {
        $count = $mgr->seed();
        $this->notify("Executed $count seeders.", $args);
        $this->json(['count' => $count]); return;
    } catch (\Exception $e) {
        $this->json(['success' => false, 'error' => $e->getMessage()], $args); return;
    }

    }

    private function handleGetprofilelog(array $payload, array $args): void {

    try {
        $log = \SPPMod\SPPXDB\SPP_XDB::getQueryLog();
        $this->json(['log' => $log], $args); return;
    } catch (\Exception $e) {
        $this->json(['success' => false, 'error' => $e->getMessage()], $args); return;
    }

    }

}
