@extends('layout')

@section('content')
<div class="glass-panel" style="max-width: 800px; margin: 0 auto;">
    <div style="margin-bottom: 20px;">
        <a href="{{ $admin_root }}/structure/types/{{ $bundle }}/fields" style="color: var(--accent-primary); text-decoration: none;">← Back to Fields</a>
    </div>

    {!! $form->render() !!}
    
    <div style="margin-top: 30px; display: flex; gap: 15px; justify-content: flex-end; border-top: 1px solid var(--glass-border); padding-top: 30px;">
        <a href="{{ $admin_root }}/structure/types/{{ $bundle }}/fields" class="btn btn-secondary">Cancel</a>
        <button type="submit" form="{{ $form->getAttribute('id') }}" class="btn btn-primary">Save Field</button>
    </div>
</div>
@endsection
