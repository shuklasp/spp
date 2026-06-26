<?php

/**
 * Audit API Controller for SPP Admin
 * 
 * Provides admin action audit logging and retrieval.
 * Logs every API action performed through the admin panel.
 */

if (!function_exists('live_list_audit_logs')) {
    function live_list_audit_logs($la, $params)
    {
        $limit = intval($params['limit'] ?? 50);
        $offset = intval($params['offset'] ?? 0);
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
                    return $la->setData(['logs' => [], 'total' => 0, 'message' => 'Audit table not yet created. Run SPPAudit::install().']);
                }
            }

            $countSql = "SELECT COUNT(*) as total FROM {$tableName}";
            $countResult = $db->exec_squery($countSql, $tableName);
            $total = $countResult[0]['total'] ?? 0;

            $sql = "SELECT * FROM {$tableName} ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}";
            $logs = $db->exec_squery($sql, $tableName);

            $la->setData(['logs' => $logs ?: [], 'total' => intval($total)]);
        } catch (\Exception $e) {
            $la->setData(['logs' => [], 'total' => 0, 'error' => $e->getMessage()]);
        }
    }
}

if (!function_exists('live_clear_audit_logs')) {
    function live_clear_audit_logs($la, $params)
    {
        try {
            $db = new \SPPMod\SPPDB\SPPDB();
            $tableName = $db->sppTable('audit_logs');
            $db->exec_squery("DELETE FROM {$tableName}", $tableName);
            $la->notify('Audit logs cleared.', 'success');
        } catch (\Exception $e) {
            $la->setStatus('error')->notify('Failed to clear audit logs: ' . $e->getMessage(), 'error');
        }
    }
}

/**
 * Log an admin action to the audit trail.
 * Called from api.php on every dispatched action.
 */
if (!function_exists('spp_admin_audit_log')) {
    function spp_admin_audit_log(string $action, array $params = [])
    {
        try {
            // Skip logging for read-only/frequent actions to avoid noise
            $skipActions = [
                'check_auth',
                'get_profile',
                'list_audit_logs',
                'diagnostics_health',
                'get_system_info',
                'load_view',
                'get_global_settings'
            ];
            if (in_array($action, $skipActions)) {
                return;
            }

            $userId = null;
            $username = 'unknown';
            try {
                if (\SPP\SPPSession::sessionExists()) {
                    $userId = \SPP\SPPSession::getSessionVar('__user_id__');
                    $username = \SPP\SPPSession::getSessionVar('__username__') ?: 'unknown';
                }
            } catch (\Exception $e) {
            }

            // Sanitize params — remove passwords and large blobs
            $safeParams = [];
            foreach ($params as $k => $v) {
                if (in_array(strtolower($k), ['password', 'secret', 'token', 'key', 'api_key'])) {
                    $safeParams[$k] = '***REDACTED***';
                } elseif (is_string($v) && strlen($v) > 500) {
                    $safeParams[$k] = substr($v, 0, 500) . '...[truncated]';
                } elseif (is_scalar($v)) {
                    $safeParams[$k] = $v;
                }
            }

            \SPPMod\SPPAudit\SPPAudit::log(
                'admin_action',
                $action,
                $action,
                null,
                ['params' => $safeParams, 'user' => $username, 'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']
            );
        } catch (\Exception $e) {
            // Never let audit logging break the main request
            error_log("[SPP Admin Audit] Failed: " . $e->getMessage());
        }
    }
}
