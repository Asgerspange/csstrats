<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Partner login · CsStrats</title>
    <link rel="icon" href="/images/logo.webp" type="image/webp">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Schibsted+Grotesk:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --canvas: oklch(16% 0.012 250);
            --surface: oklch(20% 0.012 250);
            --border: oklch(100% 0 0 / 0.10);
            --text: oklch(96% 0.005 250);
            --muted: oklch(72% 0.01 250);
            --primary: #ff8c00;
        }
        * { box-sizing: border-box; }
        html, body {
            margin: 0; min-height: 100vh;
            background: var(--canvas); color: var(--text);
            font-family: 'Schibsted Grotesk', 'Helvetica Neue', Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
            display: flex; align-items: center; justify-content: center;
        }
        .card {
            width: 100%; max-width: 380px; margin: 1.5rem;
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 12px; padding: 2rem;
        }
        .brand { display: flex; align-items: center; gap: 0.6rem; margin-bottom: 1.5rem; }
        .brand img { width: 32px; height: 32px; border-radius: 6px; }
        .brand b { font-size: 1rem; }
        .brand span { display: block; font-size: 0.75rem; color: var(--muted); }
        label { display: block; font-size: 0.75rem; font-weight: 700; color: var(--muted);
            text-transform: uppercase; letter-spacing: 0.08em; margin: 0.9rem 0 0.35rem; }
        input[type=email], input[type=password] {
            width: 100%; padding: 0.6rem 0.75rem; border-radius: 8px;
            background: oklch(24% 0.012 250); border: 1px solid var(--border);
            color: var(--text); font-size: 0.9rem; font-family: inherit;
        }
        input:focus { outline: none; border-color: var(--primary); }
        .row { display: flex; align-items: center; gap: 0.5rem; margin-top: 0.9rem; font-size: 0.8rem; color: var(--muted); }
        button {
            width: 100%; margin-top: 1.25rem; padding: 0.65rem;
            background: var(--primary); color: oklch(18% 0.03 60); border: none;
            border-radius: 8px; font-weight: 800; font-size: 0.9rem; cursor: pointer;
            font-family: inherit;
        }
        button:hover { filter: brightness(1.05); }
        .error { margin-top: 1rem; padding: 0.6rem 0.75rem; border-radius: 8px; font-size: 0.8rem;
            background: oklch(45% 0.18 22 / 0.18); border: 1px solid oklch(60% 0.18 22 / 0.4); color: oklch(85% 0.08 22); }
    </style>
</head>
<body>
    <form class="card" method="POST" action="{{ route('partner.login.attempt') }}">
        @csrf
        <div class="brand">
            <img src="/images/logo.webp" alt="CsStrats">
            <div>
                <b>CsStrats</b>
                <span>Partner dashboard</span>
            </div>
        </div>

        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">

        <label for="password">Password</label>
        <input id="password" type="password" name="password" required autocomplete="current-password">

        <div class="row">
            <input type="checkbox" id="remember" name="remember" value="1">
            <label for="remember" style="margin:0; text-transform:none; letter-spacing:0; font-weight:500;">Remember me</label>
        </div>

        <button type="submit">Sign in</button>
    </form>
</body>
</html>
