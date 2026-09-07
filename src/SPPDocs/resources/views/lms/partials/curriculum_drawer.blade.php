@php
    $percent = $progress['percentage'] ?? 0;
    $isDone = $percent >= 100;
@endphp
<div style="background: var(--vp-c-bg-soft); border: 1px solid var(--vp-c-divider); border-radius: 12px; padding: 1.25rem; font-size: 0.9rem;">
    <!-- Course Title & Progress Bar -->
    <div style="margin-bottom: 1.25rem; border-bottom: 1px solid var(--vp-c-divider); padding-bottom: 1rem;">
        <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--vp-c-brand); letter-spacing: 0.05em; margin-bottom: 0.25rem;">
            Course Syllabus
        </div>
        <a href="@url('lms/course?projectId=' . $project_id . '&course=' . $course['id'])" style="font-weight: 700; color: var(--vp-c-text-1); text-decoration: none; font-size: 1rem; display: block; margin-bottom: 0.75rem;">
            {{ $course['title'] }}
        </a>

        <div style="display: flex; justify-content: space-between; font-size: 0.78rem; font-weight: 600; color: var(--vp-c-text-2); margin-bottom: 0.35rem;">
            <span>{{ $progress['completed_count'] ?? 0 }} of {{ $progress['total_items'] ?? 0 }} done</span>
            <span>{{ $percent }}%</span>
        </div>
        <div style="height: 6px; width: 100%; background: var(--vp-c-divider); border-radius: 9999px; overflow: hidden;">
            <div style="height: 100%; width: {{ $percent }}%; background: {{ $isDone ? '#10b981' : 'var(--vp-c-brand)' }}; border-radius: 9999px; transition: width 0.3s;"></div>
        </div>
    </div>

    <!-- Modules List -->
    <div style="display: flex; flex-direction: column; gap: 1rem;">
        @foreach($course['modules'] as $mIdx => $mod)
            <div>
                <div style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--vp-c-text-3); letter-spacing: 0.05em; margin-bottom: 0.4rem;">
                    {{ $mod['title'] }}
                </div>
                <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                    @foreach($mod['lessons'] ?? [] as $lIdx => $lsn)
                        @php
                            $isCurr = ($lsn['id'] === $current_lesson_id);
                            $isLsnDone = in_array($lsn['id'], $progress['completed_lessons'] ?? []);
                            $isQuiz = ($lsn['type'] ?? '') === 'quiz';
                            $isLinear = ($course['progression_mode'] ?? 'free') === 'linear';
                            $isUnlocked = !$isLinear || $isLsnDone || $isCurr || \App\SPPDocs\Services\LmsService::canAccessLesson($project_id, [], $course['id'], $lsn['id'], $username ?? 'guest');
                        @endphp
                        @if($isUnlocked)
                            <a href="@url('lms/lesson?projectId=' . $project_id . '&course=' . $course['id'] . '&lesson=' . $lsn['id'])" 
                               style="display: flex; align-items: center; justify-content: space-between; padding: 0.45rem 0.6rem; border-radius: 6px; text-decoration: none; {{ $isCurr ? 'background: rgba(234, 88, 12, 0.12); color: var(--vp-c-brand); font-weight: 700;' : 'color: var(--vp-c-text-2);' }}">
                                <div style="display: flex; align-items: center; gap: 0.5rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <span style="font-size: 0.9rem; flex-shrink: 0;">
                                        @if($isLsnDone)
                                            <span style="color: #10b981;">✓</span>
                                        @else
                                            <span style="color: var(--vp-c-text-3);">○</span>
                                        @endif
                                    </span>
                                    <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 0.85rem;">
                                        {{ $lsn['title'] }}
                                    </span>
                                </div>
                                @if($isQuiz)
                                    <span style="font-size: 0.68rem; font-weight: 700; text-transform: uppercase; background: rgba(139, 92, 246, 0.1); color: #8b5cf6; padding: 0.1rem 0.4rem; border-radius: 9999px; margin-left: 0.4rem; flex-shrink: 0;">
                                        Quiz
                                    </span>
                                @endif
                            </a>
                        @else
                            <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.45rem 0.6rem; border-radius: 6px; color: var(--vp-c-text-3); opacity: 0.55; cursor: not-allowed;" 
                                 title="Complete previous lessons to unlock this unit">
                                <div style="display: flex; align-items: center; gap: 0.5rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <span style="font-size: 0.85rem; flex-shrink: 0;">🔒</span>
                                    <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 0.85rem;">
                                        {{ $lsn['title'] }}
                                    </span>
                                </div>
                                <span style="font-size: 0.68rem; font-weight: 700; text-transform: uppercase; background: rgba(100, 116, 139, 0.15); color: #64748b; padding: 0.1rem 0.4rem; border-radius: 9999px; margin-left: 0.4rem; flex-shrink: 0;">
                                    Locked
                                </span>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <!-- Certificate Link if Unlocked -->
    @if(!empty($progress['certificate_id']))
        <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--vp-c-divider); text-align: center;">
            <a href="@url('lms/certificate?projectId=' . $project_id . '&code=' . $progress['certificate_id'])" 
               style="display: block; padding: 0.55rem; background: #10b981; color: #fff; font-weight: 700; font-size: 0.82rem; text-decoration: none; border-radius: 6px;">
                🏆 View Certificate
            </a>
        </div>
    @endif
</div>
