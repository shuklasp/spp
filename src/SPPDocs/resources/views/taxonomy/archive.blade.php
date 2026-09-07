@extends('layouts.base')

@section('title', ($term['name'] ?? 'Term') . ' — ' . ($project['title'] ?? $project_id))

@section('sidebar')
    @spppartial('partials/sidebar.blade.php', [
        'project' => $project ?? null,
        'project_id' => $project_id ?? null,
        'version' => $project['default_version'] ?? 'v1',
        'current_path' => null
    ])
@endsection

@section('content')
<div class="sppdocs-taxonomy-archive" style="padding: 1.5rem 0;">
    <!-- Term Header Banner -->
    <div style="margin-bottom: 2rem; border-bottom: 1px solid var(--vp-c-divider); padding-bottom: 1.25rem;">
        <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.82rem; color: var(--vp-c-text-3); margin-bottom: 0.5rem;">
            <span>{{ $vocabulary['title'] ?? 'Taxonomy' }}</span>
            <span>&rsaquo;</span>
            <span style="color: var(--vp-c-brand); font-weight: 600;">{{ $term['name'] ?? 'Term' }}</span>
        </div>

        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <span style="font-size: 2rem;">{{ $term['icon'] ?? '🏷️' }}</span>
            <h1 style="margin: 0; font-size: 2.2rem; font-weight: 800; color: var(--vp-c-text-1);">
                {{ $term['name'] ?? 'Term' }}
            </h1>
            <span style="background: {{ $term['color'] ?? '#3b82f6' }}20; color: {{ $term['color'] ?? '#3b82f6' }}; border: 1px solid {{ $term['color'] ?? '#3b82f6' }}50; padding: 0.2rem 0.6rem; border-radius: 12px; font-size: 0.78rem; font-weight: 700;">
                {{ count($documents) }} article{{ count($documents) === 1 ? '' : 's' }}
            </span>
        </div>

        @if(!empty($term['description']))
            <p style="margin: 0.75rem 0 0 0; font-size: 1.05rem; color: var(--vp-c-text-2); max-width: 700px;">
                {{ $term['description'] }}
            </p>
        @endif
    </div>

    <!-- Documents Grid -->
    @if(empty($documents))
        <div style="text-align: center; padding: 3rem 1.5rem; background: var(--vp-c-bg-soft); border-radius: 8px; border: 1px dashed var(--vp-c-divider); color: var(--vp-c-text-3);">
            <p style="margin: 0; font-size: 1rem;">No documentation pages or articles are tagged with this term yet.</p>
        </div>
    @else
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.25rem;">
            @foreach($documents as $doc)
                @php
                    $docUrl = \SPP\App::getBaseUrl() . '/project/' . urlencode($project_id) . '/' . urlencode($doc['slug']);
                @endphp
                <div style="background: var(--vp-c-bg); border: 1px solid var(--vp-c-divider); border-radius: 8px; padding: 1.25rem; display: flex; flex-direction: column; justify-content: space-between;">
                    <div>
                        <div style="font-size: 0.78rem; color: var(--vp-c-text-3); margin-bottom: 0.4rem;">
                            {{ is_numeric($doc['date']) ? date('M j, Y', $doc['date']) : $doc['date'] }}
                        </div>

                        <h3 style="margin: 0 0 0.5rem 0; font-size: 1.15rem; line-height: 1.35;">
                            <a href="{{ $docUrl }}" style="color: var(--vp-c-text-1); text-decoration: none; font-weight: 700;">
                                {{ $doc['title'] }}
                            </a>
                        </h3>

                        @if(!empty($doc['description']))
                            <p style="margin: 0 0 0.75rem 0; font-size: 0.85rem; color: var(--vp-c-text-2); line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                                {{ $doc['description'] }}
                            </p>
                        @endif
                    </div>

                    <div style="border-top: 1px solid var(--vp-c-divider-light); padding-top: 0.75rem; margin-top: 0.5rem; display: flex; justify-content: space-between; align-items: center; font-size: 0.78rem;">
                        <span style="color: var(--vp-c-text-3);">{{ $doc['author'] ?? 'SPPDocs' }}</span>
                        <a href="{{ $docUrl }}" style="color: var(--vp-c-brand); text-decoration: none; font-weight: 600;">Read &rarr;</a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection