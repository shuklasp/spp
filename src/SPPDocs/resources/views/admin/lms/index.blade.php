@extends('layouts.admin')

@section('title', 'Course Studio & LMS — ' . ($project['title'] ?? $project_id))

@section('content')
@php
    $active_tab = 'lms';
@endphp

<!-- Page Header -->
<div class="adm-page-header">
    <div>
        <h1 class="adm-page-title">
            <span>🎓</span> Course Studio &amp; LMS
            <code class="adm-title-project-slug">{{ $project_id }}</code>
        </h1>
        <p class="adm-page-desc">
            Author and orchestrate Git-native courses, structured modules, interactive quizzes, student progress state tracking, and verifiable certificates.
        </p>
    </div>
    <div class="adm-page-actions" style="display: flex; gap: 0.6rem; align-items: center; flex-wrap: wrap;">
        <a href="@url('lms?projectId=' . $project_id)" target="_blank" class="adm-btn adm-btn-secondary">
            🎓 View Academy ↗
        </a>
        <a href="@url('admin/lms/analytics?project=' . $project_id)" class="adm-btn adm-btn-secondary">
            📊 Student Roster &amp; Analytics
        </a>
        <form action="@url('admin/lms/scaffold')" method="POST" style="margin: 0; display: inline;">
            <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
            <input type="hidden" name="project_id" value="{{ $project_id }}">
            <button type="submit" class="adm-btn adm-btn-secondary" title="Generate ready-to-test sample course with modules, lessons, and quizzes">
                ⚡ Scaffold Sample Course
            </button>
        </form>
        <a href="@url('admin/lms/course/edit?project=' . $project_id)" class="adm-btn adm-btn-primary">
            + New Course
        </a>
    </div>
</div>

@if(isset($_SESSION['adm_flash_success']))
    <div style="background: #ecfdf5; border: 1px solid #6ee7b7; color: #065f46; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.9rem; font-weight: 600;">
        ✓ {{ $_SESSION['adm_flash_success'] }}
        @php unset($_SESSION['adm_flash_success']); @endphp
    </div>
@endif

@if(isset($_SESSION['adm_flash_error']))
    <div style="background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.9rem; font-weight: 600;">
        ⚠ {{ $_SESSION['adm_flash_error'] }}
        @php unset($_SESSION['adm_flash_error']); @endphp
    </div>
@endif

