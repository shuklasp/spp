<?php

namespace App\SPPDocs\Services;

use Symfony\Component\Yaml\Yaml;

/**
 * PermissionManager
 * Fine-grained Role-Based Access Control (RBAC) & Governance for SPPDocs.
 * Supports:
 *   - Global Super Admin (system configuration, all projects, user management)
 *   - Project Admin (project settings, features, storage, links, team management)
 *   - Project Roles: maintainer, developer, reporter, guest
 *   - Granular resource permissions at any level.
 */
class PermissionManager
{
    private const DEFAULT_ROLES = [
        'admin' => [
            'id' => 'admin',
            'name' => 'Project Administrator',
            'description' => 'Unrestricted access across all documentation, issues, milestones, releases, and settings.',
            'is_system' => true,
            'permissions' => ['*']
        ],
        'maintainer' => [
            'id' => 'maintainer',
            'name' => 'Maintainer',
            'description' => 'Content authoring, full issue triage, milestone management, releases, and webhooks.',
            'is_system' => true,
            'permissions' => [
                'docs.read', 'docs.create', 'docs.edit', 'docs.delete', 'docs.publish',
                'issues.read', 'issues.create', 'issues.comment', 'issues.edit', 'issues.move', 'issues.delete', 'issues.log_time',
                'milestones.read', 'milestones.create', 'milestones.edit', 'milestones.delete',
                'forums.read', 'forums.post', 'forums.moderate',
                'releases.read', 'releases.manage',
                'webhooks.manage', 'automations.manage',
                'lms.read', 'lms.view_studio', 'lms.manage_courses', 'lms.author_assigned', 'lms.view_roster', 'lms.export_gradebook'
            ]
        ],
        'instructor' => [
            'id' => 'instructor',
            'name' => 'Course Instructor',
            'description' => 'Course creation, syllabus editing, lesson authoring, and student roster review.',
            'is_system' => true,
            'permissions' => [
                'docs.read',
                'lms.read', 'lms.view_studio', 'lms.author_assigned', 'lms.view_roster'
            ]
        ],
        'teaching_assistant' => [
            'id' => 'teaching_assistant',
            'name' => 'Teaching Assistant',
            'description' => 'Review student submissions, inspect grades, and export course gradebooks.',
            'is_system' => true,
            'permissions' => [
                'docs.read',
                'lms.read', 'lms.view_studio', 'lms.view_roster', 'lms.export_gradebook'
            ]
        ],
        'auditor' => [
            'id' => 'auditor',
            'name' => 'Compliance Auditor',
            'description' => 'Read-only inspection of student rosters, verification certificates, and gradebooks.',
            'is_system' => true,
            'permissions' => [
                'docs.read',
                'lms.read', 'lms.view_roster', 'lms.export_gradebook'
            ]
        ],
        'developer' => [
            'id' => 'developer',
            'name' => 'Developer',
            'description' => 'Active contributor: create/edit/move issues, log work hours, create milestones, and post discussions.',
            'is_system' => true,
            'permissions' => [
                'docs.read',
                'issues.read', 'issues.create', 'issues.comment', 'issues.edit', 'issues.move', 'issues.log_time',
                'milestones.read', 'milestones.create', 'milestones.edit',
                'forums.read', 'forums.post',
                'releases.read',
                'lms.read'
            ]
        ],
        'reporter' => [
            'id' => 'reporter',
            'name' => 'Reporter',
            'description' => 'Community reporter: open issues, post comments, and participate in forum discussions.',
            'is_system' => true,
            'permissions' => [
                'docs.read',
                'issues.read', 'issues.create', 'issues.comment',
                'milestones.read',
                'forums.read', 'forums.post',
                'releases.read',
                'lms.read'
            ]
        ],
        'viewer' => [
            'id' => 'viewer',
            'name' => 'Viewer',
            'description' => 'Read-only access to documentation, issue tracker, milestones, and release downloads.',
            'is_system' => true,
            'permissions' => [
                'docs.read', 'issues.read', 'milestones.read', 'forums.read', 'releases.read', 'lms.read'
            ]
        ],
        'guest' => [
            'id' => 'guest',
            'name' => 'Guest',
            'description' => 'Public unauthenticated access to documentation and forum threads.',
            'is_system' => true,
            'permissions' => [
                'docs.read', 'issues.read', 'forums.read', 'lms.read'
            ]
        ]
    ];

