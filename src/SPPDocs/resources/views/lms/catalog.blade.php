@extends('layouts.base')
@section('title', ($project['title'] ?? 'SPP') . ' Academy - Courses')

@section('sidebar')
    @spppartial('partials/sidebar.blade.php', [
        'project' => $project,
        'project_id' => $project_id,
        'version' => $project['default_version'] ?? 'v1',
        'current_path' => null
    ])
@endsection

@section('content')
<div style="width: 100%; max-width: 1100px; margin: 0 auto; padding-bottom: 4rem;">
    
    <!-- Hero / Header Section -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; border-bottom: 1px solid var(--vp-c-divider); padding-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.5rem;">
                <span style="font-size: 2rem;">🎓</span>
                <h1 style="font-size: 2.2rem; font-weight: 800; margin: 0; color: var(--vp-c-text-1);">
                    {{ $project['title'] ?? 'Project' }} Academy
                </h1>
            </div>
            <p style="margin: 0; color: var(--vp-c-text-2); font-size: 1.05rem; max-width: 650px; line-height: 1.5;">
                Interactive, hands-on masterclasses designed to transform developers into enterprise architects. Master concepts through rich guides, live code checks, and verifiable certifications.
            </p>
        </div>

        <div style="display: flex; gap: 0.75rem; align-items: center;">
            <a href="@url('lms/dashboard?projectId=' . $project_id)" class="adm-btn adm-btn-secondary" style="padding: 0.6rem 1.1rem; font-weight: 600; text-decoration: none; border-radius: 8px; display: inline-flex; align-items: center; gap: 0.4rem;">
                <span>📊</span> My Learning
            </a>
            @php
                $userIsAdmin = !empty($_SESSION['sppdocs_user']) && (\App\SPPDocs\Services\PermissionManager::isProjectAdmin($project_id, $_SESSION['sppdocs_user']) || \App\SPPDocs\Services\PermissionManager::isGlobalAdmin($_SESSION['sppdocs_user']));
            @endphp
            @if($userIsAdmin)
                <a href="@url('admin/lms?project=' . $project_id)" class="adm-btn adm-btn-primary" style="padding: 0.6rem 1.1rem; font-weight: 600; text-decoration: none; border-radius: 8px; display: inline-flex; align-items: center; gap: 0.4rem;">
                    <span>⚙️</span> Course Studio
                </a>
            @endif
        </div>
    </div>

    <!-- Search & Filter Controls -->
    <div style="background: var(--vp-c-bg-soft); border: 1px solid var(--vp-c-divider); border-radius: 12px; padding: 1rem 1.25rem; margin-bottom: 2.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <form action="@url('lms')" method="GET" style="display: flex; gap: 0.75rem; flex: 1; min-width: 260px; margin: 0;">
            <input type="hidden" name="projectId" value="{{ $project_id }}">
            @if($filter_level)
                <input type="hidden" name="level" value="{{ $filter_level }}">
            @endif
            <div style="position: relative; flex: 1;">
                <input type="search" 
                       name="search" 
                       value="{{ $search_query }}" 
                       placeholder="Search courses by topic, title, or keywords..." 
                       style="width: 100%; padding: 0.55rem 1rem; border-radius: 8px; border: 1px solid var(--vp-c-divider); background: var(--vp-c-bg); color: var(--vp-c-text-1); font-size: 0.95rem; box-sizing: border-box;">
            </div>
            <button type="submit" class="adm-btn adm-btn-secondary" style="padding: 0.55rem 1rem; cursor: pointer; border-radius: 8px;">
                🔍 Search
            </button>
        </form>

        <div style="display: flex; gap: 0.4rem; align-items: center; flex-wrap: wrap;">
            <span style="font-size: 0.85rem; color: var(--vp-c-text-2); font-weight: 600; margin-right: 0.25rem;">Level:</span>
            <a href="@url('lms?projectId=' . $project_id . ($search_query ? '&search=' . urlencode($search_query) : ''))" 
               style="text-decoration: none; padding: 0.35rem 0.75rem; border-radius: 9999px; font-size: 0.8rem; font-weight: 600; {{ empty($filter_level) ? 'background: var(--vp-c-brand); color: #fff;' : 'background: var(--vp-c-bg); color: var(--vp-c-text-2); border: 1px solid var(--vp-c-divider);' }}">
                All
            </a>
            @foreach(['Beginner', 'Intermediate', 'Advanced'] as $lvl)
                <a href="@url('lms?projectId=' . $project_id . '&level=' . $lvl . ($search_query ? '&search=' . urlencode($search_query) : ''))" 
                   style="text-decoration: none; padding: 0.35rem 0.75rem; border-radius: 9999px; font-size: 0.8rem; font-weight: 600; {{ $filter_level === $lvl ? 'background: var(--vp-c-brand); color: #fff;' : 'background: var(--vp-c-bg); color: var(--vp-c-text-2); border: 1px solid var(--vp-c-divider);' }}">
                    {{ $lvl }}
                </a>
            @endforeach
        </div>
    </div>

    <!-- Course Grid -->
    @if(empty($courses))
        <div style="text-align: center; padding: 4rem 1rem; background: var(--vp-c-bg-soft); border-radius: 12px; border: 1px dashed var(--vp-c-divider);">
            <span style="font-size: 3rem; display: block; margin-bottom: 1rem;">📚</span>
            <h3 style="font-size: 1.3rem; margin: 0 0 0.5rem 0; color: var(--vp-c-text-1);">No courses match your criteria</h3>
            <p style="color: var(--vp-c-text-2); margin: 0 0 1.5rem 0; font-size: 0.95rem;">
                Try adjusting your search terms or difficulty filter.
            </p>
            <a href="@url('lms?projectId=' . $project_id)" class="adm-btn adm-btn-secondary" style="text-decoration: none; padding: 0.5rem 1rem; border-radius: 6px;">
                Reset Filters
            </a>
        </div>
    @else
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.75rem;">
            @foreach($courses as $c)
                @php
                    $prog = $c['user_progress'] ?? ['percentage' => 0];
                    $percent = $prog['percentage'] ?? 0;
                    $isDone = $percent >= 100;
                    $lvlColor = match($c['level'] ?? '') {
                        'Beginner' => '#10b981',
                        'Intermediate' => '#3b82f6',
                        'Advanced' => '#8b5cf6',
                        default => '#6b7280'
                    };
                @endphp
                <div style="background: var(--vp-c-bg-soft); border: 1px solid var(--vp-c-divider); border-radius: 14px; overflow: hidden; display: flex; flex-direction: column; transition: transform 0.2s, box-shadow 0.2s;"
                     onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 12px 20px -5px rgba(0,0,0,0.08)';"
                     onmouseout="this.style.transform='none'; this.style.boxShadow='none';">
                    
                    <!-- Top Ribbon -->
                    <div style="padding: 1.25rem 1.25rem 0 1.25rem; display: flex; justify-content: space-between; align-items: center;">
                        <span style="display: inline-block; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; padding: 0.25rem 0.65rem; border-radius: 9999px; background: rgba(59, 130, 246, 0.1); color: {{ $lvlColor }}; border: 1px solid {{ $lvlColor }}33;">
                            {{ $c['level'] ?? 'All Levels' }}
                        </span>
                        
                        <div style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.8rem; color: var(--vp-c-text-2);">
                            <span>⏱️</span> {{ $c['duration'] ?? 'Self-paced' }}
                        </div>
                    </div>

                    <!-- Card Body -->
                    <div style="padding: 1.25rem; flex: 1; display: flex; flex-direction: column;">
                        <div style="display: flex; align-items: flex-start; gap: 0.75rem; margin-bottom: 0.75rem;">
                            <span style="font-size: 2rem; line-height: 1;">{{ $c['badge_icon'] ?? '🎓' }}</span>
                            <div>
                                <h2 style="margin: 0; font-size: 1.25rem; font-weight: 700; color: var(--vp-c-text-1); line-height: 1.3;">
                                    <a href="@url('lms/course?projectId=' . $project_id . '&course=' . $c['id'])" style="color: inherit; text-decoration: none;">
                                        {{ $c['title'] }}
                                    </a>
                                </h2>
                                @if(!empty($c['tagline']))
                                    <div style="font-size: 0.82rem; color: var(--vp-c-brand); font-weight: 600; margin-top: 0.2rem;">
                                        {{ $c['tagline'] }}
                                    </div>
                                @endif
                            </div>
                        </div>

                        <p style="font-size: 0.9rem; color: var(--vp-c-text-2); line-height: 1.5; margin: 0 0 1.25rem 0; flex: 1;">
                            {{ $c['description'] }}
                        </p>

                        <!-- Curriculum Stats -->
                        <div style="display: flex; gap: 1rem; font-size: 0.82rem; color: var(--vp-c-text-3); margin-bottom: 1.25rem; border-top: 1px solid var(--vp-c-divider); padding-top: 0.75rem;">
                            <span>📖 {{ $c['total_lessons'] ?? 0 }} Lessons</span>
                            <span>✅ {{ $c['total_quizzes'] ?? 0 }} Assessments</span>
                            @if(!empty($c['certificate_enabled']))
                                <span>🏆 Certificate</span>
                            @endif
                        </div>

                        <!-- Progress Bar if Enrolled -->
                        @if($percent > 0)
                            <div style="margin-bottom: 1rem;">
                                <div style="display: flex; justify-content: space-between; font-size: 0.78rem; font-weight: 600; color: var(--vp-c-text-2); margin-bottom: 0.35rem;">
                                    <span>Progress</span>
                                    <span>{{ $percent }}%</span>
                                </div>
                                <div style="height: 6px; width: 100%; background: var(--vp-c-divider); border-radius: 9999px; overflow: hidden;">
                                    <div style="height: 100%; width: {{ $percent }}%; background: {{ $isDone ? '#10b981' : 'var(--vp-c-brand)' }}; border-radius: 9999px;"></div>
                                </div>
                            </div>
                        @endif

                        <!-- Action Button -->
                        <div style="margin-top: auto;">
                            @if($isDone)
                                <a href="@url('lms/course?projectId=' . $project_id . '&course=' . $c['id'])" 
                                   style="display: block; text-align: center; padding: 0.65rem 1rem; background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); border-radius: 8px; font-weight: 600; font-size: 0.9rem; text-decoration: none;">
                                    ✅ Course Completed &bull; Review
                                </a>
                            @elseif($percent > 0)
                                <a href="@url('lms/course?projectId=' . $project_id . '&course=' . $c['id'])" 
                                   style="display: block; text-align: center; padding: 0.65rem 1rem; background: var(--vp-c-brand); color: #ffffff; border-radius: 8px; font-weight: 600; font-size: 0.9rem; text-decoration: none;">
                                    ▶️ Continue Learning &rarr;
                                </a>
                            @else
                                <a href="@url('lms/course?projectId=' . $project_id . '&course=' . $c['id'])" 
                                   style="display: block; text-align: center; padding: 0.65rem 1rem; background: var(--vp-c-bg); border: 1px solid var(--vp-c-brand); color: var(--vp-c-brand); border-radius: 8px; font-weight: 600; font-size: 0.9rem; text-decoration: none; transition: background 0.2s;"
                                   onmouseover="this.style.background='var(--vp-c-brand)'; this.style.color='#ffffff';"
                                   onmouseout="this.style.background='var(--vp-c-bg)'; this.style.color='var(--vp-c-brand)';">
                                    Start Course &rarr;
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
