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
    $res = \SPP\CLI\CommandManager::execute('dev:audit', ['list_audit_logs', '--payload' => json_encode($params), '--json' => '1']);
    if ($res['success']) {
        $data = json_decode($res['output'], true);
        if (isset($data['success']) && !$data['success']) {
            $la->setStatus('error')->notify($data['error'] ?? 'Command failed.');
        } elseif (isset($data['modal'])) {
            $la->modal($data['modal']['title'], $data['modal']['html'], $data['modal']['buttons'] ?? []);
        } elseif (isset($data['message'])) {
            $la->notify($data['message']);
            if (!empty($data['closeModal'])) $la->closeModal();
            if (!empty($data['refresh'])) $la->refresh();
            if (!empty($data['executeClientCode'])) $la->executeClientCode($data['executeClientCode']);
            if (!empty($data['redirect'])) $la->redirect($data['redirect']);
        } else {
            $la->setData($data ?: []);
        }
    } else {
        $la->setStatus('error')->notify($res['error']);
    }
}
}

if (!function_exists('live_clear_audit_logs')) {
    function live_clear_audit_logs($la, $params)
    {
    $res = \SPP\CLI\CommandManager::execute('dev:audit', ['clear_audit_logs', '--payload' => json_encode($params), '--json' => '1']);
    if ($res['success']) {
        $data = json_decode($res['output'], true);
        if (isset($data['success']) && !$data['success']) {
            $la->setStatus('error')->notify($data['error'] ?? 'Command failed.');
        } elseif (isset($data['modal'])) {
            $la->modal($data['modal']['title'], $data['modal']['html'], $data['modal']['buttons'] ?? []);
        } elseif (isset($data['message'])) {
            $la->notify($data['message']);
            if (!empty($data['closeModal'])) $la->closeModal();
            if (!empty($data['refresh'])) $la->refresh();
            if (!empty($data['executeClientCode'])) $la->executeClientCode($data['executeClientCode']);
            if (!empty($data['redirect'])) $la->redirect($data['redirect']);
        } else {
            $la->setData($data ?: []);
        }
    } else {
        $la->setStatus('error')->notify($res['error']);
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
