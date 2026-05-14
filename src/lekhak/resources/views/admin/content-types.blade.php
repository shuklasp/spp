@extends('admin.layout')

@section('content')
<div class="content-types-list">
    <div class="header-with-actions">
        <h1>Content Types</h1>
        <a href="{{ $admin_root }}/structure/types/add" class="btn btn-primary">Add Content Type</a>
    </div>

    <table class="admin-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Machine Name</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($types as $type)
            <tr>
                <td>{{ $type->name }}</td>
                <td>{{ $type->bundle }}</td>
                <td>
                    <a href="{{ $admin_root }}/structure/types/{{ $type->bundle }}/fields" class="btn btn-sm btn-info">Manage Fields</a>
                    <a href="{{ $admin_root }}/structure/types/edit/{{ $type->bundle }}" class="btn btn-sm btn-secondary">Edit</a>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
