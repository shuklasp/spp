@extends('layouts.base')
@section('title', '403 - Forbidden')

@section('content')
<div style="text-align: center; padding: 5rem 1rem;">
    <h1 style="font-size: 4rem; color: #ef4444; margin-bottom: 1rem;">403</h1>
    <h2 style="font-size: 2rem; margin-bottom: 1.5rem;">Access Denied</h2>
    <p style="color: var(--vp-c-text-2); margin-bottom: 2rem;">
        {{ $message ?? 'You do not have permission to access this resource.' }}
    </p>
    <button onclick="history.back()" style="padding: 0.75rem 1.5rem; background: var(--vp-c-bg-soft); color: var(--vp-c-text-1); border: 1px solid var(--vp-c-divider); border-radius: 4px; cursor: pointer;">
        &larr; Go Back
    </button>
</div>
@endsection
