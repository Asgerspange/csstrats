<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light">
    <meta name="theme-color" content="#f3efed">
    <title>{{ config('app.name', 'CsStrats') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="icon" href="https://csstrats.b-cdn.net/icons/isdigital.svg" type="image/svg+xml">
    <link href="https://fonts.googleapis.com/css2?family=Schibsted+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    <style>
        /* Daylight tokens, self-contained (mirrors resources/css/welcome.css). */
        :root {
            --color-saffron: oklch(52% 0.15 53);
            --color-saffron-bright: oklch(60% 0.16 53);
            --color-concrete: oklch(95.5% 0.005 53);
            --color-slate: oklch(99% 0.001 53);
            --color-ash: oklch(82% 0.008 53);
            --color-bone: oklch(45% 0.012 53);
            --color-chalk: oklch(22% 0.01 53);
            --color-error: oklch(52% 0.2 25);
        }
        * { box-sizing: border-box; }
        html { -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }
        body {
            margin: 0; min-height: 100vh;
            background: var(--color-concrete); color: var(--color-chalk);
            font-family: 'Schibsted Grotesk', 'Helvetica Neue', Arial, sans-serif;
            font-size: 16px; line-height: 1.6;
        }
        ::selection { background: oklch(52% 0.15 53 / 0.22); }
        a { color: var(--color-saffron); text-decoration: none; }

        .login-container, .register-container {
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            padding: 48px 20px;
        }
        .login-wrapper, .register-wrapper {
            width: 100%; max-width: 410px; display: flex; flex-direction: column; gap: 20px;
        }
        .login-card, .register-card {
            background: var(--color-slate); border: 1px solid var(--color-ash);
            border-radius: 14px; padding: 36px 32px;
            box-shadow: 0 1px 0 rgba(255,255,255,0.6), 0 18px 50px oklch(30% 0.01 53 / 0.08);
        }
        .login-header, .register-header { text-align: center; margin-bottom: 28px; }
        .login-header::before, .register-header::before {
            content: ''; display: block; width: 34px; height: 34px; margin: 0 auto 18px;
            background: url('https://csstrats.b-cdn.net/icons/isdigital.svg') center / contain no-repeat;
        }
        .login-title, .register-title {
            margin: 0 0 6px; font-size: 26px; font-weight: 600; letter-spacing: -0.02em;
            color: var(--color-chalk);
        }
        .accent-text { color: var(--color-saffron); }
        .login-subtitle, .register-subtitle {
            margin: 0; font-family: 'JetBrains Mono', monospace; font-size: 11px; font-weight: 500;
            letter-spacing: 0.08em; text-transform: uppercase; color: var(--color-bone);
        }

        .login-form, .register-form { display: flex; flex-direction: column; gap: 16px; }

        .google-btn {
            display: flex; align-items: center; justify-content: center; gap: 10px;
            height: 46px; border: 1px solid var(--color-ash); border-radius: 8px;
            background: oklch(100% 0 0); color: var(--color-chalk); font-weight: 500; font-size: 14px;
            transition: border-color 160ms, background 160ms;
        }
        .google-btn:hover { border-color: var(--color-bone); background: var(--color-concrete); }
        .google-icon { width: 18px; height: 18px; }

        .divider { display: flex; align-items: center; gap: 14px; color: var(--color-bone); }
        .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: var(--color-ash); }
        .divider span { font-family: 'JetBrains Mono', monospace; font-size: 10px; letter-spacing: 0.1em; }

        .form-group { display: flex; flex-direction: column; gap: 7px; }
        .form-label {
            font-family: 'JetBrains Mono', monospace; font-size: 11px; font-weight: 500;
            letter-spacing: 0.06em; text-transform: uppercase; color: var(--color-bone);
        }
        .form-input {
            height: 46px; padding: 0 14px; border: 1px solid var(--color-ash); border-radius: 8px;
            background: oklch(100% 0 0); color: var(--color-chalk); font-family: inherit; font-size: 15px;
            transition: border-color 160ms, box-shadow 160ms;
        }
        .form-input::placeholder { color: color-mix(in oklab, var(--color-bone) 70%, transparent); }
        .form-input:focus { outline: none; border-color: var(--color-saffron); box-shadow: 0 0 0 3px oklch(52% 0.15 53 / 0.12); }
        .form-input.error { border-color: var(--color-error); }
        .error-message { margin: 2px 0 0; font-size: 13px; color: var(--color-error); }

        .form-options { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
        .checkbox-label { display: flex; align-items: center; gap: 9px; cursor: pointer; }
        .checkbox-input { width: 16px; height: 16px; accent-color: var(--color-saffron); }
        .checkbox-text { font-size: 13px; color: var(--color-bone); }
        .forgot-link { font-size: 13px; color: var(--color-saffron); }
        .forgot-link:hover { text-decoration: underline; }

        .submit-btn {
            height: 48px; width: 100%; border: none; border-radius: 8px; cursor: pointer;
            background: var(--color-saffron); color: oklch(98% 0.005 53);
            font-family: 'JetBrains Mono', monospace; font-size: 12px; font-weight: 500;
            letter-spacing: 0.08em; text-transform: uppercase;
            transition: background 160ms;
        }
        .submit-btn:hover { background: var(--color-saffron-bright); }

        .signup-text, .login-text, .terms-text {
            margin: 4px 0 0; text-align: center; font-size: 14px; color: var(--color-bone);
        }
        .terms-text { font-size: 12px; }
        .signup-link, .login-link, .terms-link { color: var(--color-saffron); font-weight: 500; }
        .signup-link:hover, .login-link:hover, .terms-link:hover { text-decoration: underline; }

        .security-note {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            font-family: 'JetBrains Mono', monospace; font-size: 10px; letter-spacing: 0.06em;
            text-transform: uppercase; color: var(--color-bone);
        }
        .security-icon { width: 13px; height: 13px; }

        .auth-back {
            display: block; text-align: center; font-family: 'JetBrains Mono', monospace;
            font-size: 11px; letter-spacing: 0.08em; text-transform: uppercase; color: var(--color-bone);
        }
        .auth-back:hover { color: var(--color-chalk); }

        .auth-status {
            margin: 0 0 16px; padding: 12px 14px; border-radius: 8px;
            background: oklch(72% 0.12 145 / 0.14); border: 1px solid oklch(72% 0.12 145 / 0.4);
            color: oklch(38% 0.1 145); font-size: 14px;
        }
    </style>
</head>
<body>
    @yield('content')
</body>
</html>
