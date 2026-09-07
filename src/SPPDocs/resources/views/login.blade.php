{{-- Login Page — Clean standalone layout, no sidebar, no login button --}}
@extends('layouts.login')
@section('title', 'Login')

@section('content')
    <div class="login-card">
        <div class="login-icon"><span>🔐</span></div>
        <div class="login-title">Welcome Back</div>
        <div class="login-subtitle">Sign in to your account to continue</div>

        @if(!empty($error))
            <div class="error-box"><span>⚠️</span> {{ $error }}</div>
        @endif

        <div class="login-form-box">
            <form method="POST" action="@url('auth/login')">
                <input type="hidden" name="redirect" value="{{ $_GET['redirect'] ?? ($_POST['redirect'] ?? '') }}" />
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" required placeholder="Enter your username" />
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required placeholder="Enter your password" />
                </div>
                <button type="submit" class="login-btn">Sign In &rarr;</button>
            </form>
        </div>

        <p class="login-hint">
            Default credentials: <code>admin</code> / <code>admin</code>
        </p>
        
        <p class="login-hint login-hint-spaced">
            Don't have an account? <a href="@url('register')" class="login-link">Register</a>
        </p>
    </div>
@endsection