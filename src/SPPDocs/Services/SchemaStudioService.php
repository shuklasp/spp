<?php

namespace App\SPPDocs\Services;

use Symfony\Component\Yaml\Yaml;

/**
 * SchemaStudioService
 * Manages visual content schemas, custom fields, repeaters, relations,
 * and field validation rules for SPPDocs content collections.
 */
class SchemaStudioService
{
    public static function getSchemasDir(string $projectId): string
    {
        $dir = dirname(SPP_BASE_DIR) . '/docs/' . $projectId . '/schemas';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir;
    }

    public static function listSchemas(string $projectId): array
    {
        $dir = self::getSchemasDir($projectId);
        $schemas = [];

        // Built-in standard types
        $schemas['page'] = [
            'name' => 'page',
            'title' => 'Documentation Page',
            'description' => 'Standard technical documentation or standalone article',
            'is_builtin' => true,
            'fields' => [
                'title' => ['type' => 'text', 'label' => 'Document Title', 'required' => true],
                'description' => ['type' => 'textarea', 'label' => 'Meta Description', 'required' => false],
                'status' => ['type' => 'select', 'label' => 'Publication Status', 'options' => ['published', 'draft', 'in_review'], 'default' => 'published'],
                'author' => ['type' => 'text', 'label' => 'Author Name', 'required' => false],
                'tags' => ['type' => 'tags', 'label' => 'Tags', 'required' => false],
            ]
        ];

        $schemas['blog'] = [
            'name' => 'blog',
            'title' => 'Blog Post',
            'description' => 'Chronological publication or release announcement',
            'is_builtin' => true,
            'fields' => [
                'title' => ['type' => 'text', 'label' => 'Post Title', 'required' => true],
                'description' => ['type' => 'textarea', 'label' => 'Summary / Excerpt', 'required' => false],
                'date' => ['type' => 'date', 'label' => 'Publish Date', 'required' => true],
                'author' => ['type' => 'text', 'label' => 'Author Name', 'required' => true],
                'category' => ['type' => 'text', 'label' => 'Category', 'required' => false],
                'tags' => ['type' => 'tags', 'label' => 'Tags', 'required' => false],
                'status' => ['type' => 'select', 'label' => 'Status', 'options' => ['published', 'draft', 'scheduled'], 'default' => 'published'],
            ]
        ];

        // Scan custom schemas
        if (is_dir($dir)) {
            foreach (glob($dir . '/*.yml') as $file) {
                $name = basename($file, '.yml');
                try {
                    $parsed = Yaml::parseFile($file) ?: [];
                    $schemas[$name] = array_merge([
                        'name' => $name,
                        'title' => ucfirst(str_replace('-', ' ', $name)),
                        'description' => 'Custom content collection schema',
                        'is_builtin' => false,
                        'fields' => [],
                        'modified_at' => filemtime($file),
                    ], $parsed);
                } catch (\Throwable $e) {}
            }
        }

        ksort($schemas);
        return $schemas;
    }

    public static function getSchema(string $projectId, string $type): ?array
    {
        $all = self::listSchemas($projectId);
        return $all[$type] ?? null;
    }

    public static function saveSchema(string $projectId, string $type, array $definition): bool
    {
        $safeType = preg_replace('/[^a-zA-Z0-9\-_]/', '', strtolower($type));
        if (empty($safeType)) {
            return false;
        }

        $dir = self::getSchemasDir($projectId);
        $file = $dir . '/' . $safeType . '.yml';

        $payload = [
            'name' => $safeType,
            'title' => trim($definition['title'] ?? ucfirst(str_replace('-', ' ', $safeType))),
            'description' => trim($definition['description'] ?? ''),
            'fields' => $definition['fields'] ?? [],
            'updated_at' => time(),
        ];

        return (bool)@file_put_contents($file, Yaml::dump($payload, 4, 2), LOCK_EX);
    }

    public static function deleteSchema(string $projectId, string $type): bool
    {
        $safeType = preg_replace('/[^a-zA-Z0-9\-_]/', '', strtolower($type));
        if (empty($safeType) || in_array($safeType, ['page', 'blog'], true)) {
            return false; // Prevent deleting built-in types
        }

        $dir = self::getSchemasDir($projectId);
        $file = $dir . '/' . $safeType . '.yml';
        if (file_exists($file)) {
            return @unlink($file);
        }
        return false;
    }

    /**
     * Filter frontmatter fields based on field-level view_roles RBAC.
     */
    public static function filterFieldsForUser(string $projectId, string $type, array $frontmatter, ?string $username = null): array
    {
        $username = $username ?? ($_SESSION['sppdocs_user'] ?? $_SESSION['user'] ?? $_SESSION['username'] ?? null);

        // Global superadmins and project admins have access to all fields
        if ($username && (PermissionManager::isGlobalAdmin($username) || PermissionManager::hasProjectRole($projectId, $username, 'admin'))) {
            return $frontmatter;
        }

        $schema = self::getSchema($projectId, $type);
        if (!$schema || empty($schema['fields'])) {
            return $frontmatter;
        }

        $userRoles = $username ? PermissionManager::getUserRoles($projectId, $username) : [];

        foreach ($schema['fields'] as $fieldName => $fieldDef) {
            $viewRoles = $fieldDef['view_roles'] ?? [];
            if (!empty($viewRoles)) {
                $allowed = false;
                foreach ((array)$viewRoles as $r) {
                    if (in_array($r, $userRoles, true)) {
                        $allowed = true;
                        break;
                    }
                }
                if (!$allowed) {
                    unset($frontmatter[$fieldName]);
                }
            }
        }

        return $frontmatter;
    }

    /**
     * Check if user can edit a specific field in a schema.
     */
    public static function canEditField(string $projectId, string $type, string $fieldName, ?string $username = null): bool
    {
        $username = $username ?? ($_SESSION['sppdocs_user'] ?? $_SESSION['user'] ?? $_SESSION['username'] ?? null);

        // Global superadmins and project admins can edit all fields
        if ($username && (PermissionManager::isGlobalAdmin($username) || PermissionManager::hasProjectRole($projectId, $username, 'admin'))) {
            return true;
        }

        $schema = self::getSchema($projectId, $type);
        if (!$schema || empty($schema['fields'][$fieldName])) {
            return true;
        }

        $fieldDef = $schema['fields'][$fieldName];
        $editRoles = $fieldDef['edit_roles'] ?? [];
        if (empty($editRoles)) {
            return true;
        }

        if (!$username) {
            return false;
        }

        $userRoles = PermissionManager::getUserRoles($projectId, $username);
        foreach ((array)$editRoles as $r) {
            if (in_array($r, $userRoles, true)) {
                return true;
            }
        }

        return false;
    }
}