<!-- Course Catalog Card -->
<div class="adm-card">
    <div class="adm-card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h2 class="adm-card-title">Curriculum Catalog ({{ count($courses) }})</h2>
        <span style="font-size: 0.8rem; color: var(--adm-text-muted);">Stored in docs/{{ $project_id }}/courses/</span>
    </div>

    @if(empty($courses))
        <div style="text-align: center; padding: 3rem 1rem;">
            <span style="font-size: 3rem; display: block; margin-bottom: 0.75rem;">📚</span>
            <h3 style="margin: 0 0 0.5rem 0; color: var(--adm-text-main);">No courses created yet</h3>
            <p style="color: var(--adm-text-muted); margin: 0 0 1.5rem 0; font-size: 0.95rem;">
                Get started by creating your first course or click "Scaffold Sample Course" to automatically generate a complete curriculum.
            </p>
            <div style="display: inline-flex; gap: 0.75rem;">
                <form action="@url('admin/lms/scaffold')" method="POST" style="margin: 0;">
                    <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
                    <input type="hidden" name="project_id" value="{{ $project_id }}">
                    <button type="submit" class="adm-btn adm-btn-primary">
                        ⚡ Scaffold Sample Course
                    </button>
                </form>
                <a href="@url('admin/lms/course/edit?project=' . $project_id)" class="adm-btn adm-btn-secondary">
                    + Create Course Manually
                </a>
            </div>
        </div>
    @else
        <table class="adm-table">
            <thead>
                <tr>
                    <th>Course</th>
                    <th>Level &bull; Category</th>
                    <th>Curriculum</th>
                    <th>Passing Score</th>
                    <th>Learners</th>
                    <th>Status</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($courses as $c)
                    @php
                        $an = $c['analytics'] ?? [];
                        $lvlColor = match($c['level'] ?? '') {
                            'Beginner' => '#10b981',
                            'Intermediate' => '#3b82f6',
                            'Advanced' => '#8b5cf6',
                            default => '#6b7280'
                        };
                    @endphp
                    <tr>
                        <td>
                            <div style="display: flex; gap: 0.75rem; align-items: center;">
                                <span style="font-size: 1.6rem;">{{ $c['badge_icon'] ?? '🎓' }}</span>
                                <div>
                                    <strong style="color: var(--adm-text-main); font-size: 0.98rem;">
                                        {{ $c['title'] }}
                                    </strong>
                                    <div style="font-size: 0.78rem; color: var(--adm-text-muted); font-family: monospace;">
                                        {{ $c['id'] }}
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div style="display: inline-block; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; padding: 0.15rem 0.5rem; border-radius: 9999px; background: rgba(59, 130, 246, 0.1); color: {{ $lvlColor }};">
                                {{ $c['level'] ?? 'Intermediate' }}
                            </div>
                            <div style="font-size: 0.8rem; color: var(--adm-text-muted); margin-top: 0.2rem;">
                                {{ $c['category'] ?? 'General' }}
                            </div>
                        </td>
                        <td>
                            <div style="font-size: 0.88rem; color: var(--adm-text-main); font-weight: 600;">
                                {{ count($c['modules'] ?? []) }} Modules
                            </div>
                            <div style="font-size: 0.78rem; color: var(--adm-text-muted);">
                                {{ $c['total_lessons'] ?? 0 }} Lessons &bull; {{ $c['total_quizzes'] ?? 0 }} Quizzes
                            </div>
                        </td>
                        <td>
                            <span style="font-weight: 700; color: #8b5cf6; font-size: 0.9rem;">
                                {{ $c['passing_score'] ?? 80 }}%
                            </span>
                            @if(!empty($c['certificate_enabled']))
                                <div style="font-size: 0.75rem; color: #10b981; font-weight: 600;">
                                    🏆 Certificate
                                </div>
                            @endif
                        </td>
                        <td>
                            <div style="font-weight: 700; color: var(--adm-text-main);">
                                {{ $an['total_students'] ?? 0 }} Enrolled
                            </div>
                            <div style="font-size: 0.78rem; color: #10b981;">
                                {{ $an['completed_students'] ?? 0 }} Certified
                            </div>
                        </td>
                        <td>
                            @if(!empty($c['published']))
                                <span class="adm-badge adm-badge-success" style="background: rgba(16, 185, 129, 0.15); color: #10b981; font-weight: 700;">
                                    ● Published
                                </span>
                            @else
                                <span class="adm-badge" style="background: rgba(100, 116, 139, 0.15); color: #64748b; font-weight: 700;">
                                    ○ Draft
                                </span>
                            @endif
                        </td>
                        <td style="text-align: right;">
                            <div style="display: inline-flex; gap: 0.4rem; align-items: center;">
                                <a href="@url('lms/course?projectId=' . $project_id . '&course=' . $c['id'])" target="_blank" class="adm-btn adm-btn-secondary adm-btn-sm" title="Preview Syllabus">
                                    👁️
                                </a>
                                <a href="@url('admin/lms/course/edit?project=' . $project_id . '&course=' . $c['id'])" class="adm-btn adm-btn-secondary adm-btn-sm" title="Edit Metadata">
                                    ✏️ Edit
                                </a>
                                <a href="@url('admin/lms/analytics?project=' . $project_id . '&course=' . $c['id'])" class="adm-btn adm-btn-secondary adm-btn-sm" title="Learner Roster">
                                    👥 Roster
                                </a>
                                <form action="@url('admin/lms/course/delete')" method="POST" onsubmit="return confirm('Permanently delete course \'{{ $c['title'] }}\'? This cannot be undone.');" style="margin: 0; display: inline;">
                                    <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
                                    <input type="hidden" name="project_id" value="{{ $project_id }}">
                                    <input type="hidden" name="course_id" value="{{ $c['id'] }}">
                                    <button type="submit" class="adm-btn adm-btn-danger adm-btn-sm" title="Delete Course">
                                        🗑️
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
