<?php

/**
 * Admin RBAC (Role-Based Access Control) for SPP Admin Panel
 * 
 * Defines permission scopes for admin sections and gates API actions.
 * Permissions are stored in XDB (sys/admin_permissions) for zero-migration setup.
 */

/**
 * Admin permission scope definitions.
 * Each admin view maps to a required scope.
 */
function getAdminScopeMap(): array {
    return [
        // View => Required scope
        'dashboard'   => 'admin.dashboard',
        'system'      => 'admin.system',
        'apps'        => 'admin.apps',
        'entities'    => 'admin.entities',
        'forms'       => 'admin.forms',
        'identity'    => 'admin.identity',
        'services'    => 'admin.services',
        'routing'     => 'admin.routing',
        'xdb'         => 'admin.xdb',
        'interdb'     => 'admin.interdb',
        'parikshak'   => 'admin.parikshak',
        'spplang'     => 'admin.spplang',
        'trace'       => 'admin.trace',
        'lifecycle'   => 'admin.lifecycle',
        'commands'    => 'admin.commands',
        'reports'     => 'admin.reports',
        'ai'          => 'admin.ai',
        'api_keys'    => 'admin.api_keys',
        'mobile'      => 'admin.mobile',
        'docs'        => 'admin.docs',
    ];
}

/**
 * Map API actions to their required admin scopes.
 * Actions not listed here are allowed for any authenticated admin.
 */
