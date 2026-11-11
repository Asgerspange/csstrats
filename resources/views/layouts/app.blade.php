<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>
    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Nunito" rel="stylesheet">
    <link rel="stylesheet" href="/css/bootstrap.css">
    <link rel="stylesheet" href="/css/welcome.css">
    <link rel="stylesheet" href="/css/auth/auth.css">
            <script>
            document.documentElement.classList.add('dark')
        </script>
    <!-- Scripts -->
</head>
<body>
    <div class="row h-100">
        <div class="col-12 col-md-12 col-sm-12">
            <div class="form-content">
                @yield('content')
            </div>
        </div>
    </div>
</body>
</html>