<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSStrats Promo Banner</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --main-color-light-20: #fff7ed;
            --main-color-light-15: #ffedd5;
            --main-color-light-5: #fdba74;
            --main-color: #ff8c00;
            --main-color-dark-5: #e67e00;
            --main-color-dark-10: #cc7000;
            --main-color-dark-15: #bd3d3d;
            --main-gradient: linear-gradient(90deg, #ff8c00 0%, #bd3d3d 100%);
            --main-bg: #0a0a0a;
            --main-bg-dark: #050505;
            --primary-text-color: #ffffff;
            --secondary-text-color: #e67600;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: transparent;
            font-family: 'Inter', sans-serif;
            overflow: hidden;
            width: 1920px;
            height: 35px;
        }

        /* Banner Container */
        .banner {
            width: 1920px;
            height: 35px;
            background: var(--main-bg);
            border-top: 1px solid var(--main-color-dark-15);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        /* Subtle Background Animation */
        .banner::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 50%;
            height: 100%;
            background: linear-gradient(
                90deg,
                transparent,
                rgba(255, 140, 0, 0.1),
                transparent
            );
            animation: glint 8s infinite linear;
        }

        @keyframes glint {
            0% { left: -100%; }
            20% { left: 200%; }
            100% { left: 200%; }
        }

        .content-wrapper {
            display: flex;
            align-items: center;
            gap: 20px;
            z-index: 2;
        }

        /* Typography */
        .brand {
            color: var(--main-color);
            font-weight: 900;
            font-size: 20px;
            letter-spacing: -0.5px;
            text-transform: uppercase;
        }

        .message {
            color: var(--primary-text-color);
            font-size: 16px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .highlight {
            color: var(--main-color-light-5);
        }

        /* Promo Code Badge */
        .promo-box {
            display: flex;
            align-items: center;
            background: var(--main-gradient);
            padding: 2px 12px;
            border-radius: 4px;
            margin-left: 10px;
            box-shadow: 0 0 15px rgba(255, 140, 0, 0.2);
        }

        .promo-label {
            font-size: 11px;
            color: var(--main-color-light-20);
            font-weight: 400;
            margin-right: 8px;
            opacity: 0.9;
        }

        .promo-code {
            color: #fff;
            font-weight: 900;
            font-size: 18px;
            letter-spacing: 1px;
        }

        /* Separator Dot */
        .dot {
            width: 4px;
            height: 4px;
            background: var(--main-color-dark-15);
            border-radius: 50%;
        }

        /* Ticker Animation for long text if needed */
        .scrolling {
            display: flex;
            animation: scroll 20s linear infinite;
        }

        @keyframes scroll {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }
    </style>
</head>
<body>

    <div class="banner">
        <div class="content-wrapper">
            <!-- Brand -->
            <div class="brand">csstrats</div>
            
            <div class="dot"></div>

            <!-- Main Offer -->
            <div class="message">
                The strat tool for <span class="highlight">competitive CS2</span>
            </div>

            <div class="dot"></div>

            <!-- Call to action -->
            <div class="message" style="font-size: 14px; opacity: 0.8;">
                VISIT CSSTRATS.DK
            </div>
        </div>
    </div>

</body>
</html>