<?php
/**
 * General Controller for SPP Admin
 * Routes legacy flat actions to the new grouped service logic.
 */

// Load grouped services
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Core.php';
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/XDB.php';
require_once __DIR__ . '/Modules.php';
require_once __DIR__ . '/Entities.php';
require_once __DIR__ . '/Forms.php';
require_once __DIR__ . '/Lifecycle.php';
require_once __DIR__ . '/Routing.php';
require_once __DIR__ . '/IAM.php';
require_once __DIR__ . '/AI.php';
require_once __DIR__ . '/spplang.php';

// Auth Redirects
if (!function_exists('live_login')) { function live_login($la, $p) { live_Auth_Login($la, $p); } }
if (!function_exists('live_logout')) { function live_logout($la, $p) { live_Auth_Logout($la, $p); } }
if (!function_exists('live_check_auth')) { function live_check_auth($la, $p) { live_Auth_Profile($la, $p); } }
if (!function_exists('live_get_profile')) { function live_get_profile($la, $p) { live_Auth_Profile($la, $p); } }

// Core Redirects
if (!function_exists('live_list_apps')) { function live_list_apps($la, $p) { live_Core_ListApps($la, $p); } }
if (!function_exists('live_run_command')) { function live_run_command($la, $p) { live_Core_RunCommand($la, $p); } }
if (!function_exists('live_health_check')) { function live_health_check($la, $p) { live_Core_HealthCheck($la, $p); } }
if (!function_exists('live_get_system_info')) { function live_get_system_info($la, $p) { live_Core_GetSystemInfo($la, $p); } }
if (!function_exists('live_get_bridge_info')) { function live_get_bridge_info($la, $p) { live_Core_GetBridgeInfo($la, $p); } }

// Config Redirects
if (!function_exists('live_get_interdb_config')) { function live_get_interdb_config($la, $p) { live_Config_InterDB_Get($la, $p); } }
if (!function_exists('live_save_interdb_config')) { function live_save_interdb_config($la, $p) { live_Config_InterDB_Save($la, $p); } }
if (!function_exists('live_get_ajax_services')) { function live_get_ajax_services($la, $p) { live_Config_Ajax_List($la, $p); } }
if (!function_exists('live_save_ajax_service')) { function live_save_ajax_service($la, $p) { live_Config_Ajax_Save($la, $p); } }

// XDB Redirects
if (!function_exists('live_list_xdb_databases')) { function live_list_xdb_databases($la, $p) { live_XDB_ListDB($la, $p); } }
if (!function_exists('live_list_xdb_tables')) { function live_list_xdb_tables($la, $p) { live_XDB_ListTables($la, $p); } }
if (!function_exists('live_get_xdb_table_data')) { function live_get_xdb_table_data($la, $p) { live_XDB_GetTableData($la, $p); } }
if (!function_exists('live_get_xdb_table_columns')) { function live_get_xdb_table_columns($la, $p) { live_XDB_GetTableColumns($la, $p); } }
if (!function_exists('live_run_xdb_query')) { function live_run_xdb_query($la, $p) { live_XDB_RunQuery($la, $p); } }
if (!function_exists('live_save_xdb_record')) { function live_save_xdb_record($la, $p) { live_XDB_SaveRecord($la, $p); } }
if (!function_exists('live_delete_xdb_record')) { function live_delete_xdb_record($la, $p) { live_XDB_DeleteRecord($la, $p); } }
if (!function_exists('live_get_global_settings')) { function live_get_global_settings($la, $p) { live_Config_GetGlobalSettings($la, $p); } }
if (!function_exists('live_save_global_settings')) { function live_save_global_settings($la, $p) { live_Config_SaveGlobalSettings($la, $p); } }

// Module Redirects
if (!function_exists('live_list_modules')) { function live_list_modules($la, $p) { live_List($la, $p); } }
if (!function_exists('live_scan_module')) { function live_scan_module($la, $p) { live_Scan($la, $p); } }
if (!function_exists('live_setup_module')) { function live_setup_module($la, $p) { live_Setup($la, $p); } }
if (!function_exists('live_get_module_config')) { function live_get_module_config($la, $p) { live_GetConfig($la, $p); } }
if (!function_exists('live_save_module_config')) { function live_save_module_config($la, $p) { live_SaveConfig($la, $p); } }
if (!function_exists('live_save_module_config_raw')) { function live_save_module_config_raw($la, $p) { live_SaveConfigRaw($la, $p); } }
if (!function_exists('live_toggle_module')) { function live_toggle_module($la, $p) { live_Toggle($la, $p); } }
if (!function_exists('live_open_module_settings')) { function live_open_module_settings($la, $p) { live_OpenSettings($la, $p); } }

