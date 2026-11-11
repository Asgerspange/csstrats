@extends('layouts.auth')

@section('content')
<div class="login-container">
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-header">
                <h1 class="login-title">Confirm your <span class="accent-text">password</span></h1>
                <p class="login-subtitle">Security check</p>
            </div>

            <p style="text-align:center;color:var(--color-bone);font-size:14px;margin:0 0 20px;">
                Please confirm your password before continuing.
            </p>

            <form method="POST" action="{{ route('password.confirm') }}" class="login-form">
                @csrf
                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input
                        type="password"
                        name="password"
                        id="password"
                        class="form-input @error('password') error @enderror"
                        placeholder="Enter your password"
                        required
                        autocomplete="current-password"
                        autofocus
                    >
                    @error('password')
                        <p class="error-message">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="submit-btn">Confirm</button>

                @if (Route::has('password.request'))
                    <p class="signup-text"><a href="{{ route('password.request') }}" class="signup-link">Forgot your password?</a></p>
                @endif
            </form>
        </div>
    </div>
</div>
@endsection
