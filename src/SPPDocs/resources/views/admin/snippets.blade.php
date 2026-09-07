@extends('layouts.admin')
@section('title', 'Global Snippets - ' . ($project['title'] ?? 'Project'))

@section('content')
<div class="adm-content-container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="margin: 0; font-size: 1.6rem;">🧩 Reusable Global Snippets</h1>
            <p style="margin: 0.35rem 0 0 0; color: var(--vp-c-text-2); font-size: 0.9rem;">
                Single-source reusable Markdown fragments and boilerplate for <strong>{{ $project['title'] ?? $projectId }}</strong>
            </p>
        </div>
        <button onclick="openSnippetModal()" class="adm-btn adm-btn-primary" style="padding: 0.6rem 1.25rem; font-weight: 600; cursor: pointer;">
            + New Snippet
        </button>
    </div>

    {{-- Snippet Usage Explainer Banner --}}
    <div style="background: var(--vp-c-bg-soft); border: 1px solid var(--vp-c-divider); border-radius: 8px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; font-size: 0.85rem; line-height: 1.6; color: var(--vp-c-text-2);">
        <strong style="color: var(--vp-c-text-1);">💡 How to use snippets in documentation:</strong>
        Type <code style="background: var(--vp-c-bg); padding: 0.2rem 0.4rem; border-radius: 4px; border: 1px solid var(--vp-c-divider); color: var(--vp-c-brand);">::: snippet name="snippet-name" :::</code> anywhere inside a documentation page or blog post.
        When updated here, the new content is automatically reflected across all pages!
    </div>

    {{-- Snippets Grid / Table --}}
    <div style="border: 1px solid var(--vp-c-divider); border-radius: 8px; overflow: hidden; background: var(--vp-c-bg);">
        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
            <thead>
                <tr style="background: var(--vp-c-bg-soft); border-bottom: 1px solid var(--vp-c-divider); color: var(--vp-c-text-2);">
                    <th style="padding: 0.75rem 1rem;">Snippet Name</th>
                    <th style="padding: 0.75rem 1rem;">Markdown Directive</th>
                    <th style="padding: 0.75rem 1rem;">Preview</th>
                    <th style="padding: 0.75rem 1rem;">Last Modified</th>
                    <th style="padding: 0.75rem 1rem; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($snippets as $name => $snippet)
                <tr style="border-bottom: 1px solid var(--vp-c-divider);">
                    <td style="padding: 0.85rem 1rem; font-weight: 600;">
                        <span style="font-family: monospace; color: var(--vp-c-brand);">{{ $name }}</span>
                    </td>
                    <td style="padding: 0.85rem 1rem;">
                        <button type="button" onclick="navigator.clipboard.writeText('::: snippet name=\'{{ $name }}\' :::'); this.innerText='✓ Copied!'; setTimeout(() => this.innerText='📋 Copy Directive', 2000)" style="padding: 0.25rem 0.6rem; border: 1px solid var(--vp-c-divider); background: var(--vp-c-bg-soft); border-radius: 4px; font-size: 0.75rem; cursor: pointer; color: var(--vp-c-text-2); font-family: monospace;">
                            📋 Copy Directive
                        </button>
                    </td>
                    <td style="padding: 0.85rem 1rem; color: var(--vp-c-text-3); font-size: 0.8rem; max-width: 320px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        {{ $snippet['preview'] ?: '— Empty —' }}
                    </td>
                    <td style="padding: 0.85rem 1rem; color: var(--vp-c-text-3); font-size: 0.8rem;">
                        {{ date('M j, Y g:i A', $snippet['modified_at'] ?? time()) }}
                    </td>
                    <td style="padding: 0.85rem 1rem; text-align: right;">
                        <div style="display: inline-flex; gap: 0.5rem;">
                            <button onclick="editSnippet('{{ $name }}', decodeURIComponent('{{ rawurlencode(\App\SPPDocs\Services\SnippetManager::getSnippet($projectId, $name) ?? '') }}'))" style="padding: 0.35rem 0.75rem; border: 1px solid var(--vp-c-divider); border-radius: 6px; background: var(--vp-c-bg); cursor: pointer; font-size: 0.8rem;">
                                Edit
                            </button>
                            <form method="POST" action="{{ \SPP\App::getBaseUrl() }}/admin/snippets/delete" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this snippet?');">
                                <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
                                <input type="hidden" name="project_id" value="{{ $projectId }}">
                                <input type="hidden" name="name" value="{{ $name }}">
                                <button type="submit" style="padding: 0.35rem 0.75rem; border: 1px solid #ef4444; color: #ef4444; border-radius: 6px; background: transparent; cursor: pointer; font-size: 0.8rem;">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" style="text-align: center; padding: 3rem; color: var(--vp-c-text-3);">
                        <p style="font-size: 1.1rem; margin-bottom: 0.5rem;">No snippets created yet.</p>
                        <p style="font-size: 0.85rem;">Create reusable snippets to share content blocks across documentation pages.</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Snippet Modal --}}
