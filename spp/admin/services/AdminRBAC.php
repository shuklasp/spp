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
    // Default: super-admin gets everything
    $allScopes = array_unique(array_values(getAdminScopeMap()));

    try {
        if (!\SPP\SPPSession::sessionExists()) {
            return []; // HTTP admin requests must fail closed without a session.
        }

        $userId = \SPP\SPPSession::getSessionVar('__user_id__');
        $username = \SPP\SPPSession::getSessionVar('__username__') ?: '';
        $roleId = \SPP\SPPSession::getSessionVar('__role_id__');

        // Super-admin bypass: role_id 1 or the configured admin username
        $settings = \SPP\App::getGlobalSettings();
        $superAdmin = $settings['admin_username'] ?? 'admin';
        if ($roleId == 1 || strtolower($username) === strtolower($superAdmin)) {
            return $allScopes;
        }

        // Load scopes from XDB
        if (class_exists('\SPPMod\SPPXDB\SPP_XDB')) {
            try {
                $xdb = new \SPPMod\SPPXDB\SPP_XDB('sys', 'admin_permissions');
                $rows = $xdb->queryX("//row[user_id = '{$userId}']");
                if (!empty($rows) && !empty($rows[0]['scopes'])) {
                    return explode(',', $rows[0]['scopes']);
                }
            } catch (\Exception $e) {
                // XDB table may not exist yet — fall through to defaults
            }
        }

        // Default for non-super-admin: read-only access to common views
        return ['admin.dashboard', 'admin.docs', 'admin.apps', 'admin.entities', 'admin.forms'];

    } catch (\Exception $e) {
        return [];
    }
}

/**
 * Check if the current user has a specific admin scope.
 */
function hasAdminScope(string $scope): bool {
    $userScopes = getAdminUserScopes();
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
    return hasAdminScope($actionMap[$action]);
}

// --- API Endpoints ---

if (!function_exists('live_get_admin_permissions')) {
    function live_get_admin_permissions($la, $params) {
        $scopeMap = getAdminScopeMap();
        $userScopes = getAdminUserScopes();
        $allScopes = array_unique(array_values($scopeMap));

        $la->setData([
            'scopes' => $userScopes,
            'all_scopes' => $allScopes,
            'scope_map' => $scopeMap,
        ]);
    }
}

if (!function_exists('live_save_admin_permissions')) {
    function live_save_admin_permissions($la, $params) {
        // Only super-admins can modify permissions
        $currentScopes = getAdminUserScopes();
        if (!in_array('admin.identity', $currentScopes) && !in_array('admin.*', $currentScopes)) {
            return $la->setStatus('error')->notify('Access denied: admin.identity scope required.', 'error');
        }

        $targetUserId = $params['user_id'] ?? null;
        $newScopes = $params['scopes'] ?? [];

        if (!$targetUserId) {
            return $la->setStatus('error')->notify('User ID is required.', 'error');
        }

        if (is_string($newScopes)) {
            $newScopes = array_filter(array_map('trim', explode(',', $newScopes)));
        }

        try {
            $xdb = new \SPPMod\SPPXDB\SPP_XDB('sys', 'admin_permissions');
            $existing = $xdb->queryX("//row[user_id = '{$targetUserId}']");

            $scopeStr = implode(',', $newScopes);
            if (!empty($existing)) {
                $xdb->update(['scopes' => $scopeStr], "user_id = '{$targetUserId}'");
            } else {
                $xdb->insert(['user_id' => $targetUserId, 'scopes' => $scopeStr, 'updated_at' => date('Y-m-d H:i:s')]);
            }

            $la->notify('Admin permissions updated.', 'success');
        } catch (\Exception $e) {
            $la->setStatus('error')->notify('Failed to save permissions: ' . $e->getMessage(), 'error');
        }
    }
}
