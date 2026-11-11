<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light">
    <meta name="theme-color" content="#f3efed">
    <meta name="robots" content="index,follow">
    <title>@yield('title', 'CsStrats')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="icon" href="https://csstrats.b-cdn.net/icons/isdigital.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/images/logo.webp">
    <link href="https://fonts.googleapis.com/css2?family=Schibsted+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    <style>
        /* Daylight tokens, self-contained (mirrors resources/css/welcome.css). */
        :root {
            --color-saffron: oklch(52% 0.15 53);
            --color-concrete: oklch(95.5% 0.005 53);
            --color-slate: oklch(98.5% 0.002 53);
            --color-smoke: oklch(91% 0.006 53);
            --color-ash: oklch(82% 0.008 53);
            --color-bone: oklch(45% 0.012 53);
            --color-chalk: oklch(22% 0.01 53);
            --radius-sm: 4px;
            --radius-md: 6px;
        }
        * { box-sizing: border-box; }
        html { -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }
        body {
            margin: 0; min-height: 100vh; display: flex; flex-direction: column;
            background: var(--color-concrete); color: var(--color-chalk);
            font-family: 'Schibsted Grotesk', 'Helvetica Neue', Arial, sans-serif;
        }
        ::selection { background: oklch(52% 0.15 53 / 0.22); }

        .page-head {
            position: sticky; top: 0; z-index: 40;
            border-bottom: 1px solid var(--color-ash);
            background: color-mix(in oklab, var(--color-concrete) 80%, transparent);
            backdrop-filter: blur(16px);
        }
        .page-head-inner {
            max-width: 80rem; margin: 0 auto;
            display: flex; align-items: center; justify-content: space-between;
            padding: 16px 24px;
        }
        .page-brand { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
        .page-brand img { width: 24px; height: 24px; }
        .page-brand span {
            font-family: 'Schibsted Grotesk', sans-serif; font-weight: 500; font-size: 22px;
            letter-spacing: -0.02em; color: var(--color-chalk);
        }
        .page-brand span b { color: var(--color-saffron); font-weight: 500; }
        .page-back {
            font-family: 'JetBrains Mono', monospace; font-size: 11px; font-weight: 500;
            letter-spacing: 0.08em; text-transform: uppercase; color: var(--color-bone);
            text-decoration: none; transition: color 160ms;
        }
        .page-back:hover { color: var(--color-chalk); }

        .legal {
            flex: 1; width: 100%; max-width: 50rem; margin: 0 auto;
            padding: 64px 24px 96px;
            font-family: 'Schibsted Grotesk', sans-serif;
            color: var(--color-chalk); font-size: 16px; line-height: 1.7;
        }
        .legal :is(h1, .policy-title, .cookie-policy-title) {
            font-size: clamp(2rem, 4vw, 2.75rem); font-weight: 600; line-height: 1.1;
            letter-spacing: -0.02em; margin: 0 0 12px; text-wrap: balance;
        }
        .legal :is(.policy-subtitle) { color: var(--color-bone); font-size: 15px; margin: 0 0 6px; }
        .legal :is(h2, .policy-heading, .cookie-policy-heading) {
            font-size: 1.5rem; font-weight: 600; letter-spacing: -0.01em;
            margin: 56px 0 16px; padding-top: 24px; border-top: 1px solid var(--color-ash);
        }
        .legal :is(h3, .policy-subheading, .cookie-policy-subheading) {
            font-size: 1.125rem; font-weight: 600; margin: 28px 0 8px;
        }
        .legal :is(h4, .policy-service-title, .policy-card-title, .cookie-policy-card-title, .cookie-policy-subheading-small, .policy-subheading-small) {
            font-family: 'JetBrains Mono', monospace; font-size: 12px; font-weight: 500;
            letter-spacing: 0.08em; text-transform: uppercase; color: var(--color-bone);
            margin: 20px 0 8px;
        }
        .legal p { margin: 0 0 16px; color: color-mix(in oklab, var(--color-chalk) 88%, var(--color-bone)); }
        .legal strong { color: var(--color-chalk); font-weight: 600; }
        .legal a, .legal :is(.policy-link, .cookie-policy-link) {
            color: var(--color-saffron); text-decoration: none; border-bottom: 1px solid transparent;
            transition: border-color 160ms;
        }
        .legal a:hover { border-bottom-color: var(--color-saffron); }
        .legal :is(ul, .policy-list, .cookie-policy-list) { margin: 0 0 16px; padding-left: 20px; }
        .legal li { margin: 0 0 6px; color: color-mix(in oklab, var(--color-chalk) 88%, var(--color-bone)); }
        .legal :is(.policy-card, .cookie-policy-card) {
            background: var(--color-slate); border: 1px solid var(--color-ash);
            border-radius: var(--radius-md, 6px); padding: 24px; margin: 16px 0;
        }
        .legal :is(.policy-card, .cookie-policy-card) :is(h3, h4):first-child { margin-top: 0; }
        .legal :is(.policy-section) { margin: 0; }
        .legal :is(.policy-footer, .cookie-policy-footer) {
            margin-top: 48px; padding-top: 24px; border-top: 1px solid var(--color-ash);
            font-size: 13px; color: var(--color-bone);
        }
        .legal hr { border: none; border-top: 1px solid var(--color-ash); margin: 40px 0; }

        .consent-cta {
            display: inline-flex; align-items: center; gap: 8px; margin: 8px 0 24px;
            padding: 10px 16px; border-radius: var(--radius-sm, 4px);
            border: 1px solid var(--color-ash); background: var(--color-slate);
            font-family: 'JetBrains Mono', monospace; font-size: 11px; font-weight: 500;
            letter-spacing: 0.08em; text-transform: uppercase; color: var(--color-chalk);
            cursor: pointer; transition: border-color 160ms, background 160ms;
        }
        .consent-cta:hover { border-color: var(--color-saffron); }

        .page-foot {
            border-top: 1px solid var(--color-ash); padding: 32px 24px;
            font-family: 'JetBrains Mono', monospace; font-size: 11px; font-weight: 500;
            letter-spacing: 0.08em; text-transform: uppercase; color: var(--color-bone);
        }
        .page-foot-inner {
            max-width: 80rem; margin: 0 auto; display: flex; flex-wrap: wrap;
            align-items: center; justify-content: space-between; gap: 16px;
        }
        .page-foot a, .page-foot button {
            color: var(--color-bone); text-decoration: none; background: none; border: none;
            font: inherit; letter-spacing: inherit; text-transform: inherit; cursor: pointer;
            transition: color 160ms;
        }
        .page-foot a:hover, .page-foot button:hover { color: var(--color-saffron); }
        .page-foot nav { display: flex; flex-wrap: wrap; gap: 20px; }
    </style>
</head>
<body>
    <header class="page-head">
        <div class="page-head-inner">
            <a href="/" class="page-brand" aria-label="CsStrats home">
                <img src="https://csstrats.b-cdn.net/icons/isdigital.svg" alt="" width="24" height="24">
                <span>cs<b>strats</b></span>
            </a>
            <a href="/" class="page-back">&larr; Back to site</a>
        </div>
    </header>

    <main class="legal">
        @yield('content')
    </main>

    <footer class="page-foot">
        <div class="page-foot-inner">
            <span>&copy; {{ date('Y') }} IS Digital ApS &middot; Odense, DK</span>
            <nav>
                <a href="/tos">Terms</a>
                <a href="/policy">Privacy</a>
                <a href="/cookie-policy">Cookies</a>
                <button type="button" onClick="window.csConsent&&window.csConsent.open();return false;">Manage consents</button>
            </nav>
        </div>
    </footer>

    <x-consent />
</body>
</html>
