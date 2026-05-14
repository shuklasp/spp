@php 
    $content = $block->getContent();
    $style = $content['_style'] ?? [];
    $sectionClasses = [
        'section-block',
        'block-type-' . $block->block_type,
        ($style['bg_type'] ?? 'default') !== 'default' ? 'section-' . $style['bg_type'] : '',
        'padding-' . ($style['padding'] ?? 'medium'),
        'align-' . ($style['text_align'] ?? 'left'),
        ($style['animation'] ?? 'none') !== 'none' ? 'animate-' . $style['animation'] : ''
    ];
@endphp

<section class="{{ implode(' ', array_filter($sectionClasses)) }}">
    @if($block->block_type == 'hero')
        <div class="container">
            <div class="hero-content">
                <h1>{{ $content['title'] ?? '' }}</h1>
                <p>{{ $content['subtitle'] ?? '' }}</p>
                @if(!empty($content['button_text']))
                    <a href="#" class="btn btn-primary">{{ $content['button_text'] }}</a>
                @endif
            </div>
        </div>
    @elseif($block->block_type == 'text')
        <div class="container">
            <div class="prose">
                {!! $content['content'] ?? '' !!}
            </div>
        </div>
    @elseif($block->block_type == 'features')
        <div class="container">
            @if(!empty($content['title']))
                <h2 style="margin-bottom: 50px;">{{ $content['title'] }}</h2>
            @endif
            <div class="features-grid">
                @foreach($content['items'] ?? [] as $item)
                    <div class="feature-card">
                        <div style="font-size: 2rem; margin-bottom: 15px;">{{ $item['icon'] ?? '✨' }}</div>
                        <h3>{{ $item['text'] ?? 'Feature' }}</h3>
                    </div>
                @endforeach
            </div>
        </div>
    @elseif($block->block_type == 'dynamic_list')
        <div class="container">
            @if(!empty($content['title']))
                <h2 style="margin-bottom: 30px;">{{ $content['title'] }}</h2>
            @endif
            @php $entities = $block->resolveEntities(); @endphp
            <div class="entities-grid" style="display: grid; gap: 15px; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));">
                @foreach($entities as $entity)
                    <div class="entity-card glass-panel" style="padding: 20px; border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; background: rgba(255,255,255,0.05); text-align: left;">
                        <h3 style="margin-top: 0;">{{ $entity->title ?? ($entity->username ?? 'Untitled') }}</h3>
                        <p style="color: #94a3b8; font-size: 0.85rem; margin-bottom: 0;">
                            Type: {{ $entity->bundle ?? 'Generic' }} | 
                            {{ $entity->created }}
                        </p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</section>