function getActionScopeMap(): array {
    return [
        // Identity & IAM — restricted to admin.identity
        'list_groups'           => 'admin.identity',
        'list_group_members'    => 'admin.identity',
        'add_group_member'      => 'admin.identity',
        'remove_group_member'   => 'admin.identity',
        'save_group'            => 'admin.identity',
        'delete_group'          => 'admin.identity',
        'list_users'            => 'admin.identity',
        'save_user'             => 'admin.identity',
        'list_roles'            => 'admin.identity',
        'save_role'             => 'admin.identity',
        'list_rights'           => 'admin.identity',
        'save_right'            => 'admin.identity',
        'toggle_user_status'    => 'admin.identity',
        'list_rbac'             => 'admin.identity',
        'list_abac_policies'    => 'admin.identity',
        'save_abac_policy'      => 'admin.identity',
        'delete_abac_policy'    => 'admin.identity',
        'list_oauth_clients'    => 'admin.identity',
        'save_oauth_client'     => 'admin.identity',
        'delete_oauth_client'   => 'admin.identity',
        'list_entity_assignments'=> 'admin.identity',
        'get_iam_details'       => 'admin.identity',
        'search_entities'       => 'admin.identity',
        'assign_role_to_entity' => 'admin.identity',
        'remove_role_from_entity'=> 'admin.identity',
        'assign_right_to_role'  => 'admin.identity',
        'remove_right_from_role'=> 'admin.identity',
        'get_form_html'         => 'admin.identity',
        'save_modern_role'      => 'admin.identity',
        'Auth_ListApiKeys'      => 'admin.api_keys',
        'Auth_GenerateApiKey'   => 'admin.api_keys',
        'Auth_RevokeApiKey'     => 'admin.api_keys',
        'Auth_GenerateMFASecret'=> 'admin.identity',
        'Auth_EnableMFA'        => 'admin.identity',

        // System & Lifecycle — restricted
        'list_apps'             => 'admin.apps',
        'health_check'          => 'admin.system',
        'get_system_info'       => 'admin.system',
        'get_bridge_info'       => 'admin.system',
        'setup_bridge'          => 'admin.system',
        'test_bridge'           => 'admin.system',
        'compile_registry'      => 'admin.system',
        'save_global_settings'  => 'admin.system',
        'run_command'           => 'admin.commands',
        'execute_command'       => 'admin.commands',
        'list_commands'         => 'admin.commands',
        'get_command_ui'        => 'admin.commands',
        'run_xdb_query'         => 'admin.xdb',
        'get_xdb_table_data'    => 'admin.xdb',
        'save_xdb_record'       => 'admin.xdb',
        'delete_xdb_record'     => 'admin.xdb',
        'xdb_migrate'           => 'admin.xdb',
        'xdb_seed'              => 'admin.xdb',
        'save_ajax_service'     => 'admin.services',
        'save_interdb_config'   => 'admin.system',
        'save_module_config'    => 'admin.system',
        'save_module_config_raw'=> 'admin.system',
        'toggle_module'         => 'admin.system',
        'setup_module'          => 'admin.system',
        'lifecycle_receive'     => 'admin.lifecycle',
        'lifecycle_backup'      => 'admin.lifecycle',
        'lifecycle_save_target' => 'admin.lifecycle',
        'sync_deployment_token' => 'admin.lifecycle',
        'system_update_run'     => 'admin.lifecycle',
        'sys_upgrade'           => 'admin.lifecycle',
        'lifecycle_deploy_history'    => 'admin.lifecycle',
        'lifecycle_list_backups'      => 'admin.lifecycle',
        'lifecycle_restore_backup'    => 'admin.lifecycle',
        'lifecycle_remote_logs'       => 'admin.lifecycle',
        'lifecycle_remote_run'        => 'admin.lifecycle',
        'lifecycle_health_check'      => 'admin.lifecycle',
        'lifecycle_get_webhooks'      => 'admin.lifecycle',
        'lifecycle_save_webhooks'     => 'admin.lifecycle',
        'lifecycle_test_webhook'      => 'admin.lifecycle',
        'lifecycle_cluster_status'    => 'admin.lifecycle',
        'lifecycle_maintenance_toggle'=> 'admin.lifecycle',

        // Destructive actions
        'delete_entity'         => 'admin.entities',
        'delete_form'           => 'admin.forms',
        'uninstall_module'      => 'admin.system',
        'clear_audit_logs'      => 'admin.system',
        'list_audit_logs'       => 'admin.trace',

        // Scaffolding
        'execute_scaffold'      => 'admin.apps',
        'scaffold_template'     => 'admin.apps',
        'get_builder_context'   => 'admin.apps',
        'get_codebase_structure'=> 'admin.apps',
        'get_file_content'      => 'admin.apps',

        // Module/config/app surfaces
        'list_modules'          => 'admin.apps',
        'scan_module'           => 'admin.apps',
        'open_module_settings'  => 'admin.apps',
        'list_entities'         => 'admin.entities',
        'save_entity_config'    => 'admin.entities',
        'parse_entity_yaml'     => 'admin.entities',
        'dump_entity_yaml'      => 'admin.entities',
        'list_forms'            => 'admin.forms',
        'save_form'             => 'admin.forms',
        'parse_form_yaml'       => 'admin.forms',
        'dump_form_yaml'        => 'admin.forms',
        'list_pages'            => 'admin.routing',
        'save_page'             => 'admin.routing',
        'remove_page'           => 'admin.routing',
        'list_services'         => 'admin.services',
        'remove_service'        => 'admin.services',
        'get_interdb_config'    => 'admin.interdb',
        'list_xdb_databases'    => 'admin.xdb',
        'list_xdb_tables'       => 'admin.xdb',
        'get_xdb_table_columns' => 'admin.xdb',
        'xdb_get_profile_log'   => 'admin.xdb',
        'get_ajax_services'     => 'admin.services',
        'load_view'             => 'admin.dashboard',
        'get_ai_registry'       => 'admin.ai',
        'test_ai_prompt'        => 'admin.ai',
        'diagnostics_health'    => 'admin.system',
        'list_queue'            => 'admin.system',
        'get_event_trace'       => 'admin.trace',
        'get_admin_permissions' => 'admin.identity',
        'save_admin_permissions'=> 'admin.identity',
        'install_all_active'    => 'admin.system',
    ];
}

/**
 * Get the admin scopes for the current user.
 * Super-admins (role_id=1 or username matching global admin) get all scopes.
 * Other users get scopes from XDB admin_permissions store.
 */
