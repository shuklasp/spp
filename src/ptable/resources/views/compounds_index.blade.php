@extends('layouts.app')

@section('title', $title)

@section('styles')
<style>
    .compounds-container {
        display: flex;
        gap: 2rem;
        margin-top: 2rem;
        align-items: flex-start;
    }
    
    .filter-sidebar {
        width: 280px;
        flex-shrink: 0;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.5rem;
        position: sticky;
        top: 2rem;
    }
    
    .filter-group {
        margin-bottom: 1.5rem;
    }
    
    .filter-group:last-child {
        margin-bottom: 0;
    }
    
    .filter-title {
        font-weight: 700;
        margin-bottom: 0.75rem;
        color: var(--text);
        font-size: 0.95rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .filter-label {
        display: flex;
        align-items: center;
        margin-bottom: 0.5rem;
        cursor: pointer;
        font-size: 0.95rem;
        color: var(--text);
    }
    
    .filter-label input {
        margin-right: 0.5rem;
        accent-color: var(--primary);
        width: 1.1rem;
        height: 1.1rem;
    }
    
    .compounds-grid {
        flex: 1;
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        gap: 1.5rem;
    }
    
    .compound-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 12px;
        overflow: hidden;
        transition: transform 0.2s, box-shadow 0.2s;
        display: flex;
        flex-direction: column;
        cursor: pointer;
        text-decoration: none;
        color: inherit;
    }
    
    .compound-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 24px -8px rgba(0,0,0,0.1);
        border-color: var(--primary);
    }
    
    .compound-image-container {
        width: 100%;
        height: 180px;
        background: #f5f5f5;
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
        transform: scale(2.5);
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
    
    .compound-tags {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 1rem;
        flex-wrap: wrap;
    }
    
    .tag {
        font-size: 0.75rem;
        padding: 0.2rem 0.5rem;
        border-radius: 4px;
        background: #e2e8f0;
        color: #475569;
        font-weight: 600;
    }
    
    .tag.organic { background: #dcfce7; color: #166534; }
    .tag.inorganic { background: #e0e7ff; color: #3730a3; }
    
    .compound-desc {
        font-size: 0.9rem;
        color: var(--muted);
        line-height: 1.5;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    .page-header {
        margin-bottom: 2rem;
    }
    
    .no-results {
        grid-column: 1 / -1;
        text-align: center;
        padding: 4rem;
        background: var(--surface);
        border: 1px dashed var(--border);
        border-radius: 12px;
        color: var(--muted);
        display: none;
    }

    /* Search Bar */
    .search-box {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 1rem;
        margin-bottom: 1.5rem;
        outline: none;
        transition: border-color 0.2s;
    }
    .search-box:focus {
        border-color: var(--primary);
    }

    @media (max-width: 768px) {
        .compounds-container {
            flex-direction: column;
        }
        .filter-sidebar {
            width: 100%;
            position: static;
        }
    }
</style>
@endsection

@section('content')
<div class="page-header">
    <h1 style="font-size: 2.5rem; margin-bottom: 0.5rem;">Compounds Database</h1>
    <p style="color: var(--muted); font-size: 1.1rem;">Explore and filter through our extensive library of chemical compounds.</p>
</div>

<div class="compounds-container">
    <aside class="filter-sidebar">
        <form id="filterForm" hx-get="@url('compounds')" hx-target="#compoundsGridWrapper" hx-trigger="change, keyup delay:300ms from:#searchInput">
            <input type="text" id="searchInput" name="search" class="search-box" placeholder="Search by name or formula...">
            
            <div class="filter-group">
                <div class="filter-title">Classification</div>
                <label class="filter-label">
                    <input type="checkbox" name="type[]" value="organic"> Organic
                </label>
                <label class="filter-label">
                    <input type="checkbox" name="type[]" value="inorganic"> Inorganic
                </label>
            </div>
            
            <div class="filter-group">
                <div class="filter-title">State of Matter</div>
                <label class="filter-label">
                    <input type="checkbox" name="state[]" value="solid"> Solid
                </label>
                <label class="filter-label">
                    <input type="checkbox" name="state[]" value="liquid"> Liquid
                </label>
                <label class="filter-label">
                    <input type="checkbox" name="state[]" value="gas"> Gas
                </label>
            </div>
            
            <div class="filter-group">
                <div class="filter-title">Common Elements</div>
                <label class="filter-label">
                    <input type="checkbox" name="elements[]" value="C"> Carbon (C)
                </label>
                <label class="filter-label">
                    <input type="checkbox" name="elements[]" value="H"> Hydrogen (H)
                </label>
                <label class="filter-label">
                    <input type="checkbox" name="elements[]" value="O"> Oxygen (O)
                </label>
                <label class="filter-label">
                    <input type="checkbox" name="elements[]" value="N"> Nitrogen (N)
                </label>
                <label class="filter-label">
                    <input type="checkbox" name="elements[]" value="Cl"> Chlorine (Cl)
                </label>
            </div>
            
            <button type="reset" id="resetFilters" onclick="setTimeout(() => document.getElementById('filterForm').dispatchEvent(new Event('change')), 10)" style="width: 100%; padding: 0.5rem; background: var(--background); border: 1px solid var(--border); border-radius: 6px; cursor: pointer; margin-top: 1rem; font-weight: 600;">
                Reset Filters
            </button>
        </form>
    </aside>
    
    <div id="compoundsGridWrapper" style="flex: 1;">
        @include('partials.compounds_grid', ['compounds' => $compounds, 'hasMore' => $hasMore, 'page' => $page])
    </div>
</div>

@endsection

@section('scripts')
<script>
    // JS filtering removed - handled via HTMX + server-side pagination for optimal performance with 1000 items!
</script>
@endsection
