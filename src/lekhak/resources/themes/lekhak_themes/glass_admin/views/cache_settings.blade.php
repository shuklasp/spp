@extends('layout')

@section('content')
<div class="lekhak-local-tasks-header" style="margin-bottom: 25px; border-bottom: 1px solid var(--glass-border); padding-bottom: 12px;">
    <h2 style="margin: 0; font-size: 1.5rem; color: var(--text-main);">{{ $title }}</h2>
    <p style="margin: 5px 0 0 0; font-size: 0.9rem; color: var(--text-dim);">{{ $subtitle }}</p>
</div>

@if(isset($message))
    <div style="background: rgba(16, 185, 129, 0.1); border-left: 4px solid var(--success); padding: 15px; margin-bottom: 20px; color: var(--text-main); border-radius: 4px;">
        {{ $message }}
    </div>
@endif

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
    <div class="glass-panel" style="padding: 25px;">
        <h3 style="margin-top: 0;">Clear Entire Cache</h3>
        <p style="color: var(--text-dim); font-size: 0.9rem;">
            Clearing the cache will purge all cached pages and data. This may cause a temporary performance drop while caches are rebuilt.
        </p>
        <form method="POST" action="">
            <input type="hidden" name="action" value="clear_all">
            <button type="submit" class="btn btn-primary" onclick="return confirm('Are you sure you want to clear all caches?');">
                Clear All Caches
            </button>
        </form>
    </div>

    <div class="glass-panel" style="padding: 25px;">
        <h3 style="margin-top: 0;">Invalidate Specific Tags</h3>
        <p style="color: var(--text-dim); font-size: 0.9rem;">
            Purge only specific cache tags without clearing the entire site cache (e.g. <code>node:123, node_list</code>).
        </p>
        <form method="POST" action="">
            <input type="hidden" name="action" value="invalidate_tags">
            <input type="text" name="tags" placeholder="tag1, tag2..." class="form-control" style="width: 100%; padding: 10px; margin-bottom: 15px; background: rgba(0,0,0,0.2); border: 1px solid var(--glass-border); color: white; border-radius: 4px;" required>
            <button type="submit" class="btn btn-secondary">
                Purge Tags
            </button>
        </form>
    </div>
</div>

<div class="glass-panel" style="padding: 25px; margin-top: 20px;">
    <h3 style="margin-top: 0;">Cache Settings</h3>
    <div style="display: flex; flex-direction: column; gap: 15px;">
        <label style="display: flex; align-items: center; gap: 10px; color: var(--text-main);">
            <input type="checkbox" checked disabled>
            Enable Dynamic Page Cache
        </label>
        <label style="display: flex; align-items: center; gap: 10px; color: var(--text-main);">
            <input type="checkbox" checked disabled>
            Inject <code>X-Lekhak-Cache-Tags</code> HTTP Headers
        </label>
    </div>
</div>
@endsection
