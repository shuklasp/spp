<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class AdminGeneralCommand extends Command
{
    protected string $name = 'admin:general';
    protected string $description = 'Manage Admin General operations. Usage: admin:general <action> [--payload=...] [--json]';

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

    private function handleLogin(array $payload, array $args): void {

        live_Auth_Login($la, $p);
    
    }

    private function handleAuthVerifymfa(array $payload, array $args): void {

        live_Auth_VerifyMFA($la, $p);
    
    }

    private function handleAuthSendmagiclink(array $payload, array $args): void {

        live_Auth_SendMagicLink($la, $p);
    
    }

    private function handleAuthConsumemagiclink(array $payload, array $args): void {

        live_Auth_ConsumeMagicLink($la, $p);
    
    }

    private function handleLogout(array $payload, array $args): void {

        live_Auth_Logout($la, $p);
    
    }

    private function handleCheckAuth(array $payload, array $args): void {

        live_Auth_Profile($la, $p);
    
    }

    private function handleGetProfile(array $payload, array $args): void {

        live_Auth_Profile($la, $p);
    
    }

    private function handleAuthListapikeys(array $payload, array $args): void {

        live_IAM_ListApiKeys($la, $p);
    
    }

    private function handleAuthGenerateapikey(array $payload, array $args): void {

        live_IAM_GenerateApiKey($la, $p);
    
    }

    private function handleAuthRevokeapikey(array $payload, array $args): void {

        live_IAM_RevokeApiKey($la, $p);
    
    }

    private function handleAuthGeneratemfasecret(array $payload, array $args): void {

        live_IAM_GenerateMFASecret($la, $p);
    
    }

    private function handleAuthEnablemfa(array $payload, array $args): void {

        live_IAM_EnableMFA($la, $p);
    
    }

    private function handleListApps(array $payload, array $args): void {

        live_Core_ListApps($la, $p);
    
    }

    private function handleRunCommand(array $payload, array $args): void {

        live_Core_RunCommand($la, $p);
    
    }

    private function handleHealthCheck(array $payload, array $args): void {

        live_Core_HealthCheck($la, $p);
    
    }

    private function handleGetSystemInfo(array $payload, array $args): void {

        live_Core_GetSystemInfo($la, $p);
    
    }

    private function handleGetBridgeInfo(array $payload, array $args): void {

        live_Core_GetBridgeInfo($la, $p);
    
    }

    private function handleSetupBridge(array $payload, array $args): void {

        live_Core_SetupBridge($la, $p);
    
    }

    private function handleTestBridge(array $payload, array $args): void {

        live_Core_TestBridge($la, $p);
    
    }

    private function handleCompileRegistry(array $payload, array $args): void {

        live_Core_CompileRegistry($la, $p);
    
    }

    private function handleGetInterdbConfig(array $payload, array $args): void {

        live_Config_InterDB_Get($la, $p);
    
    }

    private function handleSaveInterdbConfig(array $payload, array $args): void {

        live_Config_InterDB_Save($la, $p);
    
    }

    private function handleGetAjaxServices(array $payload, array $args): void {

        live_Config_Ajax_List($la, $p);
    
    }

    private function handleSaveAjaxService(array $payload, array $args): void {

        live_Config_Ajax_Save($la, $p);
    
    }

    private function handleListXdbDatabases(array $payload, array $args): void {

        live_XDB_ListDB($la, $p);
    
    }

    private function handleListXdbTables(array $payload, array $args): void {

        live_XDB_ListTables($la, $p);
    
    }

    private function handleGetXdbTableData(array $payload, array $args): void {

        live_XDB_GetTableData($la, $p);
    
    }

    private function handleGetXdbTableColumns(array $payload, array $args): void {

        live_XDB_GetTableColumns($la, $p);
    
    }

    private function handleRunXdbQuery(array $payload, array $args): void {

        live_XDB_RunQuery($la, $p);
    
    }

    private function handleSaveXdbRecord(array $payload, array $args): void {

        live_XDB_SaveRecord($la, $p);
    
    }

    private function handleDeleteXdbRecord(array $payload, array $args): void {

        live_XDB_DeleteRecord($la, $p);
    
    }

    private function handleXdbMigrate(array $payload, array $args): void {

        live_XDB_Migrate($la, $p);
    
    }

    private function handleXdbSeed(array $payload, array $args): void {

        live_XDB_Seed($la, $p);
    
    }

    private function handleXdbGetProfileLog(array $payload, array $args): void {

        live_XDB_GetProfileLog($la, $p);
    
    }

    private function handleGetGlobalSettings(array $payload, array $args): void {

        live_Config_GetGlobalSettings($la, $p);
    
    }

    private function handleSaveGlobalSettings(array $payload, array $args): void {

        live_Config_SaveGlobalSettings($la, $p);
    
    }

    private function handleListModules(array $payload, array $args): void {

        live_List($la, $p);
    
    }

    private function handleScanModule(array $payload, array $args): void {

        live_Scan($la, $p);
    
    }

    private function handleSetupModule(array $payload, array $args): void {

        live_Setup($la, $p);
    
    }

    private function handleUninstallModule(array $payload, array $args): void {

        live_Uninstall($la, $p);
    
    }

    private function handleGetModuleConfig(array $payload, array $args): void {

        live_GetConfig($la, $p);
    
    }

    private function handleSaveModuleConfig(array $payload, array $args): void {

        live_SaveConfig($la, $p);
    
    }

    private function handleSaveModuleConfigRaw(array $payload, array $args): void {

        live_SaveConfigRaw($la, $p);
    
    }

    private function handleToggleModule(array $payload, array $args): void {

        live_Toggle($la, $p);
    
    }

    private function handleOpenModuleSettings(array $payload, array $args): void {

        live_OpenSettings($la, $p);
    
    }

    private function handleListEntities(array $payload, array $args): void {

        live_Entities_List($la, $p);
    
    }

    private function handleSaveEntityConfig(array $payload, array $args): void {

        live_Entities_Save($la, $p);
    
    }

    private function handleDeleteEntity(array $payload, array $args): void {

        live_Entities_Delete($la, $p);
    
    }

    private function handleParseEntityYaml(array $payload, array $args): void {

        live_Entities_ParseYAML($la, $p);
    
    }

    private function handleDumpEntityYaml(array $payload, array $args): void {

        live_Entities_DumpYAML($la, $p);
    
    }

    private function handleListForms(array $payload, array $args): void {

        live_Forms_List($la, $p);
    
    }

    private function handleSaveForm(array $payload, array $args): void {

        live_Forms_Save($la, $p);
    
    }

    private function handleDeleteForm(array $payload, array $args): void {

        live_Forms_Delete($la, $p);
    
    }

    private function handleParseFormYaml(array $payload, array $args): void {

        live_Forms_ParseYAML($la, $p);
    
    }

    private function handleDumpFormYaml(array $payload, array $args): void {

        live_Forms_DumpYAML($la, $p);
    
    }

    private function handleSystemUpdateList(array $payload, array $args): void {

        live_lifecycle_updatelist($la, $p);
    
    }

    private function handleSystemUpdateRun(array $payload, array $args): void {

        live_lifecycle_updaterun($la, $p);
    
    }

    private function handleSyncDeploymentToken(array $payload, array $args): void {

        live_lifecycle_rotatetoken($la, $p);
    
    }

    private function handleSysUpgrade(array $payload, array $args): void {

        live_sys_upgrade($la, $p);
    
    }

    private function handleListPages(array $payload, array $args): void {

        live_Routing_ListPages($la, $p);
    
    }

    private function handleSavePage(array $payload, array $args): void {

        live_Routing_SavePage($la, $p);
    
    }

    private function handleRemovePage(array $payload, array $args): void {

        live_Routing_RemovePage($la, $p);
    
    }

    private function handleListServices(array $payload, array $args): void {

        live_Routing_ListServices($la, $p);
    
    }

    private function handleSaveService(array $payload, array $args): void {

        live_Routing_SaveService($la, $p);
    
    }

    private function handleRemoveService(array $payload, array $args): void {

        live_Routing_RemoveService($la, $p);
    
    }

    private function handleListUsers(array $payload, array $args): void {

        live_IAM_ListUsers($la, $p);
    
    }

    private function handleListRoles(array $payload, array $args): void {

        live_IAM_ListRoles($la, $p);
    
    }

    private function handleListRights(array $payload, array $args): void {

        live_IAM_ListRights($la, $p);
    
    }

    private function handleListRbac(array $payload, array $args): void {

        live_IAM_ListRBAC($la, $p);
    
    }

    private function handleListAbacPolicies(array $payload, array $args): void {

        live_IAM_ListABAC($la, $p);
    
    }

    private function handleSaveAbacPolicy(array $payload, array $args): void {

        live_IAM_SaveABAC($la, $p);
    
    }

    private function handleDeleteAbacPolicy(array $payload, array $args): void {

        live_IAM_DeleteABAC($la, $p);
    
    }

    private function handleListOauthClients(array $payload, array $args): void {

        live_IAM_ListOAuthClients($la, $p);
    
    }

    private function handleSaveOauthClient(array $payload, array $args): void {

        live_IAM_SaveOAuthClient($la, $p);
    
    }

    private function handleDeleteOauthClient(array $payload, array $args): void {

        live_IAM_DeleteOAuthClient($la, $p);
    
    }

    private function handleListEntityAssignments(array $payload, array $args): void {

        live_IAM_ListEntityAssignments($la, $p);
    
    }

    private function handleGetIamDetails(array $payload, array $args): void {

        live_IAM_GetDetails($la, $p);
    
    }

    private function handleSearchEntities(array $payload, array $args): void {

        live_IAM_SearchEntities($la, $p);
    
    }

    private function handleAssignRoleToEntity(array $payload, array $args): void {

        live_IAM_AssignRole($la, $p);
    
    }

    private function handleRemoveRoleFromEntity(array $payload, array $args): void {

        live_IAM_RemoveRole($la, $p);
    
    }

    private function handleAssignRightToRole(array $payload, array $args): void {

        live_IAM_AssignRight($la, $p);
    
    }

    private function handleRemoveRightFromRole(array $payload, array $args): void {

        live_IAM_RemoveRight($la, $p);
    
    }

    private function handleToggleUserStatus(array $payload, array $args): void {

        live_IAM_ToggleUserStatus($la, $p);
    
    }

    private function handleGetFormHtml(array $payload, array $args): void {

        live_IAM_GetFormHTML($la, $p);
    
    }

    private function handleSaveUser(array $payload, array $args): void {

        live_IAM_SaveUser($la, $p);
    
    }

    private function handleSaveRole(array $payload, array $args): void {

        live_IAM_SaveRole($la, $p);
    
    }

    private function handleSaveRight(array $payload, array $args): void {

        live_IAM_SaveRight($la, $p);
    
    }

    private function handleSaveModernRole(array $payload, array $args): void {

        live_IAM_SaveModernRole($la, $p);
    
    }

    private function handleListGroups(array $payload, array $args): void {

        live_IAM_ListGroups($la, $p);
    
    }

    private function handleListGroupMembers(array $payload, array $args): void {

        live_IAM_ListGroupMembers($la, $p);
    
    }

    private function handleAddGroupMember(array $payload, array $args): void {

        live_IAM_AddGroupMember($la, $p);
    
    }

    private function handleRemoveGroupMember(array $payload, array $args): void {

        live_IAM_RemoveGroupMember($la, $p);
    
    }

    private function handleSaveGroup(array $payload, array $args): void {

        live_IAM_SaveGroup($la, $p);
    
    }

    private function handleDeleteGroup(array $payload, array $args): void {

        live_IAM_DeleteGroup($la, $p);
    
    }

    private function handleGetAiRegistry(array $payload, array $args): void {

        live_AI_GetRegistry($la, $p);
    
    }

    private function handleTestAiPrompt(array $payload, array $args): void {

        live_AI_TestPrompt($la, $p);
    
    }

    private function handleLoadView(array $payload, array $args): void {

        $view = $p['view'] ?? 'dashboard';
        $viewFile = SPP_BASE_DIR . SPP_DS . 'admin' . SPP_DS . 'views' . SPP_DS . basename($view) . '.php';
        error_log("[SPPAdmin] Loading view: $view from $viewFile");

        if (!file_exists($viewFile)) {
            error_log("[SPPAdmin] View file not found: $viewFile");
            $this->json(['success' => false, 'error' => "View '{$view}' not found."], $args); return;
        return;
        }

        // Collect data for the view
        $data = [];
        if ($view === 'system') {
            error_log("[SPPAdmin] Collecting system data...");
            $la_sys = new \SPPMod\SPPAPI\LiveAction();
            live_Core_GetSystemInfo($la_sys, []);
            $data['system'] = $la_sys->getData();

            $la_bridge = new \SPPMod\SPPAPI\LiveAction();
            live_Core_GetBridgeInfo($la_bridge, []);
            $data['bridge'] = $la_bridge->getData();

            $la_apps = new \SPPMod\SPPAPI\LiveAction();
            live_Core_ListApps($la_apps, []);
            $data['apps'] = $la_apps->getData()['apps'] ?? [];

            $la_settings = new \SPPMod\SPPAPI\LiveAction();
            live_Config_GetGlobalSettings($la_settings, []);
            $data['settings'] = $la_settings->getData();
        }

        ob_start();
        $payload = [
            'la' => $la,
            'data' => $data,
            'selectedApp' => $_REQUEST['appname'] ?? 'default'
        ];
        include $viewFile;
        $html = ob_get_clean();
        error_log("[SPPAdmin] View $view rendered, length: " . strlen($html));

        $this->json(['html' => $html], $args); return;
    
    }

}
