<div id="quiz-container" role="alert" aria-live="assertive" tabindex="-1" style="background: var(--vp-c-bg); border: 2px solid {{ $result['passed'] ? '#10b981' : '#ef4444' }}; border-radius: 14px; padding: 2rem; margin-top: 2.5rem; box-shadow: 0 4px 14px rgba(0,0,0,0.06); outline: none;">
    <script>
        (function() {
            var el = document.getElementById('quiz-container');
            if (el) {
                el.focus();
                el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        })();
    </script>
    <!-- Result Header -->
    <div style="text-align: center; margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--vp-c-divider);">
        <span style="font-size: 3.5rem; display: block; margin-bottom: 0.5rem;">
            {{ $result['passed'] ? '🎉' : '📚' }}
        </span>
        <h3 style="font-size: 1.8rem; font-weight: 800; margin: 0 0 0.4rem 0; color: {{ $result['passed'] ? '#10b981' : '#ef4444' }};">
            {{ $result['passed'] ? 'Assessment Passed!' : 'Assessment Incomplete' }}
        </h3>
        <p style="margin: 0 0 1rem 0; font-size: 1.05rem; color: var(--vp-c-text-2);">
            You scored <strong style="color: var(--vp-c-text-1);">{{ $result['score'] }}%</strong> ({{ $result['correct_count'] }} of {{ $result['total_questions'] }} correct). Required passing score: {{ $result['passing_score'] }}%.
        </p>

        @if($result['passed'])
            <div style="display: inline-block; padding: 0.35rem 0.85rem; border-radius: 9999px; background: rgba(16, 185, 129, 0.1); color: #10b981; font-weight: 700; font-size: 0.85rem; border: 1px solid rgba(16, 185, 129, 0.2);">
                ✓ Lesson Marked as Completed
            </div>
        @else
            <div style="display: inline-block; padding: 0.35rem 0.85rem; border-radius: 9999px; background: rgba(239, 68, 68, 0.1); color: #ef4444; font-weight: 700; font-size: 0.85rem; border: 1px solid rgba(239, 68, 68, 0.2);">
                Review the explanations below and try again.
            </div>
        @endif
    </div>

    <!-- Question-by-Question Feedback -->
    <div style="display: flex; flex-direction: column; gap: 1.25rem; margin-bottom: 2rem;">
        @foreach($lesson['quiz_data']['questions'] ?? [] as $idx => $q)
            @php
                $fb = $result['feedback'][$q['id']] ?? ['is_correct' => false];
                $isCorrect = !empty($fb['is_correct']);
            @endphp
            <div style="background: var(--vp-c-bg-soft); border: 1px solid {{ $isCorrect ? 'rgba(16, 185, 129, 0.3)' : 'rgba(239, 68, 68, 0.3)' }}; border-radius: 10px; padding: 1.25rem;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem; gap: 0.5rem;">
                    <div style="font-weight: 700; font-size: 0.98rem; color: var(--vp-c-text-1);">
                        <span>{{ $idx + 1 }}. {{ $q['prompt'] }}</span>
                    </div>
                    <span style="font-weight: 800; font-size: 0.85rem; flex-shrink: 0; color: {{ $isCorrect ? '#10b981' : '#ef4444' }};">
                        {{ $isCorrect ? '✓ Correct' : '✗ Incorrect' }}
                    </span>
                </div>

                @if(!empty($fb['explanation']))
                    <div style="margin-top: 0.6rem; padding: 0.65rem 0.85rem; border-radius: 6px; background: var(--vp-c-bg); border-left: 3px solid {{ $isCorrect ? '#10b981' : '#ef4444' }}; font-size: 0.85rem; color: var(--vp-c-text-2); line-height: 1.45;">
                        <strong style="color: var(--vp-c-text-1);">Explanation:</strong> {{ $fb['explanation'] }}
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <!-- Actions & Certificate Unlock -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; border-top: 1px solid var(--vp-c-divider); padding-top: 1.5rem;">
        <!-- Retake Button -->
        <a href="@url('lms/lesson?projectId=' . $project_id . '&course=' . $course['id'] . '&lesson=' . $lesson['id'])" 
           class="adm-btn adm-btn-secondary" 
           style="padding: 0.65rem 1.25rem; font-size: 0.92rem; text-decoration: none; border-radius: 8px;">
            🔄 Retake Assessment
        </a>

        <div style="display: flex; gap: 0.75rem; align-items: center;">
            @if(!empty($certificate) || !empty($progress['certificate_id']))
                @php
                    $cCode = $certificate['certificate_id'] ?? $progress['certificate_id'];
                @endphp
                <a href="@url('lms/certificate?projectId=' . $project_id . '&code=' . $cCode)" 
                   style="padding: 0.75rem 1.5rem; background: #10b981; color: #ffffff; border-radius: 8px; font-weight: 700; font-size: 1rem; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3);">
                    🏆 Claim Your Certificate! &rarr;
                </a>
            @elseif(!empty($lesson['next_lesson']))
                <a href="@url('lms/lesson?projectId=' . $project_id . '&course=' . $course['id'] . '&lesson=' . $lesson['next_lesson']['id'])" 
                   style="padding: 0.75rem 1.5rem; background: var(--vp-c-brand); color: #ffffff; border-radius: 8px; font-weight: 700; font-size: 1rem; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 10px rgba(234, 88, 12, 0.25);">
                    Next Lesson &rarr;
                </a>
            @else
                <a href="@url('lms/course?projectId=' . $project_id . '&course=' . $course['id'])" 
                   class="adm-btn adm-btn-primary" 
                   style="padding: 0.75rem 1.5rem; border-radius: 8px; text-decoration: none; font-weight: 700;">
                    Return to Syllabus &rarr;
                </a>
            @endif
        </div>
    </div>
</div>
