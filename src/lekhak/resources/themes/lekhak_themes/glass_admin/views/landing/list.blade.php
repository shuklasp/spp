@extends('layout')

@section('actions')
<a href="{{ $admin_root }}/landing/create" class="btn btn-primary">
    <span>➕</span> Create Page
</a>
@endsection

@section('content')
<div class="glass-panel">
    <table>
        <thead>
            <tr>
                <th>Page Title</th>
                <th>Path Alias</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pages ?? [] as $page)
            <tr>
                <td>
                    <div style="font-weight: 600; font-size: 1.1rem;">
                        {{ $page->title }}
                        @if($page->is_default)
                            <span class="badge badge-success" style="font-size: 0.6rem; margin-left: 10px;">HOME</span>
                        @endif
                    </div>
                </td>
                <td><code>/{{ $page->alias }}</code></td>
                <td><span class="badge badge-success">{{ $page->status }}</span></td>
                <td>
                    <div style="display: flex; gap: 10px;">
                        <a href="{{ $admin_root }}/landing/design/{{ $page->id }}" class="btn btn-secondary" style="padding: 6px 12px; font-size: 0.8rem;">Design</a>
                        @if(!$page->is_default)
                            <a href="{{ $admin_root }}/landing/set-default/{{ $page->id }}" class="btn btn-secondary" style="padding: 6px 12px; font-size: 0.8rem;">Make Home</a>
                        @endif
                    </div>
                </td>
            </tr>
            @endforeach
            @if(empty($pages))
            <tr>
                <td colspan="4" style="text-align: center; color: var(--text-dim); padding: 40px;">No landing pages created yet.</td>
            </tr>
            @endif
        </tbody>
    </table>
</div>
@endsection
