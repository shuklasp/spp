{{-- Register Page — Clean standalone layout --}}
@extends('layouts.login')
@section('title', 'Register')

@section('content')
    <div class="login-card">
        <div class="login-icon"><span>📝</span></div>
        <div class="login-title">Create an Account</div>
        <div class="login-subtitle">Join the SPP Developer Portal</div>

        @if(!empty($error))
            <div class="error-box"><span>⚠️</span> {{ $error }}</div>
        @endif
        
        @if(!empty($success))
            <div class="success-box">
                <span>✅</span> {{ $success }}
            </div>
        @endif

        <div class="login-form-box">
            <form method="POST" action="@url('auth/register')">
                <input type="hidden" name="redirect" value="{{ $_GET['redirect'] ?? ($_POST['redirect'] ?? '') }}" />
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" required placeholder="Choose a username" />
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required placeholder="Create a password" />
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="password_confirm" required placeholder="Confirm your password" />
                </div>
                <button type="submit" class="login-btn">Register &rarr;</button>
            </form>
        </div>

        <p class="login-hint login-hint-spaced">
            Already have an account? <a href="@url('login')" class="login-link">Log In</a>
        </p>
    </div>
@endsection