// Entity Redirects
if (!function_exists('live_list_entities')) { function live_list_entities($la, $p) { live_Entities_List($la, $p); } }
if (!function_exists('live_save_entity_config')) { function live_save_entity_config($la, $p) { live_Entities_Save($la, $p); } }
if (!function_exists('live_delete_entity')) { function live_delete_entity($la, $p) { live_Entities_Delete($la, $p); } }
if (!function_exists('live_parse_entity_yaml')) { function live_parse_entity_yaml($la, $p) { live_Entities_ParseYAML($la, $p); } }
if (!function_exists('live_dump_entity_yaml')) { function live_dump_entity_yaml($la, $p) { live_Entities_DumpYAML($la, $p); } }

// Form Redirects
if (!function_exists('live_list_forms')) { function live_list_forms($la, $p) { live_Forms_List($la, $p); } }
if (!function_exists('live_save_form')) { function live_save_form($la, $p) { live_Forms_Save($la, $p); } }
if (!function_exists('live_delete_form')) { function live_delete_form($la, $p) { live_Forms_Delete($la, $p); } }
if (!function_exists('live_parse_form_yaml')) { function live_parse_form_yaml($la, $p) { live_Forms_ParseYAML($la, $p); } }
if (!function_exists('live_dump_form_yaml')) { function live_dump_form_yaml($la, $p) { live_Forms_DumpYAML($la, $p); } }

if (!function_exists('live_system_update_list')) { function live_system_update_list($la, $p) { live_lifecycle_updatelist($la, $p); } }
if (!function_exists('live_system_update_run')) { function live_system_update_run($la, $p) { live_lifecycle_updaterun($la, $p); } }
if (!function_exists('live_sync_deployment_token')) { function live_sync_deployment_token($la, $p) { live_lifecycle_rotatetoken($la, $p); } }

// Routing Redirects
if (!function_exists('live_list_pages')) { function live_list_pages($la, $p) { live_Routing_ListPages($la, $p); } }
if (!function_exists('live_save_page')) { function live_save_page($la, $p) { live_Routing_SavePage($la, $p); } }
if (!function_exists('live_remove_page')) { function live_remove_page($la, $p) { live_Routing_RemovePage($la, $p); } }
if (!function_exists('live_list_services')) { function live_list_services($la, $p) { live_Routing_ListServices($la, $p); } }
if (!function_exists('live_save_service')) { function live_save_service($la, $p) { live_Routing_SaveService($la, $p); } }
if (!function_exists('live_remove_service')) { function live_remove_service($la, $p) { live_Routing_RemoveService($la, $p); } }

// IAM Redirects
if (!function_exists('live_list_users')) { function live_list_users($la, $p) { live_IAM_ListUsers($la, $p); } }
if (!function_exists('live_list_roles')) { function live_list_roles($la, $p) { live_IAM_ListRoles($la, $p); } }
if (!function_exists('live_list_rights')) { function live_list_rights($la, $p) { live_IAM_ListRights($la, $p); } }
if (!function_exists('live_list_rbac')) { function live_list_rbac($la, $p) { live_IAM_ListRBAC($la, $p); } }
if (!function_exists('live_list_entity_assignments')) { function live_list_entity_assignments($la, $p) { live_IAM_ListEntityAssignments($la, $p); } }
if (!function_exists('live_get_iam_details')) { function live_get_iam_details($la, $p) { live_IAM_GetDetails($la, $p); } }
if (!function_exists('live_search_entities')) { function live_search_entities($la, $p) { live_IAM_SearchEntities($la, $p); } }
if (!function_exists('live_assign_role_to_entity')) { function live_assign_role_to_entity($la, $p) { live_IAM_AssignRole($la, $p); } }
if (!function_exists('live_remove_role_from_entity')) { function live_remove_role_from_entity($la, $p) { live_IAM_RemoveRole($la, $p); } }
if (!function_exists('live_assign_right_to_role')) { function live_assign_right_to_role($la, $p) { live_IAM_AssignRight($la, $p); } }
if (!function_exists('live_remove_right_from_role')) { function live_remove_right_from_role($la, $p) { live_IAM_RemoveRight($la, $p); } }
if (!function_exists('live_toggle_user_status')) { function live_toggle_user_status($la, $p) { live_IAM_ToggleUserStatus($la, $p); } }
if (!function_exists('live_get_form_html')) { function live_get_form_html($la, $p) { live_IAM_GetFormHTML($la, $p); } }
if (!function_exists('live_save_user')) { function live_save_user($la, $p) { live_IAM_SaveUser($la, $p); } }
if (!function_exists('live_save_role')) { function live_save_role($la, $p) { live_IAM_SaveRole($la, $p); } }
if (!function_exists('live_save_right')) { function live_save_right($la, $p) { live_IAM_SaveRight($la, $p); } }
if (!function_exists('live_save_modern_role')) { function live_save_modern_role($la, $p) { live_IAM_SaveModernRole($la, $p); } }

