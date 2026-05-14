@extends('layout')

@section('content')
<div class="glass-panel" style="max-width: 800px; margin: 0 auto;">
    <div style="margin-bottom: 30px;">
        <h2 style="font-size: 1.5rem; margin-bottom: 10px;">{{ $title }}</h2>
        <p style="color: var(--text-dim);">{{ $subtitle }}</p>
    </div>

    {!! $form->render() !!}
    
    <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--glass-border); display: flex; gap: 15px;">
        <button type="submit" form="{{ $form->getAttribute('id') }}" class="btn btn-primary">Save Landing Page</button>
        <a href="{{ $admin_root }}/landing" class="btn btn-secondary">Cancel</a>
    </div>
</div>
@endsection
