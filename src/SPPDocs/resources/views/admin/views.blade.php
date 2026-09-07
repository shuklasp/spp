@extends('layouts.admin')

@section('title', 'Dynamic Views Studio — ' . ($project['title'] ?? $project_id))

@section('content')
<div class="adm-page-header">
    <div>
        <h1 class="adm-page-title">
            📊 Dynamic Views &amp; Display Studio
            <span class="adm-title-project-chip">📁 {{ $project['title'] ?? $project_id }}</span>
        </h1>
        <p class="adm-page-desc">
            Visual query builder across collections. Construct dynamic lists, card grids, data tables, and syndication feeds without code.
        </p>
    </div>
    <div class="adm-page-actions">
        <button type="button" class="adm-btn adm-btn-primary" onclick="openCreateViewModal()">
            ➕ Create Dynamic View
        </button>
    </div>
</div>

@if(!empty($_SESSION['adm_flash_success']))
    <div class="adm-alert adm-alert-success" style="margin-bottom: 1.25rem;">
        {{ $_SESSION['adm_flash_success'] }}
        <?php unset($_SESSION['adm_flash_success']); ?>
    </div>
@endif

@if(empty($views))
    <div class="adm-card" style="text-align: center; padding: 3rem 1.5rem; background: var(--adm-card-bg, #ffffff); border-radius: 8px; border: 1px solid var(--adm-border, #e2e8f0);">
        <span style="font-size: 3rem;">📊</span>
        <h3 style="margin: 1rem 0 0.5rem 0; color: var(--adm-text-main, #0f172a);">No Dynamic Views Configured</h3>
        <p style="color: var(--adm-text-muted, #64748b); max-width: 500px; margin: 0 auto 1.5rem auto;">
            Create visual queries to filter and present content as responsive card grids, tables, or lists anywhere in your documentation.
        </p>
        <button type="button" class="adm-btn adm-btn-primary" onclick="openCreateViewModal()">
            ➕ Build Your First View
        </button>
    </div>
@else
    <!-- Views Cards Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
        @foreach($views as $vId => $v)
            <div class="adm-card" style="background: var(--adm-card-bg, #ffffff); border: 1px solid var(--adm-border, #e2e8f0); border-radius: 8px; padding: 1.25rem; display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                        <span class="adm-badge" style="background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; font-weight: 700; text-transform: uppercase; font-size: 0.72rem;">
                            {{ $v['display_format'] ?? 'grid' }}
                        </span>
                        <span style="font-size: 0.78rem; color: var(--adm-text-dim, #94a3b8);">Collection: <strong>{{ $v['collection'] ?? 'page' }}</strong></span>
                    </div>

                    <h3 style="margin: 0 0 0.35rem 0; font-size: 1.15rem; color: var(--adm-text-main, #0f172a);">
                        {{ $v['title'] ?? ucfirst($vId) }}
                    </h3>
                    <code style="font-size: 0.78rem; color: var(--adm-text-dim, #94a3b8); background: var(--adm-bg-soft, #f8fafc); padding: 0.15rem 0.35rem; border-radius: 4px;">{{ $vId }}</code>

                    @if(!empty($v['description']))
                        <p style="margin: 0.5rem 0; font-size: 0.85rem; color: var(--adm-text-muted, #64748b);">
                            {{ $v['description'] }}
                        </p>
                    @endif

                    <div style="margin: 0.75rem 0; display: flex; gap: 0.5rem; flex-wrap: wrap; font-size: 0.78rem; color: var(--adm-text-muted, #64748b);">
                        <span>🔍 {{ count($v['filters'] ?? []) }} filter(s)</span>
                        <span>•</span>
                        <span>⬆️ {{ count($v['sorts'] ?? []) }} sort(s)</span>
                        <span>•</span>
                        <span>📄 Limit: {{ $v['limit'] ?? 10 }}</span>
                    </div>
                </div>

                <div style="border-top: 1px solid var(--adm-border, #e2e8f0); padding-top: 0.85rem; margin-top: 0.75rem; display: flex; justify-content: space-between; align-items: center;">
                    <button type="button" 
                            class="adm-btn adm-btn-secondary adm-btn-sm" 
                            onclick="navigator.clipboard.writeText('::: component name=\x22view\x22 id=\x22{{ $vId }}\x22 :::'); this.innerText='✓ Copied!'; setTimeout(() => this.innerText='📋 Embed', 2000);"
                            title="Copy Markdown directive">
                        📋 Embed
                    </button>
                    <div style="display: flex; gap: 0.4rem;">
                        <a href="@url('project/' . urlencode($project_id) . '/views/' . urlencode($vId))" target="_blank" hx-boost="false" class="adm-btn adm-btn-secondary adm-btn-sm">
                            👁️ Preview &rarr;
                        </a>
                        <form action="@url('admin/views/delete')" method="POST" onsubmit="return confirm('Delete view {{ $vId }}?');" style="margin: 0;">
                            <input type="hidden" name="project_id" value="{{ $project_id }}">
                            <input type="hidden" name="view_id" value="{{ $vId }}">
                            <button type="submit" class="adm-btn adm-btn-danger adm-btn-sm">🗑️</button>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif

