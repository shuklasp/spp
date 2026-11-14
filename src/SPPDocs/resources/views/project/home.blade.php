@extends('layouts.base')
@section('title', $project['title'] . ' - Portal')

@section('content')
<div style="width: 100%; max-width: 1100px; margin: 0 auto; padding-bottom: 4rem;">
    
    <!-- Lifecycle & Moderation Alerts -->
    @if(!empty($isArchived))
    <div style="background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.35); border-radius: 10px; padding: 1rem 1.5rem; margin: 1.5rem 0; color: #fde68a; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <span style="font-size: 1.6rem;">📦</span>
            <div>
                <strong style="font-size: 1.05rem;">Archived Project (Read-Only Mode)</strong>
                <div style="font-size: 0.88rem; opacity: 0.85; margin-top: 2px;">This project has been archived. All documentation, history, and releases remain preserved.</div>
            </div>
        </div>
        @if(!empty($canManage))
        <form action="@url('admin/project/archive')" method="POST" style="margin: 0;">
            <input type="hidden" name="csrf_token" value="{{ $_SESSION['sppdocs_csrf'] ?? '' }}">
            <input type="hidden" name="project_id" value="{{ $project_id }}">
            <button type="submit" style="background: rgba(245, 158, 11, 0.25); border: 1px solid rgba(245, 158, 11, 0.5); color: #fff; padding: 0.45rem 1rem; border-radius: 6px; cursor: pointer; font-weight: 600;">
                ♻️ Unarchive Project
            </button>
        </form>
        @endif
    </div>
    @endif

    @if(!empty($isSuspended))
    <div style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.35); border-radius: 10px; padding: 1rem 1.5rem; margin: 1.5rem 0; color: #fca5a5; display: flex; align-items: center; gap: 12px;">
        <span style="font-size: 1.6rem;">⚠️</span>
        <div>
            <strong style="font-size: 1.05rem;">Suspended by SatyaLab Moderation</strong>
            <div style="font-size: 0.88rem; opacity: 0.85; margin-top: 2px;">{{ $project['moderation']['suspension_reason'] ?? 'This project is currently hidden from public catalog pending moderation review.' }}</div>
        </div>
    </div>
    @endif

    <!-- SatyaLab Admin Quick Moderation Ribbon -->
    @if(!empty($isGlobalAdmin))
    <div style="background: rgba(255, 255, 255, 0.04); border: 1px dashed rgba(249, 115, 22, 0.4); border-radius: 8px; padding: 0.75rem 1.25rem; margin-top: 1rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
        <div style="display: flex; align-items: center; gap: 8px; font-size: 0.88rem;">
            <span style="color: var(--vp-c-brand); font-weight: 700;">🛡️ SatyaLab Admin:</span>
            <span>Visibility: <strong>{{ ucfirst($project['visibility'] ?? 'public') }}</strong></span>
            <span>&bull;</span>
            <span>Type: <strong>{{ strtoupper($project['type'] ?? 'docs') }}</strong></span>
            <span>&bull;</span>
            <span>Creator: <strong>{{ $project['creator'] ?? 'admin' }}</strong></span>
        </div>
        <div style="display: flex; gap: 8px; align-items: center;">
            <form action="@url('admin/moderate/project')" method="POST" style="margin: 0;">
                <input type="hidden" name="csrf_token" value="{{ $_SESSION['sppdocs_csrf'] ?? '' }}">
                <input type="hidden" name="project_id" value="{{ $project_id }}">
                <input type="hidden" name="redirect" value="{{ \SPP\App::getBaseUrl() }}/project/{{ $project_id }}">
                @if(!empty($project['is_featured']))
                    <input type="hidden" name="action" value="unfeature">
                    <button type="submit" style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.2); color: #fed7aa; padding: 0.35rem 0.75rem; border-radius: 6px; cursor: pointer; font-size: 0.82rem;">⭐ Unfeature</button>
                @else
                    <input type="hidden" name="action" value="feature">
                    <button type="submit" style="background: rgba(234, 179, 8, 0.2); border: 1px solid rgba(234, 179, 8, 0.4); color: #fde047; padding: 0.35rem 0.75rem; border-radius: 6px; cursor: pointer; font-size: 0.82rem;">⭐ Feature</button>
                @endif
            </form>

            <form action="@url('admin/moderate/project')" method="POST" style="margin: 0;">
                <input type="hidden" name="csrf_token" value="{{ $_SESSION['sppdocs_csrf'] ?? '' }}">
                <input type="hidden" name="project_id" value="{{ $project_id }}">
                <input type="hidden" name="redirect" value="{{ \SPP\App::getBaseUrl() }}/project/{{ $project_id }}">
                @if(!empty($isSuspended))
                    <input type="hidden" name="action" value="unsuspend">
                    <button type="submit" style="background: rgba(16, 185, 129, 0.2); border: 1px solid rgba(16, 185, 129, 0.4); color: #6ee7b7; padding: 0.35rem 0.75rem; border-radius: 6px; cursor: pointer; font-size: 0.82rem;">Lift Suspension</button>
                @else
                    <input type="hidden" name="action" value="suspend">
                    <input type="hidden" name="reason" value="Suspended by SatyaLab site moderation">
                    <button type="submit" style="background: rgba(239, 68, 68, 0.2); border: 1px solid rgba(239, 68, 68, 0.4); color: #fca5a5; padding: 0.35rem 0.75rem; border-radius: 6px; cursor: pointer; font-size: 0.82rem;">Suspend Project</button>
                @endif
            </form>
        </div>
    </div>
    @endif

    <!-- Hero Section -->
    <div style="text-align: center; padding: 3rem 1rem 2.5rem 1rem; border-bottom: 1px solid var(--vp-c-divider); margin-bottom: 2.5rem;">
        @if(!empty($project['logo']))
            @php
                $rawHeroLogo = $project['logo'];
                if (preg_match('#^https?://#i', $rawHeroLogo)) {
                    $heroLogo = $rawHeroLogo;
                } else {
                    $cleanHeroLogo = ltrim($rawHeroLogo, '/');
                    $baseClean = trim(\SPP\App::getBaseUrl(), '/');
                    if (!empty($baseClean) && str_starts_with($cleanHeroLogo, $baseClean . '/')) {
                        $heroLogo = '/' . $cleanHeroLogo;
                    } else {
                        $heroLogo = \SPP\App::getBaseUrl() . '/' . $cleanHeroLogo;
                    }
                }
            @endphp
            <img src="{{ $heroLogo }}" 
                 alt="{{ $project['title'] }} Logo" 
                 style="height: 100px; width: auto; margin-bottom: 1.25rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.06);"
                 onerror="this.style.display='none'">
        @endif
        
        <h1 style="font-size: 2.75rem; margin: 0 0 0.75rem 0; letter-spacing: -0.025em; font-weight: 800; color: var(--vp-c-text-1);">
            {{ $project['title'] }}
        </h1>
        
        @if(!empty($project['motto']))
            <p style="font-size: 1.2rem; color: var(--vp-c-text-2); max-width: 650px; margin: 0 auto 1.75rem auto; line-height: 1.6;">
                {{ $project['motto'] }}
            </p>
        @endif

        <!-- Quick Primary Actions -->
        <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; margin-top: 1.5rem;">
            @if(($projectType ?? '') === 'app' && !empty($project['demo_url']))
            <a href="{{ $project['demo_url'] }}" target="_blank" 
               style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.75rem; border-radius: 8px; background: #2563eb; color: #ffffff; text-decoration: none; font-weight: 700; font-size: 1.05rem; box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35); transition: all 0.2s;">
                <span>🚀 Launch Hosted Application</span>
                <span>↗</span>
            </a>
            @endif

            @if(\App\SPPDocs\Services\FeatureManager::isEnabled('docs', $project))
            <a href="{{ \SPP\App::getBaseUrl() }}/docs/{{ $project_id }}/{{ $project['default_version'] ?? 'v1' }}" 
               style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.75rem; border-radius: 8px; background: var(--vp-c-brand); color: #ffffff; text-decoration: none; font-weight: 600; font-size: 1.05rem; box-shadow: 0 4px 12px rgba(234, 88, 12, 0.25); transition: all 0.2s;">
                <span>📚 Explore Documentation</span>
                <span>&rarr;</span>
            </a>
            @endif

            <a href="{{ \SPP\App::getBaseUrl() }}/project/{{ $project_id }}/discussions"
               style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.5rem; border-radius: 8px; background: var(--vp-c-bg-soft); color: var(--vp-c-text-1); border: 1px solid var(--vp-c-divider); text-decoration: none; font-weight: 600; font-size: 1.05rem; transition: all 0.2s;">
                <span>💬 Stakeholder &amp; Team Discussions</span>
            </a>

            @if(\App\SPPDocs\Services\FeatureManager::isEnabled('issues', $project))
            <a href="{{ \SPP\App::getBaseUrl() }}/issues/board?projectId={{ $project_id }}"
               style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.5rem; border-radius: 8px; background: var(--vp-c-bg-soft); color: var(--vp-c-text-1); border: 1px solid var(--vp-c-divider); text-decoration: none; font-weight: 600; font-size: 1.05rem; transition: all 0.2s;">
                <span>🎯 Sprint Board &amp; Roadmap</span>
            </a>
            @endif

            @if(!empty($canManage))
            <a href="{{ \SPP\App::getBaseUrl() }}/admin/project/{{ $project_id }}"
               style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.5rem; border-radius: 8px; background: rgba(255, 255, 255, 0.05); color: var(--vp-c-text-1); border: 1px solid rgba(255, 255, 255, 0.2); text-decoration: none; font-weight: 600; font-size: 1.05rem; transition: all 0.2s;">
                <span>⚙️ Configure Project</span>
            </a>
            @endif
        </div>
    </div>

    <!-- 3-Pillar Progressive Navigation Architecture -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(310px, 1fr)); gap: 1.75rem; margin-bottom: 3.5rem;">
        
        <!-- Pillar 1: Documentation & Guides -->
        <div style="padding: 2rem; border-radius: 12px; background: var(--vp-c-bg-soft); border: 1px solid var(--vp-c-divider); display: flex; flex-direction: column; justify-content: space-between; transition: all 0.2s;"
             onmouseover="this.style.borderColor='var(--vp-c-brand)'; this.style.transform='translateY(-2px)';"
             onmouseout="this.style.borderColor='var(--vp-c-divider)'; this.style.transform='none';">
            <div>
                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                    <span style="font-size: 1.8rem;">📖</span>
                    <div>
                        <h2 style="margin: 0; font-size: 1.35rem; color: var(--vp-c-text-1); border: none;">Documentation &amp; Guides</h2>
                        <span style="font-size: 0.8rem; color: var(--vp-c-brand); font-weight: 600;">Comprehensive Knowledge Base</span>
                    </div>
                </div>
                <p style="margin: 0 0 1.25rem 0; color: var(--vp-c-text-2); font-size: 0.95rem; line-height: 1.5;">
                    Step-by-step installation walkthroughs, architectural references, code recipes, and full API documentation.
                </p>
            </div>
            <div style="display: flex; flex-direction: column; gap: 0.6rem; border-top: 1px solid var(--vp-c-divider); padding-top: 1rem;">
                <a href="{{ \SPP\App::getBaseUrl() }}/docs/{{ $project_id }}/{{ $project['default_version'] ?? 'v1' }}" style="color: var(--vp-c-brand); text-decoration: none; font-weight: 600; display: flex; justify-content: space-between;">
                    <span>Start Reading Docs</span>
                    <span>&rarr;</span>
                </a>
                @if(\App\SPPDocs\Services\FeatureManager::isEnabled('book_export', $project))
                <a href="{{ \SPP\App::getBaseUrl() }}/export/book?project={{ $project_id }}" target="_blank" hx-boost="false" style="color: var(--vp-c-text-2); text-decoration: none; font-size: 0.88rem; display: flex; justify-content: space-between;">
                    <span>📕 Download Full-Book PDF</span>
                    <span>↗</span>
                </a>
                @endif
            </div>
        </div>

        <!-- Pillar 2: Team & Stakeholder Communication (e-Vaarta) -->
        <div style="padding: 2rem; border-radius: 12px; background: var(--vp-c-bg-soft); border: 1px solid var(--vp-c-divider); display: flex; flex-direction: column; justify-content: space-between; transition: all 0.2s;"
             onmouseover="this.style.borderColor='#3b82f6'; this.style.transform='translateY(-2px)';"
             onmouseout="this.style.borderColor='var(--vp-c-divider)'; this.style.transform='none';">
            <div>
                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                    <span style="font-size: 1.8rem;">💬</span>
                    <div>
                        <h2 style="margin: 0; font-size: 1.35rem; color: var(--vp-c-text-1); border: none;">Stakeholder &amp; Team Discussions</h2>
                        <span style="font-size: 0.8rem; color: #3b82f6; font-weight: 600;">e-Vaarta (ई-वार्ता) Omnichannel Hub</span>
                    </div>
                </div>
                <p style="margin: 0 0 1.25rem 0; color: var(--vp-c-text-2); font-size: 0.95rem; line-height: 1.5;">
                    Live project discussion threads, stakeholder approvals, and internal team collaboration notes directly linked to project deliverables.
                </p>
            </div>
            <div style="display: flex; flex-direction: column; gap: 0.6rem; border-top: 1px solid var(--vp-c-divider); padding-top: 1rem;">
                <a href="{{ \SPP\App::getBaseUrl() }}/project/{{ $project_id }}/discussions" style="color: #3b82f6; text-decoration: none; font-weight: 600; display: flex; justify-content: space-between;">
                    <span>Open Discussions Hub</span>
                    <span>&rarr;</span>
                </a>
                <span style="color: var(--vp-c-text-3); font-size: 0.82rem;">⚡ SQLite WAL Thread Engine &bull; Private Notes &bull; Checklists</span>
            </div>
        </div>

        <!-- Pillar 3: Delivery, Issues & Releases -->
        <div style="padding: 2rem; border-radius: 12px; background: var(--vp-c-bg-soft); border: 1px solid var(--vp-c-divider); display: flex; flex-direction: column; justify-content: space-between; transition: all 0.2s;"
             onmouseover="this.style.borderColor='#10b981'; this.style.transform='translateY(-2px)';"
             onmouseout="this.style.borderColor='var(--vp-c-divider)'; this.style.transform='none';">
            <div>
                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                    <span style="font-size: 1.8rem;">🎯</span>
                    <div>
                        <h2 style="margin: 0; font-size: 1.35rem; color: var(--vp-c-text-1); border: none;">Milestones &amp; Roadmap</h2>
                        <span style="font-size: 0.8rem; color: #10b981; font-weight: 600;">Agile Delivery &amp; Tracking</span>
                    </div>
                </div>
                <p style="margin: 0 0 1.25rem 0; color: var(--vp-c-text-2); font-size: 0.95rem; line-height: 1.5;">
                    Track feature progress, Kanban sprint tasks, target deadlines, and versioned release notes.
                </p>
            </div>
            <div style="display: flex; flex-direction: column; gap: 0.6rem; border-top: 1px solid var(--vp-c-divider); padding-top: 1rem;">
                @if(\App\SPPDocs\Services\FeatureManager::isEnabled('issues', $project))
                <a href="{{ \SPP\App::getBaseUrl() }}/issues/board?projectId={{ $project_id }}" style="color: #10b981; text-decoration: none; font-weight: 600; display: flex; justify-content: space-between;">
                    <span>View Kanban Board</span>
                    <span>&rarr;</span>
                </a>
                @endif
                @if(\App\SPPDocs\Services\FeatureManager::isEnabled('blog', $project))
                <a href="{{ \SPP\App::getBaseUrl() }}/blog/{{ $project_id }}" style="color: var(--vp-c-text-2); text-decoration: none; font-size: 0.88rem; display: flex; justify-content: space-between;">
                    <span>📰 Project Blog &amp; Changelog</span>
                    <span>&rarr;</span>
                </a>
                @endif
            </div>
        </div>

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
    <!-- Collapsible Developer Studio & Admin Tools -->
    <details style="margin-bottom: 3.5rem; background: var(--vp-c-bg-soft); border: 1px solid var(--vp-c-divider); border-radius: 12px; padding: 1.25rem 1.5rem;">
        <summary style="font-weight: 700; font-size: 1.15rem; color: var(--vp-c-text-1); cursor: pointer; display: flex; align-items: center; justify-content: space-between; user-select: none;">
            <span style="display: flex; align-items: center; gap: 0.6rem;">
                <span>🛠️</span> Developer Studio &amp; Content Architecture Tools
            </span>
            <span style="font-size: 0.8rem; font-weight: 600; padding: 0.25rem 0.65rem; border-radius: 9999px; background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                Admin Tools &bull; Click to Expand
            </span>
        </summary>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.25rem; margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid var(--vp-c-divider);">
            @if($canManageStudio && $hasStudioViews)
            <a href="{{ \SPP\App::getBaseUrl() }}/admin/views?project={{ $project_id }}" hx-boost="false"
               style="display: flex; flex-direction: column; padding: 1.25rem; border-radius: 10px; background: var(--vp-c-bg); border: 1px solid var(--vp-c-divider); text-decoration: none; color: inherit; transition: all 0.2s;">
                <div style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.5rem;">
                    <span>🔍</span>
                    <strong style="color: var(--vp-c-text-1);">Views Studio</strong>
                </div>
                <p style="margin: 0; color: var(--vp-c-text-2); font-size: 0.85rem; line-height: 1.4;">
                    Visual query builder across collections, filter by tags, and configure card grids or data tables.
                </p>
            </a>
            @endif

            @if($canManageStudio && $hasStudioTaxonomy)
            <a href="{{ \SPP\App::getBaseUrl() }}/admin/taxonomy?project={{ $project_id }}" hx-boost="false"
               style="display: flex; flex-direction: column; padding: 1.25rem; border-radius: 10px; background: var(--vp-c-bg); border: 1px solid var(--vp-c-divider); text-decoration: none; color: inherit; transition: all 0.2s;">
                <div style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.5rem;">
                    <span>🏷️</span>
                    <strong style="color: var(--vp-c-text-1);">Taxonomy Trees</strong>
                </div>
                <p style="margin: 0; color: var(--vp-c-text-2); font-size: 0.85rem; line-height: 1.4;">
                    Hierarchical category trees, term metadata, colored tags, and automatic term archive pages.
                </p>
            </a>
            @endif

            @if($canManageStudio && $hasStudioBlocks)
            <a href="{{ \SPP\App::getBaseUrl() }}/admin/blocks?project={{ $project_id }}" hx-boost="false"
               style="display: flex; flex-direction: column; padding: 1.25rem; border-radius: 10px; background: var(--vp-c-bg); border: 1px solid var(--vp-c-divider); text-decoration: none; color: inherit; transition: all 0.2s;">
                <div style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.5rem;">
                    <span>🧱</span>
                    <strong style="color: var(--vp-c-text-1);">Theme Regions &amp; Blocks</strong>
                </div>
                <p style="margin: 0; color: var(--vp-c-text-2); font-size: 0.85rem; line-height: 1.4;">
                    Place custom blocks, taxonomy widgets, or navigation elements into theme regions.
                </p>
            </a>
            @endif

            @if($canManageStudio && $hasStudioModules)
            <a href="{{ \SPP\App::getBaseUrl() }}/admin/modules?project={{ $project_id }}" hx-boost="false"
               style="display: flex; flex-direction: column; padding: 1.25rem; border-radius: 10px; background: var(--vp-c-bg); border: 1px solid var(--vp-c-divider); text-decoration: none; color: inherit; transition: all 0.2s;">
                <div style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.5rem;">
                    <span>🧩</span>
                    <strong style="color: var(--vp-c-text-1);">Modules &amp; Hooks</strong>
                </div>
                <p style="margin: 0; color: var(--vp-c-text-2); font-size: 0.85rem; line-height: 1.4;">
                    Modular extension system supporting kernel alter hooks and custom block plugins.
                </p>
            </a>
            @endif

            @if($canManageStudio && $hasStudioSchemas)
            <a href="{{ \SPP\App::getBaseUrl() }}/admin/schemas?project={{ $project_id }}" hx-boost="false"
               style="display: flex; flex-direction: column; padding: 1.25rem; border-radius: 10px; background: var(--vp-c-bg); border: 1px solid var(--vp-c-divider); text-decoration: none; color: inherit; transition: all 0.2s;">
                <div style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.5rem;">
                    <span>📐</span>
                    <strong style="color: var(--vp-c-text-1);">Content Type Schemas</strong>
                </div>
                <p style="margin: 0; color: var(--vp-c-text-2); font-size: 0.85rem; line-height: 1.4;">
                    Define custom frontmatter fields, validation constraints, and role-based permissions.
                </p>
            </a>
            @endif

            @if($canManageStudio && $hasStudioLms)
            <a href="{{ \SPP\App::getBaseUrl() }}/admin/lms?project={{ $project_id }}" hx-boost="false"
               style="display: flex; flex-direction: column; padding: 1.25rem; border-radius: 10px; background: var(--vp-c-bg); border: 1px solid var(--vp-c-divider); text-decoration: none; color: inherit; transition: all 0.2s;">
                <div style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.5rem;">
                    <span>🎓</span>
                    <strong style="color: var(--vp-c-text-1);">Course Studio &amp; LMS</strong>
                </div>
                <p style="margin: 0; color: var(--vp-c-text-2); font-size: 0.85rem; line-height: 1.4;">
                    Author structured curriculums, organize modules, build assessments, and view analytics.
                </p>
            </a>
            @endif
        </div>
    </details>
    @endif

    <!-- External Resources and Releases -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 3rem;">
        <!-- Resources & Links -->
        <div>
            <h3 class="home-resources-title">External Resources</h3>
            <ul class="home-resources-list">
                @if(!empty($project['links']['website']))
                    <li><a href="@external_url($project['links']['website'])" target="_blank" rel="noopener" class="home-resource-item">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
                        Official Website
                    </a></li>
                @endif
                
                @if(!empty($project['links']['source']))
                    <li><a href="@external_url($project['links']['source'])" target="_blank" rel="noopener" class="home-resource-item">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"></path></svg>
                        Source Code Repository
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
        </div>

        <!-- Latest Releases -->
        <div>
            <h3 style="font-size: 1.25rem; margin-bottom: 1.25rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--vp-c-divider); font-weight: 700; color: var(--vp-c-text-1);">Latest Releases</h3>
            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                @if(!empty($project['releases']))
                    @foreach(array_slice($project['releases'], 0, 5) as $release)
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1rem; background: var(--vp-c-bg-soft); border-radius: 8px; border: 1px solid var(--vp-c-divider);">
                            <div style="display: flex; flex-direction: column;">
                                <a href="{{ $release['url'] ?? '#' }}" target="_blank" style="font-weight: 600; text-decoration: none; color: var(--vp-c-brand);">{{ $release['version'] }}</a>
                                @if(!empty($release['date']))
                                    <span style="font-size: 0.8rem; color: var(--vp-c-text-3);">{{ $release['date'] }}</span>
                                @endif
                            </div>
                            <a href="{{ $release['url'] ?? '#' }}" target="_blank" style="padding: 0.25rem 0.65rem; background: var(--vp-c-bg); border: 1px solid var(--vp-c-divider); border-radius: 6px; font-size: 0.8rem; text-decoration: none; color: var(--vp-c-text-2);">Changelog</a>
                        </div>
                    @endforeach
                @else
                    <p style="color: var(--vp-c-text-3); font-size: 0.95rem;">No releases published yet.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
