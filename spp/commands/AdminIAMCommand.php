<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class AdminIAMCommand extends Command
{
    protected string $name = 'admin:iam';
    protected string $description = 'Manage Admin IAM operations. Usage: admin:iam <action> [--payload=...] [--json]';

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

    private function handleListusers(array $payload, array $args): void {

    $db = new \SPPMod\SPPDB\SPPDB();
    $users = $db->execute_query('SELECT id, username, email, status FROM ' . \SPPMod\SPPDB\SPPDB::sppTable('users'));
    $this->json([
        'sources' => [
            [
                'label' => $db->getConnectionSummary(),
                'type' => 'database',
                'items' => $users
            ]
        ]
    ], $args); return;

    }

    private function handleListroles(array $payload, array $args): void {

    $db = new \SPPMod\SPPDB\SPPDB();
    $roles = $db->execute_query('SELECT id, role_name, description FROM ' . \SPPMod\SPPDB\SPPDB::sppTable('roles'));
    $this->json([
        'sources' => [
            [
                'label' => $db->getConnectionSummary(),
                'type' => 'database',
                'items' => $roles
            ]
        ]
    ], $args); return;

    }

    private function handleListrights(array $payload, array $args): void {

    $db = new \SPPMod\SPPDB\SPPDB();
    $rights = $db->execute_query('SELECT id, name, description FROM ' . \SPPMod\SPPDB\SPPDB::sppTable('rights'));
    $this->json([
        'sources' => [
            [
                'label' => $db->getConnectionSummary(),
                'type' => 'database',
                'items' => $rights
            ]
        ]
    ], $args); return;

    }

    private function handleListrbac(array $payload, array $args): void {

    $path = SPP_BASE_DIR . '/etc/rbac.yml';
    if (!file_exists($path)) {
        $this->json(['sources' => []], $args); return;
        return;
    }
    $config = \Symfony\Component\Yaml\Yaml::parseFile($path);
    $this->json([
        'sources' => [
            [
                'label' => 'etc/rbac.yml',
                'type' => 'yaml',
                'items' => $config['roles'] ?? []
            ]
        ]
    ], $args); return;

    }

    private function handleListabac(array $payload, array $args): void {

    $db = new \SPPMod\SPPDB\SPPDB();
    $policies = $db->execute_query('SELECT id, permission, condition_logic, status FROM ' . \SPPMod\SPPDB\SPPDB::sppTable('abac_policies') . ' ORDER BY id DESC');
    $this->json([
        'sources' => [
            [
                'label' => $db->getConnectionSummary(),
                'type' => 'database',
                'items' => $policies
            ]
        ]
    ], $args); return;

    }

    private function handleSaveabac(array $payload, array $args): void {

    $db = new \SPPMod\SPPDB\SPPDB();
    $id = $payload['id'] ?? null;
    $permission = $payload['permission'] ?? '';
    $logic = $payload['condition_logic'] ?? '';
    $status = $payload['status'] ?? 'active';

    if (empty($permission) || empty($logic)) {
        $this->json(['success' => false, 'error' => "Permission and Condition Logic are required."], $args); return;
        return;
    }

    $table = \SPPMod\SPPDB\SPPDB::sppTable('abac_policies');

    if ($id) {
        $db->execute_query("UPDATE $table SET permission = ?, condition_logic = ?, status = ? WHERE id = ?", [$permission, $logic, $status, $id]);
        $la->setStatus('success')->notify('ABAC Policy updated.');
    } else {
        $db->execute_query("INSERT INTO $table (permission, condition_logic, status) VALUES (?, ?, ?)", [$permission, $logic, $status]);
        $la->setStatus('success')->notify('ABAC Policy created.');
    }

    }

    private function handleDeleteabac(array $payload, array $args): void {

    $db = new \SPPMod\SPPDB\SPPDB();
    $id = $payload['id'] ?? null;
    if (!$id)
        $this->json(['success' => false, 'error' => "Policy ID required."], $args); return;
        return;

    $table = \SPPMod\SPPDB\SPPDB::sppTable('abac_policies');
    $db->execute_query("DELETE FROM $table WHERE id = ?", [$id]);
    $la->setStatus('success')->notify('Policy deleted.');

    }

    private function handleListoauthclients(array $payload, array $args): void {

    $db = new \SPPMod\SPPDB\SPPDB();
    $clients = $db->execute_query('SELECT id, name, redirect_uri FROM ' . \SPPMod\SPPDB\SPPDB::sppTable('oauth_clients') . ' ORDER BY name ASC');
    $this->json([
        'sources' => [
            [
                'label' => 'OAuth Clients',
                'type' => 'database',
                'items' => $clients
            ]
        ]
    ], $args); return;

    }

    private function handleSaveoauthclient(array $payload, array $args): void {

    $db = new \SPPMod\SPPDB\SPPDB();
    $id = $payload['id'] ?? null;
    $name = $payload['name'] ?? '';
    $redirect_uri = $payload['redirect_uri'] ?? '';

    if (empty($id) || empty($name) || empty($redirect_uri)) {
        $this->json(['success' => false, 'error' => "ID, Name, and Redirect URI are required."], $args); return;
        return;
    }

    $table = \SPPMod\SPPDB\SPPDB::sppTable('oauth_clients');

    // Check if client exists
    $existing = $db->execute_query("SELECT id FROM $table WHERE id = ?", [$id]);

    if (!empty($existing)) {
        // Update
        $db->execute_query("UPDATE $table SET name = ?, redirect_uri = ? WHERE id = ?", [$name, $redirect_uri, $id]);
        $la->setStatus('success')->notify('OAuth Client updated.');
    } else {
        // Create
        $client_secret = bin2hex(random_bytes(32));
        $db->execute_query("INSERT INTO $table (id, secret, name, redirect_uri) VALUES (?, ?, ?, ?)", [$id, $client_secret, $name, $redirect_uri]);
        $la->setStatus('success')->setData(['client_secret' => $client_secret])->notify('OAuth Client created.');
    }

    }

    private function handleDeleteoauthclient(array $payload, array $args): void {

    $db = new \SPPMod\SPPDB\SPPDB();
    $id = $payload['id'] ?? null;
    if (!$id)
        $this->json(['success' => false, 'error' => "Client ID required."], $args); return;
        return;

    $table = \SPPMod\SPPDB\SPPDB::sppTable('oauth_clients');
    $db->execute_query("DELETE FROM $table WHERE id = ?", [$id]);
    $la->setStatus('success')->notify('Client deleted.');

    }

    private function handleListentityassignments(array $payload, array $args): void {

    $db = new \SPPMod\SPPDB\SPPDB();
    $sql = 'SELECT er.target_class, er.target_id, er.role_id, r.role_name 
            FROM ' . \SPPMod\SPPDB\SPPDB::sppTable('entity_roles') . ' er
            JOIN ' . \SPPMod\SPPDB\SPPDB::sppTable('roles') . ' r ON er.role_id = r.id';
    $raw = $db->execute_query($sql);

    // Group by target
    $grouped = [];
    foreach ($raw as $row) {
        $key = $row['target_class'] . ':' . $row['target_id'];
        if (!isset($grouped[$key])) {
            $grouped[$key] = [
                'target_class' => $row['target_class'],
                'target_id' => $row['target_id'],
                'roles' => []
            ];
        }
        $grouped[$key]['roles'][] = ['id' => $row['role_id'], 'name' => $row['role_name']];
    }

    $this->json(array_values($grouped), $args); return;

    }

    private function handleGetdetails(array $payload, array $args): void {

    $type = $payload['type'] ?? '';
    $id = $payload['id'] ?? null;
    if (!$id)
        $this->json(['success' => false, 'error' => "ID required."], $args); return;
        return;

    $db = new \SPPMod\SPPDB\SPPDB();
    $data = ['assigned_ids' => [], 'available' => []];

    if ($type === 'users') {
        $data['available'] = $db->execute_query('SELECT id, role_name FROM ' . \SPPMod\SPPDB\SPPDB::sppTable('roles'));
        $assigned = $db->execute_query('SELECT role_id FROM ' . \SPPMod\SPPDB\SPPDB::sppTable('entity_roles') . ' WHERE target_class = ? AND target_id = ?', ['SPPMod\SPPAuth\SPPUser', $id]);
        $data['assigned_ids'] = array_map('intval', array_column($assigned, 'role_id'));
    } else if ($type === 'roles') {
        $data['available'] = $db->execute_query('SELECT id, name, description FROM ' . \SPPMod\SPPDB\SPPDB::sppTable('rights'));
        $assigned = $db->execute_query('SELECT rightid FROM ' . \SPPMod\SPPDB\SPPDB::sppTable('roleright') . ' WHERE roleid = ?', [$id]);
        $data['assigned_ids'] = array_map('intval', array_column($assigned, 'rightid'));
    }

    $this->json($data, $args); return;

    }

    private function handleSearchentities(array $payload, array $args): void {

    $type = $payload['type'] ?? '';
    $q = $payload['q'] ?? '';
    if (empty($q))
        $this->json(['results' => []], $args); return;
        return;

    $db = new \SPPMod\SPPDB\SPPDB();
    $results = [];

    // 1. Specialized quick lookups for known types
    if (str_contains($type, 'SPPUser')) {
        $sql = 'SELECT id, username as label FROM ' . \SPPMod\SPPDB\SPPDB::sppTable('users') . ' WHERE username LIKE ? OR email LIKE ? LIMIT 10';
        $rows = $db->execute_query($sql, ["%$q%", "%$q%"]);
        foreach ($rows as $r) {
            $results[] = ['id' => $r['username'], 'name' => $r['label'], 'entity' => 'SPPMod\\SPPAuth\\SPPUser', 'score' => 1.0];
        }
    } else if (str_contains($type, 'SPPGroup')) {
        $sql = 'SELECT id, name as label FROM ' . \SPPMod\SPPDB\SPPDB::sppTable('sppgroups') . ' WHERE name LIKE ? LIMIT 10';
        $rows = $db->execute_query($sql, ["%$q%"]);
        foreach ($rows as $r) {
            $results[] = ['id' => $r['id'], 'name' => $r['label'], 'entity' => 'SPPMod\\SPPAuth\\SPPGroup', 'score' => 1.0];
        }
    }

    // 2. Natural search fallback
    if (empty($results)) {
        $results = \SPPMod\SPPDB\SPPEntity::searchNatural($q);
    }

    // 3. Manual broad search fallback
    if (empty($results)) {
        $entities = \SPPMod\SPPDB\SPPEntity::listAvailableEntities();
        foreach ($entities as $name => $meta) {
            try {
                $class = "App\\Default\\Entities\\" . ucfirst($name);
                if (!class_exists($class))
                    $class = $name;
                if (!class_exists($class))
                    continue;

                $inst = new $class();
                $table = $inst->getTable();

                $sql = "SELECT * FROM $table WHERE name LIKE ? OR username LIKE ? LIMIT 5";
                $dbRes = $db->execute_query($sql, ["%$q%", "%$q%"]);

                foreach ($dbRes as $row) {
                    $results[] = [
                        'id' => $row['id'] ?? $row['username'] ?? '?',
                        'name' => $row['name'] ?? ($row['username'] ?? $name),
                        'entity' => $class,
                        'score' => 0.8
                    ];
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        // Also search SPPUser explicitly as it might not be in "AvailableEntities" (it's core)
        try {
            $userTable = \SPPMod\SPPDB\SPPDB::sppTable('users');
            $users = $db->execute_query("SELECT * FROM $userTable WHERE username LIKE ? LIMIT 5", ["%$q%"]);
            foreach ($users as $u) {
                $results[] = [
                    'id' => $u['username'],
                    'name' => $u['username'],
                    'entity' => 'SPPMod\\SPPAuth\\SPPUser',
                    'score' => 0.9
                ];
            }
        } catch (\Exception $e) {
        }
    }

    $this->json(['results' => $results], $args); return;

    }

    private function handleAssignrole(array $payload, array $args): void {

    $targetClass = $payload['target_class'] ?? '';
    $targetId = $payload['target_id'] ?? '';
    $roleIds = (array) ($payload['role_id'] ?? []);

    if (!$targetClass || !$targetId || empty($roleIds)) {
        $this->json(['success' => false, 'error' => "Missing parameters."], $args); return;
        return;
    }

    $db = new \SPPMod\SPPDB\SPPDB();
    foreach ($roleIds as $roleId) {
        $db->execute_query('REPLACE INTO ' . \SPPMod\SPPDB\SPPDB::sppTable('entity_roles') . ' (target_class, target_id, role_id) VALUES (?, ?, ?)', [$targetClass, $targetId, $roleId]);
    }
    $this->json(['success' => true, 'message' => "Roles assigned."], $args); return;

    }

    private function handleRemoverole(array $payload, array $args): void {

    $targetClass = $payload['target_class'] ?? '';
    $targetId = $payload['target_id'] ?? '';
    $roleId = $payload['role_id'] ?? '';

    $db = new \SPPMod\SPPDB\SPPDB();
    $db->execute_query('DELETE FROM ' . \SPPMod\SPPDB\SPPDB::sppTable('entity_roles') . ' WHERE target_class=? AND target_id=? AND role_id=?', [$targetClass, $targetId, $roleId]);
    $this->json(['success' => true, 'message' => "Assignment removed."], $args); return;

    }

    private function handleAssignright(array $payload, array $args): void {

    $roleId = $payload['role_id'] ?? '';
    $rightId = $payload['right_id'] ?? '';

    $db = new \SPPMod\SPPDB\SPPDB();
    $db->execute_query('REPLACE INTO ' . \SPPMod\SPPDB\SPPDB::sppTable('roleright') . ' (roleid, rightid) VALUES (?, ?)', [$roleId, $rightId]);
    $this->json(['success' => true, 'message' => "Right granted."], $args); return;

    }

    private function handleRemoveright(array $payload, array $args): void {

    $roleId = $payload['role_id'] ?? '';
    $rightId = $payload['right_id'] ?? '';

    $db = new \SPPMod\SPPDB\SPPDB();
    $db->execute_query('DELETE FROM ' . \SPPMod\SPPDB\SPPDB::sppTable('roleright') . ' WHERE roleid=? AND rightid=?', [$roleId, $rightId]);
    $this->json(['success' => true, 'message' => "Right revoked."], $args); return;

    }

    private function handleToggleuserstatus(array $payload, array $args): void {

    $id = $payload['id'] ?? '';
    $status = $payload['status'] ?? 'active';

    $db = new \SPPMod\SPPDB\SPPDB();
    $db->execute_query('UPDATE ' . \SPPMod\SPPDB\SPPDB::sppTable('users') . ' SET status=? WHERE id=?', [$status, $id]);
    $this->json(['success' => true, 'message' => "User status updated to $status."], $args); return;

    }

    private function handleGetformhtml(array $payload, array $args): void {

    $form = $payload['form'] ?? '';
    $id = $payload['id'] ?? null;

    // Simple mock forms for now
    $html = '';
    if ($form === 'user_edit') {
        $username = '';
        $email = '';
        $status = 'active';
        if ($id) {
            $db = new \SPPMod\SPPDB\SPPDB();
            $row = $db->execute_query('SELECT * FROM ' . \SPPMod\SPPDB\SPPDB::sppTable('users') . ' WHERE id=?', [$id]);
            if ($row) {
                $username = $row[0]['username'];
                $email = $row[0]['email'];
                $status = $row[0]['status'];
            }
        }
        ob_start(); require SPP_APP_DIR . '/spp/admin/partials/generated/adminiam_1.php'; $html = ob_get_clean();
    } else if ($form === 'role_edit') {
        $name = '';
        $desc = '';
        if ($id) {
            $db = new \SPPMod\SPPDB\SPPDB();
            $row = $db->execute_query('SELECT * FROM ' . \SPPMod\SPPDB\SPPDB::sppTable('roles') . ' WHERE id=?', [$id]);
            if ($row) {
                $name = $row[0]['role_name'];
                $desc = $row[0]['description'];
            }
        }
        ob_start(); require SPP_APP_DIR . '/spp/admin/partials/generated/adminiam_2.php'; $html = ob_get_clean();
    } else if ($form === 'right_edit') {
        $name = '';
        $desc = '';
        if ($id) {
            $db = new \SPPMod\SPPDB\SPPDB();
            $row = $db->execute_query('SELECT * FROM ' . \SPPMod\SPPDB\SPPDB::sppTable('rights') . ' WHERE id=?', [$id]);
            if ($row) {
                $name = $row[0]['name'];
                $desc = $row[0]['description'];
            }
        }
        ob_start(); require SPP_APP_DIR . '/spp/admin/partials/generated/adminiam_3.php'; $html = ob_get_clean();
    } else if ($form === 'step_editor') {
        ob_start(); require SPP_APP_DIR . '/spp/admin/partials/generated/adminiam_4.php'; $html = ob_get_clean();
    } else if ($form === 'field_editor') {
        ob_start(); require SPP_APP_DIR . '/spp/admin/partials/generated/adminiam_5.php'; $html = ob_get_clean();
    }

    $this->json(['html' => $html], $args); return;

    }

    private function handleSaveuser(array $payload, array $args): void {

    $id = $payload['id'] ?? null;
    $username = $payload['username'] ?? '';
    $email = $payload['email'] ?? '';
    $status = $payload['status'] ?? 'active';
    $password = $payload['password'] ?? null;

    $db = new \SPPMod\SPPDB\SPPDB();
    if ($id) {
        $db->execute_query('UPDATE ' . \SPPMod\SPPDB\SPPDB::sppTable('users') . ' SET username=?, email=?, status=? WHERE id=?', [$username, $email, $status, $id]);
        $this->json(['success' => true, 'message' => "User updated."], $args); return;
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $db->execute_query('INSERT INTO ' . \SPPMod\SPPDB\SPPDB::sppTable('users') . ' (username, email, password_hash, status, created_at) VALUES (?, ?, ?, ?, NOW())', [$username, $email, $hash, $status]);
        $this->json(['success' => true, 'message' => "User created."], $args); return;
    }

    }

    private function handleSaverole(array $payload, array $args): void {

    $id = $payload['id'] ?? null;
    $name = $payload['role_name'] ?? '';
    $desc = $payload['description'] ?? '';

    $db = new \SPPMod\SPPDB\SPPDB();
    if ($id) {
        $db->execute_query('UPDATE ' . \SPPMod\SPPDB\SPPDB::sppTable('roles') . ' SET role_name=?, description=? WHERE id=?', [$name, $desc, $id]);
        $this->json(['success' => true, 'message' => "Role updated."], $args); return;
    } else {
        $db->execute_query('INSERT INTO ' . \SPPMod\SPPDB\SPPDB::sppTable('roles') . ' (role_name, description) VALUES (?, ?)', [$name, $desc]);
        $this->json(['success' => true, 'message' => "Role created."], $args); return;
    }

    }

    private function handleSaveright(array $payload, array $args): void {

    $id = $payload['id'] ?? null;
    $name = $payload['name'] ?? '';
    $desc = $payload['description'] ?? '';

    $db = new \SPPMod\SPPDB\SPPDB();
    if ($id) {
        $db->execute_query('UPDATE ' . \SPPMod\SPPDB\SPPDB::sppTable('rights') . ' SET name=?, description=? WHERE id=?', [$name, $desc, $id]);
        $this->json(['success' => true, 'message' => "Right updated."], $args); return;
    } else {
        $db->execute_query('INSERT INTO ' . \SPPMod\SPPDB\SPPDB::sppTable('rights') . ' (name, description) VALUES (?, ?)', [$name, $desc]);
        $this->json(['success' => true, 'message' => "Right created."], $args); return;
    }

    }

    private function handleSavemodernrole(array $payload, array $args): void {

    $slug = $payload['slug'] ?? '';
    $permissions = $payload['permissions'] ?? '';
    if (empty($slug))
        $this->json(['success' => false, 'error' => "Slug required."], $args); return;
        return;

    $permList = array_filter(array_map('trim', explode("\n", $permissions)));

    $path = SPP_BASE_DIR . '/etc/rbac.yml';
    $config = file_exists($path) ? \Symfony\Component\Yaml\Yaml::parseFile($path) : ['roles' => []];

    $config['roles'][$slug] = [
        'permissions' => $permList
    ];

    file_put_contents($path, \Symfony\Component\Yaml\Yaml::dump($config, 4, 2));
    $this->json(['success' => true, 'message' => "Modern role '$slug' saved."], $args); return;

    }

    private function handleListgroups(array $payload, array $args): void {

    $appname = $payload['appname'] ?? 'default';
    $rawGroups = \SPPMod\SPPAuth\SPPGroupLoader::listAllGroups($appname);

    $sources = [];

    foreach ($rawGroups as $g) {
        $group = new \SPPMod\SPPAuth\SPPGroup();
        $group->load($g['name']);
        if ($group->id) {
            $sourceKey = $g['source'] === 'database' ? $g['db_summary'] : ($g['path'] ?? 'Unknown File');
            if (!isset($sources[$sourceKey])) {
                $sources[$sourceKey] = [
                    'label' => $sourceKey,
                    'type' => $g['source'],
                    'items' => []
                ];
            }

            $sources[$sourceKey]['items'][] = [
                'id' => $group->id,
                'name' => $group->get('name'),
                'description' => $group->get('description'),
                'source' => $group->source
            ];
        }
    }

    $this->json(['sources' => array_values($sources)], $args); return;

    }

    private function handleListgroupmembers(array $payload, array $args): void {

    $groupId = $payload['group_id'] ?? null;
    if (!$groupId)
        $this->json(['success' => false, 'error' => "Group ID required."], $args); return;
        return;

    $group = new \SPPMod\SPPAuth\SPPGroup();
    $group->load($groupId);
    if (!$group->id)
        $this->json(['success' => false, 'error' => "Group not found."], $args); return;
        return;

    $members = $group->getMembers(true); // Recursive
    $formatted = [];
    $db = new \SPPMod\SPPDB\SPPDB();

    foreach ($members as $m) {
        $name = $m['entity']->id;
        try {
            // Try to get name without full load if possible
            if (method_exists($m['entity'], 'get') && ($n = $m['entity']->get('username') ?: $m['entity']->get('name'))) {
                $name = $n;
            } else {
                // Fallback: Quick DB lookup for name/username
                $table = $m['entity']->getTable();
                $idField = 'id'; // Default
                $res = $db->execute_query("SELECT username, name FROM $table WHERE $idField = ? LIMIT 1", [$m['entity']->id]);
                if (!empty($res)) {
                    $name = $res[0]['username'] ?? ($res[0]['name'] ?? $m['entity']->id);
                }
            }
        } catch (\Exception $e) {
        }

        $formatted[] = [
            'id' => $m['entity']->id,
            'name' => $name,
            'entity' => get_class($m['entity']),
            'role' => $m['role'],
            'direct' => $m['direct'],
            'inherited_via' => $m['inherited_via'] ?? null
        ];
    }

    $this->json(['members' => $formatted], $args); return;

    }

    private function handleAddgroupmember(array $payload, array $args): void {

    $groupId = $payload['group_id'] ?? '';
    $memberClass = $payload['member_entity'] ?? '';
    $memberId = $payload['member_id'] ?? '';
    $role = $payload['role'] ?? 'member';

    try {
        \SPPMod\SPPAuth\SPPGroup::addMemberToGroup($groupId, $memberClass, $memberId, $role);
        $this->json(['success' => true, 'message' => "Member added to group.", "success"], $args); return;
    } catch (\Exception $e) {
        $this->json(['success' => false, 'error' => $e->getMessage()], $args); return;
    }

    }

    private function handleRemovegroupmember(array $payload, array $args): void {

    $groupId = $payload['group_id'] ?? '';
    $memberClass = $payload['member_entity'] ?? '';
    $memberId = $payload['member_id'] ?? '';

    try {
        \SPPMod\SPPAuth\SPPGroup::removeMemberFromGroup($groupId, $memberClass, $memberId);
        $this->json(['success' => true, 'message' => "Member removed from group."], $args); return;
    } catch (\Exception $e) {
        $this->json(['success' => false, 'error' => $e->getMessage()], $args); return;
    }

    }

    private function handleSavegroup(array $payload, array $args): void {

    try {
        \SPPMod\SPPAuth\SPPGroup::saveGroupInfo($payload);
        $this->json(['success' => true, 'message' => "Group saved successfully.", "success"], $args); return;
    } catch (\Exception $e) {
        $this->json(['success' => false, 'error' => $e->getMessage()], $args); return;
    }

    }

    private function handleDeletegroup(array $payload, array $args): void {

    $id = $payload['id'] ?? null;
    if (!$id)
        $this->json(['success' => false, 'error' => "Group ID required."], $args); return;
        return;

    $group = new \SPPMod\SPPAuth\SPPGroup();
    $group->load($id);
    if ($group->id) {
        $group->delete();
        $this->json(['success' => true, 'message' => "Group '$id' deleted."], $args); return;
    } else {
        $this->json(['success' => false, 'error' => "Group not found."], $args); return;
    }

    }

    private function handleListapikeys(array $payload, array $args): void {

    try {
        $user = \SPP\Scheduler::getActiveUser();
        if (!$user || !$user->id)
            $this->json(['success' => false, 'error' => "Unauthenticated."], $args); return;
        return;

        $db = new \SPPMod\SPPDB\SPPDB();
        $sql = "SELECT id, name, created_at, expires_at, 
                CASE WHEN expires_at IS NULL OR expires_at > NOW() THEN 1 ELSE 0 END as status
                FROM " . \SPPMod\SPPDB\SPPDB::sppTable('personal_access_tokens') . "
                WHERE userid = ? ORDER BY created_at DESC";
        $tokens = $db->execute_query($sql, [$user->id]);

        // Add pseudo token masks
        foreach ($tokens as &$t) {
            $t['token'] = 'spp_' . substr(md5($t['id'] . $t['created_at']), 0, 8) . '...';
        }

        $this->json($tokens, $args); return;
    } catch (\Exception $e) {
        $this->json(['success' => false, 'error' => "Failed to list API keys: " . $e->getMessage()], $args); return;
    }

    }

    private function handleGenerateapikey(array $payload, array $args): void {

    try {
        $user = \SPP\Scheduler::getActiveUser();
        if (!$user || !$user->id)
            $this->json(['success' => false, 'error' => "Unauthenticated."], $args); return;
        return;

        $name = $payload['name'] ?? 'API Key';
        $token = \SPPMod\SPPAuth\TokenGuard::createToken($user, $name);

        $this->json(['success' => true, 'message' => "API Key generated successfully! Please copy it now, it won't be shown again: $token", "success"], $args); return;
        // Instruct frontend to reload keys
        $la->addInstruction(['action' => 'execute', 'code' => 'app.apiKeys.loadKeys()']);
    } catch (\Exception $e) {
        $this->json(['success' => false, 'error' => "Failed to generate API key: " . $e->getMessage()], $args); return;
    }

    }

    private function handleRevokeapikey(array $payload, array $args): void {

    try {
        $user = \SPP\Scheduler::getActiveUser();
        if (!$user || !$user->id)
            $this->json(['success' => false, 'error' => "Unauthenticated."], $args); return;
        return;

        $id = $payload['id'] ?? null;
        if (!$id)
            $this->json(['success' => false, 'error' => "Token ID required."], $args); return;
        return;

        $db = new \SPPMod\SPPDB\SPPDB();
        $db->execute_query("DELETE FROM " . \SPPMod\SPPDB\SPPDB::sppTable('personal_access_tokens') . " WHERE id = ? AND userid = ?", [$id, $user->id]);

        $this->json(['success' => true, 'message' => "API Key revoked.", "success"], $args); return;
        $la->addInstruction(['action' => 'execute', 'code' => 'app.apiKeys.loadKeys()']);
    } catch (\Exception $e) {
        $this->json(['success' => false, 'error' => "Failed to revoke API key: " . $e->getMessage()], $args); return;
    }

    }

    private function handleGeneratemfasecret(array $payload, array $args): void {

    try {
        $user = \SPP\Scheduler::getActiveUser();
        if (!$user || !$user->id)
            $this->json(['success' => false, 'error' => "Unauthenticated."], $args); return;
        return;

        require_once SPP_MODULES_DIR . '/spp/sppauth/class.mfa.php';

        // Generate new secret
        $secret = \SPPMod\SPPAuth\MFA::generateSecret();

        // Temporarily store in session to prevent DB save until verified
        \SPP\SPPSession::setSessionVar('mfa_setup_secret', $secret);

        // Create an otpauth URI
        $issuer = urlencode('SPP Enterprise');
        $accountName = urlencode($user->username);
        $otpauthUrl = "otpauth://totp/$issuer:$accountName?secret=$secret&issuer=$issuer";

        // Use Google Charts API to generate a QR Code image URL
        $qrCodeUrl = "https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl=" . urlencode($otpauthUrl);

        $this->json([
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
            'manual_code' => trim(chunk_split($secret, 4, ' '))
        ], $args); return;

    } catch (\Exception $e) {
        $this->json(['success' => false, 'error' => "Failed to generate MFA secret: " . $e->getMessage()], $args); return;
    }

    }

    private function handleEnablemfa(array $payload, array $args): void {

    try {
        $user = \SPP\Scheduler::getActiveUser();
        if (!$user || !$user->id)
            $this->json(['success' => false, 'error' => "Unauthenticated."], $args); return;
        return;

        $code = $payload['code'] ?? '';
        if (empty($code))
            $this->json(['success' => false, 'error' => "Please provide the 6-digit verification code."], $args); return;
        return;

        $secret = \SPP\SPPSession::getSessionVar('mfa_setup_secret');
        if (empty($secret))
            $this->json(['success' => false, 'error' => "MFA setup session expired. Please restart the process."], $args); return;
        return;

        require_once SPP_MODULES_DIR . '/spp/sppauth/class.mfa.php';

        if (\SPPMod\SPPAuth\MFA::verifyCode($secret, $code)) {
            $db = new \SPPMod\SPPDB\SPPDB();
            $db->execute_query(
                "UPDATE " . \SPPMod\SPPDB\SPPDB::sppTable('users') . " SET mfa_secret = ?, mfa_enabled = 1 WHERE id = ?",
                [$secret, $user->id]
            );

            \SPP\SPPSession::unsetSessionVar('mfa_setup_secret');
            $this->json(['success' => true, 'message' => "Multi-Factor Authentication is now enabled!", "success"], $args); return;
        } else {
            $this->json(['success' => false, 'error' => "Invalid Authenticator code. Try again."], $args); return;
        }

    } catch (\Exception $e) {
        $this->json(['success' => false, 'error' => "Failed to enable MFA: " . $e->getMessage()], $args); return;
    }

    }

}
