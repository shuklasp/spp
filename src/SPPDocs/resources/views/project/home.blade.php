@extends('layouts.base')
@section('title', $project['title'] . ' - Portal')

@section('sidebar')
    @spppartial('partials/sidebar.blade.php', ['project' => $project, 'project_id' => $project_id, 'version' => $project['default_version'] ?? 'v1', 'current_path' => null])
@endsection

@section('content')
<div style="width: 100%; max-width: 1000px; margin: 0 auto;">
    
    <!-- Hero Section -->
    <div style="text-align: center; padding: 4rem 1rem; border-bottom: 1px solid var(--vp-c-divider); margin-bottom: 3rem;">
        @if(!empty($project['logo']))
            <img src="{{ (str_starts_with($project['logo'], 'http') || str_starts_with($project['logo'], '/')) ? $project['logo'] : \SPP\App::getBaseUrl() . '/' . ltrim($project['logo'], '/') }}" 
                 alt="{{ $project['title'] }} Logo" 
                 style="height: 120px; width: auto; margin-bottom: 1.5rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
        @endif
        
        <h1 style="font-size: 3rem; margin: 0 0 1rem 0; letter-spacing: -0.02em;">{{ $project['title'] }}</h1>
        
        @if(!empty($project['motto']))
            <p style="font-size: 1.25rem; color: var(--vp-c-text-2); max-width: 600px; margin: 0 auto; line-height: 1.6;">
                {{ $project['motto'] }}
            </p>
        @endif
    </div>

    <!-- Main Navigation Actions -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 4rem;">
        @if(\App\SPPDocs\Services\FeatureManager::isEnabled('docs', $project))
        <a href="{{ \SPP\App::getBaseUrl() }}/docs/{{ $project_id }}/{{ $project['default_version'] ?? 'v1' }}" 
           style="display: block; padding: 2rem; border-radius: 12px; background: var(--vp-c-bg-soft); border: 1px solid var(--vp-c-brand-light); text-decoration: none; color: inherit; transition: transform 0.2s, box-shadow 0.2s;"
           onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 10px 15px -3px rgba(0,0,0,0.1)';"
           onmouseout="this.style.transform='none'; this.style.boxShadow='none';">
            <h2 style="margin: 0 0 0.5rem 0; color: var(--vp-c-brand); font-size: 1.5rem; border: none;">📚 Documentation</h2>
            <p style="margin: 0; color: var(--vp-c-text-2);">Read the official guides, API references, and tutorials.</p>
        </a>
        @endif

        @if(\App\SPPDocs\Services\FeatureManager::isEnabled('blog', $project) && !empty($project['blog_dir']))
        <a href="{{ \SPP\App::getBaseUrl() }}/blog/{{ $project_id }}" 
           style="display: block; padding: 2rem; border-radius: 12px; background: var(--vp-c-bg-soft); border: 1px solid var(--vp-c-divider); text-decoration: none; color: inherit; transition: transform 0.2s, box-shadow 0.2s;"
           onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 10px 15px -3px rgba(0,0,0,0.1)';"
           onmouseout="this.style.transform='none'; this.style.boxShadow='none';">
            <h2 style="margin: 0 0 0.5rem 0; font-size: 1.5rem; border: none;">📰 Project Blog</h2>
            <p style="margin: 0; color: var(--vp-c-text-2);">Latest news, announcements, and articles.</p>
        </a>
        @endif

        @if(\App\SPPDocs\Services\FeatureManager::isEnabled('forums', $project) && !empty($project['links']['forum']))
        <a href="{{ $project['links']['forum'] ?? '#' }}" 
           style="display: block; padding: 2rem; border-radius: 12px; background: var(--vp-c-bg-soft); border: 1px solid var(--vp-c-divider); text-decoration: none; color: inherit; transition: transform 0.2s, box-shadow 0.2s;"
           onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 10px 15px -3px rgba(0,0,0,0.1)';"
           onmouseout="this.style.transform='none'; this.style.boxShadow='none';">
            <h2 style="margin: 0 0 0.5rem 0; font-size: 1.5rem; border: none;">💬 Community Forums</h2>
            <p style="margin: 0; color: var(--vp-c-text-2);">Join the discussion, ask questions, and share ideas.</p>
        </a>
        @endif

        @if(\App\SPPDocs\Services\FeatureManager::isEnabled('issues', $project) && !empty($project['links']['issues']))
        <a href="{{ $project['links']['issues'] }}" 
           style="display: block; padding: 2rem; border-radius: 12px; background: var(--vp-c-bg-soft); border: 1px solid var(--vp-c-divider); text-decoration: none; color: inherit; transition: transform 0.2s, box-shadow 0.2s;"
           onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 10px 15px -3px rgba(0,0,0,0.1)';"
           onmouseout="this.style.transform='none'; this.style.boxShadow='none';">
            <h2 style="margin: 0 0 0.5rem 0; font-size: 1.5rem; border: none;">🎯 Issue Tracker</h2>
            <p style="margin: 0; color: var(--vp-c-text-2);">Report bugs, request features, and track progress.</p>
        </a>
        @endif

        @if(\App\SPPDocs\Services\FeatureManager::isEnabled('lms', $project))
        <a href="{{ \SPP\App::getBaseUrl() }}/lms?projectId={{ $project_id }}" 
           style="display: block; padding: 2rem; border-radius: 12px; background: var(--vp-c-bg-soft); border: 1px solid var(--vp-c-divider); text-decoration: none; color: inherit; transition: transform 0.2s, box-shadow 0.2s;"
           onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 10px 15px -3px rgba(0,0,0,0.1)';"
           onmouseout="this.style.transform='none'; this.style.boxShadow='none';">
            <h2 style="margin: 0 0 0.5rem 0; font-size: 1.5rem; border: none; color: #8b5cf6;">🎓 Academy &amp; Courses</h2>
            <p style="margin: 0; color: var(--vp-c-text-2);">Interactive masterclasses, multi-format assessments, and verified certificates.</p>
        </a>
        @endif
    </div>

    @php
        $homeUser = $_SESSION['sppdocs_user'] ?? null;
        $canManageStudio = $homeUser && (\App\SPPDocs\Services\PermissionManager::isProjectAdmin($project_id, $homeUser) || \App\SPPDocs\Services\PermissionManager::isGlobalAdmin($homeUser));
        $hasStudioViews = \App\SPPDocs\Services\FeatureManager::isEnabled('views', $project);
        $hasStudioTaxonomy = \App\SPPDocs\Services\FeatureManager::isEnabled('taxonomy', $project);
        $hasStudioBlocks = \App\SPPDocs\Services\FeatureManager::isEnabled('blocks', $project);
        $hasStudioModules = \App\SPPDocs\Services\FeatureManager::isEnabled('modules', $project);
        $hasStudioSchemas = \App\SPPDocs\Services\FeatureManager::isEnabled('schemas', $project);
        $hasStudioLms = \App\SPPDocs\Services\FeatureManager::isEnabled('lms', $project);
        $hasBookExport = \App\SPPDocs\Services\FeatureManager::isEnabled('book_export', $project);
        $hasAnyStudioFeature = $canManageStudio && ($hasStudioViews || $hasStudioTaxonomy || $hasStudioBlocks || $hasStudioModules || $hasStudioSchemas || $hasStudioLms || $hasBookExport);
    @endphp

    @if($hasAnyStudioFeature)
    <!-- Content Architecture & Studio Tools -->
    <div style="margin-bottom: 4rem;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; border-bottom: 2px solid var(--vp-c-divider); padding-bottom: 0.75rem;">
            <div>
                <h2 style="font-size: 1.6rem; font-weight: 700; margin: 0; color: var(--vp-c-text-1); display: flex; align-items: center; gap: 0.5rem;">
                    <span>⚡</span> Content Architecture &amp; Studio Tools
                </h2>
                <p style="margin: 0.25rem 0 0 0; color: var(--vp-c-text-2); font-size: 0.95rem;">
                    Dynamic query builders, hierarchical taxonomies, pluggable theme regions, modular extensions, and publication engines.
                </p>
            </div>
            <span style="font-size: 0.8rem; font-weight: 600; padding: 0.35rem 0.75rem; border-radius: 9999px; background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.2);">
                SPPDocs Native Studio
            </span>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
            @if($canManageStudio && $hasStudioViews)
            <!-- Views Studio -->
            <a href="{{ \SPP\App::getBaseUrl() }}/admin/views?project={{ $project_id }}" hx-boost="false"
               style="display: flex; flex-direction: column; padding: 1.5rem; border-radius: 12px; background: var(--vp-c-bg-soft); border: 1px solid var(--vp-c-divider); text-decoration: none; color: inherit; transition: all 0.2s;"
               onmouseover="this.style.borderColor='var(--vp-c-brand)'; this.style.transform='translateY(-2px)';"
               onmouseout="this.style.borderColor='var(--vp-c-divider)'; this.style.transform='none';">
                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                    <div style="width: 40px; height: 40px; border-radius: 8px; background: rgba(59, 130, 246, 0.1); display: flex; align-items: center; justify-content: center; font-size: 1.3rem;">
                        🔍
                    </div>
                    <div>
                        <h3 style="margin: 0; font-size: 1.15rem; color: var(--vp-c-text-1);">Views Studio</h3>
                        <span style="font-size: 0.75rem; color: #3b82f6; font-weight: 600;">Dynamic Query Builder</span>
                    </div>
                </div>
                <p style="margin: 0 0 1rem 0; color: var(--vp-c-text-2); font-size: 0.88rem; line-height: 1.5; flex-grow: 1;">
                    Visual query builder across Markdown collections. Filter by taxonomy tags, configure card grids or data tables, and generate RSS/JSON feeds.
                </p>
                <div style="display: flex; align-items: center; justify-content: space-between; font-size: 0.82rem; font-weight: 600; color: var(--vp-c-brand);">
                    <span>Open Views Studio &rarr;</span>
                    <span style="color: var(--vp-c-text-3);">Query Engine</span>
                </div>
            </a>
            @endif

            @if($canManageStudio && $hasStudioTaxonomy)
            <!-- Taxonomy Trees -->
            <a href="{{ \SPP\App::getBaseUrl() }}/admin/taxonomy?project={{ $project_id }}" hx-boost="false"
               style="display: flex; flex-direction: column; padding: 1.5rem; border-radius: 12px; background: var(--vp-c-bg-soft); border: 1px solid var(--vp-c-divider); text-decoration: none; color: inherit; transition: all 0.2s;"
               onmouseover="this.style.borderColor='var(--vp-c-brand)'; this.style.transform='translateY(-2px)';"
               onmouseout="this.style.borderColor='var(--vp-c-divider)'; this.style.transform='none';">
                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                    <div style="width: 40px; height: 40px; border-radius: 8px; background: rgba(16, 185, 129, 0.1); display: flex; align-items: center; justify-content: center; font-size: 1.3rem;">
                        🏷️
                    </div>
                    <div>
                        <h3 style="margin: 0; font-size: 1.15rem; color: var(--vp-c-text-1);">Taxonomy Trees</h3>
                        <span style="font-size: 0.75rem; color: #10b981; font-weight: 600;">Multi-level Vocabularies</span>
                    </div>
                </div>
                <p style="margin: 0 0 1rem 0; color: var(--vp-c-text-2); font-size: 0.88rem; line-height: 1.5; flex-grow: 1;">
                    Hierarchical category trees, term metadata, colored tags, and automatic term archive pages resolving tagged documents.
                </p>
                <div style="display: flex; align-items: center; justify-content: space-between; font-size: 0.82rem; font-weight: 600; color: var(--vp-c-brand);">
                    <span>Manage Vocabularies &rarr;</span>
                    <span style="color: var(--vp-c-text-3);">Taxonomy &amp; Tags</span>
                </div>
            </a>
            @endif

            @if($canManageStudio && $hasStudioBlocks)
            <!-- Pluggable Blocks & Theme Regions -->
            <a href="{{ \SPP\App::getBaseUrl() }}/admin/blocks?project={{ $project_id }}" hx-boost="false"
               style="display: flex; flex-direction: column; padding: 1.5rem; border-radius: 12px; background: var(--vp-c-bg-soft); border: 1px solid var(--vp-c-divider); text-decoration: none; color: inherit; transition: all 0.2s;"
               onmouseover="this.style.borderColor='var(--vp-c-brand)'; this.style.transform='translateY(-2px)';"
               onmouseout="this.style.borderColor='var(--vp-c-divider)'; this.style.transform='none';">
                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                    <div style="width: 40px; height: 40px; border-radius: 8px; background: rgba(245, 158, 11, 0.1); display: flex; align-items: center; justify-content: center; font-size: 1.3rem;">
                        🧱
                    </div>
                    <div>
                        <h3 style="margin: 0; font-size: 1.15rem; color: var(--vp-c-text-1);">Theme Regions &amp; Blocks</h3>
                        <span style="font-size: 0.75rem; color: #f59e0b; font-weight: 600;">Pluggable Layout Engine</span>
                    </div>
                </div>
                <p style="margin: 0 0 1rem 0; color: var(--vp-c-text-2); font-size: 0.88rem; line-height: 1.5; flex-grow: 1;">
                    Place custom blocks, taxonomy widgets, view displays, or navigation elements into theme regions with path and role rules.
                </p>
                <div style="display: flex; align-items: center; justify-content: space-between; font-size: 0.82rem; font-weight: 600; color: var(--vp-c-brand);">
                    <span>Configure Blocks &rarr;</span>
                    <span style="color: var(--vp-c-text-3);">Block Layouts</span>
                </div>
            </a>
            @endif

            @if($canManageStudio && $hasStudioModules)
            <!-- App Modules & Hooks -->
            <a href="{{ \SPP\App::getBaseUrl() }}/admin/modules?project={{ $project_id }}" hx-boost="false"
               style="display: flex; flex-direction: column; padding: 1.5rem; border-radius: 12px; background: var(--vp-c-bg-soft); border: 1px solid var(--vp-c-divider); text-decoration: none; color: inherit; transition: all 0.2s;"
               onmouseover="this.style.borderColor='var(--vp-c-brand)'; this.style.transform='translateY(-2px)';"
               onmouseout="this.style.borderColor='var(--vp-c-divider)'; this.style.transform='none';">
                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                    <div style="width: 40px; height: 40px; border-radius: 8px; background: rgba(139, 92, 246, 0.1); display: flex; align-items: center; justify-content: center; font-size: 1.3rem;">
                        🧩
                    </div>
                    <div>
                        <h3 style="margin: 0; font-size: 1.15rem; color: var(--vp-c-text-1);">Modules &amp; Hooks</h3>
                        <span style="font-size: 0.75rem; color: #8b5cf6; font-weight: 600;">Pluggable Architecture</span>
                    </div>
                </div>
                <p style="margin: 0 0 1rem 0; color: var(--vp-c-text-2); font-size: 0.88rem; line-height: 1.5; flex-grow: 1;">
                    Modular extension system supporting kernel alter hooks (`document_render`, `before_save`, `sidebar_alter`) and custom block plugins.
                </p>
                <div style="display: flex; align-items: center; justify-content: space-between; font-size: 0.82rem; font-weight: 600; color: var(--vp-c-brand);">
                    <span>Explore Modules &rarr;</span>
                    <span style="color: var(--vp-c-text-3);">Extensibility</span>
                </div>
            </a>
            @endif

            @if($canManageStudio && $hasStudioSchemas)
            <!-- Content Type Schemas -->
            <a href="{{ \SPP\App::getBaseUrl() }}/admin/schemas?project={{ $project_id }}" hx-boost="false"
               style="display: flex; flex-direction: column; padding: 1.5rem; border-radius: 12px; background: var(--vp-c-bg-soft); border: 1px solid var(--vp-c-divider); text-decoration: none; color: inherit; transition: all 0.2s;"
               onmouseover="this.style.borderColor='var(--vp-c-brand)'; this.style.transform='translateY(-2px)';"
               onmouseout="this.style.borderColor='var(--vp-c-divider)'; this.style.transform='none';">
                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                    <div style="width: 40px; height: 40px; border-radius: 8px; background: rgba(236, 72, 153, 0.1); display: flex; align-items: center; justify-content: center; font-size: 1.3rem;">
                        📐
                    </div>
                    <div>
                        <h3 style="margin: 0; font-size: 1.15rem; color: var(--vp-c-text-1);">Content Type Schemas</h3>
                        <span style="font-size: 0.75rem; color: #ec4899; font-weight: 600;">Field Governance</span>
                    </div>
                </div>
                <p style="margin: 0 0 1rem 0; color: var(--vp-c-text-2); font-size: 0.88rem; line-height: 1.5; flex-grow: 1;">
                    Define custom frontmatter fields, validation constraints, and role-based edit permissions across pages, blogs, and documentation types.
                </p>
                <div style="display: flex; align-items: center; justify-content: space-between; font-size: 0.82rem; font-weight: 600; color: var(--vp-c-brand);">
                    <span>Manage Schemas &rarr;</span>
                    <span style="color: var(--vp-c-text-3);">Schema Governance</span>
                </div>
            </a>
            @endif

            @if($hasBookExport)
            <!-- Full-Book PDF & Media Styles -->
            <a href="{{ \SPP\App::getBaseUrl() }}/export/book?project={{ $project_id }}" target="_blank" hx-boost="false"
               style="display: flex; flex-direction: column; padding: 1.5rem; border-radius: 12px; background: var(--vp-c-bg-soft); border: 1px solid var(--vp-c-divider); text-decoration: none; color: inherit; transition: all 0.2s;"
               onmouseover="this.style.borderColor='var(--vp-c-brand)'; this.style.transform='translateY(-2px)';"
               onmouseout="this.style.borderColor='var(--vp-c-divider)'; this.style.transform='none';">
                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                    <div style="width: 40px; height: 40px; border-radius: 8px; background: rgba(239, 68, 68, 0.1); display: flex; align-items: center; justify-content: center; font-size: 1.3rem;">
                        📕
                    </div>
                    <div>
                        <h3 style="margin: 0; font-size: 1.15rem; color: var(--vp-c-text-1);">Full-Book Publisher</h3>
                        <span style="font-size: 0.75rem; color: #ef4444; font-weight: 600;">Print &amp; PDF Export</span>
                    </div>
                </div>
                <p style="margin: 0 0 1rem 0; color: var(--vp-c-text-2); font-size: 0.88rem; line-height: 1.5; flex-grow: 1;">
                    Compile the entire project documentation hierarchy into a single cohesive, paginated, print-ready document or PDF with table of contents.
                </p>
                <div style="display: flex; align-items: center; justify-content: space-between; font-size: 0.82rem; font-weight: 600; color: var(--vp-c-brand);">
                    <span>Export Full Book &rarr;</span>
                    <span style="color: var(--vp-c-text-3);">Book Publisher</span>
                </div>
            </a>
            @endif

            @if($canManageStudio && $hasStudioLms)
            <!-- Course Studio & LMS -->
            <a href="{{ \SPP\App::getBaseUrl() }}/admin/lms?project={{ $project_id }}" hx-boost="false"
               style="display: flex; flex-direction: column; padding: 1.5rem; border-radius: 12px; background: var(--vp-c-bg-soft); border: 1px solid var(--vp-c-divider); text-decoration: none; color: inherit; transition: all 0.2s;"
               onmouseover="this.style.borderColor='var(--vp-c-brand)'; this.style.transform='translateY(-2px)';"
               onmouseout="this.style.borderColor='var(--vp-c-divider)'; this.style.transform='none';">
                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                    <div style="width: 40px; height: 40px; border-radius: 8px; background: rgba(139, 92, 246, 0.1); display: flex; align-items: center; justify-content: center; font-size: 1.3rem;">
                        🎓
                    </div>
                    <div>
                        <h3 style="margin: 0; font-size: 1.15rem; color: var(--vp-c-text-1);">Course Studio &amp; LMS</h3>
                        <span style="font-size: 0.75rem; color: #8b5cf6; font-weight: 600;">Interactive Academy</span>
                    </div>
                </div>
                <p style="margin: 0 0 1rem 0; color: var(--vp-c-text-2); font-size: 0.88rem; line-height: 1.5; flex-grow: 1;">
                    Author structured curriculums, organize modules, build assessments, and monitor learner analytics.
                </p>
                <div style="display: flex; align-items: center; justify-content: space-between; font-size: 0.82rem; font-weight: 600; color: var(--vp-c-brand);">
                    <span>Launch Studio &rarr;</span>
                    <span style="color: var(--vp-c-text-3);">Academy Studio</span>
                </div>
            </a>
            @endif
        </div>
    </div>
    @endif

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 4rem;">
        <!-- Resources & Links -->
        <div>
            <h3 class="home-resources-title">External Resources</h3>
            <ul class="home-resources-list">
                @if(!empty($project['links']['website']))
                    <li><a href="@external_url($project['links']['website'])" target="_blank" rel="noopener" class="home-resource-item">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
                        Official Website
                    </a></li>
                @endif
                
                @if(!empty($project['links']['source']))
                    <li><a href="@external_url($project['links']['source'])" target="_blank" rel="noopener" class="home-resource-item">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"></path></svg>
                        Source Code Repository
                    </a></li>
                @endif
                
                @if(!empty($project['links']['forum']))
                    <li><a href="@external_url($project['links']['forum'])" target="_blank" rel="noopener" class="home-resource-item">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                        External Community
                    </a></li>
                @endif

                @if(!empty($project['links']['custom']) && is_array($project['links']['custom']))
                    @foreach($project['links']['custom'] as $clink)
                        @if(!empty($clink['url']) && !empty($clink['title']))
                            <li><a href="@external_url($clink['url'])" target="_blank" rel="noopener" class="home-resource-item">
                                <span class="home-resource-icon">{{ $clink['icon'] ?? '🔗' }}</span>
                                {{ $clink['title'] }}
                                <span class="home-resource-arrow">↗</span>
                            </a></li>
                        @endif
                    @endforeach
                @endif
            </ul>
            @php
                $hasAnyLinks = !empty($project['links']['website']) || !empty($project['links']['source']) || !empty($project['links']['forum']) || !empty($project['links']['custom']);
            @endphp
            @if(!$hasAnyLinks)
                <p class="home-no-resources">No external resources configured.</p>
            @endif
        </div>

        <!-- Releases -->
        <div>
            <h3 style="font-size: 1.25rem; margin-bottom: 1.5rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--vp-c-divider);">Latest Releases</h3>
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                @if(!empty($project['releases']))
                    @foreach(array_slice($project['releases'], 0, 5) as $release)
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; background: var(--vp-c-bg-alt); border-radius: 6px; border: 1px solid var(--vp-c-divider);">
                            <div style="display: flex; flex-direction: column;">
                                <a href="{{ $release['url'] ?? '#' }}" target="_blank" style="font-weight: 600; text-decoration: none; color: var(--vp-c-brand);">{{ $release['version'] }}</a>
                                @if(!empty($release['date']))
                                    <span style="font-size: 0.8rem; color: var(--vp-c-text-3);">{{ $release['date'] }}</span>
                                @endif
                            </div>
                            <a href="{{ $release['url'] ?? '#' }}" target="_blank" style="padding: 0.25rem 0.5rem; background: var(--vp-c-bg); border: 1px solid var(--vp-c-divider); border-radius: 4px; font-size: 0.8rem; text-decoration: none; color: var(--vp-c-text-2);">Changelog</a>
                        </div>
                    @endforeach
                @else
                    <p style="color: var(--vp-c-text-3);">No releases published yet.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
