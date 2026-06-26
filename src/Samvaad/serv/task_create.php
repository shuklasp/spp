<?php
/**
 * ============================================================================
 * Service: task.create — Samvaad
 * ============================================================================
 *
 * HOW THIS WORKS:
 * This script is registered in etc/services.yml and callable from JavaScript:
 *   const result = await spp_admin.callAppService('task.create', { taskTitle: 'My Task' });
 *
 * INPUT: POST data (available via $_POST)
 * OUTPUT: Set $response array — the framework auto-encodes it as JSON
 *
 * HOW TO CREATE A NEW SERVICE:
 * 1. Create a PHP file in serv/ (e.g., serv/my_service.php)
 * 2. Register in etc/services.yml:
 *    services:
 *      - name: "my.service"
 *        script: "src/Samvaad/serv/my_service.php"
 * 3. Call from JS: await spp_admin.callAppService('my.service', {param: 'value'})
 * ============================================================================
 */

$taskTitle = trim($_POST['taskTitle'] ?? 'Untitled Task');
$taskPriority = trim($_POST['taskPriority'] ?? 'Normal');

// Simulate processing — replace with real database logic:
// $db = new \SPPMod\SPPDB\SPPDB();
// $db->execute_query("INSERT INTO Samvaad_items (name, status) VALUES (?, 'active')", [$taskTitle]);

$response = [
    'status' => 'success',
    'message' => 'Task \'' . $taskTitle . '\' created with priority ' . $taskPriority . '!',
    'data' => [
        'id' => rand(1000, 9999),
        'title' => $taskTitle,
        'priority' => $taskPriority,
        'created_at' => date('Y-m-d H:i:s'),
    ]
];