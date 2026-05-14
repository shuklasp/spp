@extends('admin.layout')

@section('content')
<div class="fields-list">
    <div class="header-with-actions">
        <h1>Fields for: {{ $bundle }}</h1>
        <a href="{{ $admin_root }}/structure/types/{{ $bundle }}/fields/add" class="btn btn-primary">Add Field</a>
    </div>

    <table class="admin-table">
        <thead>
            <tr>
                <th>Label</th>
                <th>Machine Name</th>
                <th>Type</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($fields as $field)
            <tr>
                <td>{{ $field['label'] }}</td>
                <td>{{ $field['name'] }}</td>
                <td>{{ $field['type'] }}</td>
                <td>
                    <a href="#" class="btn btn-sm btn-secondary">Edit</a>
                    <a href="#" class="btn btn-sm btn-danger">Delete</a>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
