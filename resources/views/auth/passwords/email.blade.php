@extends('layouts.auth')

@section('content')
<div class="login-container">
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-header">
                <h1 class="login-title">Reset your <span class="accent-text">password</span></h1>
                <p class="login-subtitle">We will email you a link</p>
            </div>

            @if (session('status'))
                <p class="auth-status">{{ session('status') }}</p>
            @endif

            <form method="POST" action="{{ route('password.email') }}" class="login-form">
                @csrf
                <div class="form-group">
                    <label for="email" class="form-label">Email</label>
                    <input
                        type="email"
                        name="email"
                        id="email"
                        class="form-input @error('email') error @enderror"
                        value="{{ old('email') }}"
                        placeholder="Enter your email"
                        required
                        autofocus
                        autocomplete="email"
                    >
                    @error('email')
                        <p class="error-message">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="submit-btn">Send reset link</button>
                <p class="signup-text">Remembered it? <a href="/login" class="signup-link">Sign in</a></p>
            </form>
        </div>
    </div>
</div>
@endsection