function getAdminUserScopes(): array {
    file_put_contents(SPP_BASE_DIR . '/api_debug.log', '[' . date('Y-m-d H:i:s') . '] getAdminUserScopes START. sessionExists: ' . (int)\SPP\SPPSession::sessionExists() . "\n", FILE_APPEND);
    if (!\SPP\SPPSession::sessionExists()) {
        file_put_contents(SPP_BASE_DIR . '/api_debug.log', '[' . date('Y-m-d H:i:s') . '] getAdminUserScopes RETURNING EMPTY (no session)' . "\n", FILE_APPEND);
        return []; // HTTP admin requests must fail closed without a session.
    }

    try {
        $userId = \SPP\SPPSession::sessionVarExists('__user_id__') ? \SPP\SPPSession::getSessionVar('__user_id__') : null;
        $username = \SPP\SPPSession::sessionVarExists('__username__') ? \SPP\SPPSession::getSessionVar('__username__') : '';
        $sppauthUser = \SPP\SPPSession::sessionVarExists('__sppauth_user__') ? \SPP\SPPSession::getSessionVar('__sppauth_user__') : ($_SESSION['spp_admin_user'] ?? '');
        $roleId = \SPP\SPPSession::sessionVarExists('__role_id__') ? \SPP\SPPSession::getSessionVar('__role_id__') : null;

        file_put_contents(SPP_BASE_DIR . '/api_debug.log', '[' . date('Y-m-d H:i:s') . '] getAdminUserScopes userId: ' . json_encode($userId) . ' username: ' . json_encode($username) . ' sppauthUser: ' . json_encode($sppauthUser) . ' roleId: ' . json_encode($roleId) . "\n", FILE_APPEND);

        $settings = \SPP\App::getGlobalSettings();
        $superAdmin = $settings['admin_username'] ?? 'admin';
        $allScopes = array_values(getActionScopeMap());

        if ($roleId == 1 || strtolower($username) === strtolower($superAdmin) || strtolower($sppauthUser) === strtolower($superAdmin)) {
            file_put_contents(SPP_BASE_DIR . '/api_debug.log', '[' . date('Y-m-d H:i:s') . '] getAdminUserScopes RETURNING ALL SCOPES' . "\n", FILE_APPEND);
            return $allScopes;
        }

        file_put_contents(SPP_BASE_DIR . '/api_debug.log', '[' . date('Y-m-d H:i:s') . '] getAdminUserScopes RETURNING EMPTY (no match)' . "\n", FILE_APPEND);
        return [];
    } catch (\Exception $e) {
        file_put_contents(SPP_BASE_DIR . '/api_debug.log', '[' . date('Y-m-d H:i:s') . '] getAdminUserScopes EXCEPTION: ' . $e->getMessage() . "\n", FILE_APPEND);
        return [];
    }
}

/**
 * Check if the current user has a specific admin scope.
 */
function hasAdminScope(string $scope): bool {
    $userScopes = getAdminUserScopes();
    file_put_contents(SPP_BASE_DIR . '/api_debug.log', '[' . date('Y-m-d H:i:s') . '] hasAdminScope ' . $scope . ' scopes: ' . json_encode($userScopes) . "\n", FILE_APPEND);
    return in_array($scope, $userScopes) || in_array('admin.*', $userScopes);
}

/**
 * Gate an API action — returns true if allowed, false if denied.
 */
function gateAdminAction(string $action): bool {
    $actionMap = getActionScopeMap();
    if (!isset($actionMap[$action])) {
        return true;
    }
    $res = hasAdminScope($actionMap[$action]);
    return $res;
}

// --- API Endpoints ---

if (!function_exists('live_get_admin_permissions')) {
    function live_get_admin_permissions($la, $params) {
        $res = \SPP\CLI\CommandManager::execute('admin:adminrbac', ['get_admin_permissions', '--payload' => json_encode($params), '--json' => '1']);
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
            } else {
                $la->setData($data ?: []);
            }
        } else {
            $la->setStatus('error')->notify($res['error']);
        }

}
}

if (!function_exists('live_save_admin_permissions')) {
    function live_save_admin_permissions($la, $params) {
        $res = \SPP\CLI\CommandManager::execute('admin:adminrbac', ['save_admin_permissions', '--payload' => json_encode($params), '--json' => '1']);
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
            } else {
                $la->setData($data ?: []);
            }
        } else {
            $la->setStatus('error')->notify($res['error']);
        }

}
}
