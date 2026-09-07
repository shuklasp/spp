@extends('layouts.base')
@section('title', $lesson['title'] . ' - ' . $course['title'])

@section('sidebar')
    @spppartial('partials/sidebar.blade.php', [
        'project' => $project,
        'project_id' => $project_id,
        'version' => $project['default_version'] ?? 'v1',
        'current_path' => null
    ])
@endsection

@section('content')
<div style="width: 100%; max-width: 1200px; margin: 0 auto; padding-bottom: 4rem;">
    
    <!-- Top Bar / Breadcrumb Navigation -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid var(--vp-c-divider); padding-bottom: 0.75rem; flex-wrap: wrap; gap: 0.75rem;">
        <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: var(--vp-c-text-2);">
            <a href="@url('lms?projectId=' . $project_id)" style="color: inherit; text-decoration: none;">🎓 Academy</a>
            <span>/</span>
            <a href="@url('lms/course?projectId=' . $project_id . '&course=' . $course['id'])" style="color: inherit; text-decoration: none;">{{ $course['title'] }}</a>
            <span>/</span>
            <span style="color: var(--vp-c-text-1); font-weight: 600;">{{ $lesson['title'] }}</span>
        </div>

        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <a href="@url('lms/course?projectId=' . $project_id . '&course=' . $course['id'])" class="adm-btn adm-btn-secondary adm-btn-sm" style="text-decoration: none; border-radius: 6px; padding: 0.35rem 0.75rem; font-size: 0.82rem;">
                📋 Syllabus
            </a>
            <a href="@url('lms/dashboard?projectId=' . $project_id)" class="adm-btn adm-btn-secondary adm-btn-sm" style="text-decoration: none; border-radius: 6px; padding: 0.35rem 0.75rem; font-size: 0.82rem;">
                📊 Dashboard
            </a>
        </div>
    </div>

    <!-- Main Learning Layout: Content + Curriculum Drawer -->
    <div style="display: flex; gap: 2rem; align-items: flex-start; flex-wrap: wrap;">
        
        <!-- Left / Center: Lesson Focus Reader -->
        <div style="flex: 1; min-width: 320px;">
            
            <!-- Lesson Header Card -->
            <div style="margin-bottom: 2rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.4rem;">
                    <span style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--vp-c-brand); letter-spacing: 0.05em;">
                        {{ $lesson['module_title'] ?? 'Module' }}
                    </span>
                    <span style="color: var(--vp-c-text-3);">•</span>
                    <span style="font-size: 0.8rem; color: var(--vp-c-text-2);">⏱️ {{ $lesson['duration'] ?? '15 mins' }}</span>
                </div>
                <h1 style="font-size: 2.2rem; font-weight: 800; margin: 0 0 0.5rem 0; color: var(--vp-c-text-1); line-height: 1.25;">
                    {{ $lesson['title'] }}
                </h1>
                @if(!empty($lesson['frontmatter']['summary']))
                    <p style="font-size: 1.05rem; color: var(--vp-c-text-2); line-height: 1.5; margin: 0;">
                        {{ $lesson['frontmatter']['summary'] }}
                    </p>
                @endif
            </div>

            <!-- Optional Video Embed -->
            @if(!empty($lesson['frontmatter']['video_url']))
                @php
                    $videoUrl = $lesson['frontmatter']['video_url'];
                    if (str_contains($videoUrl, 'youtube.com') || str_contains($videoUrl, 'youtu.be')) {
                        $sep = str_contains($videoUrl, '?') ? '&' : '?';
                        if (!str_contains($videoUrl, 'enablejsapi=')) {
                            $videoUrl .= $sep . 'enablejsapi=1';
                        }
                    }
                @endphp
                <div style="margin-bottom: 2rem; border-radius: 12px; overflow: hidden; background: #000; aspect-ratio: 16/9;">
                    <iframe id="lms-lesson-video"
                            src="{{ $videoUrl }}" 
                            style="width: 100%; height: 100%; border: none;" 
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                            allowfullscreen></iframe>
                </div>
            @endif

            <!-- Lesson Markdown Content -->
            @if(!empty($lesson['content_html']))
                <div class="markdown-body" style="background: transparent; color: var(--vp-c-text-1); line-height: 1.7; font-size: 1.02rem;">
                    {!! $lesson['content_html'] !!}
                </div>
            @endif

            <!-- Interactive Quiz Section (if attached or standalone quiz lesson) -->
            @if(!empty($lesson['quiz_data']))
                @spppartial('lms/partials/quiz_block.blade.php', [
                    'project_id' => $project_id,
                    'course' => $course,
                    'lesson' => $lesson,
                    'quiz' => $lesson['quiz_data'],
                    'csrf_token' => $csrf_token,
                ])
            @endif

            <!-- Bottom Lesson Navigation & Complete Bar -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 3.5rem; border-top: 1px solid var(--vp-c-divider); padding-top: 1.75rem; flex-wrap: wrap; gap: 1rem;">
                <div>
                    @if(!empty($lesson['prev_lesson']))
                        <a href="@url('lms/lesson?projectId=' . $project_id . '&course=' . $course['id'] . '&lesson=' . $lesson['prev_lesson']['id'])" 
                           class="adm-btn adm-btn-secondary" 
                           style="padding: 0.6rem 1.1rem; font-size: 0.9rem; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem;">
                            <span>&larr;</span>
                            <span>{{ $lesson['prev_lesson']['title'] }}</span>
                        </a>
                    @else
                        <a href="@url('lms/course?projectId=' . $project_id . '&course=' . $course['id'])" 
                           class="adm-btn adm-btn-secondary" 
                           style="padding: 0.6rem 1.1rem; font-size: 0.9rem; border-radius: 8px; text-decoration: none;">
                            &larr; Course Syllabus
                        </a>
                    @endif
                </div>

                <!-- HTMX Completion Button -->
                @spppartial('lms/partials/lesson_complete_btn.blade.php', [
                    'project_id' => $project_id,
                    'course' => $course,
                    'lesson_id' => $lesson['id'],
                    'is_completed' => $is_completed,
                    'progress' => $progress,
                    'next_lesson' => $lesson['next_lesson'] ?? null,
                    'csrf_token' => $csrf_token,
                    'min_watch_percent' => (int)($lesson['frontmatter']['min_watch_percent'] ?? 0),
                ])
            </div>
        </div>

        <!-- Right Side: Sticky Curriculum Drawer -->
        <div style="width: 320px; flex-shrink: 0; position: sticky; top: 2rem;">
            @spppartial('lms/partials/curriculum_drawer.blade.php', [
                'project_id' => $project_id,
                'course' => $course,
                'current_lesson_id' => $lesson['id'],
                'progress' => $progress,
            ])
        </div>
    </div>
