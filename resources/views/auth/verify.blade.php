@extends('layouts.auth')

@section('content')
<div class="login-container">
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-header">
                <h1 class="login-title">Verify your <span class="accent-text">email</span></h1>
                <p class="login-subtitle">One step left</p>
            </div>

            @if (session('resent'))
                <p class="auth-status">A fresh verification link has been sent to your email address.</p>
            @endif

            <p style="text-align:center;color:var(--color-bone);font-size:14px;margin:0 0 20px;">
                Check your inbox for the verification link before continuing. If it has not arrived, request a new one.
            </p>

            <form method="POST" action="{{ route('verification.resend') }}" class="login-form">
                @csrf
                <button type="submit" class="submit-btn">Resend verification email</button>
                <p class="signup-text"><a href="/login" class="signup-link">Back to sign in</a></p>
            </form>
        </div>
    </div>
</div>
@endsection