    /**
     * File path for persistent roles storage.
     */
    public static function getRolesFilePath(): string
    {
        return __DIR__ . '/../etc/roles.json';
    }

    /**
     * Retrieve all defined roles (system + custom).
     */
    public static function getAllRoles(): array
    {
        $file = self::getRolesFilePath();
        $roles = self::DEFAULT_ROLES;

        if (file_exists($file)) {
            $custom = json_decode(file_get_contents($file), true) ?: [];
            if (is_array($custom)) {
                foreach ($custom as $id => $def) {
                    if (is_array($def) && !empty($def['name'])) {
                        $roles[$id] = [
                            'id' => $id,
                            'name' => $def['name'],
                            'description' => $def['description'] ?? '',
                            'is_system' => !empty(self::DEFAULT_ROLES[$id]),
                            'permissions' => (array)($def['permissions'] ?? [])
                        ];
                    }
                }
            }
        }

        return $roles;
    }

    /**
     * Get a specific role definition.
     */
    public static function getRole(string $id): ?array
    {
        $roles = self::getAllRoles();
        return $roles[$id] ?? null;
    }

    /**
     * Create or update a role with permissions.
     */
    public static function saveRole(string $id, string $name, string $description, array $permissions): bool
    {
        $id = strtolower(preg_replace('/[^a-zA-Z0-9_\-]/', '_', trim($id)));
        $name = trim($name);
        if (empty($id) || empty($name)) {
            return false;
        }

        $allRoles = self::getAllRoles();
        $isSystem = !empty(self::DEFAULT_ROLES[$id]);

        // Clean permissions
        $permissions = array_values(array_unique(array_filter(array_map('trim', $permissions))));

        $allRoles[$id] = [
            'id' => $id,
            'name' => $name,
            'description' => trim($description),
            'is_system' => $isSystem,
            'permissions' => $permissions
        ];

        // Filter out unchanged defaults if you want, or persist all
        $file = self::getRolesFilePath();
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        return (bool)file_put_contents($file, json_encode($allRoles, JSON_PRETTY_PRINT));
    }

    /**
     * Delete a custom role.
     * System roles cannot be deleted.
     */
    public static function deleteRole(string $id): bool
    {
        if (isset(self::DEFAULT_ROLES[$id])) {
            return false; // System roles cannot be deleted
        }

        $file = self::getRolesFilePath();
        if (!file_exists($file)) {
            return false;
        }

        $roles = json_decode(file_get_contents($file), true) ?: [];
        if (!isset($roles[$id])) {
            return false;
        }

        unset($roles[$id]);
        file_put_contents($file, json_encode($roles, JSON_PRETTY_PRINT));
        return true;
    }

