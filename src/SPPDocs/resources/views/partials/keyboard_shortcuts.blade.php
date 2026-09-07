{{-- Global Keyboard Shortcuts & Cheatsheet Modal (Zero Inline CSS) --}}
<div id="sppdocs-shortcuts-help-modal" class="sppdocs-shortcuts-backdrop" onclick="if(event.target===this) this.style.display='none'">
    <div class="sppdocs-shortcuts-modal" onclick="event.stopPropagation()">
        <div class="sppdocs-shortcuts-header">
            <h3 class="sppdocs-shortcuts-title">
                <span>⌨️</span> Keyboard Shortcuts
            </h3>
            <button type="button" class="sppdocs-shortcuts-close" onclick="document.getElementById('sppdocs-shortcuts-help-modal').style.display='none'">&times;</button>
        </div>

        <div class="sppdocs-shortcuts-list">
            <div class="sppdocs-shortcuts-section-title">Navigation & General</div>
            
            <div class="sppdocs-shortcuts-row">
                <span class="sppdocs-shortcuts-label">Open Command & AI Palette</span>
                <span class="sppdocs-shortcuts-keys"><kbd>⌘/Ctrl</kbd> + <kbd>K</kbd></span>
            </div>

            <div class="sppdocs-shortcuts-row">
                <span class="sppdocs-shortcuts-label">Focus Global Search</span>
                <span class="sppdocs-shortcuts-keys"><kbd>/</kbd></span>
            </div>

            <div class="sppdocs-shortcuts-row">
                <span class="sppdocs-shortcuts-label">Toggle Shortcuts Guide</span>
                <span class="sppdocs-shortcuts-keys"><kbd>?</kbd></span>
            </div>

            <div class="sppdocs-shortcuts-row">
                <span class="sppdocs-shortcuts-label">Close Modal or Palette</span>
                <span class="sppdocs-shortcuts-keys"><kbd>ESC</kbd></span>
            </div>

            <div class="sppdocs-shortcuts-section-title">Issues & Project Management</div>

            <div class="sppdocs-shortcuts-row">
                <span class="sppdocs-shortcuts-label">Create New Issue</span>
                <span class="sppdocs-shortcuts-keys"><kbd>C</kbd></span>
            </div>

            <div class="sppdocs-shortcuts-row">
                <span class="sppdocs-shortcuts-label">Move Selection Down / Up</span>
                <span class="sppdocs-shortcuts-keys"><kbd>J</kbd> / <kbd>K</kbd></span>
            </div>

            <div class="sppdocs-shortcuts-row">
                <span class="sppdocs-shortcuts-label">Select / Deselect Row Checkbox</span>
                <span class="sppdocs-shortcuts-keys"><kbd>X</kbd></span>
            </div>

            <div class="sppdocs-shortcuts-row">
                <span class="sppdocs-shortcuts-label">Open Selected Issue / Result</span>
                <span class="sppdocs-shortcuts-keys"><kbd>Enter</kbd> or <kbd>O</kbd></span>
            </div>

            <div class="sppdocs-shortcuts-row">
                <span class="sppdocs-shortcuts-label">Edit Current Page</span>
                <span class="sppdocs-shortcuts-keys"><kbd>E</kbd></span>
            </div>

            <div class="sppdocs-shortcuts-section-title">Quick Jumps</div>

            <div class="sppdocs-shortcuts-row">
                <span class="sppdocs-shortcuts-label">Jump to Kanban Board</span>
                <span class="sppdocs-shortcuts-keys"><kbd>B</kbd></span>
            </div>

            <div class="sppdocs-shortcuts-row">
                <span class="sppdocs-shortcuts-label">Jump to Issues List</span>
                <span class="sppdocs-shortcuts-keys"><kbd>L</kbd></span>
            </div>

            <div class="sppdocs-shortcuts-row">
                <span class="sppdocs-shortcuts-label">Jump to Milestones</span>
                <span class="sppdocs-shortcuts-keys"><kbd>M</kbd></span>
            </div>

            <div class="sppdocs-shortcuts-row">
                <span class="sppdocs-shortcuts-label">Jump to Documentation</span>
                <span class="sppdocs-shortcuts-keys"><kbd>D</kbd></span>
            </div>

            <div class="sppdocs-shortcuts-row">
                <span class="sppdocs-shortcuts-label">Jump to Project Administration</span>
                <span class="sppdocs-shortcuts-keys"><kbd>A</kbd></span>
            </div>
        </div>
    </div>
</div>

<script src="{{ \SPP\App::getBaseUrl() }}/src/SPPDocs/resources/js/sppdocs-shortcuts.js"></script>
