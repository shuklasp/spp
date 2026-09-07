@extends('layouts.base')
@section('title', '404 - Not Found')

@section('sidebar')
    {{-- Clean layout on error pages --}}
@endsection

@section('content')
<div style="text-align: center; padding: 5rem 1rem; max-width: 600px; margin: 0 auto;">
    <h1 style="font-size: 4rem; color: var(--vp-c-brand); margin-bottom: 1rem; font-weight: 800;">404</h1>
    <h2 style="font-size: 2rem; margin-bottom: 1.5rem; color: var(--vp-c-text-1);">Page Not Found</h2>
    <p style="color: var(--vp-c-text-2); margin-bottom: 2rem; line-height: 1.6;">
        {{ $message ?? 'The page you are looking for does not exist or has been moved.' }}
    </p>
    <div style="display: flex; gap: 1rem; justify-content: center; align-items: center;">
        <button onclick="history.back()" style="padding: 0.75rem 1.5rem; background: var(--vp-c-bg-soft); color: var(--vp-c-text-1); border: 1px solid var(--vp-c-divider); border-radius: 6px; cursor: pointer; font-weight: 600;">
            &larr; Go Back
        </button>
        <a href="{{ \SPP\App::getBaseUrl() }}/" style="padding: 0.75rem 1.5rem; background: var(--vp-c-brand); color: #fff; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; font-weight: 600;">
            🏠 Portal Home
        </a>
    </div>
</div>
@endsection
