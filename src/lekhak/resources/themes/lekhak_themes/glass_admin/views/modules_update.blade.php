@extends('layout')

@section('content')
<div class="lekhak-local-tasks-header" style="margin-bottom: 25px; border-bottom: 1px solid var(--glass-border); padding-bottom: 12px;">
    <h2 style="margin: 0; font-size: 1.5rem; color: var(--text-main);">{{ $title }}</h2>
    <p style="margin: 5px 0 0 0; font-size: 0.9rem; color: var(--text-dim);">{{ $subtitle }}</p>
</div>

@if(isset($message))
    <div style="background: rgba(16, 185, 129, 0.1); border-left: 4px solid var(--success); padding: 15px; margin-bottom: 20px; color: var(--text-main); border-radius: 4px;">
        {{ $message }}
    </div>
@endif

<div class="glass-panel" style="padding: 25px;">
    <p style="color: var(--text-main);">Database updates have been completed successfully. Your site's modules are up to date.</p>
    
    <div style="margin-top: 20px;">
        <a href="/school1/lekhak/admin/modules" class="btn btn-primary" style="text-decoration: none;">Return to Modules</a>
    </div>
</div>
@endsection
