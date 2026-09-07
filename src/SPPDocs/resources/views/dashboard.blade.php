@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
    <div class="card">
        <h1>📊 Dashboard</h1>
        <p>Authenticated view with data display. Modify <code>DashboardController@index</code> to fetch real data.</p>
    </div>

    {{-- Stats --}}
    <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:1rem; margin-bottom:1.5rem;">
        <div class="card" style="text-align:center;">
            <div style="font-size:2rem; font-weight:800; color:var(--primary);">{{ $stats['total_items'] ?? 0 }}</div>
            <p>Total Items</p>
        </div>
        <div class="card" style="text-align:center;">
            <div style="font-size:2rem; font-weight:800; color:#16a34a;">{{ $stats['active'] ?? 0 }}</div>
            <p>Active</p>
        </div>
        <div class="card" style="text-align:center;">
            <div style="font-size:2rem; font-weight:800; color:#64748b;">{{ $stats['completed'] ?? 0 }}</div>
            <p>Completed</p>
        </div>
    </div>

    {{-- Data table --}}
    <div class="card">
        <h2>📋 Items</h2>
        @if(!empty($items))
            <table style="width:100%; border-collapse:collapse; margin-top:1rem;">
                <thead><tr style="border-bottom:2px solid var(--border); text-align:left;">
                    <th style="padding:0.8rem;">ID</th><th style="padding:0.8rem;">Name</th><th style="padding:0.8rem;">Status</th>
                </tr></thead>
                <tbody>
                @foreach($items as $item)
                    <tr style="border-bottom:1px solid var(--border);">
                        <td style="padding:0.8rem;">{{ $item['id'] ?? '-' }}</td>
                        <td style="padding:0.8rem;">{{ $item['name'] ?? '-' }}</td>
                        <td style="padding:0.8rem;"><span class="badge badge-success">{{ $item['status'] ?? '-' }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @else
            <p style="margin-top:1rem; opacity:0.5;">No items yet. Configure SPPDB and create entities to see data here.</p>
        @endif
    </div>

    {{-- SPP-UX interactive widget --}}
    <div class="card">
        <h2>🧩 Interactive SPP-UX Widget</h2>
        @sppux('counter', ['initialCount' => 0])
    </div>
@endsection