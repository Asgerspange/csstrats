<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* Base styles for email client compatibility */
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
            background-color: #18181b; /* Dark theme background */
            color: #e4e4e7; /* Light text for readability */
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
        }
        .header {
            background-color: #09090b;
            padding: 30px 20px;
            text-align: center;
            border-bottom: 3px solid #eab308; /* Tactical yellow/gold accent */
        }
        .header h1 {
            margin: 0;
            color: #ffffff;
            font-size: 28px;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        .content {
            padding: 30px 25px;
        }
        .content h2 {
            color: #eab308;
            margin-top: 0;
            font-size: 22px;
        }
        .content p {
            line-height: 1.6;
            font-size: 15px;
            color: #d4d4d8;
        }
        .credentials-box {
            background-color: #27272a;
            border-left: 4px solid #eab308;
            padding: 15px 20px;
            margin: 25px 0;
            border-radius: 4px;
        }
        .credentials-box p {
            margin: 8px 0;
            font-size: 16px;
            color: #ffffff;
        }
        .highlight {
            color: #eab308;
            font-family: monospace;
            font-size: 18px;
            letter-spacing: 1px;
        }
        .button-container {
            text-align: center;
            margin: 35px 0 15px 0;
        }
        .btn {
            background-color: #eab308;
            color: #09090b;
            padding: 14px 32px;
            text-decoration: none;
            font-weight: bold;
            border-radius: 4px;
            font-size: 16px;
            display: inline-block;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .footer {
            text-align: center;
            padding: 20px;
            font-size: 12px;
            color: #71717a;
            background-color: #18181b;
            border-top: 1px solid #27272a;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1>CsStrats</h1>
        </div>

        <div class="content">
            <h2>Welcome to the team, {{ $user->name }}! 🎮</h2>
            
            <p>We're thrilled to have you on board. Your account has been successfully deployed so you can jump right into the server and start exploring the best setups, line-ups, and strategies.</p>

            <p>Here are your login credentials to access the platform:</p>

            <div class="credentials-box">
                <p><strong>Email:</strong> <span style="color: #eab308;">{{ $user->email }}</span></p>
                <p><strong>Password:</strong> <span class="highlight">{{ $password }}</span></p>
            </div>

            <p style="font-size: 13px; color: #a1a1aa; font-style: italic;">
                *Tip: We highly recommend changing your password by clicking "Forgot Password" after your first login to keep your account secure.
            </p>

            <div class="button-container">
                <a href="https://csstrats.dk/login" class="btn">Login to CsStrats</a>
            </div>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} CsStrats.dk. All rights reserved.</p>
            <p>GL HF!</p>
        </div>
    </div>
</body>
</html>