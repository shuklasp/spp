<?php

namespace App\SPPDocs\Traits;

/**
 * Role-Based Access Control (RBAC) guard for SPPDocs controllers.
 * 
 * Roles: viewer, contributor, project_manager, admin
 * Stored in: {project}/issues/roles.json
 */
trait RBACGuard
{
    protected function loadRoles(string $issuesDir): array
    {
        $file = $issuesDir . '/roles.json';
        if (file_exists($file)) {
            return json_decode(file_get_contents($file), true) ?: [];
        }
        return [];
    }

    protected function saveRoles(string $issuesDir, array $roles): void
    {
        $file = $issuesDir . '/roles.json';
        file_put_contents($file, json_encode($roles, JSON_PRETTY_PRINT));
    }

    /**
     * Get all effective roles for a user in a project.
     * Supports multi-role assignments conforming to SPP framework RBAC.
     */
    protected function getUserRoles(string $issuesDir, ?array $user): array
    {
        if (!$user) return ['guest'];

        $username = $user['username'] ?? '';
        
        // Session-level admin flag or Global Admin always wins
        if (($user['role'] ?? '') === 'admin' || \App\SPPDocs\Services\PermissionManager::isGlobalAdmin($username)) {
            return ['admin'];
        }

        $roles = $this->loadRoles($issuesDir);
        $raw = $roles[$username] ?? null;
        if ($raw !== null) {
            $parsed = \App\SPPDocs\Services\PermissionManager::normalizeRoles($raw);
            if (!empty($parsed)) {
                return $parsed;
            }
        }

        // Default registered users to developer (formerly contributor)
        return ['developer'];
    }

    /**
     * Get the primary effective role for a user in a project.
     * Maintained for backward compatibility.
     */
    protected function getUserRole(string $issuesDir, ?array $user): string
    {
        $roles = $this->getUserRoles($issuesDir, $user);
        $priorityOrder = ['admin', 'maintainer', 'developer', 'reporter', 'viewer', 'guest'];
        foreach ($priorityOrder as $p) {
            if (in_array($p, $roles, true)) {
                return $p;
            }
        }
        return reset($roles) ?: 'guest';
    }

    /**
     * Check if a user can perform an action.
     * Evaluates permission union across ALL roles assigned to the user for that project.
     */
    protected function can(string $issuesDir, ?array $user, string $action): bool
    {
        $roles = $this->getUserRoles($issuesDir, $user);

        // Normalize legacy role aliases across all assigned roles
        $normalizedRoles = [];
        foreach ($roles as $r) {
            if ($r === 'contributor') $r = 'developer';
            elseif ($r === 'project_manager') $r = 'maintainer';
            $normalizedRoles[] = $r;
        }

        // Action mapping to granular permissions
        $actionMap = [
            'view'          => 'issues.read',
            'create'        => 'issues.create',
            'comment'       => 'issues.comment',
            'close_own'     => 'issues.edit',
            'close_any'     => 'issues.edit',
            'assign'        => 'issues.edit',
            'milestone'     => 'milestones.create',
            'log_time'      => 'issues.log_time',
            'upload'        => 'issues.edit',
            'manage_labels' => 'issues.edit',
            'manage_teams'  => 'team.manage',
            'manage_roles'  => 'team.manage',
            'delete'        => 'issues.delete',
        ];

        $perm = $actionMap[$action] ?? $action;
        return \App\SPPDocs\Services\PermissionManager::can($perm, $normalizedRoles);
    }

    /**
     * Abort if user lacks permission.
     */
    protected function authorizeAction(string $issuesDir, ?array $user, string $action): void
    {
        if (!$this->can($issuesDir, $user, $action)) {
            http_response_code(403);
            exit('Access denied. You do not have permission to perform this action.');
        }
    }
}
