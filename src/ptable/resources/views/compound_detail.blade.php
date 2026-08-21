@extends('layouts.app')

@section('title', $title)

@section('styles')
<style>
    .detail-container {
        max-width: 1200px;
        margin: 0 auto;
        padding-bottom: 4rem;
    }
    
    .back-link {
        display: inline-flex;
        align-items: center;
        margin-bottom: 2rem;
        color: var(--primary);
        text-decoration: none;
        font-weight: 500;
        transition: color 0.2s;
    }
    .back-link:hover {
        color: var(--primary-dark);
        text-decoration: underline;
    }
    
    .hero-section {
        display: flex;
        gap: 3rem;
        margin-bottom: 4rem;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 3rem;
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    }
    
    .hero-visuals {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    
    .viewer-container {
        width: 100%;
        height: 400px;
        background: #f8fafc;
        border: 1px solid var(--border);
        border-radius: 12px;
        position: relative;
        overflow: hidden;
    }
    
    .viewer-label {
        position: absolute;
        top: 1rem;
        left: 1rem;
        background: rgba(255,255,255,0.9);
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--text);
        z-index: 10;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        pointer-events: none;
    }
    
    .viewer-hint {
        position: absolute;
        bottom: 1rem;
        right: 1rem;
        background: rgba(0,0,0,0.6);
        color: white;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.8rem;
        z-index: 10;
        pointer-events: none;
    }
    
    .hero-info {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    
    .compound-title {
        font-size: 3rem;
        font-weight: 800;
        margin-bottom: 0.5rem;
        color: var(--text);
    }
    
    .compound-formula-large {
        font-size: 2rem;
        color: var(--primary);
        font-weight: 700;
        margin-bottom: 1.5rem;
        font-family: monospace;
    }
    
    .tags {
        display: flex;
        gap: 0.75rem;
        margin-bottom: 2rem;
    }
    
    .tag {
        font-size: 0.9rem;
        padding: 0.4rem 1rem;
        border-radius: 6px;
        font-weight: 600;
    }
    
    .tag.organic { background: #dcfce7; color: #166534; }
    .tag.inorganic { background: #e0e7ff; color: #3730a3; }
    .tag.state { background: #f1f5f9; color: #475569; }
    
    .description {
        font-size: 1.1rem;
        line-height: 1.6;
        color: var(--muted);
        margin-bottom: 2rem;
    }
    
    .info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2rem;
    }
    
    .info-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 2rem;
    }
    
    .info-card h3 {
        margin-top: 0;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 1.25rem;
        color: var(--text);
    }
    
    .property-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .property-item {
        display: flex;
        justify-content: space-between;
        padding: 0.75rem 0;
        border-bottom: 1px solid var(--border);
    }
    .property-item:last-child {
        border-bottom: none;
    }
    
    .property-label {
        font-weight: 600;
        color: var(--muted);
    }
    
    .property-value {
        font-weight: 500;
        color: var(--text);
    }
    
    .facts-list {
        padding-left: 1.2rem;
        color: var(--muted);
        line-height: 1.6;
    }
    .facts-list li {
        margin-bottom: 0.75rem;
    }
    
    @media (max-width: 900px) {
        .hero-section {
            flex-direction: column;
            padding: 2rem;
        }
        .info-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endsection

@section('content')
<div class="detail-container">
    <a href="@url('compounds')" class="back-link">← Back to All Compounds</a>
    
    <div class="hero-section">
        <div class="hero-visuals">
            <!-- 3D Viewer Container -->
            <div class="viewer-container" id="molecule-viewer">
                <div class="viewer-label">Interactive 3D Structure</div>
                <div class="viewer-hint">Drag to rotate • Scroll to zoom</div>
            </div>
        </div>
        
        <div class="hero-info">
            <h1 class="compound-title">{{ $compound['name'] }}</h1>
            <div class="compound-formula-large">{!! $compound['html_formula'] !!}</div>
            
            <div class="tags">
                @if($compound['organic'])
                    <span class="tag organic">Organic</span>
                @else
                    <span class="tag inorganic">Inorganic</span>
                @endif
                <span class="tag state">{{ $compound['state'] }}</span>
            </div>
            
            <p class="description">{{ $compound['description'] }}</p>
            
            <div style="margin-bottom: 2rem;">
                <h4 style="margin-bottom: 0.5rem; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--muted);">Primary Uses</h4>
                @if(strlen($compound['uses']) > 150 || strpos($compound['uses'], '.') !== false)
                    <p class="description" style="font-size: 0.95rem; margin-bottom: 0;">{{ str_replace('===', '', $compound['uses']) }}</p>
                @else
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        @foreach(array_map('trim', explode(',', $compound['uses'])) as $use)
                            @if(!empty($use))
                                <span class="tag state" style="background: var(--background); border: 1px solid var(--border);">{{ $use }}</span>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
            
            <div style="display: flex; gap: 1rem; flex-wrap: wrap; margin-top: auto;">
                <span class="tag state" style="background: var(--background);">Elements: {{ implode(', ', $compound['elements']) }}</span>
                <span class="tag state" style="background: var(--background);">PubChem CID: {{ $compound['pubchem_cid'] }}</span>
            </div>
        </div>
    </div>
    
    <div class="info-grid">
        <div class="info-card">
            <h3>📊 Physical Properties</h3>
            <ul class="property-list">
                <li class="property-item">
                    <span class="property-label">Appearance</span>
                    <span class="property-value">{{ $compound['properties']['physical']['appearance'] ?? 'N/A' }}</span>
                </li>
                <li class="property-item">
                    <span class="property-label">Density</span>
                    <span class="property-value">{{ $compound['properties']['physical']['density'] ?? 'N/A' }}</span>
                </li>
                <li class="property-item">
                    <span class="property-label">Melting Point</span>
                    <span class="property-value">{{ $compound['properties']['physical']['melting_point'] ?? 'N/A' }}</span>
                </li>
                <li class="property-item">
                    <span class="property-label">Boiling Point</span>
                    <span class="property-value">{{ $compound['properties']['physical']['boiling_point'] ?? 'N/A' }}</span>
                </li>
                <li class="property-item">
                    <span class="property-label">Solubility</span>
                    <span class="property-value">{{ $compound['properties']['physical']['solubility'] ?? 'N/A' }}</span>
                </li>
                <li class="property-item">
                    <span class="property-label">Molar Mass</span>
                    <span class="property-value">{{ $compound['properties']['physical']['molar_mass'] ?? 'N/A' }}</span>
                </li>
            </ul>
        </div>
        
        <div class="info-card">
            <h3>⚠️ Chemical & Safety Properties</h3>
            <ul class="property-list">
                <li class="property-item">
                    <span class="property-label">pH Level</span>
                    <span class="property-value">{{ $compound['properties']['chemical']['ph'] ?? 'N/A' }}</span>
                </li>
                <li class="property-item" style="flex-direction: column; align-items: flex-start; gap: 0.5rem;">
                    <span class="property-label">Hazards & Toxicity</span>
                    <span class="property-value">{{ $compound['properties']['chemical']['hazards'] ?? 'N/A' }}</span>
                </li>
                <li class="property-item" style="flex-direction: column; align-items: flex-start; gap: 0.5rem;">
                    <span class="property-label">Reactivity</span>
                    <span class="property-value">{{ $compound['properties']['chemical']['reactivity'] ?? 'N/A' }}</span>
                </li>
            </ul>
        </div>
        
        <div class="info-card" style="grid-column: 1 / -1;">
            <h3>💡 Fascinating Facts</h3>
            <ul class="facts-list">
                @foreach($compound['facts'] as $fact)
                    <li>{{ $fact }}</li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<!-- Load local 3Dmol.js -->
<script src="@url('assets/js/3Dmol-min.js')"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    let viewer = $3Dmol.createViewer("molecule-viewer", {
        defaultcolors: $3Dmol.rasmolElementColors
    });
    
    viewer.setBackgroundColor(0xf8fafc);
    
    // Load the local SDF file
    const sdfUrl = "@url('assets/compounds/3d/' . $compound['id'] . '.sdf')";
    
    fetch(sdfUrl)
        .then(response => {
            if (!response.ok) throw new Error("SDF not found");
            return response.text();
        })
        .then(data => {
            viewer.addModel(data, "sdf");
            viewer.setStyle({}, { stick: { radius: 0.15 }, sphere: { scale: 0.3 } });
            viewer.zoomTo();
            viewer.render();
            // Optional: gently spin the molecule
            // viewer.spin("y", 0.5);
        })
        .catch(error => {
            console.error("Error loading 3D model:", error);
            document.getElementById('molecule-viewer').innerHTML = `
                <div style="width:100%; height:100%; display:flex; flex-direction:column; align-items:center; justify-content:center; background:#fff;">
                    <img src="@url('assets/compounds/' . $compound['id'] . '.png')" alt="2D Structure" style="max-height:80%; object-fit:contain;">
                    <p style="color:var(--muted); font-size:0.9rem; margin-top:1rem;">3D model unavailable, showing 2D structure</p>
                </div>
            `;
        });
});
</script>
@endsection
