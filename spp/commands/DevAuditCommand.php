<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class DevAuditCommand extends Command
{
    protected string $name = 'dev:audit';
    protected string $description = 'Manage Dev Audit operations. Usage: admin:audit <action> [--payload=...] [--json]';

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

    private function handleListAuditLogs(array $payload, array $args): void {

        $limit = intval($payload['limit'] ?? 50);
        $offset = intval($payload['offset'] ?? 0);
        $limit = min($limit, 200); // Cap at 200

        try {
            $db = new \SPPMod\SPPDB\SPPDB();
            $tableName = $db->sppTable('audit_logs');

            // Check if table exists
            $driver = 'sqlite';
            try {
                $pdo = $db->getPDO();
                if ($pdo)
                    $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
            } catch (\Exception $e) {
            }

            if ($driver === 'sqlite') {
                $checkSql = "SELECT name FROM sqlite_master WHERE type='table' AND name=?";
                $result = $db->exec_squery($checkSql, '__raw', [$tableName]);
                if (empty($result)) {
                    $this->json(['logs' => [], 'total' => 0, 'message' => 'Audit table not yet created. Run SPPAudit::install().'], $args); return;
        return;
                }
            }

            $countSql = "SELECT COUNT(*) as total FROM {$tableName}";
            $countResult = $db->exec_squery($countSql, $tableName);
            $total = $countResult[0]['total'] ?? 0;

            $sql = "SELECT * FROM {$tableName} ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}";
            $logs = $db->exec_squery($sql, $tableName);

            $this->json(['logs' => $logs ?: [], 'total' => intval($total)], $args); return;
        } catch (\Exception $e) {
            $this->json(['logs' => [], 'total' => 0, 'error' => $e->getMessage()], $args); return;
        }
    
    }

    private function handleClearAuditLogs(array $payload, array $args): void {

        try {
            $db = new \SPPMod\SPPDB\SPPDB();
            $tableName = $db->sppTable('audit_logs');
            $db->exec_squery("DELETE FROM {$tableName}", $tableName);
            $this->json(['success' => true, 'message' => 'Audit logs cleared.', 'success'], $args); return;
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => 'Failed to clear audit logs: ' . $e->getMessage(), 'error'], $args); return;
        }
    
    }

}
