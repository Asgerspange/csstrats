<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Player Stratbook Tactics</title>
    <style>
        body, html {
            font-family: 'Segoe UI', sans-serif;
            background: #191919;
            color: #a5d6a7;
            margin: 0;
            padding: 24px;
            font-size: 16px;
            line-height: 1.6;
        }
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #75aa7f;
            padding-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            flex-direction: column;
        }
        .header h1 {
            font-size: 32px;
            color: #a5d6a7;
            margin-bottom: 5px;
        }
        .tactic {
            margin-bottom: 40px;
            padding: 15px;
            background-color: #1e1e1e;
            border-radius: 8px;
            border-left: 4px solid #75aa7f;
            page-break-before: always;
            page-break-inside: avoid;
        }
        .tactic:first-child {
            page-break-before: auto;
        }
        .tactic h2 {
            font-size: 20px;
            color: #94c79b;
            margin-top: 0;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #75aa7f;
        }
        .tactic-details {
            background-color: #2a2a2a;
            padding: 12px 16px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .tactic-details p {
            margin: 5px 0;
            font-size: 16px;
        }
        .tactic-details strong {
            color: #94c79b;
        }
        .players-container {
            width: 100%;
            margin-bottom: 15px;
        }
        .player-info {
            background-color: #2a2a2a;
            padding: 12px 16px;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            margin-bottom: 15px;
            box-sizing: border-box;
            page-break-inside: avoid;
        }
        .player-info:nth-child(even) {
            margin-right: 0;
        }
        .clearfix::after {
            content: "";
            display: table;
            clear: both;
        }
        .player-info p {
            font-size: 14px;
            margin: 8px 0;
        }
        .player-name {
            font-size: 16px;
            font-weight: bold;
            color: #a5d6a7;
            margin-top: 0;
            border-bottom: 1px solid #75aa7f;
            padding-bottom: 5px;
            margin-bottom: 8px;
            display: block;
        }
        .loadouts {
            margin-left: 0;
            font-style: italic;
            color: #a5d6a7;
            padding: 6px 8px;
            border-radius: 4px;
            margin-bottom: 8px;
        }
        .loadouts strong {
            color: #94c79b;
            font-style: normal;
        }
       .descriptions {
            white-space: normal;
            line-height: 1.4;
            color: #a5d6a7;
            margin-top: 10px;
            border-radius: 0 4px 4px 0;
            text-align: justify;
        }

        .descriptions strong {
            color: #94c79b;
            font-style: normal;
            display: block;
            margin-bottom: 4px;
        }
        
        @media (max-width: 768px) {
            .player-info {
                width: 100% !important;
                margin-right: 0 !important;
                float: none !important;
            }
            body {
                padding: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
    <div class="header">
        <h1>Player Strats in {{ $sName }}</h1>
        
        <!-- Table of Contents -->
        <div class="toc" style="margin-top: 20px; text-align: left;">
            <h2 style="color:#94c79b;">Tactics</h2>
            <ul style="list-style: none; padding: 0;">
                @foreach ($tactics as $index => $tactic)
                    <li style="margin-bottom: 8px;">
                        <a href="#tactic-{{ $index }}" style="color:#a5d6a7; text-decoration: none;">
                            {{ $tactic['tactic_name'] }} - {{ $tactic['tactic_side'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    @foreach ($tactics as $index => $tactic)
        <div class="tactic" id="tactic-{{ $index }}">
            <h2>{{ $tactic['tactic_name'] }} - {{ $tactic['tactic_side'] }}</h2>
            
            <div class="tactic-details">
                <p><strong>Map:</strong> {{ $tactic['map'] }}</p>
                <p><strong>Description:</strong> {{ $tactic['description'] }}</p>
            </div>
            
            <div class="players-container clearfix">
                @foreach ($tactic['players'] as $player)
                    <div class="player-info">
                        <span class="player-name">{{ $player['name'] }}</span>
                        
                        @if (!empty($player['loadouts']))
                            <div class="loadouts"><strong>Buy:</strong> {{ implode(', ', $player['loadouts']) }}</div>
                        @endif
                        
                        @if (!empty($player['descriptions']))
                            <div class="descriptions">
                                <strong>Follow this:</strong> 
                                @if(is_array($player['descriptions']))
                                    {!! nl2br(e(implode("\n", $player['descriptions']))) !!}
                                @else
                                    {!! nl2br(e($player['descriptions'])) !!}
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
</body>
</html>