<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SatyaLab Network</title>
</head>
<body hx-boost="false">
    <style>
        :root {
            --bg-color: #050505;
            --card-bg: rgba(255, 255, 255, 0.03);
            --card-border: rgba(255, 255, 255, 0.08);
            --card-hover-border: rgba(255, 255, 255, 0.2);
            --text-primary: #ededed;
            --text-secondary: #a1a1aa;
            --accent-glow: rgba(249, 115, 22, 0.15); /* Saffron Glow */
            --accent-color: #f97316; /* Saffron Orange */
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--bg-color);
            background-image: 
                radial-gradient(circle at 15% 50%, rgba(249, 115, 22, 0.08), transparent 25%),
                radial-gradient(circle at 85% 30%, rgba(234, 88, 12, 0.08), transparent 25%);
            margin: 0;
            padding: 0;
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .navbar {
            width: 100%;
            padding: 1.25rem 3rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-sizing: border-box;
            border-bottom: 1px solid var(--card-border);
            backdrop-filter: blur(12px);
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .navbar .brand {
            font-size: 1.35rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            text-decoration: none;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .navbar .brand span {
            color: var(--accent-color);
        }
        .navbar .nav-links {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        .navbar .nav-link {
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.92rem;
            font-weight: 500;
            transition: color 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .navbar .nav-link:hover {
            color: #fff;
        }
        .navbar .nav-btn-store {
            background: rgba(249, 115, 22, 0.15);
            border: 1px solid rgba(249, 115, 22, 0.4);
            color: #fed7aa;
            padding: 0.5rem 1.1rem;
            border-radius: 8px;
            font-size: 0.88rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .navbar .nav-btn-store:hover {
            background: var(--accent-color);
            color: #fff;
            border-color: var(--accent-color);
            box-shadow: 0 4px 15px rgba(249, 115, 22, 0.3);
        }

        .hero {
            text-align: center;
            padding: 5rem 2rem 3rem;
            max-width: 850px;
        }
        .hero h1 {
            font-size: 4rem;
            font-weight: 800;
            letter-spacing: -0.04em;
            margin: 0 0 1.5rem;
            line-height: 1.1;
            /* Metallic Saffron Gradient */
            background: linear-gradient(135deg, #fff 0%, #ffedd5 30%, #f97316 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero p {
            font-size: 1.25rem;
            color: var(--text-secondary);
            margin: 0 auto;
            line-height: 1.6;
            max-width: 680px;
        }

        /* App Store Spotlight Card */
        .spotlight-container {
            max-width: 1200px;
            width: 100%;
            padding: 0 2rem;
            margin-bottom: 2rem;
            box-sizing: border-box;
        }
        .spotlight-card {
            background: linear-gradient(135deg, rgba(249, 115, 22, 0.08) 0%, rgba(255, 255, 255, 0.02) 100%);
            border: 1px solid rgba(249, 115, 22, 0.3);
            border-radius: 16px;
            padding: 2.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 2rem;
            flex-wrap: wrap;
            backdrop-filter: blur(12px);
            box-shadow: 0 15px 35px -10px rgba(0, 0, 0, 0.5), 0 0 30px -5px var(--accent-glow);
        }
        .spotlight-info h2 {
            font-size: 1.85rem;
            font-weight: 700;
            color: #fff;
            margin: 0.5rem 0 0.75rem;
            letter-spacing: -0.02em;
        }
        .spotlight-info p {
            color: var(--text-secondary);
            max-width: 650px;
            margin: 0;
            font-size: 1rem;
            line-height: 1.6;
        }
        .spotlight-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
            gap: 1.5rem;
            padding: 2rem;
            max-width: 1200px;
            width: 100%;
            box-sizing: border-box;
            margin-bottom: 4rem;
        }

        .card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 2rem;
            text-align: left;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(10px);
        }
        
        .card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; height: 1px;
            background: linear-gradient(90deg, transparent, rgba(249, 115, 22, 0.4), transparent);
            opacity: 0;
            transition: opacity 0.4s ease;
        }

        .card:hover {
            transform: translateY(-4px);
            border-color: rgba(249, 115, 22, 0.3);
            background: rgba(255, 255, 255, 0.05);
            box-shadow: 0 10px 40px -10px rgba(0,0,0,0.5), 0 0 25px -5px var(--accent-glow);
        }
        
        .card:hover::before {
            opacity: 1;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }

        .badge {
            display: inline-block;
            background: rgba(249, 115, 22, 0.1);
            border: 1px solid rgba(249, 115, 22, 0.2);
            color: #fed7aa;
            padding: 0.35rem 0.75rem;
            border-radius: 99px;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .card h2 {
            margin: 0 0 0.75rem;
            color: var(--text-primary);
            font-size: 1.5rem;
            font-weight: 600;
            letter-spacing: -0.02em;
        }

        .card p {
            color: var(--text-secondary);
            line-height: 1.6;
            margin: 0 0 2rem;
            flex-grow: 1;
            font-size: 0.95rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: transparent;
            color: var(--text-primary);
            padding: 0.75rem 1.5rem;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.9rem;
            border: 1px solid rgba(255, 255, 255, 0.15);
            transition: all 0.2s ease;
            width: fit-content;
        }

        .btn:hover {
            background: var(--accent-color);
            color: #fff;
            border-color: var(--accent-color);
            box-shadow: 0 4px 15px rgba(249, 115, 22, 0.3);
        }
        
        .card:hover .btn {
            border-color: rgba(255, 255, 255, 0.3);
        }

        /* Subtle animated grid background */
        .bg-grid {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background-size: 40px 40px;
            background-image: linear-gradient(to right, rgba(255, 255, 255, 0.02) 1px, transparent 1px),
                              linear-gradient(to bottom, rgba(255, 255, 255, 0.02) 1px, transparent 1px);
            z-index: -1;
            pointer-events: none;
            mask-image: radial-gradient(circle at center, black 0%, transparent 80%);
            -webkit-mask-image: radial-gradient(circle at center, black 0%, transparent 80%);
        }
        /* Project Filter Navigation */
        .filter-bar {
            max-width: 1200px;
            width: 100%;
            padding: 0 2rem;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            box-sizing: border-box;
        }
        .filter-pills {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }
        .filter-pill {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: var(--text-secondary);
            padding: 0.45rem 1rem;
            border-radius: 99px;
            font-size: 0.85rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .filter-pill:hover, .filter-pill.active {
            background: rgba(249, 115, 22, 0.2);
            border-color: var(--accent-color);
            color: #fff;
        }
        .search-box {
            display: flex;
            align-items: center;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 8px;
            padding: 0.4rem 0.8rem;
            gap: 8px;
        }
        .search-input {
            background: transparent;
            border: none;
            color: #fff;
            outline: none;
            font-size: 0.88rem;
            width: 180px;
        }
        .badge-type-app { background: rgba(59, 130, 246, 0.15); border-color: rgba(59, 130, 246, 0.3); color: #93c5fd; }
        .badge-type-module { background: rgba(168, 85, 247, 0.15); border-color: rgba(168, 85, 247, 0.3); color: #d8b4fe; }
        .badge-type-docs { background: rgba(249, 115, 22, 0.15); border-color: rgba(249, 115, 22, 0.3); color: #fed7aa; }
        .badge-personal { background: rgba(236, 72, 153, 0.15); border-color: rgba(236, 72, 153, 0.3); color: #f472b6; }
        .badge-group { background: rgba(14, 165, 233, 0.15); border-color: rgba(14, 165, 233, 0.3); color: #7dd3fc; }
        .badge-archived { background: rgba(245, 158, 11, 0.15); border-color: rgba(245, 158, 11, 0.3); color: #fde68a; }
        .badge-suspended { background: rgba(239, 68, 68, 0.15); border-color: rgba(239, 68, 68, 0.3); color: #fca5a5; }

        /* Modal Styles */
        .portal-modal-backdrop {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(8px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            box-sizing: border-box;
        }
        .portal-modal-backdrop.active {
            display: flex;
        }
        .portal-modal {
            background: #111113;
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 16px;
            max-width: 600px;
            width: 100%;
            padding: 2rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
        }
        .portal-modal h2 {
            margin: 0 0 1rem;
            font-size: 1.5rem;
            color: #fff;
        }
        .portal-form-group {
            margin-bottom: 1rem;
        }
        .portal-form-label {
            display: block;
            margin-bottom: 0.35rem;
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
        .portal-form-input, .portal-form-select, .portal-form-textarea {
            width: 100%;
            padding: 0.65rem 0.85rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 8px;
            color: #fff;
            box-sizing: border-box;
            font-family: inherit;
        }
        .portal-form-select option {
            background: #18181b;
            color: #fff;
        }
        .portal-modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 1.5rem;
        }
    </style>
    <div class="bg-grid"></div>

    @php
        $basePrefix = defined('APP_BASE_URI') && APP_BASE_URI !== '' && APP_BASE_URI !== '/' ? rtrim(APP_BASE_URI, '/') : '';
        $storeUrl = $basePrefix . '/store';
    @endphp

    <nav class="navbar">
        <a href="{{ $basePrefix }}/" class="brand">
            <span>🌌</span> Satya<span>Lab</span>
        </a>
        <div class="nav-links">
            <a href="{{ $storeUrl }}" class="nav-link">🏪 App Store</a>
            <a href="#flagship-projects" class="nav-link">📚 Projects</a>
            <a href="{{ $storeUrl }}/packages.json" target="_blank" class="nav-link">📜 Registry API</a>
            @if(!empty($currentUser))
                <span class="nav-link" style="color: #fed7aa; display: inline-flex; align-items: center; gap: 5px;">
                    <span>👤</span> <strong>{{ $currentUser }}</strong>
                </span>
                <button type="button" onclick="openCreateProjectModal()" class="nav-btn-store" style="background: rgba(16, 185, 129, 0.15); border-color: rgba(16, 185, 129, 0.4); color: #6ee7b7; cursor: pointer;">
                    <span>➕ New Project</span>
                </button>
            @else
                <a href="{{ $storeUrl }}/login" class="nav-link">🔑 Sign In</a>
            @endif
            <a href="{{ $storeUrl }}" class="nav-btn-store">
                <span>Explore Store →</span>
            </a>
        </div>
    </nav>

    <div class="hero">
        <h1>Building the Future</h1>
        <p>Explore sovereign applications, framework extensions, modular packages, and knowledge hubs engineered by SatyaLab.</p>
    </div>

    <!-- SatyaLab App Store Spotlight -->
    <div class="spotlight-container">
        <div class="spotlight-card">
            <div class="spotlight-info">
                <span class="badge" style="background: rgba(16, 185, 129, 0.15); border-color: rgba(16, 185, 129, 0.3); color: #34d399;">
                    Universal Package Hub
                </span>
                <h2>SatyaLab Sovereign App Store & Registry</h2>
                <p>
                    Browse, install, and distribute verified SPP applications, modules, themes, and sovereign components across satyalab.net and private clusters.
                </p>
            </div>
            <div class="spotlight-actions">
                <a href="{{ $storeUrl }}" class="btn" style="background: var(--accent-color); color: #fff; border-color: var(--accent-color); font-weight: 600;">
                    🏪 Open App Store →
                </a>
                <a href="{{ $storeUrl }}/packages.json" target="_blank" class="btn">
                    packages.json
                </a>
            </div>
        </div>
    </div>

    <!-- Project Visibility & Categorization Filter Bar -->
    @php
        $currFilter = $activeFilter ?? 'all';
    @endphp
    <div class="filter-bar" id="flagship-projects">
        <div class="filter-pills">
            <a href="{{ $basePrefix }}/?filter=all" class="filter-pill {{ $currFilter === 'all' ? 'active' : '' }}">
                <span>🌐 All Projects</span>
            </a>
            <a href="{{ $basePrefix }}/?filter=apps" class="filter-pill {{ $currFilter === 'apps' ? 'active' : '' }}">
                <span>📱 Apps &amp; Hosted</span>
            </a>
            <a href="{{ $basePrefix }}/?filter=modules" class="filter-pill {{ $currFilter === 'modules' ? 'active' : '' }}">
                <span>📦 Modules</span>
            </a>
            <a href="{{ $basePrefix }}/?filter=docs" class="filter-pill {{ $currFilter === 'docs' ? 'active' : '' }}">
                <span>📚 Documentation</span>
            </a>
            @if(!empty($currentUser))
            <a href="{{ $basePrefix }}/?filter=my" class="filter-pill {{ $currFilter === 'my' ? 'active' : '' }}">
                <span>👥 My Projects</span>
            </a>
            <a href="{{ $basePrefix }}/?filter=archived" class="filter-pill {{ $currFilter === 'archived' ? 'active' : '' }}">
                <span>📦 Archived</span>
            </a>
            @endif
        </div>

        <form method="GET" action="{{ $basePrefix }}/" class="search-box">
            @if($currFilter !== 'all')
                <input type="hidden" name="filter" value="{{ $currFilter }}">
            @endif
            <input type="text" name="search" value="{{ $searchQuery ?? '' }}" placeholder="Search projects..." class="search-input">
            <button type="submit" style="background:none; border:none; color: var(--text-secondary); cursor: pointer; padding: 0 4px;">🔍</button>
            @if(!empty($searchQuery))
                <a href="{{ $basePrefix }}/?filter={{ $currFilter }}" style="color: var(--text-secondary); text-decoration: none; font-size: 0.8rem;">✕</a>
            @endif
        </form>
    </div>
    
    <div class="grid">
        @forelse($projects as $id => $project)
        <div class="card">
            <div class="card-header" style="flex-wrap: wrap; gap: 6px;">
                @php
                    $pType = strtolower($project['type'] ?? 'docs');
                    $pVis = strtolower($project['visibility'] ?? 'public');
                    $isArch = !empty($project['is_archived']) || ($project['status'] ?? '') === 'archived';
                    $isSusp = ($project['status'] ?? '') === 'suspended';
                @endphp

                <!-- Type Badge -->
                @if($pType === 'app')
                    <span class="badge badge-type-app">📱 App</span>
                @elseif($pType === 'module')
                    <span class="badge badge-type-module">📦 Module</span>
                @elseif($pType === 'presentation')
                    <span class="badge badge-type-docs" style="background: rgba(147, 51, 234, 0.15); border-color: rgba(147, 51, 234, 0.3); color: #c084fc;">📊 Deck</span>
                @else
                    <span class="badge badge-type-docs">📚 Docs</span>
                @endif

                <!-- Visibility Badge -->
                @if($pVis === 'personal')
                    <span class="badge badge-personal" title="Personal project: Visible only to creator and administrators">🔒 Personal</span>
                @elseif(in_array($pVis, ['group', 'closed_group'], true))
                    <span class="badge badge-group" title="Closed Group project: Accessible only by designated team members">👥 Closed Group</span>
                @elseif($pVis === 'team')
                    <span class="badge badge-group" title="Team project: Shared with designated team">🏢 Team</span>
                @endif

                <!-- Package Store Link -->
                @if(!empty($project['package_name']))
                    <a href="{{ $storeUrl }}/package/{{ urlencode($project['package_name']) }}" class="badge" style="background: rgba(129, 140, 248, 0.15); border-color: rgba(129, 140, 248, 0.3); color: #a5b4fc; text-decoration: none;" title="Install with php spp.php pkg:install {{ $project['package_name'] }}">
                        📦 {{ $project['package_name'] }}
                    </a>
                @endif

                <!-- Status Badges -->
                @if($isArch)
                    <span class="badge badge-archived">📦 Archived</span>
                @endif
                @if($isSusp)
                    <span class="badge badge-suspended">⚠️ Suspended</span>
                @endif
                @if(!empty($project['is_featured']))
                    <span class="badge" style="background: rgba(234, 179, 8, 0.15); border-color: rgba(234, 179, 8, 0.3); color: #fde047;">⭐ Featured</span>
                @endif
            </div>

            <h2>{{ $project['title'] }}</h2>
            <p>{{ $project['description'] ?? ($project['motto'] ?? '') }}</p>

            <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: auto; align-items: center;">
                <a href="{{ \SPP\App::getBaseUrl() }}/project/{{ $id }}" class="btn">
                    <span>Explore {{ $project['title'] }}</span>
                </a>

                @if($pType === 'app' && !empty($project['demo_url']))
                    <a href="{{ $project['demo_url'] }}" target="_blank" class="btn" style="background: rgba(59, 130, 246, 0.2); border-color: rgba(59, 130, 246, 0.4); color: #93c5fd;">
                        <span>🚀 Launch App ↗</span>
                    </a>
                @endif

                @if(!empty($currentUser) && ($isGlobalAdmin || strcasecmp($project['creator'] ?? '', $currentUser) === 0 || !empty($project['members'][$currentUser])))
                    <a href="{{ \SPP\App::getBaseUrl() }}/admin/project/{{ $id }}" class="btn" style="padding: 0.75rem 0.9rem;" title="Configure Project Settings">
                        <span>⚙️</span>
                    </a>
                @endif
            </div>
        </div>
        @empty
        <div style="grid-column: 1 / -1; text-align: center; padding: 4rem 1rem; color: var(--text-secondary);">
            <div style="font-size: 2.5rem; margin-bottom: 1rem;">🔍</div>
            <h3 style="color: #fff; margin: 0 0 0.5rem;">No projects found</h3>
            <p style="margin: 0;">Try adjusting your filters or search keywords.</p>
        </div>
        @endforelse
    </div>

    <!-- Create Project Modal -->
    <div class="portal-modal-backdrop" id="createProjectModal">
        <div class="portal-modal">
            <h2>➕ Create New Project on SatyaLab</h2>
            <form action="@url('admin/project/create')" method="POST">
                <input type="hidden" name="csrf_token" value="{{ $_SESSION['sppdocs_csrf'] ?? '' }}">

                <div class="portal-form-group">
                    <label class="portal-form-label">Project Identifier (Slug / ID) *</label>
                    <input type="text" name="project_id" placeholder="e.g. my-awesome-app" class="portal-form-input" required pattern="[a-zA-Z0-9_-]+">
                </div>

                <div class="portal-form-group">
                    <label class="portal-form-label">Project Title *</label>
                    <input type="text" name="title" placeholder="e.g. My Awesome App" class="portal-form-input" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="portal-form-group">
                        <label class="portal-form-label">Project Type *</label>
                        <select name="type" class="portal-form-select">
                            <option value="app">📱 Application / Hosted App</option>
                            <option value="module">📦 SPP Module / Package</option>
                            <option value="docs" selected>📚 Documentation Project</option>
                            <option value="presentation">📊 Presentation / Deck</option>
                        </select>
                    </div>

                    <div class="portal-form-group">
                        <label class="portal-form-label">Visibility *</label>
                        <select name="visibility" class="portal-form-select">
                            <option value="public" selected>🌐 Public (Catalog &amp; Showcase)</option>
                            <option value="personal">🔒 Personal (Private to Creator)</option>
                            <option value="closed_group">👥 Closed Group (Authorized Members)</option>
                            <option value="team">🏢 Team (Organization Workspace)</option>
                        </select>
                    </div>
                </div>

                <div class="portal-form-group">
                    <label class="portal-form-label">Link to Store Package (Optional)</label>
                    <input type="text" name="package_name" placeholder="e.g. satyalab/school1 or vendor/package" class="portal-form-input">
                </div>

                <div class="portal-form-group">
                    <label class="portal-form-label">Brief Description</label>
                    <input type="text" name="description" placeholder="Short summary of what this project provides..." class="portal-form-input">
                </div>

                <div class="portal-form-group">
                    <label class="portal-form-label">Hosted Demo / Live App URL (for App Projects)</label>
                    <input type="text" name="demo_url" placeholder="https://example.com or /school1" class="portal-form-input">
                </div>

                <div class="portal-form-group">
                    <label class="portal-form-label">Source Code / Git Repository URL</label>
                    <input type="text" name="repo_url" placeholder="https://github.com/..." class="portal-form-input">
                </div>

                <div class="portal-modal-actions">
                    <button type="button" onclick="closeCreateProjectModal()" class="btn">Cancel</button>
                    <button type="submit" class="btn" style="background: var(--accent-color); color: #fff; border-color: var(--accent-color); font-weight: 600;">
                        Create Project →
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openCreateProjectModal() {
            var m = document.getElementById('createProjectModal');
            if (m) m.classList.add('active');
        }
        function closeCreateProjectModal() {
            var m = document.getElementById('createProjectModal');
            if (m) m.classList.remove('active');
        }
        window.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeCreateProjectModal();
        });
    </script>
</body>
</html>
