@extends('layouts.auth')

@section('content')
<div class="register-container">
    <div class="register-wrapper">
        <!-- Register Card -->
        <div class="register-card">
            <!-- Logo/Title Section -->
            <div class="register-header">
                <h1 class="register-title">
                    Join the <span class="accent-text">Elite</span>
                </h1>
                <p class="register-subtitle">Start your journey to CS2 mastery</p>
            </div>

            <form method="POST" action="{{ route('register') }}" class="register-form">
                @csrf
                
                <!-- Google Register Button -->
                <a href="{{ route('google.redirect') }}" class="google-btn">
                    <img class="google-icon" src="/google.webp" alt="Google logo"/>
                    <span class="google-text">Sign up with Google</span>
                </a>
                
                <!-- Divider -->
                <div class="divider">
                    <span>OR</span>
                </div>
                
                <!-- Name Input -->
                <div class="form-group">
                    <label for="name" class="form-label">Name</label>
                    <input 
                        type="text" 
                        name="name" 
                        id="name"
                        placeholder="Enter your name" 
                        class="form-input @error('name') error @enderror" 
                        value="{{ old('name') }}" 
                        required 
                        autocomplete="name" 
                        autofocus
                    >
                    @error('name')
                        <p class="error-message">{{ $message }}</p>
                    @enderror
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
                        placeholder="Create a password" 
                        class="form-input @error('password') error @enderror" 
                        required 
                        autocomplete="new-password"
                    >
                    @error('password')
                        <p class="error-message">{{ $message }}</p>
                    @enderror
                </div>
                
                <!-- Confirm Password Input -->
                <div class="form-group">
                    <label for="password_confirmation" class="form-label">Confirm Password</label>
                    <input 
                        type="password" 
                        name="password_confirmation" 
                        id="password-confirm"
                        placeholder="Confirm your password" 
                        class="form-input" 
                        required 
                        autocomplete="new-password"
                    >
                </div>
                
                <!-- Newsletter Checkbox -->
                <label class="checkbox-label">
                    <input 
                        type="checkbox" 
                        name="subscribe" 
                        id="subscribe" 
                        class="checkbox-input"
                        value="1"
                        checked
                    >
                    <span class="checkbox-text">Subscribe to newsletter for CS2 tips & updates</span>
                </label>
                
                <!-- Submit Button -->
                <button type="submit" class="submit-btn">
                    Create Account
                </button>
                
                <!-- Terms and Login Link -->
                <p class="terms-text">
                    By creating an account you agree with our 
                    <a href="/tos" class="terms-link iubenda-white iubenda-noiframe iubenda-embed iubenda-noiframe" title="Terms and Conditions" target="_blank">
                        Terms and Conditions
                    </a>
                </p>
                
                <p class="login-text">
                    Already have an account? 
                    <a href="/login" class="login-link">
                        Login
                    </a>
                </p>
            </form>
        </div>
    </div>
</div>

@endsection