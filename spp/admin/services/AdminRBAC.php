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
        'workflows'   => 'admin.workflows',
        'domains'     => 'admin.domains',
        'dns'         => 'admin.dns',
        'files'       => 'admin.files',
        'terminal'    => 'admin.commands',
        'database'    => 'admin.database',
        'mail'        => 'admin.mail',
        'shield'      => 'admin.shield',
        'metrics'     => 'admin.metrics',
        'server'      => 'admin.server',
        'accounts'    => 'admin.accounts',
        'apptoolkit'  => 'admin.apps',
        'runtime'     => 'admin.system',
        'reseller'    => 'admin.reseller',
    ];
}

/**
 * Map API actions to their required admin scopes.
 * Actions not listed here are allowed for any authenticated admin.
 */
function getActionScopeMap(): array {
    return [
        // Domain, routing and host configuration
        'domains_list' => 'admin.domains',
        'domain_save' => 'admin.domains',
        'domains_save' => 'admin.domains',
        'domain_delete' => 'admin.domains',
        'domains_delete' => 'admin.domains',
        'domain_scaffold_paths' => 'admin.domains',
        'domains_scaffold_paths' => 'admin.domains',
        'domain_test' => 'admin.domains',
        'domain_check_ssl' => 'admin.domains',
        'domains_get_settings' => 'admin.domains',
        'domains_set_domain_mode' => 'admin.domains',
        'domains_set_architecture_mode' => 'admin.domains',
        'domains_set_mode' => 'admin.domains',
        'domains_set_virtual_host_mode' => 'admin.domains',
        'domains_addon_list' => 'admin.domains',
        'domains_addon_add' => 'admin.domains',
        'domains_addon_remove' => 'admin.domains',
        'domains_subapp_save' => 'admin.domains',
        'domains_subapp_delete' => 'admin.domains',
        'domains_subapp_probe' => 'admin.domains',
        'domains_get_apps' => 'admin.domains',
        'domains_get_domain_apps' => 'admin.domains',
        'domains_set_default_app' => 'admin.domains',
        'domains_install_app' => 'admin.domains',
        'domains_set_app_scope' => 'admin.domains',
        'domains_delete_app' => 'admin.domains',
        'domains_track_dns' => 'admin.domains',
        'domains_proxy_get' => 'admin.domains',
        'domains_proxy_save' => 'admin.domains',
        'domains_proxy_probe' => 'admin.domains',
        'domains_vhost_inspect' => 'admin.domains',
        'domains_vhost_test' => 'admin.domains',
        'domains_fastcgi_purge' => 'admin.domains',
        'domains_webserver_drivers' => 'admin.domains',
        'domains_set_webserver_driver' => 'admin.domains',
        'domains_autossl_issue' => 'admin.domains',
        'domains_autossl_provision' => 'admin.domains',

        // Multi-domain apps & activation matrix
        'apps_activation_matrix' => 'admin.apps',
        'apps_set_activation' => 'admin.apps',
        'apps_global_list' => 'admin.apps',
        'apps_domain_list' => 'admin.apps',
        'apps_install_global' => 'admin.apps',
        'apps_install_domain' => 'admin.apps',
        'apps_uninstall_global' => 'admin.apps',
        'apps_uninstall_domain' => 'admin.apps',
        'apps_config_get' => 'admin.apps',
        'apps_config_save_global' => 'admin.apps',
        'apps_config_save_domain' => 'admin.apps',
        'apps_config_diff' => 'admin.apps',
        'apps_config_reset' => 'admin.apps',
        'database_contexts_list' => 'admin.database',
        'database_context_switch' => 'admin.database',
        'audit_logs_query' => 'admin.trace',
        'backup_scopes_list' => 'admin.lifecycle',
        'deploy_scopes_list' => 'admin.lifecycle',
        'security_policies_get' => 'admin.shield',
        'security_policies_save' => 'admin.shield',
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
        'run_command_stream'    => 'admin.commands',
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
        'deploy_git_status'           => 'admin.lifecycle',
        'deploy_push'                 => 'admin.lifecycle',
        'deploy_rollback'             => 'admin.lifecycle',
        'backup_create'               => 'admin.lifecycle',
        'backup_list'                 => 'admin.lifecycle',
        'backup_restore'              => 'admin.lifecycle',
        'backup_delete'               => 'admin.lifecycle',
        'backup_incremental_create'   => 'admin.lifecycle',
        'backup_destinations_get'     => 'admin.lifecycle',
        'backup_destination_save'     => 'admin.lifecycle',
        'backup_destination_delete'   => 'admin.lifecycle',
        'backup_destination_test'     => 'admin.lifecycle',
        'backup_destination_upload'   => 'admin.lifecycle',
        'backup_schedules_get'        => 'admin.lifecycle',
        'backup_schedule_save'        => 'admin.lifecycle',
        'backup_schedule_delete'      => 'admin.lifecycle',
        'backup_schedules_run_due'    => 'admin.lifecycle',
        'backup_retention_enforce'    => 'admin.lifecycle',
        'deploy_spp_status'           => 'admin.lifecycle',
        'deploy_spp_health'           => 'admin.lifecycle',
        'deploy_spp_plan'             => 'admin.lifecycle',
        'deploy_spp_push'             => 'admin.lifecycle',
        'deploy_spp_rollback'         => 'admin.lifecycle',
        'deploy_spp_history'          => 'admin.lifecycle',
        'deploy_spp_backups'          => 'admin.lifecycle',
        'deploy_spp_cleanup'          => 'admin.lifecycle',
        'deploy_spp_maintenance'      => 'admin.lifecycle',
        'deploy_spp_logs'             => 'admin.lifecycle',
        'deploy_spp_run'              => 'admin.lifecycle',
        'deploy_spp_clusters'         => 'admin.lifecycle',
        'deploy_spp_save_config'      => 'admin.lifecycle',
        'deploy_spp_rotate_token'     => 'admin.lifecycle',
        'deploy_spp_test_webhook'     => 'admin.lifecycle',
        'deploy_spp_env_keys'         => 'admin.lifecycle',
        'deploy_spp_env_push'         => 'admin.lifecycle',
        'deploy_spp_target_get'       => 'admin.lifecycle',
        'deploy_spp_target_save'      => 'admin.lifecycle',
        'deploy_spp_target_delete'    => 'admin.lifecycle',
        'deploy_canary_push'          => 'admin.lifecycle',
        'deploy_canary_deploy'        => 'admin.lifecycle',
        'deploy_canary_rollback'      => 'admin.lifecycle',
        'deploy_canary_releases'      => 'admin.lifecycle',
        'deploy_canary_status'        => 'admin.lifecycle',
        'deploy_mixed_run'            => 'admin.lifecycle',
        'deploy_mixed_deploy'         => 'admin.lifecycle',
        'deploy_mixed_plan'           => 'admin.lifecycle',
        'deploy_mixed_status'         => 'admin.lifecycle',
        'deploy_mixed_rollback'       => 'admin.lifecycle',
        'deploy_predeploy_comparison' => 'admin.lifecycle',
        'deploy_preflight_inspect'    => 'admin.lifecycle',
        'deploy_canary_weight'        => 'admin.lifecycle',
        'deploy_release_timeline'     => 'admin.lifecycle',

        // Bare-Metal Host & Fleet VPS Installer
        'host_installer_summary'      => 'admin.lifecycle',
        'host_installer_generate'     => 'admin.lifecycle',
        'host_installer_save'         => 'admin.lifecycle',



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
        'save_form_config'      => 'admin.forms',
        'preview_form'          => 'admin.forms',
        'parse_form_yaml'       => 'admin.forms',
        'dump_form_yaml'        => 'admin.forms',
        'list_workflows'        => 'admin.workflows',
        'save_workflow'         => 'admin.workflows',
        'delete_workflow'       => 'admin.workflows',
        'get_workflow_graph'    => 'admin.workflows',
        'Workflows_List'        => 'admin.workflows',
        'Workflows_Save'        => 'admin.workflows',
        'Workflows_Delete'      => 'admin.workflows',
        'Workflows_GetGraph'    => 'admin.workflows',
        'list_pages'            => 'admin.routing',
        'list_middleware'       => 'admin.routing',
        'routing_middleware_stack' => 'admin.routing',
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
        'switch_context'        => 'admin.dashboard',
        'set_context'           => 'admin.dashboard',
        'agent_health'          => 'admin.system',
        'agent_capabilities'    => 'admin.system',
        'agent_operations'      => 'admin.system',
        'agent_call'            => 'admin.server',
        'operation_preview'     => 'admin.system',
        'operation_execute'     => 'admin.server',
        'job_status'            => 'admin.system',
        'job_cancel'            => 'admin.system',
        'get_ai_registry'       => 'admin.ai',
        'test_ai_prompt'        => 'admin.ai',
        'diagnostics_health'    => 'admin.system',
        'list_queue'            => 'admin.system',
        'get_event_trace'       => 'admin.trace',
        'get_parikshak_trace'   => 'admin.trace',
        'run_parikshak_scan'    => 'admin.trace',
        'get_admin_permissions' => 'admin.identity',
        'save_admin_permissions'=> 'admin.identity',
        'install_all_active'    => 'admin.system',

        // Database & Reactive Data Studio
        'db_list_databases'     => 'admin.database',
        'db_list_tables'        => 'admin.database',
        'db_describe_table'     => 'admin.database',
        'db_query_table'        => 'admin.database',
        'db_save_row'           => 'admin.database',
        'db_delete_row'         => 'admin.database',
        'db_delete_rows_batch'  => 'admin.database',
        'db_execute_sql'        => 'admin.database',
        'db_check_database'     => 'admin.database',
        'db_repair_database'    => 'admin.database',
        'db_create_table'       => 'admin.database',
        'db_drop_table'         => 'admin.database',
        'db_truncate_table'     => 'admin.database',
        'db_rename_table'       => 'admin.database',
        'db_duplicate_table'    => 'admin.database',
        'db_add_column'         => 'admin.database',
        'db_drop_column'        => 'admin.database',
        'db_add_index'          => 'admin.database',
        'db_drop_index'         => 'admin.database',
        'db_create_database'    => 'admin.database',
        'db_delete_database'    => 'admin.database',
        'db_export_dump'        => 'admin.database',
        'db_import_dump'        => 'admin.database',
        'db_search_database'    => 'admin.database',
        'db_provision_list'     => 'admin.database',
        'db_provision_create_database' => 'admin.database',
        'db_provision_create_user'     => 'admin.database',
        'db_provision_grant'           => 'admin.database',
        'db_provision_revoke'          => 'admin.database',
        'db_provision_delete_db'       => 'admin.database',
        'db_provision_delete_user'     => 'admin.database',
        'db_provision_change_password' => 'admin.database',
        'db_provision_remote_hosts_get' => 'admin.database',
        'db_provision_remote_host_add' => 'admin.database',
        'db_provision_remote_host_remove' => 'admin.database',
        'db_provision_optimize_tables' => 'admin.database',
        'db_get_configs'        => 'admin.database',
        'db_save_config'        => 'admin.database',
        'db_delete_config'      => 'admin.database',
        'db_test_connection'    => 'admin.database',
        'db_schema_graph'       => 'admin.database',
        'db_export_erd_svg'     => 'admin.database',
        'db_create_snapshot'    => 'admin.database',
        'db_list_snapshots'     => 'admin.database',
        'db_compare_snapshot'   => 'admin.database',
        'db_rollback_snapshot'  => 'admin.database',
        'timemachine_create_snapshot' => 'admin.database',
        'timemachine_list_snapshots'  => 'admin.database',
        'timemachine_diff'            => 'admin.database',
        'timemachine_rollback'        => 'admin.database',
        'timemachine_delete'          => 'admin.database',
        'db_generate_migration' => 'admin.database',

        // Enterprise Communications & SPPMail
        'mail_queue_list'       => 'admin.mail',
        'mail_send_test'        => 'admin.mail',
        'mail_queue_retry'      => 'admin.mail',
        'mail_queue_delete'     => 'admin.mail',
        'mail_queue_purge'      => 'admin.mail',
        'mail_dkim_generate'    => 'admin.mail',
        'mail_deliverability_audit' => 'admin.mail',
        'mail_deliverability_align' => 'admin.mail',
        'mail_stream_counts'    => 'admin.mail',
        'mail_settings_get'     => 'admin.mail',
        'mail_settings_save'    => 'admin.mail',

        // e-Vaarta Inbound & Omnichannel Communication Actions
        'mail_inbox_list'       => 'admin.mail',
        'evaarta_inbox_list'    => 'admin.mail',
        'mail_thread_get'       => 'admin.mail',
        'evaarta_thread_get'    => 'admin.mail',
        'mail_thread_update'    => 'admin.mail',
        'evaarta_thread_update' => 'admin.mail',
        'mail_thread_mark_read' => 'admin.mail',
        'evaarta_thread_mark_read' => 'admin.mail',
        'mail_thread_delete'    => 'admin.mail',
        'evaarta_thread_delete' => 'admin.mail',
        'mail_thread_bulk'      => 'admin.mail',
        'evaarta_thread_bulk'   => 'admin.mail',
        'mail_send'             => 'admin.mail',
        'evaarta_send'          => 'admin.mail',
        'mail_tag_add'          => 'admin.mail',
        'evaarta_tag_add'       => 'admin.mail',
        'mail_tag_remove'       => 'admin.mail',
        'evaarta_tag_remove'    => 'admin.mail',
        'mail_tags_list'        => 'admin.mail',
        'evaarta_tags_list'     => 'admin.mail',
        'mail_tag_create'       => 'admin.mail',
        'evaarta_tag_create'    => 'admin.mail',
        'mail_note_add'         => 'admin.mail',
        'evaarta_note_add'      => 'admin.mail',
        'mail_note_list'        => 'admin.mail',
        'evaarta_note_list'     => 'admin.mail',
        'mail_collision_ping'   => 'admin.mail',
        'evaarta_collision_ping'=> 'admin.mail',
        'mail_collision_viewers'=> 'admin.mail',
        'evaarta_collision_viewers' => 'admin.mail',
        'mail_thread_assign'    => 'admin.mail',
        'evaarta_thread_assign' => 'admin.mail',
        'mail_action_toggle'    => 'admin.mail',
        'evaarta_action_toggle' => 'admin.mail',
        'mail_ai_summary'       => 'admin.mail',
        'evaarta_ai_summary'    => 'admin.mail',
        'mail_ai_draft'         => 'admin.mail',
        'evaarta_ai_draft'      => 'admin.mail',
        'mail_workflow_transition' => 'admin.mail',
        'evaarta_workflow_transition' => 'admin.mail',
        'mail_search'           => 'admin.mail',
        'evaarta_search'        => 'admin.mail',
        'mail_imap_sync'        => 'admin.mail',
        'evaarta_imap_sync'     => 'admin.mail',

        // Automation Rules & Triage scopes
        'mail_rule_list'        => 'admin.mail',
        'evaarta_rule_list'     => 'admin.mail',
        'mail_rules'            => 'admin.mail',
        'mail_rule_save'        => 'admin.mail',
        'evaarta_rule_save'     => 'admin.mail',
        'mail_rule_delete'      => 'admin.mail',
        'evaarta_rule_delete'   => 'admin.mail',
        'mail_rule_toggle'      => 'admin.mail',
        'evaarta_rule_toggle'   => 'admin.mail',
        'mail_rule_test'        => 'admin.mail',
        'evaarta_rule_test'     => 'admin.mail',
        'mail_screener_list'    => 'admin.mail',
        'evaarta_screener_list' => 'admin.mail',
        'mail_screeners'        => 'admin.mail',
        'mail_screener_set'     => 'admin.mail',
        'evaarta_screener_set'  => 'admin.mail',
        'mail_screen_sender'    => 'admin.mail',
        'evaarta_screen_sender' => 'admin.mail',
        'mail_screen_thread'    => 'admin.mail',
        'evaarta_screen_thread' => 'admin.mail',
        'mail_screener_delete'  => 'admin.mail',
        'evaarta_screener_delete' => 'admin.mail',
        'mail_thread_snooze'    => 'admin.mail',
        'mail_snooze'           => 'admin.mail',
        'evaarta_thread_snooze' => 'admin.mail',
        'evaarta_snooze'        => 'admin.mail',
        'mail_thread_unsnooze'  => 'admin.mail',
        'mail_unsnooze'         => 'admin.mail',
        'evaarta_thread_unsnooze'=> 'admin.mail',
        'evaarta_unsnooze'      => 'admin.mail',
        'mail_snooze_process'   => 'admin.mail',
        'evaarta_snooze_process'=> 'admin.mail',

        // Multi-Account scopes
        'mail_account_list'     => 'admin.mail',
        'evaarta_account_list'  => 'admin.mail',
        'mail_accounts'         => 'admin.mail',
        'evaarta_accounts'      => 'admin.mail',
        'mail_account_get'      => 'admin.mail',
        'evaarta_account_get'   => 'admin.mail',
        'mail_account_save'     => 'admin.mail',
        'evaarta_account_save'  => 'admin.mail',
        'mail_account_delete'   => 'admin.mail',
        'evaarta_account_delete'=> 'admin.mail',
        'mail_account_default'  => 'admin.mail',
        'evaarta_account_default' => 'admin.mail',
        'mail_account_test'     => 'admin.mail',
        'evaarta_account_test'  => 'admin.mail',
        'mail_account_sync'     => 'admin.mail',
        'evaarta_account_sync'  => 'admin.mail',
        'mail_account_sync_older' => 'admin.mail',
        'evaarta_account_sync_older' => 'admin.mail',
        'mail_sync_older'       => 'admin.mail',
        'mail_account_sync_all' => 'admin.mail',
        'evaarta_account_sync_all' => 'admin.mail',
        'mail_account_autodiscover' => 'admin.mail',
        'evaarta_account_autodiscover' => 'admin.mail',
        'mail_account_presets'  => 'admin.mail',
        'evaarta_account_presets'=> 'admin.mail',

        // Local Server Virtual Mailboxes & Forwarders
        'mail_server_accounts_list'   => 'admin.mail',
        'mail_virtual_list'           => 'admin.mail',
        'mail_virtual_mailboxes'      => 'admin.mail',
        'mail_server_mailboxes'       => 'admin.mail',
        'mail_server_account_get'     => 'admin.mail',
        'mail_virtual_get'            => 'admin.mail',
        'mail_server_account_create'  => 'admin.mail',
        'mail_virtual_create'         => 'admin.mail',
        'mail_server_account_password'=> 'admin.mail',
        'mail_server_account_passwd'  => 'admin.mail',
        'mail_virtual_password'       => 'admin.mail',
        'mail_server_account_quota'   => 'admin.mail',
        'mail_virtual_quota'          => 'admin.mail',
        'mail_server_account_status'  => 'admin.mail',
        'mail_virtual_status'         => 'admin.mail',
        'mail_server_account_delete'  => 'admin.mail',
        'mail_virtual_delete'         => 'admin.mail',
        'mail_device_config'          => 'admin.mail',
        'server_mailbox_device_config'=> 'admin.mail',
        'mail_server_domains'         => 'admin.mail',
        'mail_virtual_domains'        => 'admin.mail',
        'mail_server_forwarders_list' => 'admin.mail',
        'mail_forwarders_list'        => 'admin.mail',
        'mail_forwarder_list'         => 'admin.mail',
        'mail_server_forwarder_create'=> 'admin.mail',
        'mail_forwarder_create'       => 'admin.mail',
        'mail_server_forwarder_delete'=> 'admin.mail',
        'mail_forwarder_delete'       => 'admin.mail',
        'mail_server_autoresponder_set'=> 'admin.mail',
        'mail_autoresponder_set'      => 'admin.mail',

        // DNS Zone Editor & Anycast Propagation
        'domains_zone_get'      => 'admin.domains',
        'domain_get_zone'       => 'admin.domains',
        'domains_zone_save_record'=> 'admin.domains',
        'domain_save_record'    => 'admin.domains',
        'domains_zone_delete_record'=> 'admin.domains',
        'domain_delete_record'  => 'admin.domains',
        'domains_zone_reset'    => 'admin.domains',
        'domain_reset_zone'     => 'admin.domains',
        'domains_zone_export'   => 'admin.domains',
        'domain_export_zone'    => 'admin.domains',
        'domains_zone_import'   => 'admin.domains',
        'domain_import_zone'    => 'admin.domains',
        'domains_check_propagation'=> 'admin.domains',
        'domain_check_propagation'=> 'admin.domains',
        'domains_dnssec_get'    => 'admin.domains',
        'domain_get_dnssec'     => 'admin.domains',
        'domains_dnssec_toggle' => 'admin.domains',
        'domain_toggle_dnssec'  => 'admin.domains',

        // DNS Zone History, Rollback & Manifest OS Integration
        'domains_zone_history'      => 'admin.domains',
        'domain_get_zone_history'   => 'admin.domains',
        'dns_zone_history'          => 'admin.domains',
        'domains_preview_rollback'  => 'admin.domains',
        'domain_preview_rollback'   => 'admin.domains',
        'dns_preview_rollback'      => 'admin.domains',
        'domains_rollback_zone'     => 'admin.domains',
        'domain_rollback_zone'      => 'admin.domains',
        'dns_rollback_zone'         => 'admin.domains',
        'domains_sync_manifest'     => 'admin.domains',
        'domain_sync_manifest'      => 'admin.domains',
        'dns_sync_manifest'         => 'admin.domains',

        // Cloudflare Edge DNS & Edge SSL Bridge
        'domains_cf_get_settings'    => 'admin.domains',
        'domains_cf_save_settings'   => 'admin.domains',
        'domains_cf_verify_token'    => 'admin.domains',
        'domains_cf_list_zones'      => 'admin.domains',
        'domains_cf_list_records'    => 'admin.domains',
        'domains_cf_create_record'   => 'admin.domains',
        'domains_cf_update_record'   => 'admin.domains',
        'domains_cf_delete_record'   => 'admin.domains',
        'domains_cf_purge_cache'     => 'admin.domains',
        'domains_cf_sync_zone'       => 'admin.domains',
        'domains_cf_auto_provision'  => 'admin.domains',
        'domains_autossl_renew_all'  => 'admin.domains',
        'domains_sovereign_ssl_overview' => 'admin.domains',
        'domains_certbot_status'     => 'admin.domains',
        'domains_certbot_install'    => 'admin.domains',
        'domains_dns_preflight'      => 'admin.domains',
        'domains_certbot_issue'      => 'admin.domains',
        'domains_glue_matrix'        => 'admin.domains',
        'domains_tld_glue'           => 'admin.domains',
        'domains_registry_whois'     => 'admin.domains',
        'bind_daemon_status'         => 'admin.domains',
        'bind_daemon_install'        => 'admin.domains',
        'bind_zone_sync'             => 'admin.domains',
        'bind_all_zones_sync'        => 'admin.domains',
        'bind_zone_test'             => 'admin.domains',
        'bind_daemon_reload'         => 'admin.domains',

        // Unified Multi-Provider DNS & Registrar Subsystem
        'dns_providers_list'               => 'admin.dns.provider_admin',
        'domains_dns_providers'            => 'admin.dns.provider_admin',
        'dns_provider_account_save'        => 'admin.dns.credentials',
        'domains_dns_account_save'         => 'admin.dns.credentials',
        'dns_provider_account_delete'      => 'admin.dns.credentials',
        'domains_dns_account_delete'       => 'admin.dns.credentials',
        'dns_provider_test'                => 'admin.dns.provider_admin',
        'domains_dns_provider_test'        => 'admin.dns.provider_admin',
        'dns_mapping_get'                  => 'admin.dns.zone_read',
        'domains_dns_mapping_get'          => 'admin.dns.zone_read',
        'dns_mapping_save'                 => 'admin.dns.provider_admin',
        'domains_dns_mapping_save'         => 'admin.dns.provider_admin',
        'dns_mappings_list'                => 'admin.dns.zone_read',
        'domains_dns_mappings_list'        => 'admin.dns.zone_read',
        'dns_zone_compare'                 => 'admin.dns.zone_read',
        'domains_dns_compare'              => 'admin.dns.zone_read',
        'dns_zone_sync'                    => 'admin.dns.sync',
        'domains_dns_sync'                 => 'admin.dns.sync',
        'dns_audit_propagation'            => 'admin.dns.zone_read',
        'domains_dns_propagation'          => 'admin.dns.zone_read',
        'dns_audit_dnssec'                 => 'admin.dns.dnssec',
        'domains_dns_dnssec_audit'         => 'admin.dns.dnssec',
        'dns_audit_mail'                   => 'admin.dns.zone_read',
        'domains_dns_mail_audit'           => 'admin.dns.zone_read',
        'dns_registrar_info'               => 'admin.dns.registrar',
        'domains_registrar_info'           => 'admin.dns.registrar',
        'dns_registrar_nameservers_update' => 'admin.dns.registrar',
        'domains_registrar_nameservers_update' => 'admin.dns.registrar',
        'doctor_diagnostics'         => 'admin.server',
        'doctor_repair'              => 'admin.server',
        'host_inspect'               => 'admin.server',
        'host_provision'             => 'admin.server',
        'host_deconflict'            => 'admin.server',

        // SPPShield Security Hardening Suite
        'shield_overview'       => 'admin.shield',
        'shield_firewall_get'   => 'admin.shield',
        'shield_firewall_block' => 'admin.shield',
        'shield_firewall_unblock'=> 'admin.shield',
        'shield_whitelist_add'  => 'admin.shield',
        'shield_whitelist_remove'=> 'admin.shield',
        'shield_bruteforce_get' => 'admin.shield',
        'shield_bruteforce_clear'=> 'admin.shield',
        'shield_scan_run'       => 'admin.shield',
        'shield_quarantine_get' => 'admin.shield',
        'shield_quarantine_restore'=> 'admin.shield',
        'shield_quarantine_delete'=> 'admin.shield',
        'shield_audit_run'      => 'admin.shield',
        'shield_ssh_keys_get'   => 'admin.shield',
        'shield_ssh_keys_add'   => 'admin.shield',
        'shield_ssh_keys_remove'=> 'admin.shield',
        'shield_ssh_keys_generate'=> 'admin.shield',
        'shield_hotlink_get'      => 'admin.shield',
        'shield_hotlink_save'     => 'admin.shield',
        'shield_privacy_list'     => 'admin.shield',
        'shield_privacy_save'     => 'admin.shield',
        'shield_privacy_delete'   => 'admin.shield',

        // Phase 6: Sovereign Cloud Backup Vault & SSH Key Management
        'backup_vault_list'       => 'admin.server',
        'backup_vault_create'     => 'admin.server',
        'backup_vault_verify'     => 'admin.server',
        'backup_vault_restore'    => 'admin.server',
        'backup_vault_delete'     => 'admin.server',
        'ssh_vault_list'          => 'admin.shield',
        'ssh_vault_generate'      => 'admin.shield',
        'ssh_vault_import'        => 'admin.shield',
        'ssh_vault_revoke'        => 'admin.shield',
        'ssh_vault_delete'        => 'admin.shield',
        'ssh_vault_connect_info'  => 'admin.shield',
        'ssh_vault_audit'         => 'admin.shield',

        // Phase 7: SPP Ignition Visual Debugger & W3C Distributed Tracing
        'ignition_list'           => 'admin.metrics',
        'ignition_get'            => 'admin.metrics',
        'ignition_resolve'        => 'admin.metrics',
        'ignition_clear'          => 'admin.metrics',
        'ignition_simulate'       => 'admin.metrics',
        'trace_generate'          => 'admin.metrics',
        'trace_parse'             => 'admin.metrics',
        'trace_journey'           => 'admin.metrics',

        // Phase 8: Dead-Letter Queue (DLQ) & Custom DAG Pipelines
        'queue_dlq_list'          => 'admin.server',
        'queue_dlq_replay'        => 'admin.server',
        'queue_dlq_purge'         => 'admin.server',
        'dag_pipeline_save'       => 'admin.server',

        // SPPMetrics Server Telemetry & Web Analytics
        'metrics_overview'        => 'admin.metrics',
        'metrics_hud_get'         => 'admin.metrics',
        'metrics_traffic_get'     => 'admin.metrics',
        'metrics_history_get'     => 'admin.metrics',
        'metrics_snapshot_record' => 'admin.metrics',
        'metrics_report_generate' => 'admin.metrics',

        // SPPServer Host Management & Accounts
        'server_services_get'       => 'admin.server',
        'server_service_control'    => 'admin.server',
        'server_processes_get'      => 'admin.server',
        'server_process_kill'       => 'admin.server',
        'server_process_renice'     => 'admin.server',
        'server_accounts_get'       => 'admin.accounts',
        'server_account_create'     => 'admin.accounts',
        'server_account_transition' => 'admin.accounts',
        'server_account_delete'     => 'admin.accounts',
        'server_archive_inspect'    => 'admin.server',
        'server_archive_import'     => 'admin.server',

        // SPPApps CMS & WordPress Toolkit
        'apps_catalog_get'     => 'admin.apps',
        'apps_install'         => 'admin.apps',
        'apps_uninstall'       => 'admin.apps',
        'apps_uninstall_framework' => 'admin.apps',
        'apps_reinstall'       => 'admin.apps',
        'apps_reinstall_framework' => 'admin.apps',
        'apps_list_framework'  => 'admin.apps',
        'apps_backup_create'   => 'admin.apps',
        'apps_list_backups'    => 'admin.apps',
        'apps_restore_backup'  => 'admin.apps',
        'apps_wp_inspect'      => 'admin.apps',
        'apps_wp_harden'       => 'admin.apps',
        'apps_wp_cli'          => 'admin.apps',
        'apps_staging_create'  => 'admin.apps',
        'apps_staging_sync'    => 'admin.apps',
        'apps_staging_list'    => 'admin.apps',
        'cpanel_import_inspect'=> 'admin.apps',
        'cpanel_import_execute'=> 'admin.apps',
        'cpanel_import_list_archives' => 'admin.apps',

        // SPP Multi-Language Service Mesh & Supervisor
        'apps_mesh_status'        => 'admin.apps',
        'apps_mesh_start'         => 'admin.apps',
        'apps_mesh_stop'          => 'admin.apps',
        'apps_mesh_restart'       => 'admin.apps',
        'apps_mesh_logs'          => 'admin.apps',
        'apps_mesh_call'          => 'admin.apps',
        'apps_mesh_publish_event' => 'admin.apps',

        // SPPRuntime Multi-PHP & PHP-FPM Pools
        'runtime_php_get'        => 'admin.system',
        'runtime_php_switch'     => 'admin.system',
        'runtime_fpm_pools_get'  => 'admin.system',
        'runtime_fpm_pool_save'  => 'admin.system',
        'runtime_fpm_pool_delete'=> 'admin.system',
        'runtime_fpm_test'       => 'admin.system',
        'runtime_ini_get'        => 'admin.system',
        'runtime_ini_set'        => 'admin.system',
        'runtime_ini_override'   => 'admin.system',
        'runtime_opcache_status' => 'admin.system',
        'runtime_opcache_reset'  => 'admin.system',
        'runtime_opcache_warmup' => 'admin.system',
        'runtime_extensions_matrix' => 'admin.system',
        'runtime_sizing_advisor' => 'admin.system',

        // SPPReseller Multi-Tenancy & Reseller Management
        'reseller_list'            => 'admin.reseller',
        'reseller_create'          => 'admin.reseller',
        'reseller_update'          => 'admin.reseller',
        'reseller_transition'      => 'admin.reseller',
        'reseller_delete'          => 'admin.reseller',
        'reseller_features_get'    => 'admin.reseller',
        'reseller_features_save'   => 'admin.reseller',
        'reseller_features_delete' => 'admin.reseller',
        'reseller_brand_get'       => 'admin.reseller',
        'reseller_brand_save'      => 'admin.reseller',
        'reseller_limits_check'    => 'admin.reseller',

        // File Manager v2 (FTP, Trash, Image, Git)
        'storage_ftp_list'         => 'admin.files',
        'storage_ftp_create'       => 'admin.files',
        'storage_ftp_delete'       => 'admin.files',
        'files_ftp_list'           => 'admin.files',
        'files_ftp_create'         => 'admin.files',
        'files_ftp_delete'         => 'admin.files',
        'storage_trash_move'       => 'admin.files',
        'storage_trash_list'       => 'admin.files',
        'storage_trash_restore'    => 'admin.files',
        'storage_trash_empty'      => 'admin.files',
        'files_trash_move'         => 'admin.files',
        'files_trash_list'         => 'admin.files',
        'files_trash_restore'      => 'admin.files',
        'files_trash_empty'        => 'admin.files',
        'storage_image_process'    => 'admin.files',
        'files_image_process'      => 'admin.files',
        'storage_git_status'       => 'admin.files',
        'storage_git_pull'         => 'admin.files',
        'storage_git_log'          => 'admin.files',
        'files_git_status'         => 'admin.files',
        'files_git_pull'           => 'admin.files',
        'files_git_log'            => 'admin.files',
        'archive_create'           => 'admin.files',
        'archive_extract'          => 'admin.files',
        'archive_inspect'          => 'admin.files',
        'archive_verify'           => 'admin.files',

        // Phase 13: Live Threat Radar & Auto-Ban
        'shield_threat_radar_feed'    => 'admin.shield',
        'shield_threat_strike_record' => 'admin.shield',
        'shield_threat_auto_ban'      => 'admin.shield',
        'shield_threat_quarantine'    => 'admin.shield',
        'shield_threat_clear_strikes' => 'admin.shield',

        // Phase 14: Multi-Portal SSO & Application Context Switching
        'auth_sso_generate'           => 'admin.identity',
        'auth_sso_validate'           => 'admin.identity',
        'apps_switch_context'         => 'admin.apps',
        'apps_active_context'         => 'admin.apps',

        // Storage & File Manager
        'storage_list_directory'      => 'admin.files',
        'storage_list_files'          => 'admin.files',
        'storage_read_file'           => 'admin.files',
        'storage_write_file'          => 'admin.files',
        'storage_create_file'         => 'admin.files',
        'storage_create_directory'    => 'admin.files',
        'storage_get_file_props'      => 'admin.files',
        'storage_save_file_props'     => 'admin.files',
        'storage_delete_item'         => 'admin.files',
        'storage_bulk_delete'         => 'admin.files',
        'storage_chmod'               => 'admin.files',
        'storage_bulk_chmod'          => 'admin.files',
        'storage_rename_item'         => 'admin.files',
        'storage_move_item'           => 'admin.files',
        'storage_bulk_move'           => 'admin.files',
        'storage_copy_item'           => 'admin.files',
        'storage_bulk_copy'           => 'admin.files',
        'storage_compress_items'      => 'admin.files',
        'storage_download_folder'     => 'admin.files',
        'storage_download_selected'   => 'admin.files',
        'storage_upload_file'         => 'admin.files',
        'storage_download_file'       => 'admin.files',
        'storage_tree'                => 'admin.files',
        'storage_roots'               => 'admin.files',
        'files_roots'                 => 'admin.files',
        'storage_site_roots'          => 'admin.files',
        'domains_site_roots'          => 'admin.files',
        'domains_site_directories'    => 'admin.files',
        'storage_get_favorites'       => 'admin.files',
        'storage_save_favorites'      => 'admin.files',
        'storage_add_favorite'        => 'admin.files',
        'storage_remove_favorite'     => 'admin.files',
        'files_get_favorites'         => 'admin.files',
        'files_save_favorites'        => 'admin.files',
        'files_add_favorite'          => 'admin.files',
        'files_remove_favorite'       => 'admin.files',
        'storage_extract_zip'         => 'admin.files',
        'storage_disk_usage'          => 'admin.files',
        'storage_disk_treemap'        => 'admin.files',
        'storage_get_error_pages'     => 'admin.files',
        'storage_save_error_page'     => 'admin.files',
        'storage_get_indexing'        => 'admin.files',
        'storage_toggle_indexing'     => 'admin.files',
        'storage_list_mime_types'     => 'admin.files',
        'storage_save_mime_type'      => 'admin.files',
        'storage_remove_mime_type'    => 'admin.files',
        'storage_access_log_info'     => 'admin.files',
        'storage_access_log_read'     => 'admin.files',

        // Manifest OS Workstation & Property Operations
        'storage_get_directory_props'   => 'admin.files',
        'storage_save_directory_props'  => 'admin.files',
        'storage_get_manifest_raw'      => 'admin.files',
        'storage_save_manifest_raw'     => 'admin.files',
        'storage_validate_manifest_yaml'=> 'admin.files',
        'storage_diff_manifest'         => 'admin.files',
        'storage_manifest_health'       => 'admin.files',
        'storage_heal_manifest'         => 'admin.files',
        'storage_compile_manifests'     => 'admin.files',
        'storage_verify_integrity'      => 'admin.files',
        'storage_sign_file'             => 'admin.files',
        'storage_sign_directory'        => 'admin.files',
        'storage_check_capabilities'    => 'admin.files',
        'files_manifest_health'         => 'admin.files',
        'files_heal_manifest'           => 'admin.files',
        'files_get_directory_props'     => 'admin.files',
        'files_save_directory_props'    => 'admin.files',
        'files_get_manifest_raw'        => 'admin.files',
        'files_save_manifest_raw'       => 'admin.files',
        'files_validate_manifest_yaml'  => 'admin.files',
        'files_diff_manifest'           => 'admin.files',
        'files_compile_manifests'       => 'admin.files',
        'files_verify_integrity'        => 'admin.files',
        'files_sign_file'               => 'admin.files',
        'files_sign_directory'          => 'admin.files',
        'files_check_capabilities'      => 'admin.files',
        'props_search'                  => 'admin.files',
        'props_compile'                 => 'admin.files',
        'props_heal'                    => 'admin.files',
        'props_lint'                    => 'admin.files',
        'props_verify'                  => 'admin.files',

        // Dashboard & Authentication
        'login'                       => 'admin.dashboard',
        'check_auth'                  => 'admin.dashboard',
        'get_profile'                 => 'admin.dashboard',
        'logout'                      => 'admin.dashboard',
        'get_dashboard_metrics'       => 'admin.dashboard',
        'dashboard_summary'           => 'admin.dashboard',

        // Database, Redis & TimeMachine
        'db_seed_entity'              => 'admin.database',
        'db_list_seeders'             => 'admin.database',
        'db_run_seeder'               => 'admin.database',
        'db_slow_queries_list'        => 'admin.database',
        'db_slow_query_explain'       => 'admin.database',
        'db_slow_query_apply_index'   => 'admin.database',
        'db_slow_query_clear'         => 'admin.database',
        'redis_status'                => 'admin.database',
        'redis_keys'                  => 'admin.database',
        'redis_flush_db'              => 'admin.database',
        'redis_flush_all'             => 'admin.database',
        'timemachine_create'          => 'admin.database',
        'timemachine_list'            => 'admin.database',
        'timemachine_compare'         => 'admin.database',
        'timemachine_restore'         => 'admin.database',

        // e-Vaarta & Mail Server
        'evaarta_deliverability_align'=> 'admin.mail',
        'evaarta_stream_counts'       => 'admin.mail',
        'mail_attachment_download'    => 'admin.mail',
        'evaarta_attachment_download' => 'admin.mail',
        'rules_list'                  => 'admin.mail',
        'rule_list'                   => 'admin.mail',
        'mail_rules_list'             => 'admin.mail',
        'rule_save'                   => 'admin.mail',
        'rule_delete'                 => 'admin.mail',
        'rule_toggle'                 => 'admin.mail',
        'rule_test'                   => 'admin.mail',
        'screener_list'               => 'admin.mail',
        'screeners_list'              => 'admin.mail',
        'screener_action'             => 'admin.mail',
        'screener_set'                => 'admin.mail',
        'mail_screener_action'        => 'admin.mail',
        'server_mailboxes_list'       => 'admin.mail',
        'server_mailbox_list'         => 'admin.mail',
        'server_mailboxes'            => 'admin.mail',
        'server_mailbox_get'          => 'admin.mail',
        'server_mailbox_save'         => 'admin.mail',
        'server_mailbox_create'       => 'admin.mail',
        'server_mailbox_password'     => 'admin.mail',
        'server_mailbox_quota'        => 'admin.mail',
        'server_mailbox_status'       => 'admin.mail',
        'server_mailbox_delete'       => 'admin.mail',
        'server_domains_list'         => 'admin.mail',
        'server_domains'              => 'admin.mail',
        'server_domain_list'          => 'admin.mail',
        'server_forwarders_list'      => 'admin.mail',
        'server_forwarder_list'       => 'admin.mail',
        'server_forwarders'           => 'admin.mail',
        'server_forwarder_save'       => 'admin.mail',
        'server_forwarder_create'     => 'admin.mail',
        'server_forwarder_delete'     => 'admin.mail',
        'server_autoresponder_set'    => 'admin.mail',
        'mail_privacy_scan'           => 'admin.mail',
        'mail_privacy_proxy_image'    => 'admin.mail',

        // Server, Fleet, Crons, Queues & Host Admin
        'cron_list_jobs'              => 'admin.server',
        'cron_save_job'               => 'admin.server',
        'cron_delete_job'             => 'admin.server',
        'cron_run_now'                => 'admin.server',
        'queue_list_jobs'             => 'admin.server',
        'queue_retry_job'             => 'admin.server',
        'queue_clear'                 => 'admin.server',
        'queue_dag_status'            => 'admin.server',
        'server_infra_summary'        => 'admin.server',
        'server_infra'                => 'admin.server',
        'deploy_infra_summary'        => 'admin.server',
        'server_docker_list'          => 'admin.server',
        'server_docker_control'       => 'admin.server',
        'server_docker_logs'          => 'admin.server',
        'server_docker_prune'         => 'admin.server',
        'server_pkg_updates_check'    => 'admin.server',
        'server_swap_info'            => 'admin.server',
        'server_swap_create'          => 'admin.server',
        'server_timezone_get'         => 'admin.server',
        'server_timezone_set'         => 'admin.server',
        'watchdog_settings_get'       => 'admin.server',
        'watchdog_settings_save'      => 'admin.server',
        'watchdog_audit_run'          => 'admin.server',
        'watchdog_alert_test'         => 'admin.server',
        'fleet_list'                  => 'admin.server',
        'fleet_add'                   => 'admin.server',
        'fleet_remove'                => 'admin.server',
        'fleet_ping'                  => 'admin.server',
        'fleet_broadcast'             => 'admin.server',
        'fleet_sync_dns'              => 'admin.server',
        'fleet_status'                => 'admin.server',
        'fleet_summary'               => 'admin.server',
        'cpanel_import'               => 'admin.server',
        'clear_cache'                 => 'admin.server',
        'tail_logs'                   => 'admin.server',
        'run_migrations'              => 'admin.server',
        'run_auto_tests'              => 'admin.server',
        'operation_plan'              => 'admin.server',

        // Shield & Firewall
        'shield_ports_get'            => 'admin.shield',
        'shield_port_allow'           => 'admin.shield',
        'shield_port_deny'            => 'admin.shield',
        'shield_port_delete'          => 'admin.shield',
        'shield_fail2ban_status'      => 'admin.shield',
        'shield_fail2ban_unban'       => 'admin.shield',
        'shield_ssh_hardening_get'    => 'admin.shield',
        'shield_ssh_hardening_save'   => 'admin.shield',
        'shield_ast_scan'             => 'admin.shield',
        'shield_waf_patches_list'     => 'admin.shield',
        'shield_waf_patch_toggle'     => 'admin.shield',
        'shield_waf_inspect'          => 'admin.shield',

        // Apps & Studio
        'manifest_graph'              => 'admin.apps',
        'manifest_catalog'            => 'admin.apps',
        'manifest_bundle'             => 'admin.apps',
        'manifest_test_partial'       => 'admin.apps',
        'manifest_generate_mock'      => 'admin.apps',
        'manifest_playground_template'=> 'admin.apps',
        'manifest_get_partial_source' => 'admin.apps',
        'manifest_save_partial_source'=> 'admin.apps',
        'manifest_integrate_directive'=> 'admin.apps',
        'manifest_impact'             => 'admin.apps',
        'manifest_ai_directives'      => 'admin.apps',
        'manifest_cache_graph'        => 'admin.apps',
        'manifest_data_governance'    => 'admin.apps',
        'apps_framework_list'         => 'admin.apps',
        'apps_create_backup'          => 'admin.apps',
        'apps_backups_list'           => 'admin.apps',
        'apps_backup_restore'         => 'admin.apps',
        'apps_delete_backup'          => 'admin.apps',
        'apps_backup_delete'          => 'admin.apps',
        'apps_purge_backups'          => 'admin.apps',
        'list_pages_studio'           => 'admin.apps',
        'get_page_studio'             => 'admin.apps',
        'get_page'                    => 'admin.apps',
        'save_page_studio'            => 'admin.apps',
        'delete_page_studio'          => 'admin.apps',
        'delete_page'                 => 'admin.apps',
        'studio_context'              => 'admin.apps',
        'get_entity_schema_studio'    => 'admin.apps',
        'get_entity_schema'           => 'admin.apps',
        'compile_page_studio'         => 'admin.apps',
        'compile_page'                => 'admin.apps',
        'get_page_source'             => 'admin.apps',
        'get_page_source_studio'      => 'admin.apps',
        'save_page_source'            => 'admin.apps',
        'save_page_source_studio'     => 'admin.apps',
        'introspect_transitions'      => 'admin.apps',
        'introspect_transitions_studio'=> 'admin.apps',
        'get_app_blueprint'           => 'admin.apps',
        'get_app_blueprint_studio'    => 'admin.apps',
        'get_app_config_studio'       => 'admin.apps',
        'save_app_config_studio'      => 'admin.apps',
        'get_magic_db_data_studio'    => 'admin.apps',
        'save_magic_db_row_studio'    => 'admin.apps',
        'generate_mock_data_studio'   => 'admin.apps',
        'generate_crud_interface_studio' => 'admin.apps',
        'test_api_endpoint_studio'    => 'admin.apps',
        'run_parikshak_test_studio'   => 'admin.apps',
        'ai_prompt_component_studio'  => 'admin.apps',
        'execute_sql_query_studio'    => 'admin.apps',
        'alter_table_schema_studio'   => 'admin.apps',
        'get_database_erd_studio'     => 'admin.apps',
        'get_relational_record_studio'=> 'admin.apps',
        'import_csv_data_studio'      => 'admin.apps',
        'execute_cli_command_studio'  => 'admin.apps',
        'stream_app_logs_studio'      => 'admin.apps',
        'get_git_status_studio'       => 'admin.apps',
        'global_search_studio'        => 'admin.apps',
        'audit_page_quality_studio'   => 'admin.apps',
        'auto_fix_page_issues_studio' => 'admin.apps',
        'save_api_collection_studio'  => 'admin.apps',
        'list_api_collections_studio' => 'admin.apps',
        'extract_partial_studio'      => 'admin.apps',
        'purge_studio_cache'          => 'admin.apps',
        'live_purge_studio_cache'     => 'admin.apps',
        'check_page_health_studio'    => 'admin.apps',
        'live_check_page_health_studio' => 'admin.apps',
        'apply_auto_fix_studio'       => 'admin.apps',
        'live_apply_auto_fix_studio'  => 'admin.apps',
        'list_media_assets_studio'    => 'admin.apps',
        'live_list_media_assets_studio' => 'admin.apps',
        'dispatch_test_live_event_studio' => 'admin.apps',
        'live_dispatch_test_live_event_studio' => 'admin.apps',
        'live_get_entity_schema_studio' => 'admin.apps',
        'render_partial_preview'      => 'admin.apps',

        // Commands & Coder
        'editor_list_files'           => 'admin.commands',
        'editor_read_file'            => 'admin.commands',
        'editor_write_file'           => 'admin.commands',
        'editor_get_completions'      => 'admin.commands',
        'coder_lint'                  => 'admin.commands',
        'coder_symbols'               => 'admin.commands',
        'coder_format'                => 'admin.commands',
        'coder_quick_search'          => 'admin.commands',
        'coder_search_in_files'       => 'admin.commands',
        'coder_replace_in_files'      => 'admin.commands',
        'coder_test_discover'         => 'admin.commands',
        'coder_diagnostics'           => 'admin.commands',
        'coder_watch_eval'            => 'admin.commands',
        'coder_git_diff'              => 'admin.commands',
        'coder_tinker'                => 'admin.commands',
        'coder_run_test'              => 'admin.commands',
        'coder_autocomplete'          => 'admin.commands',
        'coder_signature_help'        => 'admin.commands',
        'coder_definition'            => 'admin.commands',
        'coder_hover'                 => 'admin.commands',
        'coder_references'            => 'admin.commands',
        'coder_ai_ghost'              => 'admin.commands',
        'coder_ai_edit'               => 'admin.commands',
        'coder_ai_fix'                => 'admin.commands',
        'coder_ai_chat'               => 'admin.commands',
        'coder_git_status'            => 'admin.commands',
        'coder_git_stage'             => 'admin.commands',
        'coder_git_unstage'           => 'admin.commands',
        'coder_git_commit'            => 'admin.commands',
        'coder_git_file_diff'         => 'admin.commands',
        'coder_git_blame'             => 'admin.commands',
        'coder_terminal_exec'         => 'admin.commands',
        'coder_terminal_env'          => 'admin.commands',
        'coder_workflow_graph'        => 'admin.commands',
        'coder_file_props_get'        => 'admin.commands',
        'coder_file_props_save'       => 'admin.commands',
        'tinker_eval'                 => 'admin.commands',
        'parikshak_run'               => 'admin.commands',

        // System, Polyglot, Webhooks & APIs
        'get_global_settings'         => 'admin.system',
        'toggle_dev_mode'             => 'admin.system',
        'get_di_bindings'             => 'admin.system',
        'polyglot_inventory'          => 'admin.system',
        'polyglot_toggle'             => 'admin.system',
        'polyglot_services'           => 'admin.system',
        'polyglot_evaluate'           => 'admin.system',
        'mesh_list_services'          => 'admin.system',
        'mesh_call_rpc'               => 'admin.system',
        'mesh_events_list'            => 'admin.system',
        'mesh_event_dispatch'         => 'admin.system',
        'webhook_list'                => 'admin.system',
        'webhook_create'              => 'admin.system',
        'webhook_update'              => 'admin.system',
        'webhook_delete'              => 'admin.system',
        'webhook_payloads'            => 'admin.system',
        'webhook_payload'             => 'admin.system',
        'webhook_clear'               => 'admin.system',
        'webhook_replay'              => 'admin.system',
        'webhook_verify'              => 'admin.system',
        'webhook_ingest'              => 'admin.system',
        'api_studio_endpoints'        => 'admin.system',
        'api_studio_spec'             => 'admin.system',
        'api_studio_send'             => 'admin.system',
        'api_studio_curl'             => 'admin.system',

        // Identity & Access Management
        'create_user'                 => 'admin.identity',
        'delete_user'                 => 'admin.identity',
        'webauthn_register_options'   => 'admin.identity',
        'webauthn_register_verify'    => 'admin.identity',
        'webauthn_auth_options'       => 'admin.identity',
        'webauthn_auth_verify'        => 'admin.identity',
        'webauthn_list'               => 'admin.identity',
        'webauthn_delete'             => 'admin.identity',

        // Reports, Metrics, Trace & AI
        'report_api'                  => 'admin.reports',
        'list_reports'                => 'admin.reports',
        'load_report'                 => 'admin.reports',
        'save_report'                 => 'admin.reports',
        'delete_report'               => 'admin.reports',
        'report_schema'               => 'admin.reports',
        'preview_report'              => 'admin.reports',
        'apm_overview'                => 'admin.metrics',
        'apm_flame_graph'             => 'admin.metrics',
        'apm_slow_transactions'       => 'admin.metrics',
        'apm_record_sample'           => 'admin.metrics',
        'trace_waterfall'             => 'admin.trace',
        'ai_get_recipes'              => 'admin.ai',
        'ai_lint_app'                 => 'admin.ai',
        'ai_sre_diagnose'             => 'admin.ai',
        'ai_sre_summarize'            => 'admin.ai',
        'ai_sre_copilot'              => 'admin.ai',
        'ai_sre_apply_patch'          => 'admin.ai',
        'workflow_simulate_list'      => 'admin.workflows',
        'workflow_simulate_step'      => 'admin.workflows',
        'workflow_simulate_rollback'  => 'admin.workflows',
        'workflow_simulate_timeout'   => 'admin.workflows',

        // Lifecycle & Deployments
        'deploy_build_package'        => 'admin.lifecycle',
        'deploy_list_packages'        => 'admin.lifecycle',
        'deploy_upload_package'       => 'admin.lifecycle',
        'deploy_sync_helpers'         => 'admin.lifecycle',
        'deploy_download_package'     => 'admin.lifecycle',
        'lifecycle_get_envs'          => 'admin.lifecycle',
        'lifecycle_get_security'      => 'admin.lifecycle',
        'lifecycle_rotate_token'      => 'admin.lifecycle',
        'lifecycle_save_env'          => 'admin.lifecycle',
        'lifecycle_compare'           => 'admin.lifecycle',
        'lifecycle_push'              => 'admin.lifecycle',

        // Authentication, MFA & Session Primitives
        'VerifyMFA'                   => 'admin.identity',
        'verify_mfa'                  => 'admin.identity',
        'SendMagicLink'               => 'admin.identity',
        'send_magic_link'             => 'admin.identity',
        'ConsumeMagicLink'            => 'admin.identity',
        'consume_magic_link'          => 'admin.identity',
        'sync_token'                  => 'admin.dashboard',
        'subscribe'                   => 'admin.dashboard',
        'live_update'                 => 'admin.dashboard',
        'coder_save_file'             => 'admin.commands',

        // System Updates & Service Control
        'system_update_list'          => 'admin.system',
        'system_update_run'           => 'admin.system',
        'call_service'                => 'admin.system',
        'get_config_all'              => 'admin.system',
        'list_queue'                  => 'admin.server',

        // Apps & Domain Scopes
        'apps_set_domain_scope'       => 'admin.apps',
        'set_base_app'                => 'admin.apps',
        'make_app'                    => 'admin.apps',
        'make_controller'             => 'admin.apps',
        'make_service'                => 'admin.apps',

        // Entities & Scaffolding
        'scaffold_dashboard'          => 'admin.entities',
        'scaffold_test'               => 'admin.entities',
        'generate_sdk'                => 'admin.entities',
        'generate_docker'             => 'admin.entities',
        'scaffold_auth'               => 'admin.entities',
        'compile_workflow'            => 'admin.entities',
        'magic_generate_schema'       => 'admin.entities',
        'ai_generate_logic'           => 'admin.entities',
        'save_entity_source'          => 'admin.entities',
        'seed_entity'                 => 'admin.entities',
        'ai_parse_scaffold'           => 'admin.entities',
        'execute_scaffold'            => 'admin.entities',
        'get_codebase_structure'      => 'admin.entities',
        'get_builder_context'         => 'admin.entities',
        'parse_entity_yaml'           => 'admin.entities',
        'save_entity_config'          => 'admin.entities',

        // Forms Studio & Validation
        'list_validators'             => 'admin.forms',
        'list_forms'                  => 'admin.forms',
        'save_validator'              => 'admin.forms',
        'delete_validator'            => 'admin.forms',
        'generate_from_entity'        => 'admin.forms',
        'parse_form_yaml'             => 'admin.forms',
        'preview_form'                => 'admin.forms',
        'get_form_html'               => 'admin.forms',
        'save_form_config'            => 'admin.forms',
        'delete_form'                 => 'admin.forms',

        // Identity & Access Management (IAM)
        'save_rbac_role'              => 'admin.identity',
        'save_user'                   => 'admin.identity',
        'search_entities'             => 'admin.identity',
        'get_iam_details'             => 'admin.identity',
        'assign_role_to_entity'       => 'admin.identity',
        'remove_role_from_entity'     => 'admin.identity',
        'toggle_user_status'          => 'admin.identity',
        'save_abac_policy'            => 'admin.identity',
        'delete_abac_policy'          => 'admin.identity',
        'save_oauth_client'           => 'admin.identity',
        'delete_oauth_client'         => 'admin.identity',
        'save_role'                   => 'admin.identity',
        'save_right'                  => 'admin.identity',
        'assign_role'                 => 'admin.identity',
        'remove_role'                 => 'admin.identity',
        'assign_right'                => 'admin.identity',
        'remove_right'                => 'admin.identity',

        // InterDB
        'get_interdb_config'          => 'admin.database',
        'save_interdb_config'         => 'admin.database',

        // e-Vaarta Mail Server & Deliverability
        'mail_queue_flush'            => 'admin.mail',
        'mail_deliverability_generate_dkim' => 'admin.mail',
        'mail_deliverability_align_dns'     => 'admin.mail',
        'mail_deliverability_send_test'     => 'admin.mail',
        'queue_flush'                 => 'admin.mail',
        'deliverability_generate_dkim'=> 'admin.mail',
        'deliverability_align_dns'    => 'admin.mail',
        'deliverability_send_test'    => 'admin.mail',

        // Drishyam Studio & Pages
        'live_preview_page_studio'    => 'admin.apps',
        'preview_page_studio'         => 'admin.apps',
        'get_workflow_topology_studio'=> 'admin.apps',
        'live_get_workflow_topology_studio' => 'admin.apps',
        'create_partial_studio'       => 'admin.apps',
        'live_create_partial_studio'  => 'admin.apps',
        'scaffold_live_component_studio' => 'admin.apps',
        'live_scaffold_live_component_studio' => 'admin.apps',
        'live_test_validate_form'     => 'admin.apps',
        'test_validate_form'          => 'admin.apps',

        // Server, Docker & Watchdog
        'server_docker_status'        => 'admin.server',
        'server_docker_action'        => 'admin.server',
        'server_swap_status'          => 'admin.server',
        'watchdog_status'             => 'admin.server',
        'watchdog_test_alert'         => 'admin.server',
        'watchdog_configure'          => 'admin.server',
        'watchdog_audit_run'          => 'admin.server',
    ];
}

