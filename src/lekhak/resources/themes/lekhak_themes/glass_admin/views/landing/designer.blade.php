@extends('layout')

@section('actions')
<div style="display: flex; gap: 10px; align-items: center;">
    <div id="save-status" style="font-size: 0.75rem; color: var(--accent-primary); opacity: 0; transition: opacity 0.3s;">Saving...</div>
    <div style="position: relative;">
        <button class="btn btn-primary" onclick="this.nextElementSibling.style.display = this.nextElementSibling.style.display === 'block' ? 'none' : 'block'">
            ➕ Add Block
        </button>
        <div style="display: none; position: absolute; top: 100%; right: 0; background: var(--glass-bg); backdrop-filter: blur(20px); border: 1px solid var(--glass-border); border-radius: var(--radius-md); width: 200px; padding: 10px; z-index: 1000; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
            <a href="javascript:void(0)" onclick="addBlockReactive('hero'); this.parentElement.style.display='none';" class="dropdown-item">Hero Section</a>
            <a href="javascript:void(0)" onclick="addBlockReactive('text'); this.parentElement.style.display='none';" class="dropdown-item">Text Block</a>
            <a href="javascript:void(0)" onclick="addBlockReactive('features'); this.parentElement.style.display='none';" class="dropdown-item">Features Grid</a>
            <a href="javascript:void(0)" onclick="addBlockReactive('cta'); this.parentElement.style.display='none';" class="dropdown-item">Call to Action</a>
            <a href="javascript:void(0)" onclick="addBlockReactive('dynamic_list'); this.parentElement.style.display='none';" class="dropdown-item">Dynamic List</a>
        </div>
    </div>
    <a href="{{ $app_root }}/{{ $page->alias }}" target="_blank" class="btn btn-secondary">👁️ Preview Page</a>
</div>
@endsection

@section('content')
<script src="{{ $admin_root }}/../resources/js/sortable.min.js"></script>

<style>
    .dropdown-item {
        display: block;
        padding: 10px;
        color: var(--text-main);
        text-decoration: none;
        border-radius: var(--radius-sm, 4px);
        transition: all 0.2s ease;
        font-size: 0.85rem;
        font-weight: 600;
    }
    .dropdown-item:hover {
        background: rgba(255, 255, 255, 0.1);
        color: var(--accent-primary);
        padding-left: 14px;
    }
    .designer-container {
        display: flex;
        gap: 30px;
        margin-top: -20px;
        height: calc(100vh - 180px);
    }

    .blocks-library {
        width: 260px;
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-lg);
        padding: 20px;
        display: flex;
        flex-direction: column;
        gap: 15px;
        overflow-y: auto;
    }

    .library-item {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: var(--radius-md);
        padding: 15px;
        cursor: grab;
        transition: all 0.2s;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .library-item:hover {
        background: rgba(255, 255, 255, 0.1);
        border-color: var(--accent-primary);
        transform: translateY(-2px);
    }

    .library-item i {
        font-size: 1.5rem;
        margin-bottom: 5px;
    }

    .library-item span {
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--text-main);
    }

    .builder-viewport {
        flex: 1;
        overflow-y: auto;
        padding: 10px;
        padding-right: 20px;
    }

    .builder-layout {
        display: grid;
        grid-template-areas: 
            "header header"
            "main sidebar"
            "footer footer";
        grid-template-columns: 1fr 300px;
        gap: 24px;
    }

    @media (max-width: 1200px) {
        .designer-container {
            flex-direction: column;
            height: auto;
        }
        .blocks-library {
            width: 100%;
            flex-direction: row;
            overflow-x: auto;
        }
        .library-item {
            min-width: 150px;
        }
        .builder-layout {
            grid-template-columns: 1fr;
            grid-template-areas: 
                "header"
                "main"
                "sidebar"
                "footer";
        }
    }

    .region {
        border: 2px dashed rgba(255, 255, 255, 0.05);
        border-radius: var(--radius-lg);
        padding: 30px 20px 20px 20px;
        background: rgba(255, 255, 255, 0.01);
        transition: all 0.3s ease;
        position: relative;
        min-height: 120px;
    }

    .region.sortable-ghost {
        background: rgba(var(--accent-primary-rgb), 0.1);
        border-color: var(--accent-primary);
    }

    .region-label {
        position: absolute;
        top: -12px;
        left: 20px;
        background: var(--accent-primary);
        padding: 4px 12px;
        font-size: 0.65rem;
        font-weight: 800;
        text-transform: uppercase;
        color: #fff;
        border-radius: 20px;
        z-index: 10;
    }

    .block-card {
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-md);
        padding: 20px;
        margin-bottom: 20px;
        backdrop-filter: blur(20px);
        cursor: grab;
        transition: all 0.2s ease;
    }

    .block-card:hover {
        border-color: rgba(255, 255, 255, 0.3);
        transform: translateY(-2px);
    }

    .block-controls {
        display: flex;
        gap: 10px;
        margin-top: 15px;
        opacity: 0;
        transition: opacity 0.2s;
    }

    .block-card:hover .block-controls {
        opacity: 1;
    }

    .control-btn {
        padding: 6px 12px;
        font-size: 0.75rem;
        font-weight: 600;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 6px;
        color: var(--text-main);
        text-decoration: none;
        cursor: pointer;
        transition: all 0.2s;
    }
    .control-btn:hover {
        background: rgba(255, 255, 255, 0.15);
        border-color: var(--accent-primary);
    }

    .empty-state {
        text-align: center;
        color: rgba(255, 255, 255, 0.1);
        font-size: 0.8rem;
        padding: 40px 20px;
    }
