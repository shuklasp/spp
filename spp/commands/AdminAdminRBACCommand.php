<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class AdminAdminRBACCommand extends Command
{
    protected string $name = 'admin:adminrbac';
    protected string $description = 'Manage Admin AdminRBAC operations. Usage: admin:adminrbac <action> [--payload=...] [--json]';

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

    private function handleGetAdminPermissions(array $payload, array $args): void {

        $scopeMap = getAdminScopeMap();
        $userScopes = getAdminUserScopes();
        $allScopes = array_unique(array_values($scopeMap));

        $this->json([
            'scopes' => $userScopes,
            'all_scopes' => $allScopes,
            'scope_map' => $scopeMap,
        ], $args); return;
    
    }

    private function handleSaveAdminPermissions(array $payload, array $args): void {

        // Only super-admins can modify permissions
        $currentScopes = getAdminUserScopes();
        if (!in_array('admin.identity', $currentScopes) && !in_array('admin.*', $currentScopes)) {
            $this->json(['success' => false, 'error' => 'Access denied: admin.identity scope required.', 'error'], $args); return;
        return;
        }

        $targetUserId = $payload['user_id'] ?? null;
        $newScopes = $payload['scopes'] ?? [];

        if (!$targetUserId) {
            $this->json(['success' => false, 'error' => 'User ID is required.', 'error'], $args); return;
        return;
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

            $this->json(['success' => true, 'message' => 'Admin permissions updated.', 'success'], $args); return;
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => 'Failed to save permissions: ' . $e->getMessage(), 'error'], $args); return;
        }
    
    }

}
