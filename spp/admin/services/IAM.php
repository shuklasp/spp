<?php
/**
 * IAM Management Service Group for SPP Admin
 */

function live_IAM_ListUsers($la, $params) {
    $db = new \SPPMod\SPPDB\SPPDB();
    $users = $db->execute_query('SELECT id, username, email, status FROM ' . \SPPMod\SPPDB\SPPDB::sppTable('users'));
    $la->setData([
        'sources' => [[
            'label' => $db->getConnectionSummary(),
            'type' => 'database',
            'items' => $users
        ]]
    ]);
}

function live_IAM_ListRoles($la, $params) {
    $db = new \SPPMod\SPPDB\SPPDB();
    $roles = $db->execute_query('SELECT id, role_name, description FROM ' . \SPPMod\SPPDB\SPPDB::sppTable('roles'));
    $la->setData([
        'sources' => [[
            'label' => $db->getConnectionSummary(),
            'type' => 'database',
            'items' => $roles
        ]]
    ]);
}

function live_IAM_ListRights($la, $params) {
    $db = new \SPPMod\SPPDB\SPPDB();
    $rights = $db->execute_query('SELECT id, name, description FROM ' . \SPPMod\SPPDB\SPPDB::sppTable('rights'));
    $la->setData([
        'sources' => [[
            'label' => $db->getConnectionSummary(),
            'type' => 'database',
            'items' => $rights
        ]]
    ]);
}

function live_IAM_ListRBAC($la, $params) {
    $path = SPP_BASE_DIR . '/etc/rbac.yml';
    if (!file_exists($path)) {
        return $la->setData(['sources' => []]);
    }
    $config = \Symfony\Component\Yaml\Yaml::parseFile($path);
    $la->setData([
        'sources' => [[
            'label' => 'etc/rbac.yml',
            'type' => 'yaml',
            'items' => $config['roles'] ?? []
        ]]
    ]);
}

function live_IAM_ListEntityAssignments($la, $params) {
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
    
    $la->setData(array_values($grouped));
}

function live_IAM_GetDetails($la, $params) {
    $type = $params['type'] ?? '';
    $id = $params['id'] ?? null;
    if (!$id) return $la->setStatus('error')->notify("ID required.");

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

    $la->setData($data);
}

function live_IAM_SearchEntities($la, $params) {
    $type = $params['type'] ?? '';
    $q = $params['q'] ?? '';
    if (empty($q)) return $la->setData(['results' => []]);

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
            $results[] = ['id' => $r['id'], 'name' => $r['label'], 'entity' => 'SPPMod\\SPPGroup\\SPPGroup', 'score' => 1.0];
        }
    }

    // 2. Natural search fallback
    if (empty($results)) {
        $results = \SPPMod\SPPEntity\SPPEntity::searchNatural($q);
    }
    
    // 3. Manual broad search fallback
    if (empty($results)) {
        $entities = \SPPMod\SPPEntity\SPPEntity::listAvailableEntities();
        foreach ($entities as $name => $meta) {
            try {
                $class = "App\\Default\\Entities\\" . ucfirst($name);
                if (!class_exists($class)) $class = $name; 
                if (!class_exists($class)) continue;
                
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
            } catch (\Exception $e) { continue; }
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
        } catch (\Exception $e) {}
    }
    
    $la->setData(['results' => $results]);
}

function live_IAM_AssignRole($la, $params) {
    $targetClass = $params['target_class'] ?? '';
    $targetId = $params['target_id'] ?? '';
    $roleIds = (array)($params['role_id'] ?? []);

    if (!$targetClass || !$targetId || empty($roleIds)) {
        return $la->setStatus('error')->notify("Missing parameters.");
    }

    $db = new \SPPMod\SPPDB\SPPDB();
    foreach ($roleIds as $roleId) {
        $db->execute_query('REPLACE INTO ' . \SPPMod\SPPDB\SPPDB::sppTable('entity_roles') . ' (target_class, target_id, role_id) VALUES (?, ?, ?)', [$targetClass, $targetId, $roleId]);
    }
    $la->notify("Roles assigned.");
}

function live_IAM_RemoveRole($la, $params) {
    $targetClass = $params['target_class'] ?? '';
    $targetId = $params['target_id'] ?? '';
    $roleId = $params['role_id'] ?? '';

    $db = new \SPPMod\SPPDB\SPPDB();
    $db->execute_query('DELETE FROM ' . \SPPMod\SPPDB\SPPDB::sppTable('entity_roles') . ' WHERE target_class=? AND target_id=? AND role_id=?', [$targetClass, $targetId, $roleId]);
    $la->notify("Assignment removed.");
}

function live_IAM_AssignRight($la, $params) {
    $roleId = $params['role_id'] ?? '';
    $rightId = $params['right_id'] ?? '';

    $db = new \SPPMod\SPPDB\SPPDB();
    $db->execute_query('REPLACE INTO ' . \SPPMod\SPPDB\SPPDB::sppTable('roleright') . ' (roleid, rightid) VALUES (?, ?)', [$roleId, $rightId]);
    $la->notify("Right granted.");
}

