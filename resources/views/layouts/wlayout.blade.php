<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- Core SEO. Per-page overrides come from the controller via view data. --}}
    <title>{{ $title ?? 'CsStrats is closing' }}</title>
    <meta name="description" content="{{ $metaDescription ?? 'CsStrats is shutting down. Existing accounts can still log in. Billing and data questions: info@csstrats.dk.' }}">
    <meta name="author" content="IS Digital ApS">
    <meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light">
    <meta name="theme-color" content="#f3efed">
    <meta name="format-detection" content="telephone=no">
    <meta name="application-name" content="CsStrats">
    <meta name="apple-mobile-web-app-title" content="CsStrats">

    {{-- Geo signals --}}
    <meta name="geo.region" content="DK-83">
    <meta name="geo.placename" content="Odense">
    <meta name="geo.position" content="55.4038;10.4024">
    <meta name="ICBM" content="55.4038, 10.4024">

    {{-- Canonical --}}
    <link rel="canonical" href="{{ $canonical ?? 'https://csstrats.dk/' }}">

    {{-- OpenGraph --}}
    <meta property="og:locale" content="en_US">
    <meta property="og:locale:alternate" content="da_DK">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="CsStrats">
    <meta property="og:title" content="{{ $title ?? 'CsStrats is closing' }}">
    <meta property="og:description" content="{{ $metaDescription ?? 'CsStrats is shutting down. Existing accounts can still log in. Billing and data questions: info@csstrats.dk.' }}">
    <meta property="og:url" content="{{ $canonical ?? 'https://csstrats.dk/' }}">
    <meta property="og:image" content="https://csstrats.dk/images/logo.webp">
    <meta property="og:image:type" content="image/webp">
    <meta property="og:image:width" content="3500">
    <meta property="og:image:height" content="3076">
    <meta property="og:image:alt" content="CsStrats wordmark">

    {{-- Twitter --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:site" content="@CsStrats2">
    <meta name="twitter:creator" content="@CsStrats2">
    <meta name="twitter:title" content="{{ $title ?? 'CsStrats is closing' }}">
    <meta name="twitter:description" content="{{ $metaDescription ?? 'CsStrats is shutting down. Existing accounts can still log in. Billing and data questions: info@csstrats.dk.' }}">
    <meta name="twitter:image" content="https://csstrats.dk/images/logo.webp">
    <meta name="twitter:image:alt" content="CsStrats wordmark">

    {{-- Performance: resource hints --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="dns-prefetch" href="https://cdn.paddle.com">
    <link rel="dns-prefetch" href="https://csstrats.b-cdn.net">

    <link rel="icon" href="/images/logo.webp" type="image/webp">
    <link rel="apple-touch-icon" href="/images/logo.webp">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Schibsted+Grotesk:wght@400;500;600;700;900&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet">
    <style>
        .material-symbols {
            font-family: 'Material Symbols Outlined';
            font-weight: 400;
            font-style: normal;
            font-size: 18px;
            line-height: 1;
            letter-spacing: normal;
            text-transform: none;
            display: inline-block;
            white-space: nowrap;
            word-wrap: normal;
            direction: ltr;
            -webkit-font-feature-settings: 'liga';
            -webkit-font-smoothing: antialiased;
        }
    </style>

    <script>
        window.PADDLE_TOKEN = '{{ config('services.paddle.client_side_token') }}';
        window.PADDLE_SANDBOX = {{ config('services.paddle.sandbox') ? 'true' : 'false' }};
        window.CSDV_KEY = '{{ config('services.demoviewer.client_key') }}';
        window.CSDV_DEMO_ID = {{ (int) (config('services.demoviewer.sample_demo_id') ?? 0) }};
        window.CSDV_API = '{{ config('services.demoviewer.api_base') }}';
        window.CSDV_SRC = '{{ config('services.demoviewer.viewer_src') }}';
    </script>

    {{-- Structured data: organisation + site only. Sales offers and FAQ
         were removed with the closing landing. --}}
    <script type="application/ld+json">
    @php echo json_encode([
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'Organization',
                '@id' => 'https://csstrats.dk/#organization',
                'name' => 'CsStrats',
                'legalName' => 'IS Digital ApS',
                'url' => 'https://csstrats.dk/',
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => 'https://csstrats.dk/images/logo.webp',
                    'width' => 3500,
                    'height' => 3076,
                ],
                'description' => 'CsStrats is closing. Built by IS Digital ApS in Odense, Denmark.',
                'email' => 'info@csstrats.dk',
                'address' => [
                    '@type' => 'PostalAddress',
                    'addressLocality' => 'Odense',
                    'addressRegion' => 'Region of Southern Denmark',
                    'addressCountry' => 'DK',
                ],
                'sameAs' => [
                    'https://discord.gg/Rmp5p4r7CT',
                    'https://x.com/CsStrats2',
                    'https://www.youtube.com/@CSStrats2',
                    'https://www.instagram.com/csstrats/',
                    'https://www.tiktok.com/@csstrats',
                ],
            ],
            [
                '@type' => 'WebSite',
                '@id' => 'https://csstrats.dk/#website',
                'url' => 'https://csstrats.dk/',
                'name' => 'CsStrats',
                'description' => 'CsStrats is closing. Existing accounts can still log in.',
                'inLanguage' => 'en',
                'publisher' => ['@id' => 'https://csstrats.dk/#organization'],
            ],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); @endphp
    </script>

    @vite(['resources/js/welcome.js'])
</head>
<body>
    @yield('content')

    <x-consent />
</body>
</html>
