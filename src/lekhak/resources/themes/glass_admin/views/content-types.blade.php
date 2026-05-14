@extends('layout')

@section('actions')
<a href="{{ $admin_root }}/structure/types/add" class="btn btn-primary">
    <span>➕</span> Create New Type
</a>
@endsection

@section('content')
<div class="glass-panel">
    <table>
        <thead>
            <tr>
                <th>Type Name</th>
                <th>Machine Name</th>
                <th>Strategy</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($types ?? [] as $type)
            <tr>
                <td>
                    <div style="font-weight: 600; font-size: 1rem;">{{ $type->name }}</div>
                    <div style="font-size: 0.8rem; color: var(--text-dim);">{{ $type->description }}</div>
                </td>
                <td><code>{{ $type->machine_name }}</code></td>
                <td>
                    <span class="badge {{ $type->storage_strategy === 'dynamic' ? 'badge-success' : 'badge-warning' }}">
                        {{ $type->storage_strategy }}
                    </span>
                </td>
                <td>
                    <div style="display: flex; gap: 10px;">
                        <a href="{{ $admin_root }}/structure/types/edit/{{ $type->machine_name }}" class="btn btn-secondary" style="padding: 6px 12px; font-size: 0.8rem;">Edit</a>
                        <a href="#" class="btn btn-secondary" style="padding: 6px 12px; font-size: 0.8rem; color: var(--danger);">Delete</a>
                    </div>
                </td>
            </tr>
            @endforeach
            @if(empty($types))
            <tr>
                <td colspan="4" style="text-align: center; color: var(--text-dim); padding: 40px;">No content types defined yet.</td>
            </tr>
            @endif
        </tbody>
    </table>
</div>
@endsection
