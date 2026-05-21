<?php
namespace App\Lekhak\Permissions;

use SPP\Auth\PermissionProviderInterface;

/**
 * ContentPermissionProvider
 *
 * Supplies Lekhak content-domain permissions to the framework's
 * PermissionService. These are the fine-grained permissions checked
 * during content lifecycle operations (create, edit, delete, publish, etc.).
 */
class ContentPermissionProvider implements PermissionProviderInterface
{
    /**
     * All content-domain permissions.
     */
    private const PERMISSIONS = [
        'content.create' => [
            'label'       => 'Create Content',
            'description' => 'Create new content nodes of any type.',
        ],
        'content.edit.own' => [
            'label'       => 'Edit Own Content',
            'description' => 'Edit content nodes that the user authored.',
        ],
        'content.edit.any' => [
            'label'       => 'Edit Any Content',
            'description' => 'Edit any content node regardless of authorship.',
        ],
        'content.delete.own' => [
            'label'       => 'Delete Own Content',
            'description' => 'Delete content nodes that the user authored.',
        ],
        'content.delete.any' => [
            'label'       => 'Delete Any Content',
            'description' => 'Delete any content node.',
        ],
        'content.submit_review' => [
            'label'       => 'Submit for Review',
            'description' => 'Move content from draft to review state.',
        ],
        'content.approve' => [
            'label'       => 'Approve & Publish',
            'description' => 'Approve reviewed content and publish it.',
        ],
        'content.reject' => [
            'label'       => 'Reject Content',
            'description' => 'Reject content and send it back to draft.',
        ],
        'content.fast_publish' => [
            'label'       => 'Fast Publish',
            'description' => 'Publish content directly without review.',
        ],
        'content.unpublish' => [
            'label'       => 'Unpublish',
            'description' => 'Move published content back to draft.',
        ],
        'content.archive' => [
            'label'       => 'Archive Content',
            'description' => 'Move content to the archive.',
        ],
        'content.restore' => [
            'label'       => 'Restore from Archive',
            'description' => 'Restore archived content back to draft.',
        ],
        'content.editor_approve' => [
            'label'       => 'Editor-Level Approval',
            'description' => 'Approve content at the editor stage (article workflow).',
        ],
        'content.view.unpublished' => [
            'label'       => 'View Unpublished Content',
            'description' => 'View content that has not been published.',
        ],
        'content.manage_types' => [
            'label'       => 'Manage Content Types',
            'description' => 'Create, edit, and delete content type definitions.',
        ],
        'content.manage_fields' => [
            'label'       => 'Manage Fields',
            'description' => 'Add, edit, and remove fields on content types.',
        ],
        'content.manage_taxonomy' => [
            'label'       => 'Manage Taxonomy',
            'description' => 'Create and manage vocabularies and terms.',
        ],
    ];

    public function supports(string $permission): bool
    {
        return str_starts_with($permission, 'content.');
    }

    public function check(string $permission, $context = null, ?string $userId = null): bool
    {
        // Delegate to the existing IAM / RBAC system.
        // The PermissionService already checks SPPAuth and rbac.yml before
        // calling providers, so arriving here means the standard checks failed.
        // Application-specific context-aware checks can go here:

        if ($permission === 'content.edit.own' && $context) {
            // Check if the context entity's author matches the current user
            return $this->isOwner($context, $userId);
        }

        if ($permission === 'content.delete.own' && $context) {
            return $this->isOwner($context, $userId);
        }

        return false;
    }

    public function listPermissions(): array
    {
        return self::PERMISSIONS;
    }

    // ── Internal ───────────────────────────────────────────────────────

    private function isOwner($entity, ?string $userId): bool
    {
        if (!is_object($entity) || !method_exists($entity, 'get')) {
            return false;
        }

        $authorId = $entity->get('author') ?? $entity->get('uid') ?? null;
        if (!$authorId) return false;

        $currentUser = $userId;
        if (!$currentUser && class_exists('\\SPPMod\\SPPAuth\\SPPAuth')) {
            $cu = \SPPMod\SPPAuth\SPPAuth::getCurrentUser();
            $currentUser = $cu['username'] ?? ($cu['id'] ?? null);
        }

        return $currentUser && (string)$authorId === (string)$currentUser;
    }
}
