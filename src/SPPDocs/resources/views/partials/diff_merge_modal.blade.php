{{-- 3-Way Diff Merge Conflict Modal Partial --}}
<div id="sppdocs-diff-modal" class="sppdocs-cmd-backdrop" style="display: none;">
    <div class="sppdocs-cmd-modal sppdocs-diff-modal-card" onclick="event.stopPropagation()">
        <div class="sppdocs-diff-header">
            <div class="sppdocs-diff-title-row">
                <span class="sppdocs-diff-icon">⚠️</span>
                <div>
                    <h3 class="sppdocs-diff-title">Concurrent Edit Detected</h3>
                    <p class="sppdocs-diff-desc">Another collaborator saved changes to this document while you were editing.</p>
                </div>
            </div>
            <button type="button" class="sppdocs-diff-close" onclick="closeDiffModal()">&times;</button>
        </div>

        <div class="sppdocs-diff-content">
            <div class="sppdocs-diff-summary" id="sppdocs-diff-summary">
                Analyzing differences between your local edits and the remote version...
            </div>

            <div class="sppdocs-diff-preview-box">
                <textarea id="sppdocs-diff-merged-preview" class="sppdocs-diff-textarea" readonly></textarea>
            </div>
        </div>

        <div class="sppdocs-diff-actions">
            <button type="button" class="sppdocs-diff-btn secondary" onclick="acceptServerVersion()">
                📥 Overwrite with Server Version
            </button>
            <button type="button" class="sppdocs-diff-btn warning" onclick="forceMyVersion()">
                ⚡ Force Save My Local Edits
            </button>
            <button type="button" class="sppdocs-diff-btn primary" id="sppdocs-btn-apply-merge" onclick="applyMergedVersion()">
                ✨ Auto-Merge Changes into Editor
            </button>
        </div>
    </div>
</div>