    /**
     * Dictionary of all available permissions organized by module.
     */
    public static function getAvailablePermissions(): array
    {
        return [
            'docs' => [
                'label' => 'Documentation & Content',
                'description' => 'Authoring, editing, and publishing documentation pages and blog articles.',
                'permissions' => [
                    'docs.read' => ['label' => 'Read Documentation', 'desc' => 'View documentation pages, guides, and blog posts'],
                    'docs.create' => ['label' => 'Create Pages', 'desc' => 'Create new markdown documentation and blog articles'],
                    'docs.edit' => ['label' => 'Edit Content', 'desc' => 'Modify content and frontmatter in web markdown editor'],
                    'docs.delete' => ['label' => 'Delete Pages', 'desc' => 'Delete markdown pages and archived revisions'],
                    'docs.publish' => ['label' => 'Trigger SSG Builds', 'desc' => 'Compile and export standalone static site bundles'],
                ]
            ],
            'issues' => [
                'label' => 'Issue Tracking & Kanban',
                'description' => 'Issue lifecycle, card movements, prioritization, and time tracking.',
                'permissions' => [
                    'issues.read' => ['label' => 'View Issues', 'desc' => 'View issue lists, detail pages, board, and calendar'],
                    'issues.create' => ['label' => 'Open Issues', 'desc' => 'Create new tasks, bugs, features, and epics'],
                    'issues.comment' => ['label' => 'Post Comments', 'desc' => 'Add comments and discussion notes to issues'],
                    'issues.edit' => ['label' => 'Edit Issues', 'desc' => 'Update title, description, priority, labels, and due dates'],
                    'issues.move' => ['label' => 'Move Cards', 'desc' => 'Drag and transition cards between Kanban status columns'],
                    'issues.delete' => ['label' => 'Delete Issues', 'desc' => 'Permanently remove issues from project storage'],
                    'issues.log_time' => ['label' => 'Log Time', 'desc' => 'Record spent work hours and time tracking logs'],
                ]
            ],
            'milestones' => [
                'label' => 'Milestones & Sprints',
                'description' => 'Sprint roadmap, milestone schedules, and progress tracking.',
                'permissions' => [
                    'milestones.read' => ['label' => 'View Milestones', 'desc' => 'View sprint cards and progress fraction metrics'],
                    'milestones.create' => ['label' => 'Create Milestones', 'desc' => 'Define new target sprints with start and end dates'],
                    'milestones.edit' => ['label' => 'Edit Milestones', 'desc' => 'Update milestone title, targets, and dates'],
                    'milestones.delete' => ['label' => 'Delete Milestones', 'desc' => 'Remove milestone records from project'],
                ]
            ],
            'forums' => [
                'label' => 'Community Discussions',
                'description' => 'Threaded forum categories, replies, and community moderation.',
                'permissions' => [
                    'forums.read' => ['label' => 'Read Forums', 'desc' => 'Browse categories and view discussion threads'],
                    'forums.post' => ['label' => 'Post & Reply', 'desc' => 'Create new discussion threads and post replies'],
                    'forums.moderate' => ['label' => 'Moderate Forums', 'desc' => 'Lock threads, pin topics, and mark accepted solutions'],
                ]
            ],
            'releases' => [
                'label' => 'Releases & Downloads',
                'description' => 'Publishing release artifacts and changelog notes.',
                'permissions' => [
                    'releases.read' => ['label' => 'Download Releases', 'desc' => 'Access and download published release artifacts'],
                    'releases.manage' => ['label' => 'Manage Releases', 'desc' => 'Add, update, or remove release versions and notes'],
                ]
            ],
            'integrations' => [
                'label' => 'Integrations & Webhooks',
                'description' => 'External notifications, CI/CD Git webhooks, and storage migrations.',
                'permissions' => [
                    'webhooks.manage' => ['label' => 'Manage Webhooks', 'desc' => 'Configure outbound HTTP event webhooks with SSRF guard'],
                    'automations.manage' => ['label' => 'Manage Automations', 'desc' => 'Configure inbound Git commit parsers and lifecycle triggers'],
                    'storage.manage' => ['label' => 'Storage & Migrations', 'desc' => 'Configure database parameters and execute 3-way migrations'],
                ]
            ],
            'governance' => [
                'label' => 'Project Governance',
                'description' => 'Team membership, role assignments, and project configurations.',
                'permissions' => [
                    'team.manage' => ['label' => 'Manage Team Members', 'desc' => 'Add/remove members and assign project roles'],
                    'project.settings' => ['label' => 'Project Settings', 'desc' => 'Modify branding, modular features, and repository links'],
                ]
            ],
            'lms' => [
                'label' => 'Academy & Course Studio (LMS)',
                'description' => 'Course creation, syllabus editing, lesson authoring, roster management, and gradebook exports.',
                'permissions' => [
                    'lms.read' => ['label' => 'Access Academy', 'desc' => 'Browse courses and take lessons and quizzes'],
                    'lms.view_studio' => ['label' => 'Access Course Studio', 'desc' => 'View the course management dashboard'],
                    'lms.manage_courses' => ['label' => 'Manage Courses', 'desc' => 'Create, publish, or delete courses'],
                    'lms.author_assigned' => ['label' => 'Author Assigned Courses', 'desc' => 'Edit syllabus and lessons for assigned courses'],
                    'lms.view_roster' => ['label' => 'View Roster & Analytics', 'desc' => 'Inspect student attempts, scores, and completion'],
                    'lms.export_gradebook' => ['label' => 'Export Gradebooks', 'desc' => 'Download gradebook CSV and audit records'],
                ]
            ]
        ];
    }

    /**
     * Normalize role input (string, array, comma-separated) into an array of role slugs.
     */
    public static function normalizeRoles($raw): array
    {
        if ($raw === null || $raw === false || $raw === '') {
            return [];
        }

        if (is_string($raw)) {
            if (str_contains($raw, ',')) {
                $raw = explode(',', $raw);
            } else {
                $raw = [$raw];
            }
        }

        if (!is_array($raw)) {
            return [];
        }

        $clean = [];
        foreach ($raw as $r) {
            if (is_string($r)) {
                $r = trim($r);
                if ($r !== '' && $r !== 'none' && $r !== 'revoke') {
                    $clean[] = $r;
                }
            }
        }

        return array_values(array_unique($clean));
    }

