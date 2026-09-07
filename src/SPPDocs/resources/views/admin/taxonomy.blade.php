@extends('layouts.admin')

@section('title', 'Taxonomy & Hierarchical Categories — ' . ($project['title'] ?? $project_id))

@section('content')
<div class="adm-content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h1 style="margin: 0 0 0.35rem 0; font-size: 1.6rem; font-weight: 700; color: var(--vp-c-text-1);">
                🏷️ Hierarchical Taxonomy & Vocabularies
            </h1>
            <p style="margin: 0; color: var(--vp-c-text-2); font-size: 0.9rem;">
                Classification engine. Manage nested parent-child category trees, free tags, and automatic term archives.
            </p>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <button type="button" class="adm-btn adm-btn-primary" onclick="openAddTermModal()">
                ➕ Add Taxonomy Term
            </button>
        </div>
    </div>

    @if(!empty($_SESSION['adm_flash_success']))
        <div class="adm-alert adm-alert-success" style="margin-bottom: 1.25rem;">
            {{ $_SESSION['adm_flash_success'] }}
            <?php unset($_SESSION['adm_flash_success']); ?>
        </div>
    @endif

    <!-- Vocabularies Selector Tabs -->
    <div style="display: flex; gap: 0.5rem; border-bottom: 2px solid var(--vp-c-divider); margin-bottom: 1.5rem; overflow-x: auto; padding-bottom: 0.5rem;">
        @foreach($vocabularies as $vKey => $vInfo)
            <a href="@url('admin/taxonomy?project=' . urlencode($project_id) . '&vocab=' . urlencode($vKey))" 
               class="adm-btn {{ $activeVocabKey === $vKey ? 'adm-btn-primary' : 'adm-btn-secondary' }} adm-btn-sm" 
               style="border-radius: 20px; text-decoration: none;">
                🏷️ {{ $vInfo['title'] ?? ucfirst($vKey) }} 
                <span style="font-size: 0.75rem; opacity: 0.8;">({{ count($vInfo['terms'] ?? []) }} terms)</span>
            </a>
        @endforeach
    </div>

    <!-- Active Vocabulary Overview Card -->
    @if(!empty($activeVocab))
        <div class="adm-card" style="background: var(--vp-c-bg); border: 1px solid var(--vp-c-divider); border-radius: 8px; overflow: hidden; margin-bottom: 2rem;">
            <div style="padding: 1rem 1.25rem; border-bottom: 1px solid var(--vp-c-divider); background: var(--vp-c-bg-soft); display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h3 style="margin: 0; font-size: 1.15rem; color: var(--vp-c-text-1);">
                        {{ $activeVocab['title'] }}
                    </h3>
                    <span style="font-size: 0.82rem; color: var(--vp-c-text-3);">
                        Type: {{ !empty($activeVocab['hierarchical']) ? 'Hierarchical Tree (Parent & Child)' : 'Flat Tags' }}
                    </span>
                </div>
                <span style="font-size: 0.85rem; color: var(--vp-c-text-2);">
                    Vocabulary ID: <code>{{ $activeVocabKey }}</code>
                </span>
            </div>

            <!-- Terms Tree Table -->
            @if(empty($activeVocab['terms']))
                <div style="padding: 3rem; text-align: center; color: var(--vp-c-text-3);">
                    <p style="margin: 0;">No terms defined in this vocabulary yet.</p>
                </div>
            @else
                <div style="overflow-x: auto;">
                    <table class="adm-table" style="width: 100%; border-collapse: collapse; font-size: 0.88rem;">
                        <thead>
                            <tr style="background: var(--vp-c-bg-mute); text-align: left; border-bottom: 1px solid var(--vp-c-divider);">
                                <th style="padding: 0.75rem 1rem;">Term Name & Hierarchy</th>
                                <th style="padding: 0.75rem 1rem;">Slug</th>
                                <th style="padding: 0.75rem 1rem;">Description</th>
                                <th style="padding: 0.75rem 1rem; width: 100px;">Color</th>
                                <th style="padding: 0.75rem 1rem; text-align: right; width: 140px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $renderNode = function($node, $depth = 0) use (&$renderNode, $project_id, $activeVocabKey) {
                            @endphp
                                <tr style="border-bottom: 1px solid var(--vp-c-divider);">
                                    <td style="padding: 0.75rem 1rem;">
                                        <div style="display: flex; align-items: center; gap: 0.4rem; padding-left: {{ $depth * 24 }}px;">
                                            @if($depth > 0)
                                                <span style="color: var(--vp-c-text-3); font-size: 0.9rem;">↳</span>
                                            @endif
                                            <span style="font-size: 1.1rem;">{{ $node['icon'] ?? '🏷️' }}</span>
                                            <strong style="color: var(--vp-c-text-1);">{{ $node['name'] }}</strong>
                                        </div>
                                    </td>
                                    <td style="padding: 0.75rem 1rem;">
                                        <code style="font-size: 0.8rem;">{{ $node['slug'] }}</code>
                                    </td>
                                    <td style="padding: 0.75rem 1rem; font-size: 0.82rem; color: var(--vp-c-text-2);">
                                        {{ $node['description'] ?: '—' }}
                                    </td>
                                    <td style="padding: 0.75rem 1rem;">
                                        <span style="display: inline-block; width: 14px; height: 14px; border-radius: 50%; background: {{ $node['color'] ?? '#3b82f6' }}; vertical-align: middle; margin-right: 0.35rem;"></span>
                                        <span style="font-size: 0.78rem; font-family: monospace;">{{ $node['color'] ?? '#3b82f6' }}</span>
                                    </td>
                                    <td style="padding: 0.75rem 1rem; text-align: right;">
                                        <div style="display: flex; justify-content: flex-end; gap: 0.4rem; align-items: center;">
                                            <a href="@url('project/' . urlencode($project_id) . '/taxonomy/' . urlencode($activeVocabKey) . '/' . urlencode($node['slug']))" target="_blank" hx-boost="false" class="adm-btn adm-btn-secondary adm-btn-sm" title="View Archive">
                                                👁️
                                            </a>
                                            <form action="@url('admin/taxonomy/delete')" method="POST" onsubmit="return confirm('Delete term {{ $node['name'] }}?');" style="margin: 0;">
                                                <input type="hidden" name="project_id" value="{{ $project_id }}">
                                                <input type="hidden" name="vocab" value="{{ $activeVocabKey }}">
                                                <input type="hidden" name="slug" value="{{ $node['slug'] }}">
                                                <button type="submit" class="adm-btn adm-btn-danger adm-btn-sm">🗑️</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                @if(!empty($node['children']))
                                    @foreach($node['children'] as $child)
                                        @php $renderNode($child, $depth + 1); @endphp
                                    @endforeach
                                @endif
                            @php
                                };
                            @endphp

                            @foreach($hierarchyTree as $rootNode)
                                @php $renderNode($rootNode, 0); @endphp
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
</div>

