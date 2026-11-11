@extends('layouts.auth')

@section('content')
<div class="login-container">
    <div class="login-wrapper">
        <!-- Login Card -->
        <div class="login-card">
            <!-- Logo/Title Section -->
            <div class="login-header">
                <h1 class="login-title">
                    Welcome <span class="accent-text">Back</span>
                </h1>
                <p class="login-subtitle">Master CS2 like a pro</p>
            </div>

            <form method="POST" action="{{ route('login') }}" class="login-form">
                @csrf

                @if (session('status'))
                    <p class="error-message">{{ session('status') }}</p>
                @endif
                
                <!-- Google Login Button -->
                <a href="{{ route('google.redirect') }}" class="google-btn">
                    <img class="google-icon" src="/google.webp" alt="Google logo"/>
                    <span class="google-text">Continue with Google</span>
                </a>
                
                <!-- Divider -->
                <div class="divider">
                    <span>OR</span>
                </div>
                
                <!-- Email Input -->
                <div class="form-group">
                    <label for="email" class="form-label">Email</label>
                    <input 
                        type="email" 
                        name="email" 
                        id="email"
                        placeholder="Enter your email" 
                        class="form-input @error('email') error @enderror" 
                        value="{{ old('email') }}" 
                        required 
                        autocomplete="email" 
                        autofocus
                    >
                    @error('email')
                        <p class="error-message">{{ $message }}</p>
                    @enderror
                </div>
                
                <!-- Password Input -->
                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input 
                        type="password" 
                        name="password" 
                        id="password"
                        placeholder="Enter your password" 
                        class="form-input @error('password') error @enderror" 
                        required 
                        autocomplete="current-password"
                    >
                    @error('password')
                        <p class="error-message">{{ $message }}</p>
                    @enderror
                </div>
                
                <!-- Remember Me & Forgot Password -->
                <div class="form-options">
                    <label class="checkbox-label">
                        <input 
                            type="checkbox" 
                            name="remember" 
                            id="remember" 
                            class="checkbox-input"
                            {{ old('remember') ? 'checked' : '' }}
                        >
                        <span class="checkbox-text">Remember me</span>
                    </label>
                    
                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}" class="forgot-link">
                            Forgot password?
                        </a>
                    @endif
                </div>
                
                <!-- Submit Button -->
                <button type="submit" class="submit-btn">
                    Sign In
                </button>
            </form>
        </div>
        
        <!-- Security Note -->
        <div class="security-note">
            <svg class="security-icon" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
            </svg>
            <span>Secure login powered by industry-standard encryption</span>
        </div>
    </div>
</div>

@endsection