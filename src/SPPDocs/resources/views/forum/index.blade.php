@extends('layouts.base')
@section('title', 'Forums - ' . ($project['title'] ?? 'Project'))

@section('sidebar')
    @spppartial('partials/sidebar.blade.php', ['project' => $project, 'project_id' => $project_id, 'version' => 'v1', 'current_path' => null])
@endsection

@section('content')
    <h1>Community Forums</h1>
    <p>Welcome to the discussion boards for {{ $project['title'] ?? 'this project' }}.</p>
    
    <div style="margin-top: 2rem; display: flex; flex-direction: column; gap: 1rem;">
        @foreach($forums as $forum)
            <div style="border: 1px solid var(--vp-c-divider); border-radius: 8px; padding: 1.5rem; background: var(--vp-c-bg);">
                <h2 style="margin-top: 0; margin-bottom: 0.5rem; border: none;">
                    <a href="{{ \SPP\App::getBaseUrl() }}/forums/forum?projectId={{ $project_id }}&forumSlug={{ $forum['slug'] }}" style="text-decoration: none;">{{ $forum['title'] }}</a>
                </h2>
                <p style="margin: 0; color: var(--vp-c-text-2);">{{ $forum['description'] }}</p>
                <div style="margin-top: 1rem; font-size: 0.85rem; color: var(--vp-c-text-3);">
                    {{ $forum['thread_count'] }} active threads
                </div>
            </div>
        @endforeach
        
        @if(empty($forums))
            <p>No forums available or you don't have permission to view them.</p>
        @endif
    </div>
@endsection
