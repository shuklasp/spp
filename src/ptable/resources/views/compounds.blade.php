@extends('layouts.app')

@section('title', $title)

@section('styles')
<style>
    .compounds-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1.5rem;
        margin-top: 2rem;
    }
    
    .compound-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 12px;
        overflow: hidden;
        transition: transform 0.2s, box-shadow 0.2s;
        display: flex;
        flex-direction: column;
    }
    
    .compound-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 24px -8px rgba(0,0,0,0.1);
    }
    
    .compound-image-container {
        width: 100%;
        height: 200px;
        background: #f5f5f5; /* Match the grey background of PubChem PNGs */
        display: flex;
        align-items: center;
        justify-content: center;
        border-bottom: 1px solid var(--border);
        padding: 1rem;
        overflow: hidden;
    }
    
    .compound-image {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
        transform: scale(2.8); /* Significantly scale up to combat PubChem's massive whitespace */
    }
    
    .compound-details {
        padding: 1.5rem;
        flex: 1;
        display: flex;
        flex-direction: column;
    }
    
    .compound-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 0.5rem;
    }
    
    .compound-name {
        font-size: 1.1rem;
        font-weight: 700;
        margin: 0;
        color: var(--text);
    }
    
    .compound-formula {
        background: var(--primary-light);
        color: var(--primary);
        padding: 0.25rem 0.5rem;
        border-radius: 6px;
        font-family: monospace;
        font-weight: 700;
        font-size: 0.9rem;
    }
    
    .compound-desc {
        font-size: 0.9rem;
        color: var(--muted);
        margin-bottom: 1rem;
        line-height: 1.5;
        flex: 1;
    }
    
    .compound-uses {
        font-size: 0.85rem;
        border-top: 1px dashed var(--border);
        padding-top: 1rem;
    }
    
    .compound-uses-label {
        font-weight: 600;
        color: var(--text);
        display: block;
        margin-bottom: 0.25rem;
    }
    
    .element-header {
        text-align: center;
        margin-bottom: 3rem;
    }
    
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        background: var(--surface);
        border: 1px dashed var(--border);
        border-radius: 12px;
        color: var(--muted);
    }
</style>
@endsection

@section('content')
<div class="element-header">
    <a href="@url('')" style="display: inline-block; margin-bottom: 1rem; color: var(--primary); text-decoration: none; font-weight: 500;">← Back to Periodic Table</a>
    <h1 style="font-size: 2.5rem;">Compounds of {{ $element['name'] }} ({{ $element['symbol'] }})</h1>
    <p>Explore the famous chemical compounds containing {{ $element['name'] }}.</p>
</div>

@if(empty($compounds))
    <div class="empty-state">
        <div style="font-size: 3rem; margin-bottom: 1rem;">🧪</div>
        <h3>No compounds found</h3>
        <p>We haven't added curated compounds for {{ $element['name'] }} yet.</p>
    </div>
@else
    <div class="compounds-grid">
        @foreach($compounds as $compound)
            <div class="compound-card">
                <div class="compound-image-container">
                    <!-- Ensure you check if image exists or use fallback -->
                    <img src="@url('assets/compounds/' . $compound['id'] . '.png')" alt="{{ $compound['name'] }} Structure" class="compound-image" onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'100\' height=\'100\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%23cbd5e1\' stroke-width=\'2\'><circle cx=\'12\' cy=\'12\' r=\'10\'></circle><line x1=\'12\' y1=\'8\' x2=\'12\' y2=\'12\'></line><line x1=\'12\' y1=\'16\' x2=\'12.01\' y2=\'16\'></line></svg>';" />
                </div>
                <div class="compound-details">
                    <div class="compound-header">
                        <h3 class="compound-name">{{ $compound['name'] }}</h3>
                        <div class="compound-formula">{!! $compound['html_formula'] !!}</div>
                    </div>
                    <p class="compound-desc">{{ $compound['description'] }}</p>
                    <div class="compound-uses">
                        <span class="compound-uses-label">Primary Uses:</span>
                        {{ $compound['uses'] }}
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
@endsection