</style>

<div class="designer-container">
    <div class="blocks-library" id="library">
        <div style="font-size: 0.7rem; text-transform: uppercase; color: var(--text-dim); margin-bottom: 10px; font-weight: 700;">Library</div>
        <div class="library-item" data-type="hero">
            <span>🚀</span>
            <span>Hero Section</span>
        </div>
        <div class="library-item" data-type="text">
            <span>📝</span>
            <span>Text Block</span>
        </div>
        <div class="library-item" data-type="features">
            <span>✨</span>
            <span>Features Grid</span>
        </div>
        <div class="library-item" data-type="cta">
            <span>📢</span>
            <span>Call to Action</span>
        </div>
        <div class="library-item" data-type="dynamic_list">
            <span>🔄</span>
            <span>Dynamic List</span>
        </div>
    </div>

    <div class="builder-viewport">
        <div class="builder-layout" id="builder">
            @php
                $regions = ['header', 'main', 'sidebar', 'footer'];
                $blocksByRegion = [];
                foreach($regions as $r) $blocksByRegion[$r] = [];
                foreach($blocks as $b) {
                    $r = $b->region ?: 'main';
                    if(!isset($blocksByRegion[$r])) $blocksByRegion[$r] = [];
                    $blocksByRegion[$r][] = $b;
                }
            @endphp

            @foreach($regions as $region)
            <div class="region" data-region="{{ $region }}" style="grid-area: {{ $region }};">
                <div class="region-label">{{ $region }}</div>
                
                <div class="block-list" id="region-{{ $region }}" style="min-height: 50px;">
                    @foreach($blocksByRegion[$region] as $block)
                    <div class="block-card" data-id="{{ $block->id }}" id="block-card-{{ $block->id }}">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span class="badge" style="background: rgba(255,255,255,0.1); color: var(--text-dim); font-size: 0.6rem;">{{ strtoupper($block->block_type) }}</span>
                            <span style="font-size: 0.6rem; color: rgba(255,255,255,0.1);">#{{ $block->id }}</span>
                        </div>
                        
                        <div style="margin: 15px 0; font-size: 0.85rem;">
                            @php $data = $block->getContent(); @endphp
                            <strong class="block-title">{{ $data['title'] ?? '' }}</strong>
                            <div class="block-preview" style="opacity: 0.6; margin-top: 5px; font-size: 0.75rem;">
                                {{ substr(strip_tags($data['content'] ?? $data['text'] ?? ''), 0, 100) }}...
                            </div>
                        </div>

                        <div class="block-controls">
                            <button type="button" class="control-btn" onclick="openEditModal({{ $block->id }})">Edit</button>
                            <button type="button" class="control-btn" style="color: var(--danger);" onclick="deleteBlockReactive({{ $block->id }}, this)">Delete</button>
                        </div>
                    </div>
                    @endforeach

                    @if(empty($blocksByRegion[$region]))
                    <div class="empty-state">Drag blocks here</div>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>

