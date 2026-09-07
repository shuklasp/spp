@extends('layouts.base')
@section('title', 'My Learning - ' . ($project['title'] ?? 'SPP Academy'))

@section('sidebar')
    @spppartial('partials/sidebar.blade.php', [
        'project' => $project,
        'project_id' => $project_id,
        'version' => $project['default_version'] ?? 'v1',
        'current_path' => null
    ])
@endsection

@section('content')
<div style="width: 100%; max-width: 1050px; margin: 0 auto; padding-bottom: 4rem;">
    
    <!-- User Profile & Greeting Header -->
    <div style="background: var(--vp-c-bg-soft); border: 1px solid var(--vp-c-divider); border-radius: 16px; padding: 2rem; margin-bottom: 2.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem;">
        <div style="display: flex; gap: 1.25rem; align-items: center;">
            <div style="width: 64px; height: 64px; border-radius: 50%; background: var(--vp-c-brand); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.75rem; font-weight: 800; box-shadow: 0 4px 10px rgba(234, 88, 12, 0.3);">
                {{ strtoupper(substr($dashboard['username'] ?? 'U', 0, 1)) }}
            </div>
            <div>
                <div style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--vp-c-brand); letter-spacing: 0.05em; margin-bottom: 0.2rem;">
                    Learner Dashboard
                </div>
                <h1 style="font-size: 1.8rem; font-weight: 800; margin: 0; color: var(--vp-c-text-1);">
                    Welcome back, {{ $dashboard['username'] }}!
                </h1>
                <p style="margin: 0.25rem 0 0 0; color: var(--vp-c-text-2); font-size: 0.95rem;">
                    Track your course progress, review passed assessments, and access your verified credentials.
                </p>
            </div>
        </div>

        <a href="@url('lms?projectId=' . $project_id)" class="adm-btn adm-btn-secondary" style="padding: 0.6rem 1.25rem; font-weight: 600; text-decoration: none; border-radius: 8px;">
            📚 Browse All Courses &rarr;
        </a>
    </div>

    <!-- Learning Metrics Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem; margin-bottom: 3rem;">
        <div style="background: var(--vp-c-bg-soft); border: 1px solid var(--vp-c-divider); border-radius: 12px; padding: 1.25rem; text-align: center;">
            <div style="font-size: 2rem; margin-bottom: 0.25rem;">📖</div>
            <div style="font-size: 1.8rem; font-weight: 800; color: var(--vp-c-text-1); margin-bottom: 0.15rem;">
                {{ $dashboard['total_enrolled'] ?? 0 }}
            </div>
            <div style="font-size: 0.82rem; color: var(--vp-c-text-2); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">
                Courses Enrolled
            </div>
        </div>

        <div style="background: var(--vp-c-bg-soft); border: 1px solid var(--vp-c-divider); border-radius: 12px; padding: 1.25rem; text-align: center;">
            <div style="font-size: 2rem; margin-bottom: 0.25rem;">✅</div>
            <div style="font-size: 1.8rem; font-weight: 800; color: #10b981; margin-bottom: 0.15rem;">
                {{ $dashboard['total_completed'] ?? 0 }}
            </div>
            <div style="font-size: 0.82rem; color: var(--vp-c-text-2); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">
                Courses Completed
            </div>
        </div>

        <div style="background: var(--vp-c-bg-soft); border: 1px solid var(--vp-c-divider); border-radius: 12px; padding: 1.25rem; text-align: center;">
            <div style="font-size: 2rem; margin-bottom: 0.25rem;">🎯</div>
            <div style="font-size: 1.8rem; font-weight: 800; color: #8b5cf6; margin-bottom: 0.15rem;">
                {{ $dashboard['total_quizzes_passed'] ?? 0 }}
            </div>
            <div style="font-size: 0.82rem; color: var(--vp-c-text-2); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">
                Assessments Passed
            </div>
        </div>

        <div style="background: var(--vp-c-bg-soft); border: 1px solid var(--vp-c-divider); border-radius: 12px; padding: 1.25rem; text-align: center;">
            <div style="font-size: 2rem; margin-bottom: 0.25rem;">🏆</div>
            <div style="font-size: 1.8rem; font-weight: 800; color: #f59e0b; margin-bottom: 0.15rem;">
                {{ count($dashboard['certificates'] ?? []) }}
            </div>
            <div style="font-size: 0.82rem; color: var(--vp-c-text-2); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">
                Certificates Earned
            </div>
        </div>
    </div>

    <!-- In Progress Courses -->
    <div style="margin-bottom: 3rem;">
        <h2 style="font-size: 1.5rem; font-weight: 800; color: var(--vp-c-text-1); margin: 0 0 1rem 0; display: flex; align-items: center; gap: 0.5rem;">
            <span>⏳</span> In Progress Courses
        </h2>

        @if(empty($dashboard['in_progress_courses']))
            <div style="background: var(--vp-c-bg-soft); border: 1px dashed var(--vp-c-divider); border-radius: 12px; padding: 2.5rem 1rem; text-align: center;">
                <p style="color: var(--vp-c-text-2); margin: 0 0 1rem 0; font-size: 0.95rem;">
                    You have no courses currently in progress.
                </p>
                <a href="@url('lms?projectId=' . $project_id)" class="adm-btn adm-btn-primary" style="text-decoration: none; padding: 0.55rem 1.2rem; border-radius: 6px;">
                    Explore Course Catalog
                </a>
            </div>
        @else
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                @foreach($dashboard['in_progress_courses'] as $c)
                    @php
                        $prog = $c['user_progress'] ?? ['percentage' => 0];
                        $percent = $prog['percentage'] ?? 0;
                    @endphp
                    <div style="background: var(--vp-c-bg-soft); border: 1px solid var(--vp-c-divider); border-radius: 12px; padding: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                        <div style="display: flex; gap: 1rem; align-items: center; flex: 1; min-width: 260px;">
                            <span style="font-size: 2.2rem;">{{ $c['badge_icon'] ?? '🎓' }}</span>
                            <div>
                                <h3 style="margin: 0 0 0.25rem 0; font-size: 1.15rem; font-weight: 700; color: var(--vp-c-text-1);">
                                    <a href="@url('lms/course?projectId=' . $project_id . '&course=' . $c['id'])" style="color: inherit; text-decoration: none;">
                                        {{ $c['title'] }}
                                    </a>
                                </h3>
                                <div style="font-size: 0.82rem; color: var(--vp-c-text-2);">
                                    {{ $c['category'] ?? 'General' }} &bull; {{ $c['duration'] ?? 'Self-paced' }}
                                </div>
                            </div>
                        </div>

                        <!-- Progress indicator -->
                        <div style="width: 200px;">
                            <div style="display: flex; justify-content: space-between; font-size: 0.78rem; font-weight: 600; color: var(--vp-c-text-2); margin-bottom: 0.35rem;">
                                <span>{{ $prog['completed_count'] ?? 0 }} of {{ $prog['total_items'] ?? 0 }} done</span>
                                <span>{{ $percent }}%</span>
                            </div>
                            <div style="height: 6px; width: 100%; background: var(--vp-c-divider); border-radius: 9999px; overflow: hidden;">
                                <div style="height: 100%; width: {{ $percent }}%; background: var(--vp-c-brand); border-radius: 9999px;"></div>
                            </div>
                        </div>

                        <a href="@url('lms/course?projectId=' . $project_id . '&course=' . $c['id'])" 
                           class="adm-btn adm-btn-primary" 
                           style="padding: 0.6rem 1.25rem; font-size: 0.9rem; font-weight: 700; text-decoration: none; border-radius: 8px;">
                            Resume &rarr;
                        </a>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Completed Courses & Verifiable Credentials -->
    <div>
        <h2 style="font-size: 1.5rem; font-weight: 800; color: var(--vp-c-text-1); margin: 0 0 1rem 0; display: flex; align-items: center; gap: 0.5rem;">
            <span>🏆</span> Completed Courses &amp; Certifications
        </h2>

        @if(empty($dashboard['completed_courses']))
            <div style="background: var(--vp-c-bg-soft); border: 1px dashed var(--vp-c-divider); border-radius: 12px; padding: 2.5rem 1rem; text-align: center;">
                <p style="color: var(--vp-c-text-2); margin: 0; font-size: 0.95rem;">
                    Complete 100% of a course's lessons and pass all assessments to earn your verifiable certificate of completion.
                </p>
            </div>
        @else
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                @foreach($dashboard['completed_courses'] as $c)
                    @php
                        $prog = $c['user_progress'] ?? [];
                        $certCode = $prog['certificate_id'] ?? null;
                    @endphp
                    <div style="background: var(--vp-c-bg-soft); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 12px; padding: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                        <div style="display: flex; gap: 1rem; align-items: center;">
                            <span style="font-size: 2.2rem;">🏅</span>
                            <div>
                                <h3 style="margin: 0 0 0.25rem 0; font-size: 1.15rem; font-weight: 700; color: var(--vp-c-text-1);">
                                    {{ $c['title'] }}
                                </h3>
                                <div style="font-size: 0.82rem; color: #10b981; font-weight: 600;">
                                    ✓ 100% Completed &bull; Passed all required assessments
                                </div>
                            </div>
                        </div>

                        <div style="display: flex; gap: 0.75rem; align-items: center;">
                            @if($certCode)
                                <a href="@url('lms/certificate?projectId=' . $project_id . '&code=' . $certCode)" 
                                   style="padding: 0.6rem 1.25rem; background: #10b981; color: #ffffff; font-weight: 700; font-size: 0.9rem; text-decoration: none; border-radius: 8px; display: inline-flex; align-items: center; gap: 0.4rem; box-shadow: 0 4px 8px rgba(16, 185, 129, 0.25);">
                                    <span>🏆</span> View Certificate
                                </a>
                            @endif
                            <a href="@url('lms/course?projectId=' . $project_id . '&course=' . $c['id'])" 
                               class="adm-btn adm-btn-secondary" 
                               style="padding: 0.6rem 1rem; font-size: 0.9rem; text-decoration: none; border-radius: 8px;">
                                Review Syllabus
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
