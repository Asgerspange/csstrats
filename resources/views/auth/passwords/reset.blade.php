@extends('layouts.auth')

@section('content')
<div class="login-container">
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-header">
                <h1 class="login-title">Choose a new <span class="accent-text">password</span></h1>
                <p class="login-subtitle">Almost done</p>
            </div>

            <form method="POST" action="{{ route('password.update') }}" class="login-form">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div class="form-group">
                    <label for="email" class="form-label">Email</label>
                    <input
                        type="email"
                        name="email"
                        id="email"
                        class="form-input @error('email') error @enderror"
                        value="{{ $email ?? old('email') }}"
                        required
                        autocomplete="email"
                        readonly
                    >
                    @error('email')
                        <p class="error-message">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">New password</label>
                    <input
                        type="password"
                        name="password"
                        id="password"
                        class="form-input @error('password') error @enderror"
                        placeholder="Create a password"
                        required
                        autocomplete="new-password"
                        autofocus
                    >
                    @error('password')
                        <p class="error-message">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="password_confirmation" class="form-label">Confirm password</label>
                    <input
                        type="password"
                        name="password_confirmation"
                        id="password_confirmation"
                        class="form-input"
                        placeholder="Confirm your password"
                        required
                        autocomplete="new-password"
                    >
                </div>

                <button type="submit" class="submit-btn">Reset password</button>
            </form>
        </div>
    </div>
</div>
@endsection
