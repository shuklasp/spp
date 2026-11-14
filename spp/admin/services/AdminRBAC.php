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

        // If not found in active context, inspect default context bucket
        if ($userId === null && isset($_SESSION['__default_sppsession']['vars'])) {
            $defVars = $_SESSION['__default_sppsession']['vars'];
            $userId = $defVars['__user_id__']['val'] ?? null;
            $username = $defVars['__username__']['val'] ?? $username;
            $roleId = $defVars['__role_id__']['val'] ?? $roleId;
            if (!empty($defVars['__sppauth_user__']['val'])) {
                $sppauthUser = $defVars['__sppauth_user__']['val'];
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
    return in_array($scope, $userScopes) || in_array('admin.*', $userScopes);
}

/**
 * Gate an API action — returns true if allowed, false if denied.
 */
function gateAdminAction(string $action): bool {
    $actionMap = getActionScopeMap();
    if (!isset($actionMap[$action])) {
        // Legacy actions remain available until they are explicitly migrated to
        // this map.  Sensitive domain actions are mapped above.
        return true;
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