<!-- Create/Edit View Modal -->
<div id="viewModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); z-index: 10000; align-items: center; justify-content: center; backdrop-filter: blur(2px);">
    <div style="background: var(--adm-card-bg, #ffffff); border-radius: 10px; width: 850px; max-width: 95vw; max-height: 90vh; overflow-y: auto; padding: 1.75rem; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.35); border: 1px solid var(--adm-border, #e2e8f0);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; border-bottom: 1px solid var(--adm-border, #e2e8f0); padding-bottom: 0.75rem;">
            <h3 style="margin: 0; font-size: 1.25rem; color: var(--adm-text-main, #0f172a);">📊 Visual View Builder</h3>
            <button type="button" onclick="document.getElementById('viewModal').style.display='none'" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--adm-text-dim, #94a3b8);">&times;</button>
        </div>

        <form action="@url('admin/views/save')" method="POST">
            <input type="hidden" name="project_id" value="{{ $project_id }}">

            <!-- General Settings -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div class="form-group">
                    <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; color: var(--adm-text-main, #0f172a);">View Identifier</label>
                    <input type="text" name="view_id" id="view_id_input" required placeholder="e.g. recent_articles, guides" style="width: 100%; box-sizing: border-box; padding: 0.45rem 0.6rem; border: 1px solid var(--adm-border, #e2e8f0); border-radius: 6px; background: var(--adm-input-bg, #ffffff); color: var(--adm-text-main, #0f172a);">
                </div>
                <div class="form-group">
                    <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; color: var(--adm-text-main, #0f172a);">Display Title</label>
                    <input type="text" name="title" id="view_title_input" required placeholder="e.g. Recent Articles" style="width: 100%; box-sizing: border-box; padding: 0.45rem 0.6rem; border: 1px solid var(--adm-border, #e2e8f0); border-radius: 6px; background: var(--adm-input-bg, #ffffff); color: var(--adm-text-main, #0f172a);">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                <div class="form-group">
                    <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; color: var(--adm-text-main, #0f172a);">Source Collection</label>
                    <select name="collection" style="width: 100%; box-sizing: border-box; padding: 0.45rem; border: 1px solid var(--adm-border, #e2e8f0); border-radius: 6px; background: var(--adm-input-bg, #ffffff); color: var(--adm-text-main, #0f172a);">
                        <option value="page">Documentation Pages (docs)</option>
                        <option value="blog">Blog Posts & Announcements</option>
                        @foreach($schemas as $sId => $sDef)
                            @if(!in_array($sId, ['page', 'blog']))
                                <option value="{{ $sId }}">{{ $sDef['title'] ?? ucfirst($sId) }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; color: var(--adm-text-main, #0f172a);">Display Format</label>
                    <select name="display_format" style="width: 100%; box-sizing: border-box; padding: 0.45rem; border: 1px solid var(--adm-border, #e2e8f0); border-radius: 6px; background: var(--adm-input-bg, #ffffff); color: var(--adm-text-main, #0f172a);">
                        <option value="grid">Responsive Card Grid</option>
                        <option value="table">Data Table</option>
                        <option value="list">Unformatted List</option>
                        <option value="json">REST JSON Endpoint</option>
                    </select>
                </div>
                <div class="form-group">
                    <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; color: var(--adm-text-main, #0f172a);">Items Limit</label>
                    <input type="number" name="limit" value="10" min="1" max="100" style="width: 100%; box-sizing: border-box; padding: 0.45rem; border: 1px solid var(--adm-border, #e2e8f0); border-radius: 6px; background: var(--adm-input-bg, #ffffff); color: var(--adm-text-main, #0f172a);">
                </div>
            </div>

            <!-- Filter Rules Builder -->
            <div style="margin-bottom: 1.25rem; background: var(--adm-bg-soft, #f8fafc); padding: 1rem; border-radius: 8px; border: 1px solid var(--adm-border, #e2e8f0);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                    <strong style="font-size: 0.9rem; color: var(--adm-text-main, #0f172a);">🔍 Filter Criteria (WHERE)</strong>
                    <button type="button" class="adm-btn adm-btn-secondary adm-btn-sm" onclick="addFilterRow()">+ Add Filter</button>
                </div>
                <div id="filtersContainer" style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <div class="filter-row" style="display: grid; grid-template-columns: 2fr 1.5fr 3fr auto; gap: 0.5rem; align-items: center;">
                        <input type="text" name="filters[0][field]" placeholder="Field (e.g. status, category)" value="status" style="padding: 0.4rem; border: 1px solid var(--adm-border, #e2e8f0); border-radius: 4px; background: var(--adm-input-bg, #ffffff); color: var(--adm-text-main, #0f172a);">
                        <select name="filters[0][operator]" style="padding: 0.4rem; border: 1px solid var(--adm-border, #e2e8f0); border-radius: 4px; background: var(--adm-input-bg, #ffffff); color: var(--adm-text-main, #0f172a);">
                            <option value="=">=</option>
                            <option value="!=">!=</option>
                            <option value="like">Contains (LIKE)</option>
                            <option value=">">&gt;</option>
                            <option value="<">&lt;</option>
                        </select>
                        <input type="text" name="filters[0][value]" placeholder="Value (e.g. published)" value="published" style="padding: 0.4rem; border: 1px solid var(--adm-border, #e2e8f0); border-radius: 4px; background: var(--adm-input-bg, #ffffff); color: var(--adm-text-main, #0f172a);">
                        <button type="button" onclick="this.parentElement.remove()" style="background: none; border: none; color: #ef4444; font-size: 1.1rem; cursor: pointer;">&times;</button>
                    </div>
                </div>
            </div>

            <!-- Sort Rules Builder -->
            <div style="margin-bottom: 1.5rem; background: var(--adm-bg-soft, #f8fafc); padding: 1rem; border-radius: 8px; border: 1px solid var(--adm-border, #e2e8f0);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                    <strong style="font-size: 0.9rem; color: var(--adm-text-main, #0f172a);">⬆️ Sort Criteria (ORDER BY)</strong>
                    <button type="button" class="adm-btn adm-btn-secondary adm-btn-sm" onclick="addSortRow()">+ Add Sort</button>
                </div>
                <div id="sortsContainer" style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <div class="sort-row" style="display: grid; grid-template-columns: 3fr 2fr auto; gap: 0.5rem; align-items: center;">
                        <input type="text" name="sorts[0][field]" placeholder="Field (e.g. date, modified_at)" value="date" style="padding: 0.4rem; border: 1px solid var(--adm-border, #e2e8f0); border-radius: 4px; background: var(--adm-input-bg, #ffffff); color: var(--adm-text-main, #0f172a);">
                        <select name="sorts[0][direction]" style="padding: 0.4rem; border: 1px solid var(--adm-border, #e2e8f0); border-radius: 4px; background: var(--adm-input-bg, #ffffff); color: var(--adm-text-main, #0f172a);">
                            <option value="DESC">Descending (Newest first)</option>
                            <option value="ASC">Ascending (Oldest first)</option>
                        </select>
                        <button type="button" onclick="this.parentElement.remove()" style="background: none; border: none; color: #ef4444; font-size: 1.1rem; cursor: pointer;">&times;</button>
                    </div>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                <button type="button" class="adm-btn adm-btn-secondary" onclick="document.getElementById('viewModal').style.display='none'">Cancel</button>
                <button type="submit" class="adm-btn adm-btn-primary">💾 Save View</button>
            </div>
        </form>
    </div>
</div>

<script>
let filterIndex = 1;
let sortIndex = 1;

function openCreateViewModal() {
    document.getElementById('view_id_input').value = '';
    document.getElementById('view_title_input').value = '';
    document.getElementById('viewModal').style.display = 'flex';
}

function addFilterRow() {
    const container = document.getElementById('filtersContainer');
    const div = document.createElement('div');
    div.className = 'filter-row';
    div.style = 'display: grid; grid-template-columns: 2fr 1.5fr 3fr auto; gap: 0.5rem; align-items: center;';
    div.innerHTML = `
        <input type="text" name="filters[${filterIndex}][field]" placeholder="Field" style="padding: 0.4rem; border: 1px solid var(--adm-border, #e2e8f0); border-radius: 4px; background: var(--adm-input-bg, #ffffff); color: var(--adm-text-main, #0f172a);">
        <select name="filters[${filterIndex}][operator]" style="padding: 0.4rem; border: 1px solid var(--adm-border, #e2e8f0); border-radius: 4px; background: var(--adm-input-bg, #ffffff); color: var(--adm-text-main, #0f172a);">
            <option value="=">=</option>
            <option value="!=">!=</option>
            <option value="like">Contains</option>
            <option value=">">&gt;</option>
            <option value="<">&lt;</option>
        </select>
        <input type="text" name="filters[${filterIndex}][value]" placeholder="Value" style="padding: 0.4rem; border: 1px solid var(--adm-border, #e2e8f0); border-radius: 4px; background: var(--adm-input-bg, #ffffff); color: var(--adm-text-main, #0f172a);">
        <button type="button" onclick="this.parentElement.remove()" style="background: none; border: none; color: #ef4444; font-size: 1.1rem; cursor: pointer;">&times;</button>
    `;
    container.appendChild(div);
    filterIndex++;
}

function addSortRow() {
    const container = document.getElementById('sortsContainer');
    const div = document.createElement('div');
    div.className = 'sort-row';
    div.style = 'display: grid; grid-template-columns: 3fr 2fr auto; gap: 0.5rem; align-items: center;';
    div.innerHTML = `
        <input type="text" name="sorts[${sortIndex}][field]" placeholder="Field" style="padding: 0.4rem; border: 1px solid var(--adm-border, #e2e8f0); border-radius: 4px; background: var(--adm-input-bg, #ffffff); color: var(--adm-text-main, #0f172a);">
        <select name="sorts[${sortIndex}][direction]" style="padding: 0.4rem; border: 1px solid var(--adm-border, #e2e8f0); border-radius: 4px; background: var(--adm-input-bg, #ffffff); color: var(--adm-text-main, #0f172a);">
            <option value="DESC">Descending</option>
            <option value="ASC">Ascending</option>
        </select>
        <button type="button" onclick="this.parentElement.remove()" style="background: none; border: none; color: #ef4444; font-size: 1.1rem; cursor: pointer;">&times;</button>
    `;
    container.appendChild(div);
    sortIndex++;
}
</script>
@endsection