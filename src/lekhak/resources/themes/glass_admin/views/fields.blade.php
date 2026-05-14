@extends('layout')

@section('actions')
<a href="{{ $admin_root }}/structure/types/{{ $bundle }}/fields/add" class="btn btn-primary">
    <span>➕</span> Add Field
</a>
@endsection

@section('content')
<div class="glass-panel">
    <div style="margin-bottom: 20px;">
        <a href="{{ $admin_root }}/structure/types" style="color: var(--accent-primary); text-decoration: none;">← Back to Content Types</a>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Field Name</th>
                <th>Machine Name</th>
                <th>Type</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($fields ?? [] as $field)
            <tr>
                <td><strong>{{ $field['label'] ?? $field['name'] }}</strong></td>
                <td><code>{{ $field['name'] }}</code></td>
                <td>{{ $field['type'] }}</td>
                <td>
                    <a href="#" class="btn btn-secondary" style="padding: 6px 12px; font-size: 0.8rem;">Edit</a>
                </td>
            </tr>
            @endforeach
            @if(empty($fields))
            <tr>
                <td colspan="4" style="text-align: center; color: var(--text-dim); padding: 40px;">No fields defined for this bundle.</td>
            </tr>
            @endif
        </tbody>
    </table>
</div>
@endsection
