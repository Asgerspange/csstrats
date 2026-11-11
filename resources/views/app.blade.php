<!DOCTYPE html>
<html lang="en" class="dark-mode dark">
<head>
    @if (app()->isProduction())
        {{-- UXwizz only loads once the visitor has granted analytics consent
             (cs_consent cookie set by <x-consent />). Tags pushed before
             load are buffered in UST_CT and flushed when/if UXwizz loads. --}}
        <script>
        UST_CT = [];UST = { s: Date.now(), addTag: function(tag) { UST_CT.push(tag) } };UST.addEvent = UST.addTag;
        (function () {
            function hasAnalyticsConsent() {
                try {
                    var m = document.cookie.match(/(?:^|;\s*)cs_consent=([^;]*)/);
                    if (!m) return false;
                    return !!JSON.parse(decodeURIComponent(m[1])).analytics;
                } catch (e) { return false; }
            }
            var loaded = false;
            function loadUXWizz() {
                if (loaded) return; loaded = true;
                var s = document.createElement('STYLE'); s.id = 'ust_body_style';
                s.appendChild(document.createTextNode('body {opacity: 0}')); document.head.appendChild(s);
                setTimeout(function () { var el = document.getElementById('ust_body_style'); el && el.remove(); }, 800);
                var ab = document.createElement('script'); ab.src = 'https://uxwizz.isdigital.dk/server/ab/csstrats.dk.ab.js?v=8.9.5'; ab.defer = true; document.head.appendChild(ab);
                var rr = document.createElement('script'); rr.src = 'https://uxwizz.isdigital.dk/server/ust-rr.min.js?v=8.9.5'; rr.async = true; document.head.appendChild(rr);
            }
            window.csLoadAnalytics = loadUXWizz;
            if (hasAnalyticsConsent()) loadUXWizz();
            document.addEventListener('cs-consent-changed', function (e) { if (e.detail && e.detail.analytics) loadUXWizz(); });
        })();
        </script>

            @auth
                @php
                    $uxwizzUser = [
                        'id'       => auth()->id(),
                        'has_team' => auth()->user()->teams()->exists(),
                        'has_sub'  => auth()->user()->subscribed(),
                        'on_trial' => auth()->user()->hasGrantedAccess(),
                        'days_old' => auth()->user()->created_at
                                        ? now()->diffInDays(auth()->user()->created_at)
                                        : null,
                    ];
                @endphp
                <script>
                (function() {
                    function uxwizzReady(fn) {
                        if (window.UST && typeof window.UST.addTag === 'function') { fn(); }
                        else { setTimeout(function() { uxwizzReady(fn); }, 100); }
                    }
        
                    uxwizzReady(function() {
                        var user = {!! json_encode($uxwizzUser) !!};
        
                        UST.addTag(user.has_team ? 'segment:has_team' : 'segment:no_team');
        
                        if (user.on_trial)     UST.addTag('segment:trial');
                        else if (user.has_sub) UST.addTag('segment:paid');
                        else                   UST.addTag('segment:free');
        
                        if (user.days_old !== null) {
                            if (user.days_old <= 1)       UST.addTag('cohort:day1');
                            else if (user.days_old <= 3)  UST.addTag('cohort:day3');
                            else if (user.days_old <= 7)  UST.addTag('cohort:day7');
                            else if (user.days_old <= 30) UST.addTag('cohort:day30');
                            else                          UST.addTag('cohort:day30plus');
                        }
                    });
                })();
                </script>
            @endauth
    @endif

    <meta name="default" id="theme">
    <meta charset="UTF-8">
    <meta name="view-transition" content="same-origin">
    <link rel="icon" href="/images/logo.webp" type="image/webp">
    <link rel="preload" href="/css/primeicons.css" as="style" onload="this.rel='stylesheet'">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preload" href="/css/font.css" as="style" onload="this.rel='stylesheet'">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="CsStrats is your ultimate resource for CS2 teams, offering expert strategies, tactics, and tips to enhance teamplay, communication, and overall game performance. Master the game with our in-depth guides and elevate your team's success!">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <!-- Theme Management -->
    <script>
        if (!localStorage.getItem('theme')) {
            localStorage.setItem('theme', 'dark');
        }

        if (localStorage.getItem('theme') === 'dark') {
            document.documentElement.classList.remove('light-mode');
            document.documentElement.classList.add('dark-mode');
        }
    </script>

    <noscript>
        <link rel="stylesheet" href="/css/font.css">
        <link rel="stylesheet" href="/css/primeicons.css">
    </noscript>
    <script>
        window.PADDLE_TOKEN = '{{ config('services.paddle.client_side_token') }}';
        window.PADDLE_SANDBOX = {{ config('services.paddle.sandbox') ? 'true' : 'false' }};
    </script>
    {{-- @routes --}}
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter';
        }
    </style>
    <!-- Base Styles -->
    <style>
        html {
            height: 100%;
            overflow: auto;
        }
        body {
            margin: 0;
            background: var(--bg);
            background-size: cover;
            height: 100%;
            overflow-x: hidden;
            overflow-y: auto;
            top: 0 !important;
            display: flex;
            flex-direction: column;
        }
        
        #app {
            height: 100%;
            width: 100%;
        }

        .font-inter {
            font-family: 'Inter', sans-serif;
        }
    </style>
        <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300..700&display=swap" rel="stylesheet">

    <title inertia>CsStrats</title>
    <link rel="preload" href="/css/variables.css" as="style" onload="this.rel='stylesheet'">
    <noscript>
        <link rel="stylesheet" href="/css/theme.css">
    </noscript>
    @vite(['resources/js/app.js'])

    @php 
        try {
            $themeJson = file_get_contents(resource_path('themes.json'));
            $themes = json_decode($themeJson, true);

            // Safely get user theme with fallback
            $currentTheme = 'original'; // Default fallback
            
            if (auth()->check() && auth()->user()) {
                try {
                    $userTheme = auth()->user()->getSetting('theme');
                    // Check if theme exists in themes.json
                    if ($userTheme && isset($themes[$userTheme])) {
                        $currentTheme = $userTheme;
                    }
                } catch (\Exception $e) {
                    // Log the error but continue with default theme
                    \Log::error('Error getting user theme', [
                        'user_id' => auth()->id(),
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            $themeVariables = $themes[$currentTheme]['variables'] ?? [];
        } catch (\Exception $e) {
            // If anything fails, use safe defaults
            \Log::error('Error loading themes', [
                'user_id' => auth()->id() ?? 'guest',
                'error' => $e->getMessage()
            ]);
            
            $themes = [];
            $currentTheme = 'original';
            $themeVariables = [];
        }
    @endphp

    <style>        .space-grotesk-300 {
  font-family: "Space Grotesk", sans-serif;
  font-optical-sizing: auto;
  font-weight: 300;
  font-style: normal;
}
          .space-grotesk-400 {
  font-family: "Space Grotesk", sans-serif;
  font-optical-sizing: auto;
  font-weight: 400;
  font-style: normal;
}

          .space-grotesk-500 {
  font-family: "Space Grotesk", sans-serif;
  font-optical-sizing: auto;
  font-weight: 500;
  font-style: normal;
}

          .space-grotesk-600 {
  font-family: "Space Grotesk", sans-serif;
  font-optical-sizing: auto;
  font-weight: 600;
  font-style: normal;
}

          .space-grotesk-700 {
  font-family: "Space Grotesk", sans-serif;
  font-optical-sizing: auto;
  font-weight: 700;
  font-style: normal;
}

        /* Scoped by [data-theme] so it outranks app.css's plain :root, which
           the JS bundle injects at runtime after this server-rendered block. */
        :root[data-theme="{{ $currentTheme }}"] {
            @foreach ($themeVariables as $property => $value)
                {{ $property }}: {{ $value }};
            @endforeach
        }
    </style>

    <script>
        window.themes = @json($themes);
        window.currentTheme = @json($currentTheme);
        // Theme scope hook: lets CSS target a specific theme (e.g. the
        // daylight overrides) without per-component edits.
        document.documentElement.setAttribute('data-theme', window.currentTheme);
    </script>
</head>
<body>
    <main id="app" style="height: 100%;">
        </main>
    <x-consent />
</body>
</html>
