@extends('admin.layout')

@section('content')
<div class="lekhak-admin-dashboard">
    <h1>Lekhak CMS Dashboard</h1>
    
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Content Nodes</h3>
            <span class="count">{{ $nodeCount }}</span>
        </div>
        <div class="stat-card">
            <h3>Content Types</h3>
            <span class="count">{{ $typeCount }}</span>
        </div>
    </div>

    <div class="actions">
        <a href="{{ $admin_root }}/structure/types" class="btn btn-primary">Manage Structure</a>
        <a href="#editor" class="btn btn-secondary" data-spp-evt="nav-editor" data-spp-type="click">Add Content</a>
    </div>

    <!-- Live Component Implementation -->
    <div class="mt-4">
        {!! \App\Lekhak\Components\LiveStats::embed() !!}
    </div>
</div>
@endsection