</div>

<script>
(function() {
    var minPercent = {{ (int)($lesson['frontmatter']['min_watch_percent'] ?? 0) }};
    if (minPercent <= 0) return;

    var maxWatched = 0;
    function updateProgress(percent) {
        if (percent > maxWatched) {
            maxWatched = Math.min(100, Math.round(percent));
            var pctElem = document.getElementById('video-watch-percent');
            if (pctElem) pctElem.textContent = maxWatched + '%';
            
            if (maxWatched >= minPercent) {
                unlockCompletionButton();
            }
        }
    }

    function unlockCompletionButton() {
        var btn = document.getElementById('lesson-complete-btn');
        if (btn && (btn.hasAttribute('disabled') || btn.getAttribute('data-locked') === 'true')) {
            btn.removeAttribute('disabled');
            btn.removeAttribute('data-locked');
            btn.style.background = 'var(--vp-c-brand)';
            btn.style.cursor = 'pointer';
            btn.style.boxShadow = '0 4px 8px rgba(234, 88, 12, 0.25)';
            var lbl = document.getElementById('lesson-complete-btn-label');
            if (lbl) lbl.textContent = 'Mark as Completed';
            var status = document.getElementById('video-watch-status');
            if (status) {
                status.innerHTML = '<span style="color: #10b981; font-weight: 600;">✓ Video Requirement Met (' + maxWatched + '%)</span>';
            }
        }
    }

    // Monitor HTML5 videos
    document.querySelectorAll('video').forEach(function(vid) {
        vid.addEventListener('timeupdate', function() {
            if (vid.duration > 0) {
                updateProgress((vid.currentTime / vid.duration) * 100);
            }
        });
        vid.addEventListener('ended', function() {
            updateProgress(100);
        });
    });

    // Monitor YouTube iframe postMessage
    window.addEventListener('message', function(e) {
        try {
            var data = typeof e.data === 'string' ? JSON.parse(e.data) : e.data;
            if (data && data.event === 'infoDelivery' && data.info) {
                var cur = data.info.currentTime;
                var dur = data.info.duration;
                if (dur > 0 && cur !== undefined) {
                    updateProgress((cur / dur) * 100);
                }
            }
        } catch (err) {}
    });

    // Programmatic event listener for testing or custom video wrappers
    window.addEventListener('lms:video-progress', function(e) {
        if (e.detail && e.detail.percent !== undefined) {
            updateProgress(e.detail.percent);
        }
    });
})();
</script>
@endsection