<div id="snippet-modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; padding: 1rem;">
    <div style="background: var(--vp-c-bg); border-radius: 12px; width: 100%; max-width: 600px; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 50px rgba(0,0,0,0.25); padding: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h2 id="modal-title" style="margin: 0;">Create New Snippet</h2>
            <button onclick="document.getElementById('snippet-modal').style.display='none'" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--vp-c-text-2);">✕</button>
        </div>
        <form method="POST" action="{{ \SPP\App::getBaseUrl() }}/admin/snippets/save">
            <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
            <input type="hidden" name="project_id" value="{{ $projectId }}">

            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <div>
                    <label style="font-weight: 600; font-size: 0.85rem; display: block; margin-bottom: 0.25rem;">Snippet Name (alphanumeric, hyphens) *</label>
                    <input type="text" id="snippet-name-input" name="name" required placeholder="e.g. prerequisites, install-cmd" style="width: 100%; padding: 0.6rem; border: 1px solid var(--vp-c-divider); border-radius: 6px; font-family: monospace;">
                </div>
                <div>
                    <label style="font-weight: 600; font-size: 0.85rem; display: block; margin-bottom: 0.25rem;">Markdown Content *</label>
                    <textarea id="snippet-content-input" name="content" rows="10" required placeholder="Type the reusable markdown here..." style="width: 100%; padding: 0.6rem; border: 1px solid var(--vp-c-divider); border-radius: 6px; font-family: monospace; font-size: 0.9rem; line-height: 1.5;"></textarea>
                </div>
            </div>

            <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem; justify-content: flex-end;">
                <button type="button" onclick="document.getElementById('snippet-modal').style.display='none'" style="padding: 0.6rem 1.2rem; border: 1px solid var(--vp-c-divider); border-radius: 8px; background: var(--vp-c-bg); cursor: pointer;">Cancel</button>
                <button type="submit" style="padding: 0.6rem 1.5rem; background: var(--vp-c-brand); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">Save Snippet</button>
            </div>
        </form>
    </div>
</div>

<script>
function openSnippetModal() {
    document.getElementById('modal-title').textContent = 'Create New Snippet';
    document.getElementById('snippet-name-input').value = '';
    document.getElementById('snippet-name-input').readOnly = false;
    document.getElementById('snippet-content-input').value = '';
    document.getElementById('snippet-modal').style.display = 'flex';
}

function editSnippet(name, content) {
    document.getElementById('modal-title').textContent = 'Edit Snippet: ' + name;
    document.getElementById('snippet-name-input').value = name;
    document.getElementById('snippet-name-input').readOnly = true;
    document.getElementById('snippet-content-input').value = content;
    document.getElementById('snippet-modal').style.display = 'flex';
}
</script>
@endsection