function live_IAM_RemoveRight($la, $params) {
    $roleId = $params['role_id'] ?? '';
    $rightId = $params['right_id'] ?? '';

    $db = new \SPPMod\SPPDB\SPPDB();
    $db->execute_query('DELETE FROM ' . \SPPMod\SPPDB\SPPDB::sppTable('roleright') . ' WHERE roleid=? AND rightid=?', [$roleId, $rightId]);
    $la->notify("Right revoked.");
}

function live_IAM_ToggleUserStatus($la, $params) {
    $id = $params['id'] ?? '';
    $status = $params['status'] ?? 'active';

    $db = new \SPPMod\SPPDB\SPPDB();
    $db->execute_query('UPDATE ' . \SPPMod\SPPDB\SPPDB::sppTable('users') . ' SET status=? WHERE id=?', [$status, $id]);
    $la->notify("User status updated to $status.");
}

function live_IAM_GetFormHTML($la, $params) {
    $form = $params['form'] ?? '';
    $id = $params['id'] ?? null;
    
    // Simple mock forms for now
    $html = '';
    if ($form === 'user_edit') {
        $username = ''; $email = ''; $status = 'active';
        if ($id) {
            $db = new \SPPMod\SPPDB\SPPDB();
            $row = $db->execute_query('SELECT * FROM ' . \SPPMod\SPPDB\SPPDB::sppTable('users') . ' WHERE id=?', [$id]);
            if ($row) {
                $username = $row[0]['username'];
                $email = $row[0]['email'];
                $status = $row[0]['status'];
            }
        }
        $html = "
            <div class='form-group'>
                <label>Username</label>
                <input type='text' name='username' class='spp-element' value='$username' required>
            </div>
            <div class='form-group'>
                <label>Email</label>
                <input type='email' name='email' class='spp-element' value='$email' required>
            </div>
            <div class='form-group'>
                <label>Status</label>
                <select name='status' class='spp-element'>
                    <option value='active' " . ($status === 'active' ? 'selected' : '') . ">Active</option>
                    <option value='inactive' " . ($status === 'inactive' ? 'selected' : '') . ">Inactive</option>
                </select>
            </div>
            " . (!$id ? "
            <div class='form-group'>
                <label>Password</label>
                <input type='password' name='password' class='spp-element' required>
            </div>" : "");
    } else if ($form === 'role_edit') {
        $name = ''; $desc = '';
        if ($id) {
            $db = new \SPPMod\SPPDB\SPPDB();
            $row = $db->execute_query('SELECT * FROM ' . \SPPMod\SPPDB\SPPDB::sppTable('roles') . ' WHERE id=?', [$id]);
            if ($row) {
                $name = $row[0]['role_name'];
                $desc = $row[0]['description'];
            }
        }
        $html = "
            <div class='form-group'>
                <label>Role Name</label>
                <input type='text' name='role_name' class='spp-element' value='$name' required>
            </div>
            <div class='form-group'>
                <label>Description</label>
                <textarea name='description' class='spp-element'>$desc</textarea>
            </div>";
    } else if ($form === 'right_edit') {
        $name = ''; $desc = '';
        if ($id) {
            $db = new \SPPMod\SPPDB\SPPDB();
            $row = $db->execute_query('SELECT * FROM ' . \SPPMod\SPPDB\SPPDB::sppTable('rights') . ' WHERE id=?', [$id]);
            if ($row) {
                $name = $row[0]['name'];
                $desc = $row[0]['description'];
            }
        }
        $html = "
            <div class='form-group'>
                <label>Right Name</label>
                <input type='text' name='name' class='spp-element' value='$name' required>
            </div>
            <div class='form-group'>
                <label>Description</label>
                <textarea name='description' class='spp-element'>$desc</textarea>
            </div>";
    }

    $la->setData(['html' => $html]);
}

function live_IAM_SaveUser($la, $params) {
    $id = $params['id'] ?? null;
    $username = $params['username'] ?? '';
    $email = $params['email'] ?? '';
    $status = $params['status'] ?? 'active';
    $password = $params['password'] ?? null;

    $db = new \SPPMod\SPPDB\SPPDB();
    if ($id) {
        $db->execute_query('UPDATE ' . \SPPMod\SPPDB\SPPDB::sppTable('users') . ' SET username=?, email=?, status=? WHERE id=?', [$username, $email, $status, $id]);
        $la->notify("User updated.");
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $db->execute_query('INSERT INTO ' . \SPPMod\SPPDB\SPPDB::sppTable('users') . ' (username, email, password_hash, status, created_at) VALUES (?, ?, ?, ?, NOW())', [$username, $email, $hash, $status]);
        $la->notify("User created.");
    }
}