    /**
     * Check if a specific role or array of roles possesses a permission.
     * Alias for self::can().
     */
    public static function hasPermission(string|array $role, string $permission): bool
    {
        return self::can($permission, $role);
    }

    /**
     * Check if a specific role or array of roles possesses a permission.
     * Evaluates permission union across all provided roles.
     */
    public static function can(string $permission, string|array $role): bool
    {
        if (is_array($role)) {
            foreach ($role as $r) {
                if (self::can($permission, (string)$r)) {
                    return true;
                }
            }
            return false;
        }

        if ($role === 'admin') {
            return true;
        }

        $roles = self::getAllRoles();
        $roleDef = $roles[$role] ?? ($roles['guest'] ?? null);
        if (!$roleDef) {
            return false;
        }

        $perms = $roleDef['permissions'] ?? [];
        if (in_array('*', $perms, true)) {
            return true;
        }

        if (in_array($permission, $perms, true)) {
            return true;
        }

        // Domain wildcard support, e.g. "issues.*" matches "issues.create"
        $parts = explode('.', $permission, 2);
        if (count($parts) === 2 && in_array($parts[0] . '.*', $perms, true)) {
            return true;
        }

        return false;
    }

    /**
     * Retrieve the list of Global Super Admin usernames.
     */
    public static function getGlobalAdmins(): array
    {
        $configFile = __DIR__ . '/../etc/sppdocs.yml';
        if (file_exists($configFile)) {
            $config = Yaml::parseFile($configFile) ?: [];
            if (!empty($config['global_admins']) && is_array($config['global_admins'])) {
                return array_unique(array_filter($config['global_admins']));
            }
        }
        // Fallback default: 'admin' is always seeded as Global Super Admin
        return ['admin'];
    }

    /**
     * Persist the list of Global Super Admin usernames into sppdocs.yml.
     * Prevents removing the last remaining global admin.
     */
    public static function setGlobalAdmins(array $admins): bool
    {
        $admins = array_unique(array_filter(array_map('trim', $admins)));
        if (empty($admins)) {
            return false; // Prevent lockout
        }

        $configFile = __DIR__ . '/../etc/sppdocs.yml';
        $config = file_exists($configFile) ? (Yaml::parseFile($configFile) ?: []) : [];
        $config['global_admins'] = array_values($admins);

        file_put_contents($configFile, Yaml::dump($config, 4, 2));
        return true;
    }

    /**
     * Determine if a user has Global Super Admin privileges.
     */
    public static function isGlobalAdmin(?string $username = null): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if ($username === null) {
            $username = $_SESSION['sppdocs_user'] ?? null;
            if (!$username && class_exists('\SPPMod\SPPAuth\SPPAuth') && \SPPMod\SPPAuth\SPPAuth::authSessionExists()) {
                $u = \SPPMod\SPPAuth\SPPAuth::guard('web')->user();
                $username = $u->username ?? null;
            }
        }

        if (!$username) {
            return false;
        }

        $globalAdmins = self::getGlobalAdmins();
        if (in_array($username, $globalAdmins, true)) {
            return true;
        }

        // Framework superuser / root check
        if (class_exists('\SPP\SPPAuth') && \SPP\SPPAuth::isLoggedIn() && \SPP\SPPAuth::hasRight('admin')) {
            return true;
        }

