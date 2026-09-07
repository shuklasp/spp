@extends('layouts.base')

@section('title', ($view['title'] ?? 'Dynamic View') . ' — ' . ($project['title'] ?? $project_id))

@section('sidebar')
    @spppartial('partials/sidebar.blade.php', [
        'project' => $project ?? null,
        'project_id' => $project_id ?? null,
        'version' => $project['default_version'] ?? 'v1',
        'current_path' => null
    ])
@endsection

@section('content')
<div class="sppdocs-view-page" style="padding: 1.5rem 0;">
    <div style="margin-bottom: 2rem; border-bottom: 1px solid var(--vp-c-divider); padding-bottom: 1rem;">
        <h1 style="margin: 0 0 0.5rem 0; font-size: 2.2rem; font-weight: 800; color: var(--vp-c-text-1);">
            {{ $view['title'] ?? 'Dynamic View' }}
        </h1>
        @if(!empty($view['description']))
            <p style="margin: 0; font-size: 1.05rem; color: var(--vp-c-text-2);">
                {{ $view['description'] }}
            </p>
        @endif
    </div>

    @spppartial('partials/views_renderer.blade.php', [
        'viewResult' => $viewResult,
        'project_id' => $project_id
    ])
</div>
@endsection