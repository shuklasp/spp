<?php
$badgeStyle = function($status) {
    if ($status === 'completed') return 'background: rgba(16, 185, 129, 0.2); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3);';
    if ($status === 'running') return 'background: rgba(96, 165, 250, 0.2); color: #60a5fa; border: 1px solid rgba(96, 165, 250, 0.3); animation: pulse 2s infinite;';
    return 'background: rgba(156, 163, 175, 0.2); color: #9ca3af; border: 1px solid rgba(156, 163, 175, 0.3);';
};
?>
<div style="display: flex; flex-direction: column; gap: 1rem;">
    <!-- Extract -->
    <div style="display: flex; align-items: center; justify-content: space-between; padding: 1rem; border-radius: 8px; background: rgba(0,0,0,0.2); border: 1px solid var(--surface-border);">
        <div>
            <strong style="color: var(--text-color); font-size: 1.1rem;">1. ExtractDataJob</strong>
            <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">Extracts raw data from external API</div>
        </div>
        <div style="padding: 0.25rem 0.75rem; border-radius: 4px; font-weight: bold; font-size: 0.85rem; text-transform: uppercase; <?= $badgeStyle($state['ExtractData'] ?? 'pending') ?>">
            <?= htmlspecialchars($state['ExtractData'] ?? 'pending') ?>
        </div>
    </div>

    <!-- Transform -->
    <div style="display: flex; align-items: center; justify-content: space-between; padding: 1rem; border-radius: 8px; background: rgba(0,0,0,0.2); border: 1px solid var(--surface-border); margin-left: 2rem; position: relative;">
        <!-- Arrow connector -->
        <div style="position: absolute; left: -1rem; top: -1rem; width: 2px; height: 1.5rem; background: var(--surface-border);"></div>
        <div style="position: absolute; left: -1rem; top: 0.5rem; width: 1rem; height: 2px; background: var(--surface-border);"></div>
        
        <div>
            <strong style="color: var(--text-color); font-size: 1.1rem;">2. TransformDataJob</strong>
            <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">Cleans and normalizes the data (Depends on: ExtractDataJob)</div>
        </div>
        <div style="padding: 0.25rem 0.75rem; border-radius: 4px; font-weight: bold; font-size: 0.85rem; text-transform: uppercase; <?= $badgeStyle($state['TransformData'] ?? 'pending') ?>">
            <?= htmlspecialchars($state['TransformData'] ?? 'pending') ?>
        </div>
    </div>

    <!-- Load -->
    <div style="display: flex; align-items: center; justify-content: space-between; padding: 1rem; border-radius: 8px; background: rgba(0,0,0,0.2); border: 1px solid var(--surface-border); margin-left: 4rem; position: relative;">
        <!-- Arrow connector -->
        <div style="position: absolute; left: -1rem; top: -1rem; width: 2px; height: 1.5rem; background: var(--surface-border);"></div>
        <div style="position: absolute; left: -1rem; top: 0.5rem; width: 1rem; height: 2px; background: var(--surface-border);"></div>
        
        <div>
            <strong style="color: var(--text-color); font-size: 1.1rem;">3. LoadDataJob</strong>
            <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">Inserts normalized data into Database (Depends on: TransformDataJob)</div>
        </div>
        <div style="padding: 0.25rem 0.75rem; border-radius: 4px; font-weight: bold; font-size: 0.85rem; text-transform: uppercase; <?= $badgeStyle($state['LoadData'] ?? 'pending') ?>">
            <?= htmlspecialchars($state['LoadData'] ?? 'pending') ?>
        </div>
    </div>

    <!-- Notify -->
    <div style="display: flex; align-items: center; justify-content: space-between; padding: 1rem; border-radius: 8px; background: rgba(0,0,0,0.2); border: 1px solid var(--surface-border); margin-left: 4rem; position: relative;">
        <!-- Arrow connector -->
        <div style="position: absolute; left: -1rem; top: -2.5rem; width: 2px; height: 3rem; background: var(--surface-border);"></div>
        <div style="position: absolute; left: -1rem; top: 0.5rem; width: 1rem; height: 2px; background: var(--surface-border);"></div>
        
        <div>
            <strong style="color: var(--text-color); font-size: 1.1rem;">4. NotifyAdminJob</strong>
            <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">Sends completion email (Depends on: TransformDataJob)</div>
        </div>
        <div style="padding: 0.25rem 0.75rem; border-radius: 4px; font-weight: bold; font-size: 0.85rem; text-transform: uppercase; <?= $badgeStyle($state['NotifyAdmin'] ?? 'pending') ?>">
            <?= htmlspecialchars($state['NotifyAdmin'] ?? 'pending') ?>
        </div>
    </div>
</div>

<style>
@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}
</style>