        return false;
    }

    /**
     * Determine if a user is an Admin of a specific project (or Global Super Admin).
     */
    public static function isProjectAdmin(string $projectId, ?string $username = null): bool
    {
        if (self::isGlobalAdmin($username)) {
            return true;
        }

        $roles = self::getProjectUserRoles($projectId, $username);
        return in_array('admin', $roles, true);
    }

    /**
     * Resolve all assigned roles for a user in a specific project.
     * Supports multi-role assignments conforming to SPP framework RBAC.
     */
    public static function getProjectUserRoles(string $projectId, ?string $username = null): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if ($username === null) {
            $username = $_SESSION['sppdocs_user'] ?? null;
            if (!$username && class_exists('\SPPMod\SPPAuth\SPPAuth') && \SPPMod\SPPAuth\SPPAuth::authSessionExists()) {
                $u = \SPPMod\SPPAuth\SPPAuth::guard('web')->user();
                $username = $u->username ?? null;
            }
        }

        if (!$username) {
            return ['guest'];
        }

        // Global super admin is admin in every project
        if (self::isGlobalAdmin($username)) {
            return ['admin'];
        }

        // Check project-specific roles ({issuesDir}/roles.json)
        $issuesDir = self::resolveIssuesDir($projectId);
        if ($issuesDir && file_exists($issuesDir . '/roles.json')) {
            $roles = json_decode(file_get_contents($issuesDir . '/roles.json'), true) ?: [];
            if (isset($roles[$username])) {
                $parsed = self::normalizeRoles($roles[$username]);
                if (!empty($parsed)) {
                    return $parsed;
                }
            }
        }

        // Check project.yml ACL write & members
        $projectConfig = self::loadProjectConfig($projectId);
        if ($projectConfig) {
            if (!empty($projectConfig['members'][$username])) {
                return self::normalizeRoles($projectConfig['members'][$username]);
            }
            $writeAcl = $projectConfig['acl']['write'] ?? [];
            if (in_array($username, $writeAcl, true)) {
                return ['maintainer'];
            }
        }

        return ['reporter'];
    }

    /**
     * Resolve the primary effective role of a user in a specific project.
     * Maintained for backward compatibility.
     */
    public static function getProjectUserRole(string $projectId, ?string $username = null): string
    {
        $roles = self::getProjectUserRoles($projectId, $username);
        if (empty($roles)) {
            return 'guest';
        }

        // Priority resolution: admin > maintainer > developer > reporter > viewer > guest
        $priorityOrder = ['admin', 'maintainer', 'developer', 'reporter', 'viewer', 'guest'];
        foreach ($priorityOrder as $p) {
            if (in_array($p, $roles, true)) {
                return $p;
            }
        }

        return reset($roles);
    }

    /**
     * Check if a user possesses a specific role in a project.
     */
    public static function hasProjectRole(string $projectId, ?string $username, string $role): bool
    {
        $roles = self::getProjectUserRoles($projectId, $username);
        return in_array($role, $roles, true);
    }

    /**
     * Alias for getProjectUserRoles.
     */
    public static function getUserRoles(string $projectId, ?string $username = null): array
    {
        return self::getProjectUserRoles($projectId, $username);
    }

    /**
     * Check if a user possesses a permission on a project.
     * Evaluates permission union across ALL roles assigned to the user for that project.
     */
    public static function canUser(string $permission, string $projectId, ?string $username = null): bool
    {
        if (self::isGlobalAdmin($username)) {
            return true;
        }

        $roles = self::getProjectUserRoles($projectId, $username);
        if (in_array('admin', $roles, true)) {
            return true;
        }

        foreach ($roles as $r) {
            if (self::can($permission, $r)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if a user has authority to manage or author a specific course.
     * Supports Global Admin, Project Admin, lms.manage_courses, and scoped lms.author_assigned.
     */
    public static function canUserManageCourse(string $projectId, string $courseId, ?string $username = null): bool
    {
        if (!$username) {
            return false;
        }

        if (self::isGlobalAdmin($username) || self::isProjectAdmin($projectId, $username)) {
            return true;
        }

        if (self::canUser('lms.manage_courses', $projectId, $username)) {
            return true;
        }

        if (self::canUser('lms.author_assigned', $projectId, $username)) {
            $project = self::loadProjectConfig($projectId);
            if ($project) {
                $course = LmsService::getCourse($projectId, $project, $courseId);
                if ($course) {
                    $instUser = is_array($course['instructor'] ?? null)
                        ? ($course['instructor']['username'] ?? ($course['instructor']['name'] ?? ''))
                        : (string)($course['instructor'] ?? '');
                    if ($instUser !== '' && strcasecmp($instUser, $username) === 0) {
                        return true;
                    }
                    $coInstructors = (array)($course['co_instructors'] ?? []);
                    foreach ($coInstructors as $co) {
                        if (is_string($co) && strcasecmp($co, $username) === 0) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * Load project members and their effective roles.
     */
    public static function getProjectMembers(string $projectId): array
    {
        $members = [];
        $allRoles = self::getAllRoles();
        
        // 1. Seed with Global Admins
        foreach (self::getGlobalAdmins() as $admin) {
            $members[$admin] = [
                'username' => $admin,
                'role' => 'admin',
                'roles' => ['admin'],
                'role_names' => ['Global Super Admin'],
                'is_global' => true
            ];
        }

        // 2. Load project-specific roles
        $issuesDir = self::resolveIssuesDir($projectId);
        if ($issuesDir && file_exists($issuesDir . '/roles.json')) {
            $roles = json_decode(file_get_contents($issuesDir . '/roles.json'), true) ?: [];
            foreach ($roles as $user => $assigned) {
                $userRoles = self::normalizeRoles($assigned);
                if (empty($userRoles)) {
                    continue;
                }
                $roleNames = array_map(function($rId) use ($allRoles) {
                    return $allRoles[$rId]['name'] ?? ucfirst($rId);
                }, $userRoles);

                if (!isset($members[$user])) {
                    $members[$user] = [
                        'username' => $user,
                        'role' => implode(', ', $userRoles),
                        'roles' => $userRoles,
                        'role_names' => $roleNames,
                        'is_global' => false
                    ];
                }
            }
        }

        return array_values($members);
    }

    /**
     * Save project member roles (supports multi-role arrays per user).
     */
    public static function saveProjectMembers(string $projectId, array $userRoles): void
    {
        $issuesDir = self::resolveIssuesDir($projectId);
        if (!$issuesDir) {
            return;
        }

        if (!is_dir($issuesDir)) {
            @mkdir($issuesDir, 0777, true);
        }

        $roles = [];
        $globalAdmins = self::getGlobalAdmins();

        foreach ($userRoles as $username => $assigned) {
            $username = trim($username);
            if (!empty($username) && !in_array($username, $globalAdmins, true)) {
                $clean = self::normalizeRoles($assigned);
                if (!empty($clean)) {
                    $roles[$username] = $clean;
                }
            }
        }

        file_put_contents($issuesDir . '/roles.json', json_encode($roles, JSON_PRETTY_PRINT));
    }

    /**
     * Helper to load project YAML configuration.
     */
    public static function loadProjectConfig(string $projectId): ?array
    {
        $configFile = __DIR__ . '/../etc/sppdocs.yml';
        if (!file_exists($configFile)) {
            return null;
        }

        $mainConfig = Yaml::parseFile($configFile) ?: [];
        $projectConfigPath = $mainConfig['projects'][$projectId] ?? null;

        if (!$projectConfigPath) {
            $fallback = dirname(SPP_BASE_DIR) . '/docs/' . $projectId . '/project.yml';
            if (file_exists($fallback)) {
                $cfg = Yaml::parseFile($fallback);
                if (is_array($cfg)) {
                    $cfg['_config_path'] = $fallback;
                }
                return $cfg;
            }
            return null;
        }

        if (!str_starts_with($projectConfigPath, '/') && !preg_match('/^[a-zA-Z]:[\/\\\\]/', $projectConfigPath)) {
            $projectConfigPath = dirname(SPP_BASE_DIR) . '/' . $projectConfigPath;
        }

        if (file_exists($projectConfigPath)) {
            $cfg = Yaml::parseFile($projectConfigPath);
            if (is_array($cfg)) {
                $cfg['_config_path'] = $projectConfigPath;
            }
            return $cfg;
        }

        return null;
    }

    /**
     * Helper to resolve project issues directory.
     */
    public static function resolveIssuesDir(string $projectId): ?string
    {
        $projectConfig = self::loadProjectConfig($projectId);
        if (!$projectConfig) {
            return null;
        }

        if (!empty($projectConfig['issues_dir'])) {
            $dir = $projectConfig['issues_dir'];
        } elseif (!empty($projectConfig['_config_path'])) {
            $dir = dirname($projectConfig['_config_path']) . '/issues';
        } else {
            $pagesDir = $projectConfig['pages_dir'] ?? ('docs/' . $projectId . '/pages');
            $base = dirname(SPP_BASE_DIR) . '/' . $pagesDir;
            $dir = dirname($base) . '/issues';
        }

        if (!str_starts_with($dir, '/') && !preg_match('/^[a-zA-Z]:[\/\\\\]/', $dir)) {
            $dir = dirname(SPP_BASE_DIR) . '/' . $dir;
        }

        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        return $dir;
    }

    /**
     * Discover all registered projects from configuration.
     */
    public static function getAllProjectList(): array
    {
        $configFile = __DIR__ . '/../etc/sppdocs.yml';
        if (!file_exists($configFile)) {
            return [];
        }

        $mainConfig = Yaml::parseFile($configFile) ?: [];
        $rawProjects = $mainConfig['projects'] ?? [];
        $list = [];

        foreach ($rawProjects as $pId => $path) {
            $cfg = self::loadProjectConfig($pId);
            $title = $cfg['title'] ?? $cfg['name'] ?? ucfirst($pId);
            $list[$pId] = [
                'id' => $pId,
                'title' => $title,
                'config_path' => $path
            ];
        }

        return $list;
    }

    /**
     * Retrieve all project role assignments for a given user across the entire platform.
     */
    public static function getUserAssignedRoles(string $username): array
    {
        $assignments = [];
        $allRoles = self::getAllRoles();

        // If global admin, record root authority
        if (self::isGlobalAdmin($username)) {
            $assignments['*'] = [
                'project_id' => '*',
                'project_title' => 'All Projects',
                'role_id' => 'admin',
                'role_name' => 'Global Super Admin',
                'roles' => [
                    [
                        'id' => 'admin',
                        'name' => 'Global Super Admin',
                        'is_custom' => false,
                        'description' => 'Unrestricted access across all projects.'
                    ]
                ],
                'role_ids' => ['admin'],
                'is_global' => true,
                'is_custom' => false,
            ];
        }

        $projects = self::getAllProjectList();
        foreach ($projects as $pId => $pInfo) {
            $issuesDir = self::resolveIssuesDir($pId);
            $assignedRoles = [];

            if ($issuesDir && file_exists($issuesDir . '/roles.json')) {
                $pRoles = json_decode(file_get_contents($issuesDir . '/roles.json'), true) ?: [];
                if (!empty($pRoles[$username])) {
                    $assignedRoles = self::normalizeRoles($pRoles[$username]);
                }
            }

            if (empty($assignedRoles)) {
                $pConfig = self::loadProjectConfig($pId);
                $writeAcl = $pConfig['acl']['write'] ?? [];
                if (in_array($username, $writeAcl, true)) {
                    $assignedRoles = ['maintainer'];
                }
            }

            if (!empty($assignedRoles)) {
                $roleDefs = [];
                $roleNames = [];
                $hasCustom = false;
                foreach ($assignedRoles as $rId) {
                    $roleDef = $allRoles[$rId] ?? null;
                    $rName = $roleDef['name'] ?? ucfirst($rId);
                    $isCust = empty(self::DEFAULT_ROLES[$rId]);
                    if ($isCust) {
                        $hasCustom = true;
                    }
                    $roleDefs[] = [
                        'id' => $rId,
                        'name' => $rName,
                        'is_custom' => $isCust,
                        'description' => $roleDef['description'] ?? ''
                    ];
                    $roleNames[] = $rName;
                }

                $assignments[$pId] = [
                    'project_id' => $pId,
                    'project_title' => $pInfo['title'],
                    'role_id' => implode(', ', $assignedRoles),
                    'role_name' => implode(', ', $roleNames),
                    'roles' => $roleDefs,
                    'role_ids' => $assignedRoles,
                    'is_global' => false,
                    'is_custom' => $hasCustom,
                ];
            }
        }

        return $assignments;
    }

    /**
     * Assign or revoke roles for a user in a specific project.
     * Supports array of roles, comma-separated string, or single string.
     */
    public static function assignUserProjectRole(string $username, string $projectId, $roleIds): bool
    {
        $username = trim($username);
        $projectId = trim($projectId);

        if (empty($username) || empty($projectId)) {
            return false;
        }

        $issuesDir = self::resolveIssuesDir($projectId);
        if (!$issuesDir) {
            return false;
        }

        if (!is_dir($issuesDir)) {
            @mkdir($issuesDir, 0777, true);
        }

        $rolesFile = $issuesDir . '/roles.json';
        $roles = file_exists($rolesFile) ? (json_decode(file_get_contents($rolesFile), true) ?: []) : [];

        $cleanRoles = self::normalizeRoles($roleIds);

        if (empty($cleanRoles)) {
            unset($roles[$username]);
        } else {
            $roles[$username] = $cleanRoles;
        }

        $saved = (bool)file_put_contents($rolesFile, json_encode($roles, JSON_PRETTY_PRINT));
        if ($saved) {
            self::syncWithFrameworkRBAC($username, $cleanRoles);
        }

        return $saved;
    }

    /**
     * Save multiple project role assignments for a user.
     */
    public static function saveUserProjectRoles(string $username, array $projectRoles): bool
    {
        $username = trim($username);
        if (empty($username)) {
            return false;
        }

        $allProjects = self::getAllProjectList();
        foreach ($allProjects as $pId => $pInfo) {
            if (array_key_exists($pId, $projectRoles)) {
                self::assignUserProjectRole($username, $pId, $projectRoles[$pId]);
            }
        }

        return true;
    }

    /**
     * Retrieve all users and projects currently assigned to a given role.
     */
    public static function getRoleAssignments(string $roleId): array
    {
        $assignments = [];
        $allProjects = self::getAllProjectList();

        if ($roleId === 'admin') {
            foreach (self::getGlobalAdmins() as $admin) {
                $assignments[] = [
                    'username' => $admin,
                    'project_id' => '*',
                    'project_title' => 'Global Super Admin (All Projects)',
                    'is_global' => true
                ];
            }
        }

        foreach ($allProjects as $pId => $pInfo) {
            $issuesDir = self::resolveIssuesDir($pId);
            if ($issuesDir && file_exists($issuesDir . '/roles.json')) {
                $roles = json_decode(file_get_contents($issuesDir . '/roles.json'), true) ?: [];
                foreach ($roles as $user => $assignedRole) {
                    $userRoles = self::normalizeRoles($assignedRole);
                    if (in_array($roleId, $userRoles, true)) {
                        $assignments[] = [
                            'username' => $user,
                            'project_id' => $pId,
                            'project_title' => $pInfo['title'],
                            'is_global' => false
                        ];
                    }
                }
            }
        }

        return $assignments;
    }

    /**
     * Optional synchronization with SPP Framework's central DB RBAC (spp_roles, spp_userroles).
     * If SPPAuth and DB tables exist, mirrors project roles into framework userroles pivot.
     */
    public static function syncWithFrameworkRBAC(string $username, array $roles): void
    {
        if (!class_exists('\SPPMod\SPPDB\SPPDB')) {
            return;
        }

        try {
            $db = new \SPPMod\SPPDB\SPPDB();
            $usersTable = \SPPMod\SPPDB\SPPDB::sppTable('users');
            $userRolesTable = \SPPMod\SPPDB\SPPDB::sppTable('userroles');
            $rolesTable = \SPPMod\SPPDB\SPPDB::sppTable('roles');

            if (!$db->tableExists('users') || !$db->tableExists('userroles') || !$db->tableExists('roles')) {
                return;
            }

            $uRows = $db->execute_query("SELECT id FROM {$usersTable} WHERE username = ?", [$username]);
            if (empty($uRows)) {
                return;
            }
            $userId = (int)$uRows[0]['id'];

            foreach ($roles as $rSlug) {
                $rRows = $db->execute_query("SELECT id FROM {$rolesTable} WHERE name = ? OR name = ?", [$rSlug, ucfirst($rSlug)]);
                if (!empty($rRows)) {
                    $roleId = (int)$rRows[0]['id'];
                    $exists = $db->execute_query("SELECT count(*) as c FROM {$userRolesTable} WHERE userid=? AND roleid=?", [$userId, $roleId]);
                    if ((int)($exists[0]['c'] ?? 0) === 0) {
                        $db->insertValues('userroles', ['userid' => $userId, 'roleid' => $roleId]);
                    }
                }
            }
        } catch (\Throwable $e) {
            // Silently continue if database is unavailable or not using SQL
        }
    }

    /**
     * Generate a signed, time-stable preview token for unreleased drafts and scheduled documents.
     */
    public static function generatePreviewToken(string $projectId, string $type, string $slug): string
    {
        $salt = \SPP\SPPConfig::get('app.key') ?? 'sppdocs_default_preview_secret_key';
        return hash_hmac('sha256', "preview:{$projectId}:{$type}:{$slug}", $salt);
    }

    /**
     * Verify whether a preview token is valid for a given document.
     */
    public static function verifyPreviewToken(string $projectId, string $type, string $slug, ?string $token): bool
    {
        if (empty($token)) {
            return false;
        }
        $expected = self::generatePreviewToken($projectId, $type, $slug);
        return hash_equals($expected, $token);
    }
}
