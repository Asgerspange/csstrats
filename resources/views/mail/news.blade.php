<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.6;
            color: #333333;
            margin: 0;
            padding: 0;
            background-color: #f9f9f9;
        }
        p {
            color: black;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #ffffff;
        }
        .header {
            padding: 10px 0;
            text-align: center;
            color: black;
            border-bottom: 1px solid #eeeeee;
        }
        .content {
            padding: 20px 0;
        }
        .footer {
            text-align: center;
            padding: 15px 0;
            font-size: 12px;
            color: #666666;
            border-top: 1px solid #eeeeee;
        }
        .unsubscribe {
            margin-top: 20px;
            padding: 10px 0;
            border-top: 1px solid #eeeeee;
            font-size: 12px;
            color: #666666;
        }
        .unsubscribe a {
            color: #1a73e8;
            text-decoration: none;
        }
        .button {
            display: inline-block;
            padding: 8px 16px;
            background-color: #1a73e8;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 4px;
            margin: 10px 0;
        }
        img {
            max-width: 100%;
            height: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>{{ $companyName }}</h2>
        </div>
        
        <div class="content">
            @if($recipientName)
                <p>Hello {{ $recipientName }},</p>
            @else
                <p>Hello,</p>
            @endif
            
            {!! $content !!}
            
            @if(isset($callToAction))
                <div style="text-align: center; margin: 20px 0;">
                    <a href="{{ $callToAction['url'] }}" class="button">{{ $callToAction['text'] }}</a>
                </div>
            @endif
        </div>
        
        <div class="unsubscribe">
            <p>If you no longer wish to receive these emails, you can <a href="{{ $link }}">unsubscribe here</a>.</p>
        </div>
        
        <div class="footer">
            <p>&copy; {{ date('Y') }} {{ $companyName }}. All rights reserved.</p>
            <p>{{ $companyAddress }}</p>
        </div>
    </div>
</body>
</html>