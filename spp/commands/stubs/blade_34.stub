{{--
  Login Page — Uses @sppguest to show only to non-authenticated users
--}}
@extends('layouts.app')
@section('title', 'Login')
@section('content')
    @sppguest
    <div style="max-width: 400px; margin: 2rem auto;">
        <div class="card">
            <h2 style="margin-top:0;">🔐 Login</h2>
            @if(!empty($error))
                <div style="background: rgba(239,68,68,0.1); color: #dc2626; padding: 0.8rem; border-radius: 8px; margin-bottom: 1rem;">{{ $error }}</div>
            @endif
            <form method="POST" action="@url('auth/login')">
                <div style="margin-bottom: 1rem;">
                    <label style="display:block; font-weight:600; font-size:0.85rem; margin-bottom:0.4rem; color:var(--muted);">Username</label>
                    <input type="text" name="username" required style="width:100%; padding:0.8rem; border:1px solid var(--border); border-radius:8px; font-family:inherit;">
                </div>
                <div style="margin-bottom: 1.5rem;">
                    <label style="display:block; font-weight:600; font-size:0.85rem; margin-bottom:0.4rem; color:var(--muted);">Password</label>
                    <input type="password" name="password" required style="width:100%; padding:0.8rem; border:1px solid var(--border); border-radius:8px; font-family:inherit;">
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;">Sign In</button>
            </form>
            <p style="margin-top:1rem; font-size:0.85rem; text-align:center;">Default: admin / admin</p>
        </div>
    </div>
    @endsppguest

    @sppauth
    <div class="card" style="text-align:center;">
        <h2>✅ You are already logged in</h2>
        <a href="@url('dashboard')" class="btn btn-primary">Go to Dashboard</a>
    </div>
    @endsppauth
@endsection