<!-- Native Glassmorphism Edit Modal Overlay -->
<div id="edit-modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); z-index: 2000; align-items: center; justify-content: center;">
    <div class="glass-panel" style="width: 100%; max-width: 650px; padding: 30px; border-radius: var(--radius-lg); position: relative; animation: zoom 0.3s ease; max-height: 90vh; display: flex; flex-direction: column;">
        <div id="edit-modal-content">
            <!-- Dynamically injected modal markup -->
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const regions = ['header', 'main', 'sidebar', 'footer'];
        const saveStatus = document.getElementById('save-status');
        const modalOverlay = document.getElementById('edit-modal-overlay');
        const modalContent = document.getElementById('edit-modal-content');

        // Initialize Regions
        regions.forEach(regionId => {
            new Sortable(document.getElementById('region-' + regionId), {
                group: 'shared',
                animation: 150,
                onEnd: saveLayout
            });
        });

        // Initialize Library (Clone on drag)
        new Sortable(document.getElementById('library'), {
            group: {
                name: 'shared',
                pull: 'clone',
                put: false
            },
            sort: false,
            animation: 150,
            onEnd: function (evt) {
                if (evt.to.classList.contains('block-list')) {
                    const type = evt.item.getAttribute('data-type');
                    const region = evt.to.parentElement.getAttribute('data-region');
                    // Add reactively and pass the temporary clone node to be replaced
                    addBlockReactive(type, region, evt.newIndex + 1, evt.item);
                }
            }
        });

        window.addBlockReactive = async function(type, region = 'main', weight = null, placeholderItem = null) {
            saveStatus.style.opacity = '1';
            saveStatus.innerText = 'Adding block...';
            
            let url = `{{ $admin_root }}/landing/block/add/{{ $page->id }}?type=${type}&region=${region}&ajax=1`;
            if (weight !== null) url += `&weight=${weight}`;

            try {
                const res = await fetch(url);
                if (res.ok) {
                    const data = await res.json();
                    if (data.success && data.block) {
                        const b = data.block;
                        const cardHtml = `
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span class="badge" style="background: rgba(255,255,255,0.1); color: var(--text-dim); font-size: 0.6rem;">${b.block_type.toUpperCase()}</span>
                                <span style="font-size: 0.6rem; color: rgba(255,255,255,0.1);">#${b.id}</span>
                            </div>
                            
                            <div style="margin: 15px 0; font-size: 0.85rem;">
                                <strong class="block-title">${b.title || ''}</strong>
                                <div class="block-preview" style="opacity: 0.6; margin-top: 5px; font-size: 0.75rem;">
                                    ${b.preview || ''}...
                                </div>
                            </div>

                            <div class="block-controls">
                                <button type="button" class="control-btn" onclick="openEditModal(${b.id})">Edit</button>
                                <button type="button" class="control-btn" style="color: var(--danger);" onclick="deleteBlockReactive(${b.id}, this)">Delete</button>
                            </div>
                        `;

                        const card = document.createElement('div');
                        card.className = 'block-card';
                        card.id = 'block-card-' + b.id;
                        card.setAttribute('data-id', b.id);
                        card.innerHTML = cardHtml;

                        const listEl = document.getElementById('region-' + region);
                        const emptyState = listEl.querySelector('.empty-state');
                        if (emptyState) emptyState.remove();

                        if (placeholderItem && placeholderItem.parentNode) {
                            placeholderItem.parentNode.replaceChild(card, placeholderItem);
                        } else {
                            listEl.appendChild(card);
                        }

                        saveStatus.innerText = 'Added ✅';
                        setTimeout(() => saveStatus.style.opacity = '0', 2000);

                        saveLayout();
                        return;
                    }
                }
            } catch (err) {
                console.error(err);
            }

            saveStatus.innerText = 'Error adding block';
            if (placeholderItem) placeholderItem.remove();
        };

        window.openEditModal = async function(id) {
            modalOverlay.style.display = 'flex';
            modalContent.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--text-dim);">Loading block configuration...</div>';
            try {
                const res = await fetch(`{{ $admin_root }}/landing/block/edit/${id}?ajax=1`);
                if (res.ok) {
                    modalContent.innerHTML = await res.text();
                } else {
                    modalContent.innerHTML = '<div style="color: var(--danger); text-align: center; padding: 20px;">Failed to load configuration.</div>';
                }
            } catch (err) {
                modalContent.innerHTML = '<div style="color: var(--danger); text-align: center; padding: 20px;">Network error loading configuration.</div>';
            }
        };

        window.closeEditModal = function() {
            modalOverlay.style.display = 'none';
        };

        window.submitModalEdit = async function(evt, id) {
            evt.preventDefault();
            const form = evt.target;
            const formData = new FormData(form);
            const dataParams = new URLSearchParams();
            for (const pair of formData) {
                dataParams.append(pair[0], pair[1]);
            }

            saveStatus.style.opacity = '1';
            saveStatus.innerText = 'Updating block...';

            try {
                const res = await fetch(`{{ $admin_root }}/landing/block/edit/${id}?ajax=1`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: dataParams.toString()
                });

                if (res.ok) {
                    const data = await res.json();
                    if (data.success) {
                        // Update card UI live
                        const card = document.getElementById('block-card-' + id);
                        if (card) {
                            const titleEl = card.querySelector('.block-title');
                            const previewEl = card.querySelector('.block-preview');
                            if (titleEl) titleEl.innerText = data.title || '';
                            if (previewEl) previewEl.innerText = (data.preview || '') + '...';
                        }
                        closeEditModal();
                        saveStatus.innerText = 'Updated ✅';
                        setTimeout(() => saveStatus.style.opacity = '0', 2000);
                        return;
                    }
                }
            } catch (err) {
                console.error('Update failed', err);
            }
            saveStatus.innerText = 'Error updating block';
        };

        window.deleteBlockReactive = async function(id, btnEl) {
            if (!confirm('Are you sure you want to delete this block?')) return;
            
            const card = document.getElementById('block-card-' + id) || btnEl.closest('.block-card');
            const listEl = card ? card.parentNode : null;
            
            if (card) {
                card.style.transition = 'all 0.3s ease';
                card.style.transform = 'scale(0.9)';
                card.style.opacity = '0';
            }

            saveStatus.style.opacity = '1';
            saveStatus.innerText = 'Deleting...';

            try {
                const res = await fetch(`{{ $admin_root }}/landing/block/delete/${id}?ajax=1`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (res.ok) {
                    const data = await res.json();
                    if (data.success) {
                        if (card) card.remove();
                        // Check if list is now empty to display empty state
                        if (listEl && listEl.querySelectorAll('.block-card').length === 0) {
                            if (!listEl.querySelector('.empty-state')) {
                                const emptyDiv = document.createElement('div');
                                emptyDiv.className = 'empty-state';
                                emptyDiv.innerText = 'Drag blocks here';
                                listEl.appendChild(emptyDiv);
                            }
                        }
                        saveStatus.innerText = 'Deleted 🗑️';
                        setTimeout(() => saveStatus.style.opacity = '0', 2000);
                        saveLayout();
                        return;
                    }
                }
            } catch (err) {
                console.error(err);
            }
            if (card) {
                card.style.transform = 'none';
                card.style.opacity = '1';
            }
            saveStatus.innerText = 'Failed to delete';
        };

        async function saveLayout() {
            saveStatus.style.opacity = '1';
            saveStatus.innerText = 'Saving...';
            
            const layout = [];
            regions.forEach(region => {
                const el = document.getElementById('region-' + region);
                const blocks = el.querySelectorAll('.block-card');
                blocks.forEach((block, index) => {
                    layout.push({
                        id: block.getAttribute('data-id'),
                        region: region,
                        weight: index + 1
                    });
                });
            });

            const response = await fetch('{{ $admin_root }}/landing/layout/update/{{ $page->id }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ blocks: layout })
            });
            
            if (response.ok) {
                saveStatus.innerText = 'Saved ✅';
                setTimeout(() => saveStatus.style.opacity = '0', 2000);
            }
        }
    });
</script>
@endsection
