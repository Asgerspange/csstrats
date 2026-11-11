<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>You've been invited — CsStrats</title>
    <link rel="icon" href="/images/logo.webp" type="image/webp" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    @vite(['resources/js/invite.js'])
    @php
        $inviteData = [
            'team'           => $team,
            'token'          => $token,
            'roles'          => $roles,
            'is_auth'        => $is_auth,
            'already_member' => $already_member,
        ];
    @endphp
    <script>
        window.__INVITE__ = {!! json_encode($inviteData) !!};
    </script>
</head>
<body style="margin:0;background:#0f172a;min-height:100vh;font-family:'Inter',sans-serif;">
    <div id="invite-app"></div>
</body>
</html>