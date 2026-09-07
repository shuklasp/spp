<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit {{ ucfirst($type) }}</title>
    <link rel="stylesheet" href="@url('css/admin.css')">
    <link rel="stylesheet" href="/school1/public/assets/tui-editor/toastui-editor.min.css" />
</head>
<body class="editor-body">
    <div class="editor-nav">
        <div><a href="{{ \SPP\App::getBaseUrl() }}/admin/project/{{ $project_id }}">&larr; Back to {{ $project['title'] }}</a></div>
        <div>Editing {{ ucfirst($type) }}</div>
        @spppartial('partials/collab_presence.blade.php')
    </div>
    
    <input type="hidden" id="last_modified" value="{{ $last_modified ?? 0 }}">
    
    <div class="editor-container">
        <div class="form-row">
            <div class="form-group">
                <label>Filename (without .md)</label>
                <input type="text" id="filename" value="{{ $filename }}" placeholder="e.g. my-new-post" {{ $filename ? 'readonly' : '' }}>
            </div>
            <div class="form-group">
                <label>Title</label>
                <input type="text" id="title" value="{{ $frontmatter['title'] ?? '' }}">
            </div>
            
            @if(isset($schema['fields']))
                @php
                    $currentUser = $_SESSION['sppdocs_user'] ?? 'admin';
                    $isGlobalAdmin = \App\SPPDocs\Services\PermissionManager::isGlobalAdmin($currentUser);
                    $isProjectAdmin = \App\SPPDocs\Services\PermissionManager::isProjectAdmin($project_id, $currentUser);
                    $userRoles = \App\SPPDocs\Services\PermissionManager::getUserRoles($project_id, $currentUser);
                @endphp
                @foreach($schema['fields'] as $key => $field)
                    @if($key === 'title') @continue @endif
                    @php
                        $canView = $isGlobalAdmin || $isProjectAdmin || empty($field['view_roles']) || !empty(array_intersect($userRoles, (array)$field['view_roles']));
                        $canEdit = $isGlobalAdmin || $isProjectAdmin || empty($field['edit_roles']) || !empty(array_intersect($userRoles, (array)$field['edit_roles']));
                    @endphp
                    @if(!$canView) @continue @endif

                    <div class="form-group">
                        <label style="display: flex; justify-content: space-between; align-items: center;">
                            <span>{{ $field['label'] ?? ucfirst($key) }} @if(!empty($field['required']))<span style="color:#ef4444;">*</span>@endif</span>
                            @if(!$canEdit)
                                <span style="font-size: 0.72rem; background: var(--vp-c-bg-mute); color: #dc2626; padding: 0.1rem 0.4rem; border-radius: 4px; border: 1px solid var(--vp-c-divider);">🔒 Restricted</span>
                            @endif
                        </label>
                        @php
                            $fType = $field['type'] ?? 'text';
                            $val = $frontmatter[$key] ?? ($field['default'] ?? '');
                            if (is_array($val)) $val = implode(', ', $val);
                            $disAttr = !$canEdit ? 'disabled' : '';
                        @endphp
                        @if($fType === 'taxonomy')
                            @php
                                $vocabKey = $field['vocab'] ?? 'categories';
                                $vocabTree = \App\SPPDocs\Services\TaxonomyService::getHierarchyTree($project_id, $vocabKey);
                            @endphp
                            <select class="schema-field" data-key="{{ $key }}" {{ $disAttr }}>
                                <option value="">-- Select {{ $field['label'] ?? 'Term' }} --</option>
                                @php
                                    $renderOpts = function($nodes, $d = 0) use (&$renderOpts, $val) {
                                        foreach ($nodes as $n) {
                                            $selected = ((string)$val === (string)$n['slug']) ? 'selected' : '';
                                            $prefix = str_repeat('&nbsp;&nbsp;&nbsp;', $d) . ($d > 0 ? '↳ ' : '');
                                            echo "<option value=\"{$n['slug']}\" {$selected}>{$prefix}{$n['icon']} {$n['name']}</option>";
                                            if (!empty($n['children'])) {
                                                $renderOpts($n['children'], $d + 1);
                                            }
                                        }
                                    };
                                    $renderOpts($vocabTree);
                                @endphp
                            </select>
                        @elseif($fType === 'entity_reference')
                            <input type="text" class="schema-field" data-key="{{ $key }}" value="{{ $val }}" placeholder="Target document slug or path" {{ $disAttr }}>
                        @elseif($fType === 'select' && !empty($field['options']))
                            <select class="schema-field" data-key="{{ $key }}" {{ $disAttr }}>
                                @foreach($field['options'] as $opt)
                                    <option value="{{ $opt }}" {{ (string)$val === (string)$opt ? 'selected' : '' }}>{{ ucfirst($opt) }}</option>
                                @endforeach
                            </select>
                        @elseif($fType === 'boolean')
                            <select class="schema-field" data-key="{{ $key }}" {{ $disAttr }}>
                                <option value="true" {{ $val === true || $val === 'true' || $val === 1 ? 'selected' : '' }}>Yes (True)</option>
                                <option value="false" {{ $val === false || $val === 'false' || $val === 0 || empty($val) ? 'selected' : '' }}>No (False)</option>
                            </select>
                        @elseif($fType === 'textarea')
                            <textarea class="schema-field" data-key="{{ $key }}" rows="2" style="width:100%; border:1px solid var(--vp-c-divider); border-radius:6px; padding:0.4rem;" {{ $disAttr }}>{{ $val }}</textarea>
                        @else
                            <input type="{{ $fType === 'date' ? 'date' : ($fType === 'number' ? 'number' : 'text') }}" 
                                   class="schema-field" 
                                   data-key="{{ $key }}" 
                                   value="{{ $val }}"
                                   placeholder="{{ $fType === 'tags' ? 'tag1, tag2' : '' }}"
                                   {{ $disAttr }}>
                        @endif
                    </div>
                @endforeach
            @else
                <div class="form-group">
                    <label>Publish Status</label>
                    <select class="schema-field" data-key="status">
                        <option value="published" {{ ($frontmatter['status'] ?? 'published') === 'published' ? 'selected' : '' }}>Published</option>
                        <option value="draft" {{ ($frontmatter['status'] ?? '') === 'draft' ? 'selected' : '' }}>Draft</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Publish Date (YYYY-MM-DD)</label>
                    <input type="date" class="schema-field" data-key="date" value="{{ $frontmatter['date'] ?? date('Y-m-d') }}">
                </div>
                @if($type === 'blog')
                    <div class="form-group">
                        <label>Author</label>
                        <input type="text" id="author" value="{{ $frontmatter['author'] ?? '' }}">
                    </div>
                @endif
                <div class="form-group">
                    <label>Category</label>
                    <input type="text" class="schema-field" data-key="category" value="{{ $frontmatter['category'] ?? '' }}" placeholder="e.g. Technology">
                </div>
                <div class="form-group">
                    <label>Tags (comma separated)</label>
                    <input type="text" class="schema-field" data-key="tags" value="{{ isset($frontmatter['tags']) ? (is_array($frontmatter['tags']) ? implode(', ', $frontmatter['tags']) : $frontmatter['tags']) : '' }}" placeholder="e.g. news, update">
                </div>
            @endif
        </div>
        
        <textarea id="markdown-content" style="display:none;">{{ $content }}</textarea>
        <div id="editor"></div>
    </div>
    
    <div class="actions" style="display: flex; gap: 0.5rem; align-items: center;">
        <button type="button" class="btn btn-slash-trigger" onclick="openSlashMenu(window.innerWidth / 2 - 160, 240)">⚡ Insert Component (/)</button>
        @if(!empty($filename))
            @php
                $previewToken = \App\SPPDocs\Services\PermissionManager::generatePreviewToken($project_id, $type, $filename);
                $previewUrl = \SPP\App::getBaseUrl() . '/preview?project=' . urlencode($project_id) . '&type=' . urlencode($type) . '&file=' . urlencode($filename) . '&token=' . urlencode($previewToken);
            @endphp
            <button type="button" class="btn" style="background: var(--vp-c-bg-mute); color: var(--vp-c-text-1); border: 1px solid var(--vp-c-divider);" onclick="navigator.clipboard.writeText('{{ $previewUrl }}'); this.innerText='✓ Preview Link Copied!'; setTimeout(() => this.innerText='🔗 Share Preview Link', 2500);" title="Copy secret preview link to clipboard">🔗 Share Preview Link</button>
        @endif
        <button class="btn" onclick="saveDocument()">Save Document</button>
    </div>

    @spppartial('partials/diff_merge_modal.blade.php')
    @spppartial('partials/editor_slash_menu.blade.php')

    <script src="/school1/public/assets/tui-editor/toastui-editor-all.min.js"></script>
    <script>
        const csrfToken = "{{ $_SESSION['sppdocs_csrf'] ?? '' }}";
        const projectId = '{{ $project_id }}';
        const docType = '{{ $type }}';
        const baseUrl = '{{ \SPP\App::getBaseUrl() }}';
        let baseContent = document.getElementById('markdown-content').value;
        let lastServerContent = '';
        let lastServerMtime = 0;

        const editor = new toastui.Editor({
            el: document.querySelector('#editor'),
            height: '100%',
            initialEditType: 'wysiwyg',
            previewStyle: 'vertical',
            initialValue: baseContent,
            hooks: {
                addImageBlobHook: (blob, callback) => {
                    const formData = new FormData();
                    formData.append('image', blob);
                    formData.append('project_id', projectId);
                    
                    fetch(baseUrl + '/admin/upload', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            let url = data.url;
                            let ext = url.split('.').pop().toLowerCase();
                            if (['mp4', 'webm', 'ogg'].includes(ext)) {
                                editor.insertText(`\n<video src="${url}" controls style="max-width: 100%;"></video>\n`);
                                callback('', '');
                            } else {
                                callback(url, 'alt text');
                            }
                        } else {
                            alert('Upload failed');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Upload failed');
                    });
                }
            }
        });

        // Real-Time Multi-Cursor Collaboration & Presence Loop
        function sendCollabHeartbeat() {
            const filename = document.getElementById('filename').value;
            if (!filename || !projectId) return;

            let cursor = null;
            try {
                if (editor && typeof editor.getSelection === 'function') {
                    const sel = editor.getSelection();
                    if (sel) cursor = { start: sel[0], end: sel[1] };
                }
            } catch(e) {}

            fetch(baseUrl + '/api/collab/heartbeat', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    project_id: projectId,
                    page_slug: filename,
                    status: 'editing',
                    cursor: cursor
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    updatePresenceBar(data);
                }
            })
            .catch(() => {});
        }

        function updatePresenceBar(data) {
            const peersContainer = document.getElementById('sppdocs-collab-peers-container');
            const statusText = document.getElementById('sppdocs-collab-status-text');
            const pulse = document.getElementById('sppdocs-collab-pulse');
            if (!peersContainer || !statusText) return;

            const peers = data.peers || [];
            if (peers.length === 0) {
                peersContainer.innerHTML = '';
                statusText.textContent = 'Only you are editing';
                pulse.classList.remove('locked');
            } else {
                const names = peers.map(p => p.username).join(', ');
                statusText.textContent = `${peers.length} active collaborator${peers.length > 1 ? 's' : ''} (${names})`;
                
                if (data.is_locked_by_other) {
                    pulse.classList.add('locked');
                    statusText.textContent = `⚠️ Locked by ${data.lock_holder}`;
                } else {
                    pulse.classList.remove('locked');
                }

                // Render avatars & cursors
                let html = '';
                peers.forEach(p => {
                    const col = p.color || '#3b82f6';
                    const av = (p.avatar || p.username.charAt(0)).toUpperCase();
                    let cursorInfo = '';
                    if (p.cursor && p.cursor.start) {
                        cursorInfo = ` [L${p.cursor.start[0] || 1}]`;
                    }
                    html += `<span class="sppdocs-collab-avatar" style="background-color: ${col};" title="${p.username} (${p.status})${cursorInfo}">${av}</span>`;
                });
                peersContainer.innerHTML = html;
            }
        }

        // Start heartbeat immediately and schedule every 10 seconds
        sendCollabHeartbeat();
        const heartbeatTimer = setInterval(sendCollabHeartbeat, 10000);

        // SSE Real-Time Collaboration Stream for zero-delay presence
        const filenameVal = document.getElementById('filename').value;
        if (filenameVal && projectId && window.EventSource) {
            try {
                const sseUrl = baseUrl + '/api/collab/stream?project=' + encodeURIComponent(projectId) + '&page=' + encodeURIComponent(filenameVal);
                const es = new EventSource(sseUrl);
                es.addEventListener('presence', function(e) {
                    try {
                        const payload = JSON.parse(e.data);
                        updatePresenceBar(payload);
                    } catch(err) {}
                });
            } catch(e) {}
        }

        // Leave beacon on page exit
        window.addEventListener('beforeunload', function() {
            const filename = document.getElementById('filename').value;
            if (filename && projectId && navigator.sendBeacon) {
                const payload = JSON.stringify({ project_id: projectId, page_slug: filename });
                navigator.sendBeacon(baseUrl + '/api/collab/leave', payload);
            }
        });

        // 3-Way Merge & Conflict Resolution
        function handleConcurrencyConflict(data) {
            lastServerContent = data.server_content || '';
            lastServerMtime = data.server_mtime || 0;
            const myContent = editor.getMarkdown();

            // Request automated 3-way merge
            fetch(baseUrl + '/api/collab/diff-merge', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    base: baseContent,
                    theirs: lastServerContent,
                    mine: myContent
                })
            })
            .then(res => res.json())
            .then(mergeRes => {
                const modal = document.getElementById('sppdocs-diff-modal');
                const summary = document.getElementById('sppdocs-diff-summary');
                const preview = document.getElementById('sppdocs-diff-merged-preview');
                const applyBtn = document.getElementById('sppdocs-btn-apply-merge');

                preview.value = mergeRes.merged || '';

                if (!mergeRes.has_conflicts) {
                    summary.innerHTML = '✨ <strong>Clean 3-Way Merge!</strong> Edits occurred in different sections without line conflicts. You can safely auto-merge into the editor.';
                    applyBtn.textContent = '✨ Auto-Merge Changes into Editor';
                } else {
                    summary.innerHTML = `⚠️ <strong>${mergeRes.conflict_count} Conflicting Hunk(s) Detected!</strong> Both you and another user edited the same lines. Review conflict markers (&lt;&lt;&lt;&lt;&lt;&lt;&lt; / ======= / &gt;&gt;&gt;&gt;&gt;&gt;&gt;) below:`;
                    applyBtn.textContent = '🔍 Load Merged Text with Conflict Markers into Editor';
                }

                modal.style.display = 'flex';
            })
            .catch(err => {
                alert('CONCURRENCY_ERROR: The file was modified by someone else. Please copy your changes and reload.');
            });
        }

        window.closeDiffModal = function() {
            const modal = document.getElementById('sppdocs-diff-modal');
            if (modal) modal.style.display = 'none';
        };

        window.applyMergedVersion = function() {
            const preview = document.getElementById('sppdocs-diff-merged-preview');
            editor.setMarkdown(preview.value);
            document.getElementById('last_modified').value = lastServerMtime;
            baseContent = lastServerContent;
            closeDiffModal();
            alert('Merged changes loaded into editor! Please review and click Save Document.');
        };

        window.acceptServerVersion = function() {
            if (confirm('Discard your local changes and load the latest server version?')) {
                editor.setMarkdown(lastServerContent);
                document.getElementById('last_modified').value = lastServerMtime;
                baseContent = lastServerContent;
                closeDiffModal();
            }
        };

        window.forceMyVersion = function() {
            if (confirm('Are you sure you want to overwrite the server version with your local edits?')) {
                closeDiffModal();
                saveDocument(true);
            }
        };

        function saveDocument(forceSave = false) {
            const filename = document.getElementById('filename').value;
            if (!filename) {
                alert('Please enter a filename');
                return;
            }
            
            const payload = {
                project_id: projectId,
                type: docType,
                filename: filename,
                title: document.getElementById('title').value,
                content: editor.getMarkdown(),
                schemaFields: {},
                last_modified: parseInt(document.getElementById('last_modified').value, 10),
                force_save: forceSave
            };
            
            // Gather custom schema fields
            document.querySelectorAll('.schema-field').forEach(input => {
                payload.schemaFields[input.dataset.key] = input.value;
            });
            
            @if(!isset($schema['fields']) && $type === 'blog')
            payload.author = document.getElementById('author').value;
            @endif

            fetch(baseUrl + '/admin/save', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.href = data.url;
                } else if (data.error === 'CONCURRENCY_ERROR' && data.can_merge) {
                    handleConcurrencyConflict(data);
                } else {
                    alert('Error: ' + (data.message || data.error));
                }
            });
        }

        // Slash Command Hybrid Markdown Snippets
        const slashSnippets = {
            table: "\n| Column 1 | Column 2 | Column 3 |\n| :--- | :--- | :--- |\n| Data A1 | Data A2 | Data A3 |\n| Data B1 | Data B2 | Data B3 |\n",
            api: "\n:::api GET /api/v1/resource\nheaders:\n  Authorization: Bearer <TOKEN>\n  Content-Type: application/json\nbody:\n  query: \"sample\"\n:::\n",
            tabs: "\n:::tabs\n@tab PHP\n```php\necho \"Hello from SPP\";\n```\n@tab JavaScript\n```javascript\nconsole.log(\"Hello from SPP\");\n```\n@tab cURL\n```bash\ncurl -X GET \"https://api.example.com\"\n```\n:::\n",
            alert: "\n> [!NOTE]\n> Insert contextual tips, notices, or architectural warnings here.\n",
            code: "\n```php\n// Your code snippet here\n```\n",
            mermaid: "\n```mermaid\ngraph TD\n    A[Start Client] --> B{Check Auth}\n    B -->|Authenticated| C[Serve SPPDocs Hub]\n    B -->|Guest| D[Redirect to Login]\n```\n",
            snippet: "\n::: snippet name=\"prerequisites\" :::\n"
        };

        window.insertSlashSnippet = function(type) {
            const snip = slashSnippets[type];
            if (snip) {
                editor.insertText(snip);
            }
            closeSlashMenu();
        };

        const slashMenu = document.getElementById('sppdocs-slash-menu');
        window.openSlashMenu = function(x, y) {
            if (!slashMenu) return;
            slashMenu.style.left = (x || (window.innerWidth / 2 - 160)) + 'px';
            slashMenu.style.top = (y || 220) + 'px';
            slashMenu.style.display = 'block';
        };

        window.closeSlashMenu = function() {
            if (slashMenu) slashMenu.style.display = 'none';
        };

        window.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && slashMenu && slashMenu.style.display === 'block') {
                closeSlashMenu();
            }
        });
    </script>
</body>
</html>


