@extends('layout')

@section('content')
<div class="glass-panel" style="text-align: center; padding: 100px 40px;">
    <div style="font-size: 4rem; margin-bottom: 20px; opacity: 0.5;">🛠️</div>
    <h2 style="font-size: 2rem; margin-bottom: 10px;">{{ $title }}</h2>
    <p style="color: var(--text-dim); max-width: 600px; margin: 0 auto 30px;">
        This section is currently under construction. We are working hard to bring you the full {{ strtolower($title) }} experience within the Glass Admin theme.
    </p>
    <a href="{{ $admin_root }}" class="btn btn-primary">Return to Dashboard</a>
</div>
@endsection
