@extends('layouts.base')
@section('title', $project['title'] . ' News & Updates')

@section('sidebar')
    @spppartial('partials/sidebar.blade.php', [
        'project' => $project,
        'project_id' => $project_id,
        'version' => $version ?? ($project['default_version'] ?? 'v1'),
        'current_path' => 'blog'
    ])
@endsection

@section('content')
<div class="sppdocs-blog-container" style="max-width: 920px; margin: 0 auto; padding: 1.5rem 0 4rem 0;">
    
    <!-- Breadcrumb Context Bar -->
    <nav class="sppdocs-breadcrumb" style="display: flex; align-items: center; gap: 8px; font-size: 0.85rem; color: var(--vp-c-text-3); margin-bottom: 1.5rem; flex-wrap: wrap;">
        <a href="{{ \SPP\App::getBaseUrl() }}/" style="color: var(--vp-c-text-2); text-decoration: none;">Platform</a>
        <span>/</span>
        <a href="{{ \SPP\App::getBaseUrl() }}/project/{{ $project_id }}" style="color: var(--vp-c-text-2); text-decoration: none; font-weight: 500;">
            📁 {{ $project['title'] }}
        </a>
        <span>/</span>
        <span style="color: var(--vp-c-brand); font-weight: 600;">📰 News & Updates</span>
    </nav>

    <!-- Hero Header Banner -->
    <header style="margin-bottom: 2.5rem; padding-bottom: 2rem; border-bottom: 1px solid var(--vp-c-divider);">
        <div style="display: inline-flex; align-items: center; gap: 8px; padding: 4px 12px; border-radius: 999px; background: var(--vp-c-bg-soft); border: 1px solid rgba(234, 88, 12, 0.2); color: var(--vp-c-brand); font-size: 0.78rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 1rem;">
            <span>📰 Institutional Blog &amp; Releases</span>
        </div>
        
        <h1 style="font-size: 2.5rem; font-weight: 800; color: var(--vp-c-text-1); margin: 0 0 0.75rem 0; letter-spacing: -0.03em; line-height: 1.2;">
            {{ $project['title'] }} News &amp; Updates
        </h1>
        
        <p style="font-size: 1.1rem; color: var(--vp-c-text-2); margin: 0; line-height: 1.6; max-width: 700px;">
            {{ $project['motto'] ?? 'Official announcements, engineering changelogs, and technical stories from the development team.' }}
        </p>

        <!-- Active Filter Indicator -->
        @if(isset($_GET['tag']) || isset($_GET['category']))
            <div style="margin-top: 1.25rem; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <span style="font-size: 0.85rem; color: var(--vp-c-text-3);">Active Filter:</span>
                @if(isset($_GET['tag']))
                    <span style="display: inline-flex; align-items: center; gap: 6px; background: var(--vp-c-bg-soft); color: var(--vp-c-brand); border: 1px solid var(--vp-c-divider); padding: 4px 10px; border-radius: 6px; font-size: 0.82rem; font-weight: 600;">
                        <span>🏷️ Tag: {{ htmlspecialchars($_GET['tag']) }}</span>
                        <a href="{{ \SPP\App::getBaseUrl() }}/blog/{{ $project_id }}" style="color: inherit; text-decoration: none; font-weight: 700; margin-left: 4px;" title="Clear filter">&times;</a>
                    </span>
                @endif
                @if(isset($_GET['category']))
                    <span style="display: inline-flex; align-items: center; gap: 6px; background: var(--vp-c-bg-soft); color: var(--vp-c-brand); border: 1px solid var(--vp-c-divider); padding: 4px 10px; border-radius: 6px; font-size: 0.82rem; font-weight: 600;">
                        <span>📁 Category: {{ htmlspecialchars($_GET['category']) }}</span>
                        <a href="{{ \SPP\App::getBaseUrl() }}/blog/{{ $project_id }}" style="color: inherit; text-decoration: none; font-weight: 700; margin-left: 4px;" title="Clear filter">&times;</a>
                    </span>
                @endif
                <a href="{{ \SPP\App::getBaseUrl() }}/blog/{{ $project_id }}" style="font-size: 0.82rem; color: var(--vp-c-text-3); text-decoration: underline;">Clear all filters</a>
            </div>
        @endif
    </header>

    <!-- Articles List -->
    @if(empty($posts))
        <div style="text-align: center; padding: 4rem 2rem; background: var(--vp-c-bg-alt); border: 1px dashed var(--vp-c-divider); border-radius: 12px; margin-top: 1rem;">
            <div style="font-size: 2.5rem; margin-bottom: 1rem;">📰</div>
            <h3 style="font-size: 1.25rem; font-weight: 600; color: var(--vp-c-text-1); margin: 0 0 0.5rem 0;">No news posts published yet</h3>
            <p style="font-size: 0.95rem; color: var(--vp-c-text-2); max-width: 480px; margin: 0 auto 1.5rem auto;">
                Check back soon for release notes, technical articles, and announcements from {{ $project['title'] }}.
            </p>
            <a href="{{ \SPP\App::getBaseUrl() }}/project/{{ $project_id }}" 
               style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 8px; background: var(--vp-c-brand); color: #ffffff; text-decoration: none; font-size: 0.9rem; font-weight: 500;">
                &larr; Back to Project Overview
            </a>
        </div>
    @else
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            @foreach($posts as $post)
            <article style="background: var(--vp-c-bg); border: 1px solid var(--vp-c-divider); border-radius: 12px; padding: 1.75rem 2rem; transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.2s ease, border-color 0.2s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.03);"
                     onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 12px 24px -6px rgba(0,0,0,0.07)'; this.style.borderColor='var(--vp-c-brand-light)';"
                     onmouseout="this.style.transform='none'; this.style.boxShadow='0 1px 3px rgba(0,0,0,0.03)'; this.style.borderColor='var(--vp-c-divider)';">
                
                <!-- Metadata Strip -->
                <div style="display: flex; align-items: center; gap: 12px; font-size: 0.82rem; color: var(--vp-c-text-3); margin-bottom: 0.75rem; flex-wrap: wrap;">
                    <span style="display: inline-flex; align-items: center; gap: 4px;">
                        <span>📅</span> {{ is_numeric($post['date']) ? date('M j, Y', $post['date']) : date('M j, Y', strtotime($post['date'])) }}
                    </span>
                    <span>&bull;</span>
                    <span style="display: inline-flex; align-items: center; gap: 4px;">
                        <span>✍️</span> {{ $post['author'] }}
                    </span>
                    @if(!empty($post['category']))
                        <span>&bull;</span>
                        <a href="{{ \SPP\App::getBaseUrl() }}/blog/{{ $project_id }}?category={{ urlencode($post['category']) }}" 
                           style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 999px; background: var(--vp-c-bg-soft); color: var(--vp-c-brand); text-decoration: none; font-weight: 500; border: 1px solid rgba(234, 88, 12, 0.15);">
                            🏷️ {{ $post['category'] }}
                        </a>
                    @endif
                </div>

                <!-- Post Title -->
                <h2 style="font-size: 1.45rem; font-weight: 700; margin: 0 0 0.75rem 0; line-height: 1.35; letter-spacing: -0.01em;">
                    <a href="{{ \SPP\App::getBaseUrl() }}/blog/{{ $project_id }}/{{ $post['slug'] }}" 
                       style="color: var(--vp-c-text-1); text-decoration: none; transition: color 0.15s ease;"
                       onmouseover="this.style.color='var(--vp-c-brand)';"
                       onmouseout="this.style.color='var(--vp-c-text-1)';">
                        {{ $post['title'] }}
                    </a>
                </h2>

                <!-- Excerpt -->
                <p style="color: var(--vp-c-text-2); font-size: 0.98rem; line-height: 1.65; margin: 0 0 1.25rem 0;">
                    {!! $post['excerpt'] !!}
                </p>

                <!-- Footer: Tags and Read More -->
                <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--vp-c-divider); padding-top: 1rem; flex-wrap: wrap; gap: 12px;">
                    <div style="display: flex; gap: 6px; flex-wrap: wrap; align-items: center;">
                        @if(!empty($post['tags']))
                            @foreach((is_array($post['tags']) ? $post['tags'] : explode(',', $post['tags'])) as $tag)
                                <a href="{{ \SPP\App::getBaseUrl() }}/blog/{{ $project_id }}?tag={{ urlencode(trim($tag)) }}" 
                                   style="padding: 2px 8px; border-radius: 4px; font-size: 0.78rem; background: var(--vp-c-bg-alt); color: var(--vp-c-text-2); border: 1px solid var(--vp-c-divider); text-decoration: none; transition: all 0.15s ease;"
                                   onmouseover="this.style.background='var(--vp-c-bg-soft)'; this.style.color='var(--vp-c-brand)';"
                                   onmouseout="this.style.background='var(--vp-c-bg-alt)'; this.style.color='var(--vp-c-text-2)';">
                                    #{{ trim($tag) }}
                                </a>
                            @endforeach
                        @endif
                    </div>

                    <a href="{{ \SPP\App::getBaseUrl() }}/blog/{{ $project_id }}/{{ $post['slug'] }}" 
                       style="display: inline-flex; align-items: center; gap: 6px; font-weight: 600; font-size: 0.88rem; color: var(--vp-c-brand); text-decoration: none; padding: 6px 14px; border-radius: 6px; background: var(--vp-c-bg-soft); border: 1px solid rgba(234, 88, 12, 0.2); transition: all 0.2s ease;"
                       onmouseover="this.style.background='var(--vp-c-brand)'; this.style.color='#ffffff';"
                       onmouseout="this.style.background='var(--vp-c-bg-soft)'; this.style.color='var(--vp-c-brand)';">
                        <span>Read Article</span>
                        <span>&rarr;</span>
                    </a>
                </div>
            </article>
            @endforeach
        </div>
    @endif
</div>
@endsection
