@extends('admin.layout')

@section('content')
<div class="block-editor">
    <h1>Configure Block: {{ strtoupper($block->block_type) }}</h1>
    
    <div class="glass-panel">
        <form action="{{ $admin_root }}/landing/block/edit/{{ $block->id }}" method="POST">
            <div class="form-group">
                <label>Block Title</label>
                <input type="text" name="config[title]" value="{{ $content['title'] ?? '' }}" class="form-control">
            </div>

            @if($block->block_type == 'dynamic_list')
                <div class="dynamic-config" style="margin-top: 20px; border-top: 1px solid #333; padding-top: 20px;">
                    <h3>Entity Source</h3>
                    <div class="form-group">
                        <label>Entity Type</label>
                        <select name="config[entity_type]" class="form-control">
                            <option value="node" {{ ($content['entity_type'] ?? '') == 'node' ? 'selected' : '' }}>Content (Nodes)</option>
                            <option value="user" {{ ($content['entity_type'] ?? '') == 'user' ? 'selected' : '' }}>Users</option>
                            <option value="type" {{ ($content['entity_type'] ?? '') == 'type' ? 'selected' : '' }}>Content Types</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Filters / Conditions (Visual Syntax)</label>
                        <p class="help-text" style="font-size: 0.8rem; color: #888;">Format: key = value (one per line, e.g. bundle = article)</p>
                        @php 
                            $conds = [];
                            foreach($content['conditions'] ?? [] as $k => $v) $conds[] = "$k = $v";
                            $conds_str = implode("\n", $conds);
                        @endphp
                        <textarea name="config[conditions_raw]" class="form-control" rows="4">{{ $conds_str }}</textarea>
                    </div>

                    <div class="form-group">
                        <label>Max Items</label>
                        <input type="number" name="config[limit]" value="{{ $content['limit'] ?? 5 }}" class="form-control">
                    </div>
                </div>
            @endif

            @if($block->block_type == 'hero')
                <div class="form-group">
                    <label>Subtitle</label>
                    <input type="text" name="config[subtitle]" value="{{ $content['subtitle'] ?? '' }}" class="form-control">
                </div>
                <div class="form-group">
                    <label>Button Text</label>
                    <input type="text" name="config[button_text]" value="{{ $content['button_text'] ?? '' }}" class="form-control">
                </div>
            @endif

            <div class="form-actions" style="margin-top: 30px;">
                <button type="submit" class="btn btn-primary">Save Configuration</button>
                <a href="{{ $admin_root }}/landing/design/{{ $block->page_id }}" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>

<style>
.form-group { margin-bottom: 20px; }
.form-group label { display: block; margin-bottom: 8px; font-weight: bold; }
.form-control { 
    width: 100%; 
    background: rgba(0,0,0,0.2); 
    border: 1px solid rgba(255,255,255,0.1); 
    padding: 12px; 
    border-radius: 8px; 
    color: white;
}
</style>
@endsection