/**
 * Get the admin scopes for the current user.
 * Super-admins (role_id=1 or username matching global admin) get all scopes.
 * Other users get scopes from XDB admin_permissions store.
 */
function getAdminUserScopes(): array {
    // Complete scope universe: view scopes, action scopes, and wildcard
    $viewScopes = array_values(array_unique(array_values(getAdminScopeMap())));
    $actionScopes = array_values(array_unique(array_values(getActionScopeMap())));
    $allScopes = array_values(array_unique(array_merge($viewScopes, $actionScopes, ['admin.*'])));

    try {
        if (php_sapi_name() === 'cli') {
            return $allScopes;
        }

        // Verify session / authentication
        $hasAuth = false;
        if (!empty($_SESSION['spp_admin_fallback']) || !empty($_SESSION['spp_admin_user'])) {
            $hasAuth = true;
        } elseif (function_exists('isAdminAuthenticated') && isAdminAuthenticated()) {
            $hasAuth = true;
        } elseif (\SPP\SPPSession::sessionExists()) {
            $hasAuth = true;
        } elseif (isset($_SESSION['__default_sppsession']) || isset($_SESSION['__sppadmin_sppsession'])) {
            $hasAuth = true;
        }

        if (!$hasAuth) {
            return []; // HTTP admin requests must fail closed without authentication.
        }

        $userId = null;
        $username = '';
        $roleId = null;
        $sppauthUser = $_SESSION['spp_admin_user'] ?? '';

        // Try getting session variables from active SPPSession context
        try {
            if (\SPP\SPPSession::sessionExists()) {
                $userId = \SPP\SPPSession::sessionVarExists('__user_id__') ? \SPP\SPPSession::getSessionVar('__user_id__') : null;
                $username = \SPP\SPPSession::sessionVarExists('__username__') ? \SPP\SPPSession::getSessionVar('__username__') : '';
                $roleId = \SPP\SPPSession::sessionVarExists('__role_id__') ? \SPP\SPPSession::getSessionVar('__role_id__') : null;
                if (\SPP\SPPSession::sessionVarExists('__sppauth_user__')) {
                    $sppauthUser = \SPP\SPPSession::getSessionVar('__sppauth_user__');
                }
            }
        } catch (\Throwable $t) {
            // Context switch may cause sessionVarExists to fail; fall back to root or default session bucket
        }

        // If not found in active context, inspect session buckets (e.g. __sppadmin_sppsession, __default_sppsession, or any __*_sppsession)
        if ($userId === null || empty($username)) {
            $buckets = ['__sppadmin_sppsession', '__default_sppsession'];
            foreach ($buckets as $b) {
                if (isset($_SESSION[$b]['vars']) && is_array($_SESSION[$b]['vars'])) {
                    $defVars = $_SESSION[$b]['vars'];
                    $userId = $userId ?? ($defVars['__user_id__']['val'] ?? null);
                    $username = !empty($username) ? $username : ($defVars['__username__']['val'] ?? ($defVars['spp_admin_user']['val'] ?? ''));
                    $roleId = $roleId ?? ($defVars['__role_id__']['val'] ?? null);
                    if (empty($sppauthUser) && !empty($defVars['__sppauth_user__']['val'])) {
                        $sppauthUser = $defVars['__sppauth_user__']['val'];
                    }
                }
            }
            if (($userId === null || empty($username)) && isset($_SESSION) && is_array($_SESSION)) {
                foreach ($_SESSION as $k => $v) {
                    if (is_array($v) && isset($v['vars']) && is_array($v['vars'])) {
                        $userId = $userId ?? ($v['vars']['__user_id__']['val'] ?? null);
                        $username = !empty($username) ? $username : ($v['vars']['__username__']['val'] ?? ($v['vars']['spp_admin_user']['val'] ?? ''));
                        $roleId = $roleId ?? ($v['vars']['__role_id__']['val'] ?? null);
                        if (empty($sppauthUser) && !empty($v['vars']['__sppauth_user__']['val'])) {
                            $sppauthUser = $v['vars']['__sppauth_user__']['val'];
                        }
                    }
                }
            }
        }

        // Super-admin bypass:
        // 1) Fallback admin login
        if (!empty($_SESSION['spp_admin_fallback'])) {
            return $allScopes;
        }

        // 2) Role ID 1 (Administrator)
        if ($roleId == 1) {
            return $allScopes;
        }

        // 3) Username matching configured super-admin or 'admin'
        $settings = \SPP\App::getGlobalSettings();
        $superAdmin = $settings['admin_auth']['username'] ?? ($settings['admin_username'] ?? 'admin');
        $sppauthUsername = is_array($sppauthUser) ? ($sppauthUser['username'] ?? '') : (string)$sppauthUser;
        $adminSessionUser = is_string($_SESSION['spp_admin_user'] ?? null) ? $_SESSION['spp_admin_user'] : '';

        if (
            (is_string($username) && $username !== '' && (strtolower($username) === strtolower($superAdmin) || strtolower($username) === 'admin')) ||
            (is_string($sppauthUsername) && $sppauthUsername !== '' && (strtolower($sppauthUsername) === strtolower($superAdmin) || strtolower($sppauthUsername) === 'admin')) ||
            ($adminSessionUser !== '' && (strtolower($adminSessionUser) === strtolower($superAdmin) || strtolower($adminSessionUser) === 'admin'))
        ) {
            return $allScopes;
        }

        // Load scopes from XDB
        if ($userId !== null && class_exists('\SPPMod\SPPXDB\SPP_XDB')) {
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

        // A missing permissions record must never turn an authenticated user into
        // an administrator.  Explicit grants are required outside the recognised
        // administrator identities above.
        return [];

    } catch (\Throwable $e) {
        return [];
    }
}

/**
 * Check if the current user has a specific admin scope.
 */
function hasAdminScope(string $scope): bool {
    $userScopes = getAdminUserScopes();
    if (in_array($scope, $userScopes, true) || in_array('admin.*', $userScopes, true)) {
        return true;
    }
    // Support wildcard and parent scope hierarchy (e.g., 'admin.files' grants 'admin.files.write')
    $parts = explode('.', $scope);
    if (count($parts) > 2) {
        $parent = $parts[0] . '.' . $parts[1];
        if (in_array($parent, $userScopes, true) || in_array($parent . '.*', $userScopes, true)) {
            return true;
        }
    }
    // Backward compatibility: 'admin.domains' grants 'admin.dns.*'
    if (isset($parts[0], $parts[1]) && $parts[0] === 'admin' && $parts[1] === 'dns') {
        if (in_array('admin.domains', $userScopes, true) || in_array('admin.domains.*', $userScopes, true)) {
            return true;
        }
    }
    return false;
}

/**
 * Gate an API action — returns true if allowed, false if denied.
 * MANDATORY SECURITY INVARIANT: Must FAIL CLOSED. Any unmapped action is denied.
 * Enforces domain boundary authorization: validates that the admin has access to the target domain.
 */
function gateAdminAction(string $action, ?string $targetDomain = null): bool {
    $actionMap = getActionScopeMap();
    if (!isset($actionMap[$action])) {
        // FAIL-CLOSED: Unmapped actions are strictly denied
        return false;
    }

    if ($targetDomain === null) {
        $targetDomain = $_REQUEST['domain'] ?? $_REQUEST['domain_id'] ?? null;
    }

    if (class_exists('\\SPP\\Core\\Security\\AuthorizationService')) {
        $auth = \SPP\Core\Security\AuthorizationService::getInstance();
        $scope = $actionMap[$action];
        return $auth->can($scope, $targetDomain);
    }

    $res = hasAdminScope($actionMap[$action]);
    return $res;
}

// --- API Endpoints ---

if (!function_exists('live_get_admin_permissions')) {
    function live_get_admin_permissions($la, $params) {
        $scopeMap = getAdminScopeMap();
        $userScopes = array_values(getAdminUserScopes());
        $allScopes = array_values(array_unique(array_values($scopeMap)));

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
