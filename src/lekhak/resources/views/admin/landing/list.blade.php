@extends('admin.layout')

@section('content')
<div class="landing-page-list">
    <div class="header-with-actions">
        <h1>Landing Pages</h1>
        <a href="{{ $admin_root }}/landing/create" class="btn btn-primary">Create New Page</a>
    </div>

    <table class="admin-table">
        <thead>
            <tr>
                <th>Title</th>
                <th>Slug</th>
                <th>Default</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pages as $page)
            <tr>
                <td>{{ $page->title }}</td>
                <td>{{ $page->alias }}</td>
                <td>
                    @if($page->is_default)
                        <span class="badge badge-success">Homepage</span>
                    @else
                        <a href="{{ $admin_root }}/landing/set-default/{{ $page->id }}" class="text-muted">Set as Home</a>
                    @endif
                </td>
                <td>
                    <a href="{{ $admin_root }}/landing/design/{{ $page->id }}" class="btn btn-sm btn-info">Design</a>
                    <a href="{{ $admin_root }}/landing/edit/{{ $page->id }}" class="btn btn-sm btn-secondary">Edit Settings</a>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
