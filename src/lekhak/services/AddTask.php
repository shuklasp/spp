<?php
/**
 * AddTask Service (Zero-Boilerplate Example)
 * 
 * Automatically discovered by api.php via the new 'Pure PHP/HTML' mode.
 * Exposes $la (LiveAction) and $params ($_REQUEST) automatically.
 */

$task = $params['task_name'] ?? 'New Task';

// Just echo the HTML for the new item. 
// SPPUX will pick this up and append it to the target specified in HTML.
?>
<div class="task-item" style="padding: 10px; background: rgba(255,255,255,0.05); margin-bottom: 5px; border-radius: 4px; display: flex; justify-content: space-between; align-items: center;">
    <span><?php echo htmlspecialchars($task); ?></span>
    <button class="btn secondary-btn" style="padding: 2px 8px; font-size: 0.7rem;" data-live-remove data-live-target="closest .task-item">✕</button>
</div>

<?php
// We can still use LiveAction methods alongside the echoed HTML
$la->notify("Task added: " . $task);