<!-- Add Term Modal -->
<div id="termModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); z-index: 10000; align-items: center; justify-content: center; backdrop-filter: blur(2px);">
    <div style="background: var(--vp-c-bg); border-radius: 10px; width: 550px; max-width: 90vw; padding: 1.75rem; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.35); border: 1px solid var(--vp-c-divider);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; border-bottom: 1px solid var(--vp-c-divider); padding-bottom: 0.75rem;">
            <h3 style="margin: 0; font-size: 1.2rem; color: var(--vp-c-text-1);">➕ Add Taxonomy Term</h3>
            <button type="button" onclick="document.getElementById('termModal').style.display='none'" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--vp-c-text-3);">&times;</button>
        </div>

        <form action="@url('admin/taxonomy/save')" method="POST">
            <input type="hidden" name="project_id" value="{{ $project_id }}">
            <input type="hidden" name="vocab" value="{{ $activeVocabKey }}">

            <div class="form-group" style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem;">Term Name</label>
                <input type="text" name="name" id="term_name" required placeholder="e.g. Kernel Architecture" style="width: 100%; box-sizing: border-box; padding: 0.45rem; border: 1px solid var(--vp-c-divider); border-radius: 6px;">
            </div>

            <div class="form-group" style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem;">Slug (Identifier)</label>
                <input type="text" name="slug" id="term_slug" required placeholder="e.g. kernel-architecture" style="width: 100%; box-sizing: border-box; padding: 0.45rem; border: 1px solid var(--vp-c-divider); border-radius: 6px;">
            </div>

            @if(!empty($activeVocab['hierarchical']))
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem;">Parent Term (Hierarchy)</label>
                    <select name="parent" style="width: 100%; box-sizing: border-box; padding: 0.45rem; border: 1px solid var(--vp-c-divider); border-radius: 6px;">
                        <option value="">-- None (Top Level) --</option>
                        @foreach(($activeVocab['terms'] ?? []) as $tSlug => $tInfo)
                            <option value="{{ $tSlug }}">{{ $tInfo['name'] }} ({{ $tSlug }})</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div class="form-group">
                    <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem;">Badge Color</label>
                    <input type="color" name="color" value="#3b82f6" style="width: 100%; height: 38px; border: 1px solid var(--vp-c-divider); border-radius: 6px; padding: 2px; cursor: pointer;">
                </div>
                <div class="form-group">
                    <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem;">Icon Emoji</label>
                    <input type="text" name="icon" value="🏷️" style="width: 100%; box-sizing: border-box; padding: 0.45rem; border: 1px solid var(--vp-c-divider); border-radius: 6px; text-align: center; font-size: 1.2rem;">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem;">Description</label>
                <textarea name="description" rows="2" placeholder="Brief explanation of this taxonomy term..." style="width: 100%; box-sizing: border-box; padding: 0.45rem; border: 1px solid var(--vp-c-divider); border-radius: 6px;"></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                <button type="button" class="adm-btn adm-btn-secondary" onclick="document.getElementById('termModal').style.display='none'">Cancel</button>
                <button type="submit" class="adm-btn adm-btn-primary">Save Term</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddTermModal() {
    document.getElementById('term_name').value = '';
    document.getElementById('term_slug').value = '';
    document.getElementById('termModal').style.display = 'flex';
}

document.getElementById('term_name').addEventListener('input', function() {
    const slugInput = document.getElementById('term_slug');
    if (!slugInput.dataset.manual) {
        slugInput.value = this.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
    }
});
document.getElementById('term_slug').addEventListener('input', function() {
    this.dataset.manual = '1';
});
</script>
@endsection