@extends('layouts.admin')

@section('title', 'Student Roster & Analytics — ' . ($project['title'] ?? $project_id))

@section('content')
@php
    $active_tab = 'lms';
@endphp

<div class="adm-page-header">
    <div>
        <h1 class="adm-page-title">
            <span>📊</span> Student Roster &amp; Learning Analytics
            <code class="adm-title-project-slug">{{ $project_id }}</code>
        </h1>
        <p class="adm-page-desc">
            Monitor real-time student engagement, assessment performance, completion rates, and verifiable certificate issuance.
        </p>
    </div>
    <div class="adm-page-actions" style="display: flex; gap: 0.75rem;">
        <a href="@url('admin/lms?project=' . $project_id)" class="adm-btn adm-btn-secondary">
            &larr; Course Studio
        </a>
        @if(!empty($active_course_id))
            <a href="@url('admin/lms/export?project=' . $project_id . '&course=' . $active_course_id)" class="adm-btn adm-btn-primary" style="display: inline-flex; align-items: center; gap: 0.4rem;">
                <span>📥</span> Export Gradebook (CSV)
            </a>
        @endif
    </div>
</div>

<!-- Course Selector Filter -->
<div style="background: var(--adm-card-bg, #ffffff); border: 1px solid var(--adm-border, #e2e8f0); border-radius: 12px; padding: 1.25rem; margin-bottom: 2rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
    <div style="display: flex; align-items: center; gap: 0.75rem;">
        <span style="font-weight: 700; color: var(--adm-text-main);">Filter Course:</span>
        <form method="GET" action="@url('admin/lms/analytics')" style="margin: 0;">
            <input type="hidden" name="project" value="{{ $project_id }}">
            <select name="course" onchange="this.form.submit()" class="adm-form-input" style="padding: 0.45rem 1rem; font-weight: 600;">
                @foreach($courses as $c)
                    <option value="{{ $c['id'] }}" {{ $active_course_id === $c['id'] ? 'selected' : '' }}>
                        {{ $c['badge_icon'] ?? '🎓' }} {{ $c['title'] }} ({{ $c['id'] }})
                    </option>
                @endforeach
            </select>
        </form>
    </div>

    @if(!empty($analytics['course']))
        <span style="font-size: 0.85rem; color: var(--adm-text-muted);">
            Passing threshold: <strong style="color: #8b5cf6;">{{ $analytics['course']['passing_score'] ?? 80 }}%</strong>
        </span>
    @endif
</div>

<!-- Top Metric Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
    <div class="adm-card" style="padding: 1.25rem; text-align: center;">
        <div style="font-size: 1.8rem; font-weight: 800; color: var(--adm-text-main); margin-bottom: 0.2rem;">
            {{ $analytics['total_students'] ?? 0 }}
        </div>
        <div style="font-size: 0.8rem; font-weight: 700; color: var(--adm-text-muted); text-transform: uppercase; letter-spacing: 0.05em;">
            Total Enrolled
        </div>
    </div>

    <div class="adm-card" style="padding: 1.25rem; text-align: center;">
        <div style="font-size: 1.8rem; font-weight: 800; color: #10b981; margin-bottom: 0.2rem;">
            {{ $analytics['completed_students'] ?? 0 }}
        </div>
        <div style="font-size: 0.8rem; font-weight: 700; color: var(--adm-text-muted); text-transform: uppercase; letter-spacing: 0.05em;">
            Certified Graduates
        </div>
    </div>

    <div class="adm-card" style="padding: 1.25rem; text-align: center;">
        <div style="font-size: 1.8rem; font-weight: 800; color: #3b82f6; margin-bottom: 0.2rem;">
            {{ $analytics['in_progress_students'] ?? 0 }}
        </div>
        <div style="font-size: 0.8rem; font-weight: 700; color: var(--adm-text-muted); text-transform: uppercase; letter-spacing: 0.05em;">
            In Progress
        </div>
    </div>

    <div class="adm-card" style="padding: 1.25rem; text-align: center;">
        <div style="font-size: 1.8rem; font-weight: 800; color: #8b5cf6; margin-bottom: 0.2rem;">
            {{ $analytics['average_quiz_score'] ?? 0 }}%
        </div>
        <div style="font-size: 0.8rem; font-weight: 700; color: var(--adm-text-muted); text-transform: uppercase; letter-spacing: 0.05em;">
            Average Score
        </div>
    </div>
</div>

<!-- Student Roster Table -->
<div class="adm-card">
    <div class="adm-card-header">
        <h2 class="adm-card-title">Enrolled Student Lineage &amp; Progress</h2>
    </div>

    @if(empty($analytics['roster']))
        <div style="text-align: center; padding: 3rem 1rem;">
            <span style="font-size: 2.5rem; display: block; margin-bottom: 0.5rem;">👥</span>
            <h3 style="margin: 0 0 0.4rem 0; color: var(--adm-text-main);">No student progress records yet</h3>
            <p style="color: var(--adm-text-muted); margin: 0; font-size: 0.95rem;">
                Students who start lessons or attempt quizzes will automatically appear in this roster.
            </p>
        </div>
    @else
        <table class="adm-table">
            <thead>
                <tr>
                    <th>Learner</th>
                    <th>Lifecycle State</th>
                    <th>Progress Fraction</th>
                    <th>Last Active</th>
                    <th style="text-align: right;">Certificate</th>
                </tr>
            </thead>
            <tbody>
                @foreach($analytics['roster'] as $r)
                    @php
                        $isCertified = ($r['percentage'] >= 100);
                    @endphp
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.6rem;">
                                <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--adm-border, #e2e8f0); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; color: var(--adm-text-main);">
                                    {{ strtoupper(substr($r['username'], 0, 1)) }}
                                </div>
                                <strong style="color: var(--adm-text-main);">
                                    {{ $r['username'] }}
                                </strong>
                            </div>
                        </td>
                        <td>
                            @if($isCertified)
                                <span class="adm-badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981; font-weight: 700;">
                                    ● Certified
                                </span>
                            @else
                                <span class="adm-badge" style="background: rgba(59, 130, 246, 0.15); color: #3b82f6; font-weight: 700;">
                                    ● In Progress
                                </span>
                            @endif
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <div style="flex: 1; height: 6px; background: var(--adm-border, #e2e8f0); border-radius: 9999px; overflow: hidden; width: 120px;">
                                    <div style="height: 100%; width: {{ $r['percentage'] }}%; background: {{ $isCertified ? '#10b981' : 'var(--vp-c-brand, #ea580c)' }}; border-radius: 9999px;"></div>
                                </div>
                                <span style="font-weight: 700; font-size: 0.85rem; color: var(--adm-text-main);">
                                    {{ $r['percentage'] }}%
                                </span>
                            </div>
                        </td>
                        <td style="color: var(--adm-text-muted); font-size: 0.85rem;">
                            {{ $r['last_active'] }}
                        </td>
                        <td style="text-align: right;">
                            @if(!empty($r['certificate_id']))
                                <a href="@url('lms/certificate?projectId=' . $project_id . '&code=' . $r['certificate_id'])" target="_blank" class="adm-btn adm-btn-secondary adm-btn-sm" style="font-family: monospace; font-size: 0.78rem;">
                                    🏆 {{ $r['certificate_id'] }} ↗
                                </a>
                            @else
                                <span style="font-size: 0.8rem; color: var(--adm-text-muted); font-style: italic;">
                                    Incomplete
                                </span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
