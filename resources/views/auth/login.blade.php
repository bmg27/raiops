@extends('layouts.guest')

@section('body-class', 'login-page')

@section('content')
    <div class="login-container d-flex align-items-center justify-content-center">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-12 col-sm-10 col-md-8 col-lg-6 col-xl-5">
                    <div class="chat-bubble-card p-4">
                        <!-- RaiOps Avatar and Welcome -->
                        <div class="rai-avatar-section">
                            <div class="rai-avatar" style="background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%); font-size: 0.9rem;">Ops</div>
                            <div class="welcome-text">
                                <h5>{{ __('RaiOps Admin') }}</h5>
                                <small>{{ __('Central management console for Rāi platform') }}</small>
                            </div>
                        </div>

                        <!-- Logo -->
                        <div class="logo-container text-center py-3">
                            <h1 style="font-family: 'Playfair Display', serif; color: #1e3a5f; font-size: 2.5rem; margin: 0;">
                                Rāi<span style="color: #c4956a; font-weight: 300;">Ops</span>
                            </h1>
                            <small class="text-muted">Operations & Administration</small>
                        </div>

                        <!-- Display Validation Errors -->
                        @if ($errors->any())
                            <div class="alert alert-danger rounded-3 mb-3">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                    <div>
                                        @if($errors->count() == 1)
                                            {{ $errors->first() }}
                                        @else
                                            <ul class="mb-0 ps-3">
                                                @foreach ($errors->all() as $error)
                                                    <li>{{ $error }}</li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Display Session Status -->
                        @if (session('status'))
                            <div class="alert alert-success rounded-3 mb-3">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-check-circle-fill me-2"></i>
                                    <div>{{ session('status') }}</div>
                                </div>
            </div>
                        @endif

                        <!-- Login Form -->
                        <form method="POST" action="{{ route('login') }}" id="login-form">
            @csrf

                            <!-- Email Address -->
                            <div class="form-floating">
                                <input type="email"
                                       class="form-control @error('email') is-invalid @enderror"
                                       id="email"
                                       name="email"
                                       placeholder="name@example.com"
                                       value="{{ old('email') }}"
                                       required
                                       autofocus
                                       autocomplete="username">
                                <label for="email">{{ __('Email Address') }}</label>
                                @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
            </div>

                            <!-- Password -->
                            <div class="form-floating">
                                <input type="password"
                                       class="form-control @error('password') is-invalid @enderror"
                                       id="password"
                                       name="password"
                                       placeholder="Password"
                                       required
                                       autocomplete="current-password">
                                <label for="password">{{ __('Password') }}</label>
                                @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
            </div>

                            <!-- Remember Me -->
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                                <label class="form-check-label" for="remember">
                                    {{ __('Keep me signed in') }}
                </label>
            </div>

                            <!-- Submit Button and Password Reset Link -->
                            <div class="d-flex justify-content-between align-items-center mb-3">
                @if (Route::has('password.request'))
                                    <a href="{{ route('password.request') }}" class="forgot-link">
                                        <i class="bi bi-arrow-clockwise me-1"></i>{{ __('Forgot password?') }}
                    </a>
                @endif
                                <button type="submit" class="btn btn-login">
                                    <i class="bi bi-box-arrow-in-right me-2"></i>{{ __('Sign In') }}
                                </button>
                            </div>
                        </form>

                        <!-- Register Link -->
                        <div class="register-section">
                            <span class="text-muted">{{ __('Need access?') }}</span>
                            <a href="{{ route('register') }}" class="register-link ms-1">
                                {{ __('Request admin account') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Focus on email field when page loads
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('email').focus();
        });

        // Add some interactive feedback
        const form = document.getElementById('login-form');
        const submitBtn = form.querySelector('button[type="submit"]');

        form.addEventListener('submit', function() {
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>{{ __("Signing in...") }}';
            submitBtn.disabled = true;
        });
    </script>
@endpush
