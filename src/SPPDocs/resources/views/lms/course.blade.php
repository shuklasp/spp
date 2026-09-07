@extends('layouts.base')
@section('title', $course['title'] . ' - Academy Syllabus')

@section('sidebar')
    @spppartial('partials/sidebar.blade.php', [
        'project' => $project,
        'project_id' => $project_id,
        'version' => $project['default_version'] ?? 'v1',
        'current_path' => null
    ])
@endsection

@section('content')
<div style="width: 100%; max-width: 950px; margin: 0 auto; padding-bottom: 4rem;">
    
    <!-- Breadcrumb -->
    <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: var(--vp-c-text-2); margin-bottom: 1.5rem;">
        <a href="@url('lms?projectId=' . $project_id)" style="color: inherit; text-decoration: none;">🎓 Academy</a>
        <span>/</span>
        <span style="color: var(--vp-c-text-1); font-weight: 600;">{{ $course['title'] }}</span>
    </div>

    <!-- Course Header Hero -->
    <div style="background: var(--vp-c-bg-soft); border: 1px solid var(--vp-c-divider); border-radius: 16px; padding: 2.25rem; margin-bottom: 2.5rem;">
        <div style="display: flex; gap: 1.5rem; align-items: flex-start; flex-wrap: wrap;">
            <div style="font-size: 3.5rem; background: var(--vp-c-bg); border: 1px solid var(--vp-c-divider); border-radius: 16px; width: 84px; height: 84px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 6px rgba(0,0,0,0.03);">
                {{ $course['badge_icon'] ?? '🎓' }}
            </div>

            <div style="flex: 1; min-width: 280px;">
                <div style="display: flex; gap: 0.6rem; align-items: center; margin-bottom: 0.6rem; flex-wrap: wrap;">
                    @php
                        $lvlColor = match($course['level'] ?? '') {
                            'Beginner' => '#10b981',
                            'Intermediate' => '#3b82f6',
                            'Advanced' => '#8b5cf6',
                            default => '#6b7280'
                        };
                    @endphp
                    <span style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; padding: 0.2rem 0.6rem; border-radius: 9999px; background: rgba(59, 130, 246, 0.1); color: {{ $lvlColor }}; border: 1px solid {{ $lvlColor }}33;">
                        {{ $course['level'] ?? 'Intermediate' }}
                    </span>
                    <span style="font-size: 0.8rem; color: var(--vp-c-text-2);">•</span>
                    <span style="font-size: 0.8rem; color: var(--vp-c-text-2);">📁 {{ $course['category'] ?? 'Architecture' }}</span>
                    <span style="font-size: 0.8rem; color: var(--vp-c-text-2);">•</span>
                    <span style="font-size: 0.8rem; color: var(--vp-c-text-2);">⏱️ {{ $course['duration'] ?? 'Self-paced' }}</span>
                </div>

                <h1 style="font-size: 2.2rem; font-weight: 800; margin: 0 0 0.4rem 0; color: var(--vp-c-text-1); line-height: 1.2;">
                    {{ $course['title'] }}
                </h1>
                
                @if(!empty($course['tagline']))
                    <p style="font-size: 1.1rem; color: var(--vp-c-brand); font-weight: 600; margin: 0 0 1rem 0;">
                        {{ $course['tagline'] }}
                    </p>
                @endif

                <p style="font-size: 1rem; color: var(--vp-c-text-2); line-height: 1.6; margin: 0 0 1.5rem 0;">
                    {{ $course['description'] }}
                </p>

                <!-- Progress & Action Bar -->
                @php
                    $percent = $progress['percentage'] ?? 0;
                    $isDone = $percent >= 100;
                    $hasCert = !empty($progress['certificate_id']);
                @endphp

                <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                    @if($isDone && $hasCert)
                        <a href="@url('lms/certificate?projectId=' . $project_id . '&code=' . $progress['certificate_id'])" 
                           style="padding: 0.75rem 1.5rem; background: #10b981; color: #ffffff; border-radius: 8px; font-weight: 700; font-size: 1rem; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3);">
                            🏆 View Certificate of Completion
                        </a>
                        <a href="@url('lms/lesson?projectId=' . $project_id . '&course=' . $course['id'] . '&lesson=' . ($first_lesson['id'] ?? ''))" 
                           class="adm-btn adm-btn-secondary" style="padding: 0.75rem 1.25rem; font-size: 0.95rem; text-decoration: none; border-radius: 8px;">
                            Review Course
                        </a>
                    @elseif($percent > 0 && !empty($first_lesson))
                        <a href="@url('lms/lesson?projectId=' . $project_id . '&course=' . $course['id'] . '&lesson=' . $first_lesson['id'])" 
                           style="padding: 0.75rem 1.5rem; background: var(--vp-c-brand); color: #ffffff; border-radius: 8px; font-weight: 700; font-size: 1rem; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 10px rgba(234, 88, 12, 0.25);">
                            ▶️ Resume Learning (Lesson {{ $first_lesson['title'] ?? '' }})
                        </a>
                    @elseif(!empty($first_lesson))
                        <a href="@url('lms/lesson?projectId=' . $project_id . '&course=' . $course['id'] . '&lesson=' . $first_lesson['id'])" 
                           style="padding: 0.75rem 1.5rem; background: var(--vp-c-brand); color: #ffffff; border-radius: 8px; font-weight: 700; font-size: 1rem; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 10px rgba(234, 88, 12, 0.25);">
                            🚀 Start Learning Now
                        </a>
                    @endif

                    @if($percent > 0)
                        <div style="flex: 1; min-width: 180px; max-width: 260px;">
                            <div style="display: flex; justify-content: space-between; font-size: 0.8rem; font-weight: 600; color: var(--vp-c-text-2); margin-bottom: 0.35rem;">
                                <span>{{ $progress['completed_count'] ?? 0 }} of {{ $progress['total_items'] ?? 0 }} completed</span>
                                <span>{{ $percent }}%</span>
                            </div>
                            <div style="height: 8px; width: 100%; background: var(--vp-c-divider); border-radius: 9999px; overflow: hidden;">
                                <div style="height: 100%; width: {{ $percent }}%; background: {{ $isDone ? '#10b981' : 'var(--vp-c-brand)' }}; border-radius: 9999px;"></div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Grid: Prerequisites & Instructor Info -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 3rem;">
        <!-- Instructor Card -->
        @if(!empty($course['instructor']['name']))
            <div style="background: var(--vp-c-bg-soft); border: 1px solid var(--vp-c-divider); border-radius: 12px; padding: 1.25rem;">
                <div style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; color: var(--vp-c-text-3); letter-spacing: 0.05em; margin-bottom: 0.75rem;">
                    Course Instructor
                </div>
                <div style="display: flex; gap: 0.75rem; align-items: center; margin-bottom: 0.5rem;">
                    <div style="width: 44px; height: 44px; border-radius: 50%; background: var(--vp-c-brand); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.1rem;">
                        {{ strtoupper(substr($course['instructor']['name'], 0, 1)) }}
                    </div>
                    <div>
                        <div style="font-weight: 700; color: var(--vp-c-text-1); font-size: 1.05rem;">
                            {{ $course['instructor']['name'] }}
                        </div>
                        <div style="font-size: 0.82rem; color: var(--vp-c-text-2);">
                            {{ $course['instructor']['title'] ?? 'Instructor' }}
                        </div>
                    </div>
                </div>
                @if(!empty($course['instructor']['bio']))
                    <p style="font-size: 0.85rem; color: var(--vp-c-text-2); margin: 0; line-height: 1.4;">
                        {{ $course['instructor']['bio'] }}
                    </p>
                @endif
            </div>
        @endif

        <!-- Prerequisites & Certification Requirements -->
        <div style="background: var(--vp-c-bg-soft); border: 1px solid var(--vp-c-divider); border-radius: 12px; padding: 1.25rem;">
            <div style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; color: var(--vp-c-text-3); letter-spacing: 0.05em; margin-bottom: 0.75rem;">
                Prerequisites &amp; Requirements
            </div>
            @if(!empty($course['prerequisites']))
                <ul style="margin: 0 0 0.75rem 0; padding-left: 1.25rem; font-size: 0.88rem; color: var(--vp-c-text-2); line-height: 1.5;">
                    @foreach($course['prerequisites'] as $prereq)
                        <li>{{ $prereq }}</li>
                    @endforeach
                </ul>
            @else
                <p style="font-size: 0.88rem; color: var(--vp-c-text-2); margin: 0 0 0.75rem 0;">No prior knowledge required. Suitable for all skill levels.</p>
            @endif
            <div style="font-size: 0.82rem; color: var(--vp-c-brand); font-weight: 600; display: flex; align-items: center; gap: 0.4rem;">
                <span>🎯</span> Passing Score: {{ $course['passing_score'] ?? 80 }}% on all assessments required for certification.
            </div>
        </div>
    </div>

    <!-- Course Curriculum / Syllabus -->
    <div style="margin-bottom: 2rem;">
        <h2 style="font-size: 1.6rem; font-weight: 800; color: var(--vp-c-text-1); margin: 0 0 0.5rem 0; display: flex; align-items: center; gap: 0.5rem;">
            <span>📚</span> Course Syllabus
        </h2>
        <p style="color: var(--vp-c-text-2); margin: 0 0 1.5rem 0; font-size: 0.95rem;">
            {{ count($course['modules'] ?? []) }} Modules &bull; {{ $course['total_lessons'] ?? 0 }} Lessons &bull; {{ $course['total_quizzes'] ?? 0 }} Assessments
        </p>

        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            @foreach($course['modules'] as $mIndex => $mod)
                <div style="background: var(--vp-c-bg-soft); border: 1px solid var(--vp-c-divider); border-radius: 12px; overflow: hidden;">
                    <!-- Module Header -->
                    <div style="padding: 1.25rem; background: var(--vp-c-bg); border-bottom: 1px solid var(--vp-c-divider);">
                        <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--vp-c-brand); letter-spacing: 0.05em; margin-bottom: 0.2rem;">
                            Module {{ $mIndex + 1 }}
                        </div>
                        <h3 style="margin: 0 0 0.35rem 0; font-size: 1.2rem; font-weight: 700; color: var(--vp-c-text-1);">
                            {{ $mod['title'] }}
                        </h3>
                        @if(!empty($mod['description']))
                            <p style="margin: 0; font-size: 0.88rem; color: var(--vp-c-text-2); line-height: 1.4;">
                                {{ $mod['description'] }}
                            </p>
                        @endif
                    </div>

                    <!-- Module Lessons List -->
                    <div>
                        @foreach($mod['lessons'] ?? [] as $lIndex => $lsn)
                            @php
                                $isLsnDone = in_array($lsn['id'], $progress['completed_lessons'] ?? []);
                                $isQuiz = ($lsn['type'] ?? 'reading') === 'quiz';
                                $isLinear = ($course['progression_mode'] ?? 'free') === 'linear';
                                $isUnlocked = !$isLinear || $isLsnDone || \App\SPPDocs\Services\LmsService::canAccessLesson($project_id, $project, $course['id'], $lsn['id'], $user['username'] ?? 'guest');
                            @endphp
                            <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.9rem 1.25rem; border-bottom: 1px solid var(--vp-c-divider); transition: background 0.15s; {{ !$isUnlocked ? 'opacity: 0.6; background: var(--vp-c-bg-soft);' : '' }}"
                                 @if($isUnlocked)
                                     onmouseover="this.style.background='var(--vp-c-bg)';"
                                     onmouseout="this.style.background='transparent';"
                                 @endif>
                                <div style="display: flex; align-items: center; gap: 0.85rem; flex: 1;">
                                    <span style="font-size: 1.1rem;">
                                        @if($isLsnDone)
                                            <span style="color: #10b981;" title="Completed">✅</span>
                                        @elseif(!$isUnlocked)
                                            <span title="Locked — Complete prior units first">🔒</span>
                                        @else
                                            <span style="color: var(--vp-c-text-3);" title="Not Completed">⚪</span>
                                        @endif
                                    </span>
                                    <div>
                                        @if($isUnlocked)
                                            <a href="@url('lms/lesson?projectId=' . $project_id . '&course=' . $course['id'] . '&lesson=' . $lsn['id'])" 
                                               style="color: var(--vp-c-text-1); text-decoration: none; font-weight: 600; font-size: 0.95rem;">
                                                {{ $lsn['title'] }}
                                            </a>
                                        @else
                                            <span style="color: var(--vp-c-text-2); font-weight: 600; font-size: 0.95rem; cursor: not-allowed;" title="Complete earlier lessons to unlock">
                                                {{ $lsn['title'] }}
                                            </span>
                                        @endif
                                        @if($isQuiz)
                                            <span style="margin-left: 0.5rem; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; background: rgba(139, 92, 246, 0.1); color: #8b5cf6; padding: 0.15rem 0.5rem; border-radius: 9999px;">
                                                Assessment
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                <div style="display: flex; align-items: center; gap: 1rem;">
                                    <span style="font-size: 0.8rem; color: var(--vp-c-text-2);">
                                        {{ $lsn['duration'] ?? '15 mins' }}
                                    </span>
                                    @if($isUnlocked)
                                        <a href="@url('lms/lesson?projectId=' . $project_id . '&course=' . $course['id'] . '&lesson=' . $lsn['id'])" 
                                           class="adm-btn adm-btn-secondary adm-btn-sm" style="padding: 0.35rem 0.75rem; font-size: 0.82rem; text-decoration: none; border-radius: 6px;">
                                            {{ $isLsnDone ? 'Review' : 'Start' }} &rarr;
                                        </a>
                                    @else
                                        <span class="adm-btn adm-btn-secondary adm-btn-sm" style="padding: 0.35rem 0.75rem; font-size: 0.82rem; border-radius: 6px; opacity: 0.5; cursor: not-allowed;">
                                            🔒 Locked
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