// Group Redirects
if (!function_exists('live_list_groups')) { function live_list_groups($la, $p) { live_IAM_ListGroups($la, $p); } }
if (!function_exists('live_list_group_members')) { function live_list_group_members($la, $p) { live_IAM_ListGroupMembers($la, $p); } }
if (!function_exists('live_add_group_member')) { function live_add_group_member($la, $p) { live_IAM_AddGroupMember($la, $p); } }
if (!function_exists('live_remove_group_member')) { function live_remove_group_member($la, $p) { live_IAM_RemoveGroupMember($la, $p); } }
if (!function_exists('live_save_group')) { function live_save_group($la, $p) { live_IAM_SaveGroup($la, $p); } }
if (!function_exists('live_delete_group')) { function live_delete_group($la, $p) { live_IAM_DeleteGroup($la, $p); } }

// AI Redirects
if (!function_exists('live_get_ai_registry')) { function live_get_ai_registry($la, $p) { live_AI_GetRegistry($la, $p); } }
if (!function_exists('live_test_ai_prompt')) { function live_test_ai_prompt($la, $p) { live_AI_TestPrompt($la, $p); } }

/**
 * Hybrid View Loader
 * Renders a PHP view and returns it as a LiveAction HTML fragment.
 */
if (!function_exists('live_load_view')) {
    function live_load_view($la, $p) {
        $view = $p['view'] ?? 'dashboard';
        $viewFile = SPP_BASE_DIR . SPP_DS . 'admin' . SPP_DS . 'views' . SPP_DS . basename($view) . '.php';
        error_log("[SPPAdmin] Loading view: $view from $viewFile");
        
        if (!file_exists($viewFile)) {
            error_log("[SPPAdmin] View file not found: $viewFile");
            return $la->setStatus('error')->notify("View '{$view}' not found.");
        }
        
        // Collect data for the view
        $data = [];
        if ($view === 'system') {
            error_log("[SPPAdmin] Collecting system data...");
            $la_sys = new \SPPMod\SPPAjax\LiveAction();
            live_Core_GetSystemInfo($la_sys, []);
            $data['system'] = $la_sys->getData();
            
            $la_bridge = new \SPPMod\SPPAjax\LiveAction();
            live_Core_GetBridgeInfo($la_bridge, []);
            $data['bridge'] = $la_bridge->getData();
            
            $la_apps = new \SPPMod\SPPAjax\LiveAction();
            live_Core_ListApps($la_apps, []);
            $data['apps'] = $la_apps->getData()['apps'] ?? [];
            
            $la_settings = new \SPPMod\SPPAjax\LiveAction();
            live_Config_GetGlobalSettings($la_settings, []);
            $data['settings'] = $la_settings->getData();
        }
        
        ob_start();
        $params = [
            'la' => $la, 
            'data' => $data,
            'selectedApp' => $_REQUEST['appname'] ?? 'default'
        ];
        include $viewFile;
        $html = ob_get_clean();
        error_log("[SPPAdmin] View $view rendered, length: " . strlen($html));
        
        $la->setData(['html' => $html]);
    }
}
