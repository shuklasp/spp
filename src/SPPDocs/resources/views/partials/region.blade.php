{{-- Reusable Theme Region Partial --}}
@if(!empty($blocks))
<div class="sppdocs-theme-region sppdocs-region-{{ str_replace('_', '-', $region) }}" data-region="{{ $region }}">
    @foreach($blocks as $block)
        <div class="sppdocs-block sppdocs-block-{{ str_replace('_', '-', $block['type'] ?? 'default') }}" id="block-{{ $block['id'] }}" style="margin-bottom: 1.25rem;">
            @if(!empty($block['title']) && empty($block['hide_title']))
                <h3 class="sppdocs-block-title" style="font-size: 0.95rem; font-weight: 700; color: var(--vp-c-text-1); margin: 0 0 0.5rem 0; text-transform: uppercase; letter-spacing: 0.05em;">
                    {{ $block['title'] }}
                </h3>
            @endif
            
            <div class="sppdocs-block-content">
                @if(($block['type'] ?? '') === 'view')
                    @spppartial('partials/blocks/view.blade.php', ['block' => $block, 'project_id' => $project_id, 'context' => $context])
                @elseif(($block['type'] ?? '') === 'alert')
                    @spppartial('partials/blocks/alert.blade.php', ['block' => $block, 'project_id' => $project_id, 'context' => $context])
                @elseif(($block['type'] ?? '') === 'taxonomy_tree')
                    @spppartial('partials/blocks/taxonomy_tree.blade.php', ['block' => $block, 'project_id' => $project_id, 'context' => $context])
                @elseif(($block['type'] ?? '') === 'toc')
                    @spppartial('partials/blocks/toc.blade.php', ['block' => $block, 'project_id' => $project_id, 'context' => $context])
                @else
                    @spppartial('partials/blocks/custom.blade.php', ['block' => $block, 'project_id' => $project_id, 'context' => $context])
                @endif
            </div>
        </div>
    @endforeach
</div>
@endif