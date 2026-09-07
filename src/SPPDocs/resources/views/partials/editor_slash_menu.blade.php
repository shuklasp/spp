{{-- Floating Slash Command Menu Partial (Zero Inline CSS) --}}
<div id="sppdocs-slash-menu" class="sppdocs-slash-menu" style="display: none;">
    <div class="sppdocs-slash-header">
        <span>Insert Component</span>
        <kbd>ESC to cancel</kbd>
    </div>
    <div class="sppdocs-slash-list" id="sppdocs-slash-list">
        <div class="sppdocs-slash-item" data-cmd="table" onclick="insertSlashSnippet('table')">
            <span class="sppdocs-slash-icon">📊</span>
            <div class="sppdocs-slash-info">
                <span class="sppdocs-slash-title">Table</span>
                <span class="sppdocs-slash-desc">Insert a markdown grid table with headers</span>
            </div>
            <span class="sppdocs-slash-cmd">/table</span>
        </div>

        <div class="sppdocs-slash-item" data-cmd="api" onclick="insertSlashSnippet('api')">
            <span class="sppdocs-slash-icon">⚡</span>
            <div class="sppdocs-slash-info">
                <span class="sppdocs-slash-title">API Playground</span>
                <span class="sppdocs-slash-desc">Interactive REST endpoint runner with headers and response</span>
            </div>
            <span class="sppdocs-slash-cmd">/api</span>
        </div>

        <div class="sppdocs-slash-item" data-cmd="tabs" onclick="insertSlashSnippet('tabs')">
            <span class="sppdocs-slash-icon">📑</span>
            <div class="sppdocs-slash-info">
                <span class="sppdocs-slash-title">Multi-Code Tabs</span>
                <span class="sppdocs-slash-desc">Tabbed code blocks for PHP, JS, Python, cURL</span>
            </div>
            <span class="sppdocs-slash-cmd">/tabs</span>
        </div>

        <div class="sppdocs-slash-item" data-cmd="alert" onclick="insertSlashSnippet('alert')">
            <span class="sppdocs-slash-icon">💡</span>
            <div class="sppdocs-slash-info">
                <span class="sppdocs-slash-title">Callout Alert</span>
                <span class="sppdocs-slash-desc">Note, Tip, Important, Warning, or Caution alert</span>
            </div>
            <span class="sppdocs-slash-cmd">/alert</span>
        </div>

        <div class="sppdocs-slash-item" data-cmd="code" onclick="insertSlashSnippet('code')">
            <span class="sppdocs-slash-icon">💻</span>
            <div class="sppdocs-slash-info">
                <span class="sppdocs-slash-title">Code Block</span>
                <span class="sppdocs-slash-desc">Fenced syntax-highlighted code with copy button</span>
            </div>
            <span class="sppdocs-slash-cmd">/code</span>
        </div>

        <div class="sppdocs-slash-item" data-cmd="mermaid" onclick="insertSlashSnippet('mermaid')">
            <span class="sppdocs-slash-icon">📐</span>
            <div class="sppdocs-slash-info">
                <span class="sppdocs-slash-title">Mermaid Diagram</span>
                <span class="sppdocs-slash-desc">Flowchart, sequence, or class diagram</span>
            </div>
            <span class="sppdocs-slash-cmd">/mermaid</span>
        </div>

        <div class="sppdocs-slash-item" data-cmd="snippet" onclick="insertSlashSnippet('snippet')">
            <span class="sppdocs-slash-icon">🧩</span>
            <div class="sppdocs-slash-info">
                <span class="sppdocs-slash-title">Global Snippet</span>
                <span class="sppdocs-slash-desc">Embed reusable single-source markdown fragment</span>
            </div>
            <span class="sppdocs-slash-cmd">/snippet</span>
        </div>
    </div>
</div>
