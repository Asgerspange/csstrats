<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #f4f4f5;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
        }
        .email-container {
            max-width: 600px;
            margin: 40px auto;
            background-color: #18181b;
            color: #e4e4e7;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
        }
        .header {
            background-color: #09090b;
            padding: 30px 20px;
            text-align: center;
            border-bottom: 3px solid #ff8c00;
        }
        .header h1 {
            margin: 0;
            color: #ffffff;
            font-size: 26px;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        .content {
            padding: 30px 25px;
        }
        .content h2 {
            color: #ff8c00;
            margin-top: 0;
            font-size: 20px;
            font-weight: 600;
        }
        .content p {
            line-height: 1.6;
            font-size: 15px;
            color: #d4d4d8;
        }
        .info-box {
            background-color: #27272a;
            border-left: 4px solid #ff8c00;
            padding: 15px 20px;
            margin: 22px 0;
            border-radius: 4px;
        }
        .info-box p {
            margin: 6px 0;
            font-size: 15px;
            color: #ffffff;
        }
        .info-box .label {
            color: #a1a1aa;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-family: ui-monospace, monospace;
        }
        .credentials-box {
            background-color: #27272a;
            padding: 16px 20px;
            margin: 16px 0 24px;
            border-radius: 4px;
            border: 1px dashed rgba(255, 140, 0, 0.4);
        }
        .credentials-box p { margin: 6px 0; }
        .credentials-box code {
            color: #ff8c00;
            font-family: ui-monospace, monospace;
            font-size: 15px;
            letter-spacing: 0.5px;
        }
        .button-container {
            text-align: center;
            margin: 28px 0 12px;
        }
        .btn {
            background-color: #ff8c00;
            color: #09090b;
            padding: 14px 28px;
            text-decoration: none;
            font-weight: bold;
            border-radius: 4px;
            font-size: 15px;
            display: inline-block;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .btn-secondary {
            color: #a1a1aa;
            text-decoration: none;
            font-size: 13px;
        }
        .footer {
            text-align: center;
            padding: 20px;
            font-size: 12px;
            color: #71717a;
            background-color: #18181b;
            border-top: 1px solid #27272a;
        }
        .small {
            font-size: 12px;
            color: #a1a1aa;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1>CsStrats</h1>
        </div>

        <div class="content">
            <h2>You've been invited to join {{ $team->name }}</h2>

            <p>{{ $inviter->name }} has invited you to join <strong>{{ $team->name }}</strong> on CsStrats, a tactics workbench for competitive CS2 teams.</p>

            <div class="info-box">
                <p><span class="label">Team</span><br>{{ $team->name }}</p>
                <p><span class="label">Role</span><br>{{ $invitation->role }}</p>
                <p><span class="label">Invited by</span><br>{{ $inviter->name }}</p>
            </div>

            @if ($accountCreated && $tempPassword)
                <p>We've created an account for you so you can accept this invite right away.</p>

                <div class="credentials-box">
                    <p><span class="label">Email</span><br><code>{{ $invitation->email }}</code></p>
                    <p><span class="label">Temporary password</span><br><code>{{ $tempPassword }}</code></p>
                </div>

                <p class="small">For security, change your password after first login from the profile page.</p>
            @endif

            <div class="button-container">
                <a href="{{ $inviteUrl }}" class="btn">Accept invitation</a>
            </div>

            <p style="text-align: center; margin-top: 16px;">
                <a href="{{ $loginUrl }}" class="btn-secondary">Or sign in first, then return to the link above</a>
            </p>

            <p class="small" style="margin-top: 24px;">
                If you weren't expecting this invite, you can ignore this email and the invite will expire automatically.
            </p>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} CsStrats.dk</p>
        </div>
    </div>
</body>
</html>