function live_IAM_SaveRole($la, $params) {
    $id = $params['id'] ?? null;
    $name = $params['role_name'] ?? '';
    $desc = $params['description'] ?? '';

    $db = new \SPPMod\SPPDB\SPPDB();
    if ($id) {
        $db->execute_query('UPDATE ' . \SPPMod\SPPDB\SPPDB::sppTable('roles') . ' SET role_name=?, description=? WHERE id=?', [$name, $desc, $id]);
        $la->notify("Role updated.");
    } else {
        $db->execute_query('INSERT INTO ' . \SPPMod\SPPDB\SPPDB::sppTable('roles') . ' (role_name, description) VALUES (?, ?)', [$name, $desc]);
        $la->notify("Role created.");
    }
}

function live_IAM_SaveRight($la, $params) {
    $id = $params['id'] ?? null;
    $name = $params['name'] ?? '';
    $desc = $params['description'] ?? '';

    $db = new \SPPMod\SPPDB\SPPDB();
    if ($id) {
        $db->execute_query('UPDATE ' . \SPPMod\SPPDB\SPPDB::sppTable('rights') . ' SET name=?, description=? WHERE id=?', [$name, $desc, $id]);
        $la->notify("Right updated.");
    } else {
        $db->execute_query('INSERT INTO ' . \SPPMod\SPPDB\SPPDB::sppTable('rights') . ' (name, description) VALUES (?, ?)', [$name, $desc]);
        $la->notify("Right created.");
    }
}

function live_IAM_SaveModernRole($la, $params) {
    $slug = $params['slug'] ?? '';
    $permissions = $params['permissions'] ?? '';
    if (empty($slug)) return $la->setStatus('error')->notify("Slug required.");

    $permList = array_filter(array_map('trim', explode("\n", $permissions)));
    
    $path = SPP_BASE_DIR . '/etc/rbac.yml';
    $config = file_exists($path) ? \Symfony\Component\Yaml\Yaml::parseFile($path) : ['roles' => []];
    
    $config['roles'][$slug] = [
        'permissions' => $permList
    ];
    
    file_put_contents($path, \Symfony\Component\Yaml\Yaml::dump($config, 4, 2));
    $la->notify("Modern role '$slug' saved.");
}

// --- Group Management ---

function live_IAM_ListGroups($la, $params) {
    $appname = $params['appname'] ?? 'default';
    $rawGroups = \SPPMod\SPPGroup\SPPGroupLoader::listAllGroups($appname);
    
    $sources = [];
    
    foreach ($rawGroups as $g) {
        $group = new \SPPMod\SPPGroup\SPPGroup();
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
    
    $la->setData(['sources' => array_values($sources)]);
}

function live_IAM_ListGroupMembers($la, $params) {
    $groupId = $params['group_id'] ?? null;
    if (!$groupId) return $la->setStatus('error')->notify("Group ID required.");

    $group = new \SPPMod\SPPGroup\SPPGroup();
    $group->load($groupId);
    if (!$group->id) return $la->setStatus('error')->notify("Group not found.");

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
        } catch (\Exception $e) {}

        $formatted[] = [
            'id' => $m['entity']->id,
            'name' => $name,
            'entity' => get_class($m['entity']),
            'role' => $m['role'],
            'direct' => $m['direct'],
            'inherited_via' => $m['inherited_via'] ?? null
        ];
    }

    $la->setData(['members' => $formatted]);
}

function live_IAM_AddGroupMember($la, $params) {
    $groupId = $params['group_id'] ?? '';
    $memberClass = $params['member_entity'] ?? '';
    $memberId = $params['member_id'] ?? '';
    $role = $params['role'] ?? 'member';

    try {
        \SPPMod\SPPGroup\SPPGroup::addMemberToGroup($groupId, $memberClass, $memberId, $role);
        $la->notify("Member added to group.", "success");
    } catch (\Exception $e) {
        $la->setStatus('error')->notify($e->getMessage());
    }
}

function live_IAM_RemoveGroupMember($la, $params) {
    $groupId = $params['group_id'] ?? '';
    $memberClass = $params['member_entity'] ?? '';
    $memberId = $params['member_id'] ?? '';

    try {
        \SPPMod\SPPGroup\SPPGroup::removeMemberFromGroup($groupId, $memberClass, $memberId);
        $la->notify("Member removed from group.");
    } catch (\Exception $e) {
        $la->setStatus('error')->notify($e->getMessage());
    }
}

function live_IAM_SaveGroup($la, $params) {
    try {
        \SPPMod\SPPGroup\SPPGroup::saveGroupInfo($params);
        $la->notify("Group saved successfully.", "success");
    } catch (\Exception $e) {
        $la->setStatus('error')->notify($e->getMessage());
    }
}

function live_IAM_DeleteGroup($la, $params) {
    $id = $params['id'] ?? null;
    if (!$id) return $la->setStatus('error')->notify("Group ID required.");

    $group = new \SPPMod\SPPGroup\SPPGroup();
    $group->load($id);
    if ($group->id) {
        $group->delete();
        $la->notify("Group '$id' deleted.");
    } else {
        $la->setStatus('error')->notify("Group not found.");
    }
}

