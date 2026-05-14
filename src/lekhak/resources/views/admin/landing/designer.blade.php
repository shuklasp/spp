@extends('admin.layout')

@section('content')
<div class="landing-designer">
    <div class="header-with-actions">
        <h1>Designing: {{ $page->title }}</h1>
        <div class="designer-tools">
            <span class="view-status">Live Preview Enabled</span>
            <a href="{{ $app_root }}/{{ $page->alias }}" target="_blank" class="btn btn-outline">View Page</a>
        </div>
    </div>

    <div class="designer-layout">
        <div class="block-list">
            @if(empty($blocks))
                <div class="empty-state">
                    <p>No blocks added yet. Start building your page!</p>
                </div>
            @endif

            @foreach($blocks as $block)
                <div class="block-item glass-panel" data-id="{{ $block->id }}">
                    <div class="block-header">
                        <span class="block-type">{{ strtoupper($block->block_type) }}</span>
                        <div class="block-actions">
                            <a href="{{ $admin_root }}/landing/block/edit/{{ $block->id }}" class="btn-icon">⚙️</a>
                            <a href="{{ $admin_root }}/landing/block/delete/{{ $block->id }}" class="btn-icon delete">🗑️</a>
                        </div>
                    </div>
                    <div class="block-preview">
                        @php $content = $block->getContent(); @endphp
                        @if($block->block_type == 'hero')
                            <h2>{{ $content['title'] ?? '' }}</h2>
                            <p>{{ $content['subtitle'] ?? '' }}</p>
                        @elseif($block->block_type == 'text')
                            <div class="text-excerpt">{{ substr($content['content'] ?? '', 0, 100) }}...</div>
                        @else
                            <div class="generic-preview">Content preview for {{ $block->block_type }}</div>
                        @endif
                    </div>
                </div>
            @endforeach

            <div class="add-block-section">
                <h3>Add New Block</h3>
                <div class="block-picker">
                    <a href="{{ $admin_root }}/landing/block/add/{{ $page->id }}?type=hero" class="picker-item">
                        <span class="icon">🖼️</span>
                        <span>Hero Section</span>
                    </a>
                    <a href="{{ $admin_root }}/landing/block/add/{{ $page->id }}?type=text" class="picker-item">
                        <span class="icon">📝</span>
                        <span>Text Block</span>
                    </a>
                    <a href="{{ $admin_root }}/landing/block/add/{{ $page->id }}?type=features" class="picker-item">
                        <span class="icon">🚀</span>
                        <span>Features Grid</span>
                    </a>
                    <a href="{{ $admin_root }}/landing/block/add/{{ $page->id }}?type=cta" class="picker-item">
                        <span class="icon">📢</span>
                        <span>Call to Action</span>
                    </a>
                    <a href="{{ $admin_root }}/landing/block/add/{{ $page->id }}?type=dynamic_list" class="picker-item">
                        <span class="icon">⚡</span>
                        <span>Dynamic Content</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.landing-designer {
    padding: 20px;
}
.header-with-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}
.designer-layout {
    max-width: 800px;
    margin: 0 auto;
}
.block-item {
    margin-bottom: 20px;
    border-left: 4px solid var(--accent-color, #3498db);
    transition: transform 0.2s;
}
.block-item:hover {
    transform: translateX(5px);
}
.block-header {
    display: flex;
    justify-content: space-between;
    padding-bottom: 10px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    margin-bottom: 10px;
}
.block-type {
    font-size: 0.7rem;
    font-weight: bold;
    letter-spacing: 1px;
    color: var(--text-dim, #999);
}
.block-preview {
    padding: 10px 0;
}
.text-excerpt {
    font-style: italic;
    color: #888;
}
.add-block-section {
    margin-top: 40px;
    padding: 20px;
    background: rgba(255,255,255,0.05);
    border-radius: 12px;
    border: 2px dashed rgba(255,255,255,0.1);
}
.block-picker {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-top: 15px;
}
.picker-item {
    background: rgba(255,255,255,0.1);
    padding: 15px;
    border-radius: 8px;
    text-align: center;
    text-decoration: none;
    color: white;
    transition: background 0.3s;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.picker-item:hover {
    background: rgba(255,255,255,0.2);
}
.picker-item .icon {
    font-size: 1.5rem;
}
.btn-icon.delete:hover {
    color: #e74c3c;
}
</style>
@endsection
