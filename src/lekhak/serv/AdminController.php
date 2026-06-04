<?php
namespace App\Lekhak\Serv;

use SPPMod\SPPView\ViewPage;
use SPPMod\SPPView\ViewFormBuilder;
use App\Lekhak\Entities\ContentType;
use App\Lekhak\Entities\Field;
use SPPMod\Lekhak\Core\LekhakNode;

/**
 * Class AdminController
 * Handles the administrative interface for Lekhak CMS.
 */
class AdminController
{
    public function __construct()
    {
        // Start session if not started
        if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') {
            @session_start();
        }

        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $q = $_GET['q'] ?? '';
        // Skip login/logout routes from authentication check
        if (str_ends_with($uri, 'login') || str_ends_with($uri, 'logout') || str_ends_with($q, 'login') || str_ends_with($q, 'logout')) {
            return;
        }

        // Ensure user is authenticated using Lekhak's own session bucket
        if (!\SPPMod\SPPAuth\SPPAuth::check()) {
            $appRoot = $this->getAppRoot();
            header("Location: " . $appRoot . "/admin/login");
            exit;
        }
    }

    public function login()
    {
        $error = '';

        // Ensure spp_users table and admin account exist
        $this->ensureAdminUser();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            if (!empty($username) && !empty($password)) {
                try {
                    // Try SPPUser-based verification first
                    if (\SPPMod\SPPAuth\SPPUser::verifyUserPassword($username, $password)) {
                        $user = new \SPPMod\SPPAuth\SPPUser($username);
                        \SPPMod\SPPAuth\SPPAuth::guard('web')->login($user);
                        header("Location: " . $this->getAppRoot() . "/admin");
                        exit;
                    } else {
                        $error = 'Invalid username or password.';
                    }
                } catch (\Exception $e) {
                    // Fallback: if tables/user are missing, allow admin/admin
                    if ($username === 'admin' && $password === 'admin') {
                        $user = (object)['id' => 'admin', 'username' => 'admin', 'email' => 'admin@lekhak.local'];
                        \SPPMod\SPPAuth\SPPAuth::guard('web')->login($user);
                        header("Location: " . $this->getAppRoot() . "/admin");
                        exit;
                    }
                    $error = 'Authentication error: ' . $e->getMessage();
                }
            } else {
                $error = 'Please enter both username and password.';
            }
        }

        return $this->render("admin/login", [
            'title' => 'Lekhak Workspace Login',
            'error' => $error
        ]);
    }

    /**
     * Ensures the spp_users table contains at least one admin user.
     */
    protected function ensureAdminUser(): void
    {
        try {
            if (!\SPPMod\SPPAuth\SPPUser::userExists('admin')) {
                \SPPMod\SPPAuth\SPPUser::saveUserInfo([
                    'username' => 'admin',
                    'email' => 'admin@lekhak.local',
                    'password' => 'admin',
                    'status' => 'active'
                ]);
            }
        } catch (\Exception $e) {
            // Silently fail — the fallback login will still work
        }
    }

    public function logout()
    {
        \SPPMod\SPPAuth\SPPAuth::logout();
        header("Location: " . $this->getAppRoot() . "/admin/login");
        exit;
    }

    protected function getAppRoot()
    {
        return \SPP\App::getBaseUrl('lekhak');
    }

    protected function render($view, $data = [])
    {
        $renderer = \SPPMod\Lekhak\Core\Renderer::getInstance();
        
        $appRoot = $this->getAppRoot();

        $data['web_root'] = defined('APP_BASE_URI') ? APP_BASE_URI : '';
        $data['app_root'] = $appRoot;
        $data['admin_root'] = $appRoot . '/admin';
        
        // Strip admin/ prefix if it exists to match theme structure
        if (strpos($view, 'admin/') === 0) {
            $view = substr($view, 6);
        }
        
        $data['view_name'] = $view;
        
        // Enforce premium admin theme for all forms
        \SPPMod\SPPView\SPPViewForm_Element::setTheme('glass_admin');
        


        // Automatically flush stale Blade cache files in development/admin context to guarantee real-time style/theme compilation
        $cacheDir = (defined('SPP_BASE_DIR') ? SPP_BASE_DIR : dirname(__DIR__, 4)) . '/var/cache/lekhak/blade';
        if (is_dir($cacheDir)) {
            foreach (glob($cacheDir . '/*.bladec') as $cf) {
                @unlink($cf);
            }
        }

        // Ensure common data is present
        $data['view_mode'] = $data['view_mode'] ?? 'full';
        
        return $renderer->render($view, $data);
    }

    public function dashboard()
    {
        ContentType::ensureSchema();
        \SPPMod\Lekhak\Core\LandingPage::ensureSchema();

        // Advanced Stats
        $stats = [
            'nodes' => LekhakNode::count(),
            'types' => ContentType::count(),
            'landing' => \SPPMod\Lekhak\Core\LandingPage::count(),
            'users' => \SPPMod\SPPAuth\SPPUser::count()
        ];
        
        $recent_nodes = LekhakNode::find_all([], 'created DESC', 10);
        
        return $this->render("dashboard", [
            'title' => 'Admin Overview',
            'subtitle' => 'Live metrics from your Lekhak instance.',
            'stats' => $stats,
            'recent_nodes' => $recent_nodes
        ]);
    }



    public function manageContentTypes()
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        $table = \SPPMod\SPPDB\SPPDB::sppTable('content_types');
        if (!$db->tableExists($table)) {
            ContentType::install();
        }
        $types = ContentType::find_all();
        
        return $this->render("content-types", [
            'title' => 'Content Structures',
            'subtitle' => 'Define the architecture of your data.',
            'types' => $types
        ]);
    }

    public function editContentType($name = null)
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        $table = \SPPMod\SPPDB\SPPDB::sppTable('content_types');
        if (!$db->tableExists($table)) {
            ContentType::install();
        }
        $type = new ContentType();
        if ($name && $name !== 'add') {
            $existing = ContentType::find_one(['name' => $name]);
            if ($existing) {
                $type = $existing;
            }
        }
        
        $builder = new ViewFormBuilder($type);
        $form = $builder->build();
        $form->setAttribute('action', $this->getAppRoot() . "/admin/structure/types/" . ($name ?? 'add'));
        
        if ($form->isSubmitted() && $form->isValid()) {
            $form->save();
            header("Location: " . $this->getAppRoot() . "/admin/structure/types");
            exit;
        }

        return $this->render("content-type-form", [
            'title' => $name ? 'Edit Content Type' : 'Create Content Type',
            'subtitle' => $name ? "Modifying {$name} structure." : 'Initialize a new data bundle.',
            'form' => $form,
            'name' => $name
        ]);
    }

    public function manageFields($bundle)
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        $table = \SPPMod\SPPDB\SPPDB::sppTable('fields');
        if (!$db->tableExists($table)) {
            Field::install();
        }
        $fields = $db->execute_query("SELECT * FROM {$table} WHERE bundle = ?", [$bundle]);
        
        return $this->render("fields", [
            'title' => 'Manage Fields',
            'subtitle' => "Configuration for: {$bundle}",
            'bundle' => $bundle,
            'fields' => $fields
        ]);
    }

    public function addField($bundle)
    {
        $field = new Field();
        $field->bundle = $bundle;
        
        $builder = new ViewFormBuilder($field);
        $form = $builder->build();
        $form->setAttribute('action', $this->getAppRoot() . "/admin/structure/types/{$bundle}/fields/add");
        
        if ($form->isSubmitted() && $form->isValid()) {
            $form->save();
            // Trigger schema evolution
            $orchestrator = new \SPPMod\Lekhak\Core\StorageOrchestrator();
            $orchestrator->ensureSchema(LekhakNode::class);
            
            header("Location: " . $this->getAppRoot() . "/admin/structure/types/{$bundle}/fields");
            exit;
        }

        return $this->render("field-form", [
            'title' => 'Add New Field',
            'subtitle' => "Expanding {$bundle} metadata.",
            'form' => $form,
            'bundle' => $bundle,
            'field_name' => 'add'
        ]);
    }

    public function manageContent()
    {
        $nodes = LekhakNode::find_all([], 'created DESC');
        return $this->render("content", [
            'title' => 'Content Management',
            'subtitle' => 'All nodes in the system.',
            'nodes' => $nodes
        ]);
    }

    public function manageMedia()
    {
        header("Location: " . $this->getAppRoot() . "/admin#media");
        exit;
    }

    public function settings()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $settings = [
                'theme' => $_POST['theme'] ?? 'dark',
                'enable_edge_consensus' => isset($_POST['enable_edge_consensus']) ? true : false,
                'enable_merkle_trace' => isset($_POST['enable_merkle_trace']) ? true : false,
                'speculative_offline' => isset($_POST['speculative_offline']) ? true : false,
                'strict_sri' => isset($_POST['strict_sri']) ? true : false,
                'ambient_scale' => $_POST['ambient_scale'] ?? '1.05',
                'primary_accent' => $_POST['primary_accent'] ?? '#f97316',
                'lekhni_default_mode' => $_POST['lekhni_default_mode'] ?? 'document',
                'lekhni_ai_copilot' => isset($_POST['lekhni_ai_copilot']) ? true : false,
                'lekhni_code_language' => $_POST['lekhni_code_language'] ?? 'html',
                'designer_grid_snap' => isset($_POST['designer_grid_snap']) ? true : false,
                'designer_autosave' => (int)($_POST['designer_autosave'] ?? 300),
                'structure_strict_schema' => isset($_POST['structure_strict_schema']) ? true : false,
                'content_default_status' => $_POST['content_default_status'] ?? 'draft',
                'content_revision_tracking' => isset($_POST['content_revision_tracking']) ? true : false,
            ];

            foreach ($settings as $key => $val) {
                \SPP\SPPConfig::set('app:' . $key, $val);
            }

            header("Location: " . $this->getAppRoot() . "/admin/settings?saved=1");
            exit;
        }

        return $this->render("settings", [
            'title' => 'System Settings',
            'subtitle' => 'Global configuration for Lekhak CMS.',
            'settings' => [
                'theme' => \SPP\SPPConfig::get('app:theme', 'dark'),
                'enable_edge_consensus' => \SPP\SPPConfig::get('app:enable_edge_consensus', true),
                'enable_merkle_trace' => \SPP\SPPConfig::get('app:enable_merkle_trace', false),
                'speculative_offline' => \SPP\SPPConfig::get('app:speculative_offline', true),
                'strict_sri' => \SPP\SPPConfig::get('app:strict_sri', false),
                'ambient_scale' => \SPP\SPPConfig::get('app:ambient_scale', '1.05'),
                'primary_accent' => \SPP\SPPConfig::get('app:primary_accent', '#f97316'),
                'lekhni_default_mode' => \SPP\SPPConfig::get('app:lekhni_default_mode', 'document'),
                'lekhni_ai_copilot' => \SPP\SPPConfig::get('app:lekhni_ai_copilot', true),
                'lekhni_code_language' => \SPP\SPPConfig::get('app:lekhni_code_language', 'html'),
                'designer_grid_snap' => \SPP\SPPConfig::get('app:designer_grid_snap', true),
                'designer_autosave' => \SPP\SPPConfig::get('app:designer_autosave', 300),
                'structure_strict_schema' => \SPP\SPPConfig::get('app:structure_strict_schema', false),
                'content_default_status' => \SPP\SPPConfig::get('app:content_default_status', 'draft'),
                'content_revision_tracking' => \SPP\SPPConfig::get('app:content_revision_tracking', true),
            ]
        ]);
    }

    public function manageUsers()
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        $usersTable = \SPPMod\SPPDB\SPPDB::sppTable('spp_users');
        $rolesTable = \SPPMod\SPPDB\SPPDB::sppTable('spp_roles');
        $userRolesTable = \SPPMod\SPPDB\SPPDB::sppTable('spp_userroles');

        // Populate default roles if empty
        $roles = $db->execute_query("SELECT * FROM {$rolesTable}");
        if (empty($roles)) {
            $db->execute_query("INSERT INTO {$rolesTable} (role_name, description) VALUES (?, ?)", ['Administrator', 'Full system access and configurations.']);
            $db->execute_query("INSERT INTO {$rolesTable} (role_name, description) VALUES (?, ?)", ['Editor', 'Manage content, fields, and taxonomies.']);
            $db->execute_query("INSERT INTO {$rolesTable} (role_name, description) VALUES (?, ?)", ['Contributor', 'Create and edit own content.']);
            $roles = $db->execute_query("SELECT * FROM {$rolesTable}");
        }

        // Handle POST requests for user actions (Create user, Update role/status, Delete user)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';
            
            if ($action === 'create' || $action === 'update') {
                $uid = (int)($_POST['user_id'] ?? 0);
                $username = trim($_POST['username'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $password = $_POST['password'] ?? '';
                $role_id = (int)($_POST['role_id'] ?? 0);
                $status = $_POST['status'] ?? 'active';
                
                if ($action === 'create' && !empty($username) && !empty($password)) {
                    \SPPMod\SPPAuth\SPPUser::saveUserInfo([
                        'username' => $username,
                        'email' => $email,
                        'password' => $password,
                        'status' => $status
                    ]);
                    // Get newly created user ID
                    $newUserId = \SPPMod\SPPAuth\SPPUser::find_one(['username' => $username])->id;
                    $db->execute_query("INSERT INTO {$userRolesTable} (userid, roleid) VALUES (?, ?)", [$newUserId, $role_id]);
                    $_SESSION['flash_success'] = "User '{$username}' created successfully.";
                } elseif ($action === 'update' && $uid > 0) {
                    $updateData = ['id' => $uid, 'email' => $email, 'status' => $status];
                    if (!empty($password)) {
                        $updateData['password'] = $password;
                    }
                    // Fetch existing username since saveUserInfo uses it for upsert
                    $existingUser = $db->execute_query("SELECT username FROM {$usersTable} WHERE id = ?", [$uid]);
                    if ($existingUser) {
                        $updateData['username'] = $existingUser[0]['username'];
                        \SPPMod\SPPAuth\SPPUser::saveUserInfo($updateData);
                        $db->execute_query("DELETE FROM {$userRolesTable} WHERE userid = ?", [$uid]);
                        $db->execute_query("INSERT INTO {$userRolesTable} (userid, roleid) VALUES (?, ?)", [$uid, $role_id]);
                        $_SESSION['flash_success'] = "User updated successfully.";
                    }
                }
            } elseif ($action === 'delete') {
                $uid = (int)($_POST['user_id'] ?? 0);
                if ($uid > 0) {
                    $db->execute_query("DELETE FROM {$usersTable} WHERE id = ?", [$uid]);
                    $db->execute_query("DELETE FROM {$userRolesTable} WHERE userid = ?", [$uid]);
                    $_SESSION['flash_success'] = "User deleted successfully.";
                }
            }
            
            header("Location: " . $this->getAppRoot() . "/admin/users");
            exit;
        }

        // Fetch users and link their role_name
        $users = $db->execute_query("SELECT u.*, r.role_name, r.id as role_id FROM {$usersTable} u LEFT JOIN {$userRolesTable} ur ON u.id = ur.userid LEFT JOIN {$rolesTable} r ON ur.roleid = r.id");
        if (empty($users)) {
            // Seed a default admin if table was empty
            $adminRole = $db->execute_query("SELECT id FROM {$rolesTable} WHERE role_name = 'Administrator' LIMIT 1")[0]['id'] ?? 1;
            \SPPMod\SPPAuth\SPPUser::saveUserInfo([
                'username' => 'admin',
                'email' => 'admin@lekhak.local',
                'password' => 'admin',
                'status' => 'active'
            ]);
            $adminId = \SPPMod\SPPAuth\SPPUser::find_one(['username' => 'admin'])->id;
            $db->execute_query("INSERT INTO {$userRolesTable} (userid, roleid) VALUES (?, ?)", [$adminId, $adminRole]);
            
            $users = $db->execute_query("SELECT u.*, r.role_name, r.id as role_id FROM {$usersTable} u LEFT JOIN {$userRolesTable} ur ON u.id = ur.userid LEFT JOIN {$rolesTable} r ON ur.roleid = r.id");
        }

        return $this->render("users", [
            'title' => 'User Management',
            'subtitle' => 'Control access, roles, and status of Lekhak administrators.',
            'users' => $users,
            'roles' => $roles
        ]);
    }

    public function manageRoles()
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        $rolesTable = \SPPMod\SPPDB\SPPDB::sppTable('spp_roles');
        $rightsTable = \SPPMod\SPPDB\SPPDB::sppTable('spp_rights');
        $roleRightTable = \SPPMod\SPPDB\SPPDB::sppTable('spp_roleright');

        // Populate default rights if empty
        $rights = $db->execute_query("SELECT * FROM {$rightsTable}");
        if (empty($rights)) {
            $defaultRights = [
                ['name' => 'administer site configuration', 'description' => 'Change global settings, themes, and configuration.'],
                ['name' => 'administer content types', 'description' => 'Manage structural schema, fields, and views.'],
                ['name' => 'administer users', 'description' => 'Manage user accounts and roles.'],
                ['name' => 'bypass node access', 'description' => 'View, edit, and delete all content regardless of author.'],
                ['name' => 'create content', 'description' => 'Create new content nodes.'],
                ['name' => 'edit own content', 'description' => 'Edit content created by the user.'],
                ['name' => 'delete own content', 'description' => 'Delete content created by the user.']
            ];
            foreach ($defaultRights as $right) {
                $db->execute_query("INSERT INTO {$rightsTable} (name, description) VALUES (?, ?)", [$right['name'], $right['description']]);
            }
            $rights = $db->execute_query("SELECT * FROM {$rightsTable}");
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';

            if ($action === 'create_role') {
                $role_name = trim($_POST['role_name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                if (!empty($role_name)) {
                    $db->execute_query("INSERT INTO {$rolesTable} (role_name, description) VALUES (?, ?)", [$role_name, $description]);
                    $_SESSION['flash_success'] = "Role '{$role_name}' created successfully.";
                }
            } elseif ($action === 'update_rights') {
                $role_id = (int)($_POST['role_id'] ?? 0);
                $assigned_rights = $_POST['rights'] ?? [];
                
                if ($role_id > 0) {
                    $db->execute_query("DELETE FROM {$roleRightTable} WHERE roleid = ?", [$role_id]);
                    foreach ($assigned_rights as $right_id) {
                        $db->execute_query("INSERT INTO {$roleRightTable} (roleid, rightid) VALUES (?, ?)", [$role_id, (int)$right_id]);
                    }
                    $_SESSION['flash_success'] = "Permissions updated successfully.";
                }
            } elseif ($action === 'delete_role') {
                $role_id = (int)($_POST['role_id'] ?? 0);
                if ($role_id > 0 && $role_id !== 1) { // Prevent deleting default Administrator
                    $db->execute_query("DELETE FROM {$rolesTable} WHERE id = ?", [$role_id]);
                    $db->execute_query("DELETE FROM {$roleRightTable} WHERE roleid = ?", [$role_id]);
                    $_SESSION['flash_success'] = "Role deleted successfully.";
                }
            }
            
            header("Location: " . $this->getAppRoot() . "/admin/roles");
            exit;
        }

        $roles = $db->execute_query("SELECT * FROM {$rolesTable}");
        $roleRightsMap = [];
        $roleRightsRaw = $db->execute_query("SELECT roleid, rightid FROM {$roleRightTable}");
        if ($roleRightsRaw) {
            foreach ($roleRightsRaw as $rr) {
                $roleRightsMap[$rr['roleid']][] = $rr['rightid'];
            }
        }

        return $this->render("roles", [
            'title' => 'Roles & Permissions',
            'subtitle' => 'Define access control levels across the CMS.',
            'roles' => $roles,
            'rights' => $rights,
            'roleRightsMap' => $roleRightsMap
        ]);
    }

    public function manageFieldsGlobal()
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        $table = \SPPMod\SPPDB\SPPDB::sppTable('fields');
        if (!$db->tableExists($table)) {
            Field::install();
        }
        $fields = $db->execute_query("SELECT * FROM {$table} ORDER BY bundle ASC");
        
        return $this->render("fields", [
            'title' => 'Global Fields',
            'subtitle' => 'Viewing all fields across all content types.',
            'fields' => $fields,
            'bundle' => 'all'
        ]);
    }
}
