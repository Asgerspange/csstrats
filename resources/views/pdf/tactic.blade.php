<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $tactic->name }}</title>
    <style>
        /* Base Styles using your theme colors */
        @page { margin: 0cm; }
        body { 
            font-family: 'Helvetica', sans-serif; 
            background-color: #171717; /* bg */
            color: #B5B5B5; /* text secondary */
            line-height: 1.6; 
            margin: 0;
            padding-bottom: 40px;
        }

        /* Header Section */
        .header-banner {
            background-color: #131313; /* card bg */
            padding: 24px;
            border-bottom: 4px solid #FF8C00; /* primary accent */
        }

        .tactic-name { 
            font-size: 24px; 
            font-weight: bold; 
            margin: 0; 
            color: #FF8C00; /* primary text */
            text-transform: uppercase;
        }

        .meta {
            position: absolute;
            top: 8px;
            right: 8px;
            color: #B5B5B5; 
            font-size: 14px; 
            text-transform: uppercase; 
            letter-spacing: 2px;
            margin-bottom: 5px;
        }

        .container { padding: 24px; }

        .section-title { 
            color: #FF8C00; 
            font-size: 18px;
            font-weight: bold;
            text-transform: uppercase;
            border-bottom: 1px solid #303030;
            padding-bottom: 5px;
            margin: 30px 0 15px 0;
        }

        .card { 
            background-color: #131313; 
            padding: 20px; 
            border-radius: 8px;
            border: 1px solid #303030;
        }

        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        
        th { 
            text-align: left; 
            background: #202020;
            color: #FF8C00; 
            padding: 12px; 
            font-size: 12px;
            text-transform: uppercase;
        }

        td { 
            padding: 15px 12px; 
            border-bottom: 1px solid #303030; 
            vertical-align: top; 
            color: #B5B5B5;
        }

        .player-name { color: #FFFFFF; font-weight: bold; display: block; font-size: 18px}
        .player-role { color: #FF8C00; font-size: 11px; text-transform: uppercase; }

        .badge { 
            display: inline-block; 
            padding: 4px 10px; 
            background: #303030;
            color: #B5B5B5;
            border-radius: 4px; 
            font-size: 11px; 
            margin-right: 5px;
            margin-bottom: 5px;
            border: 1px solid #404040;
        }

        .tag { color: #FF8C00; font-weight: bold; margin-right: 10px; }

        .side-ct { color: #4a90e2; font-weight: bold; }
        .side-t { color: #e24a4a; font-weight: bold; }
        
        .map-hero {
            width: 100%;
            max-height: 200px;
            object-fit: cover;
            opacity: 0.4;
            border-bottom: 1px solid #303030;
        }
    </style>
</head>
<body>

    <div class="header-banner">
        <div class="meta">
            {{ $tactic->map }} • 
            <span class="{{ $tactic->side == 'CT' ? 'side-ct' : 'side-t' }}">{{ $tactic->side }}</span> • 
            {{ $tactic->type }}
        </div>
        <h1 class="tactic-name">{{ $tactic->name }}</h1>
    </div>

    <div class="container">
        
        <div class="section-title">General Strategy</div>
        <div class="card">
            {{ $tactic->description ?: 'No general description provided.' }}
        </div>

        <div class="section-title">Player Assignments</div>
        <table>
            <thead>
                <tr>
                    <th width="25%">Player</th>
                    <th width="45%">Instructions</th>
                    <th width="30%">Equipment</th>
                </tr>
            </thead>
            <tbody>
                @foreach($players as $player)
                <tr>
                    <td>
                        <span class="player-name">{{ $player['name'] }}</span>
                        <span class="player-role">{{ $player['role'] }}</span>
                    </td>
                    <td>{{ $player['description'] }}</td>
                    <td>
                        @foreach($player['loadout'] as $item)
                            <span class="badge">{{ str_replace('-', ' ', $item) }}</span>
                        @endforeach
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="section-title">Tags & Classification</div>
        <div>
            @foreach($tactic->tags as $tag)
                <span class="tag">#{{ $tag }}</span>
            @endforeach
        </div>

    </div>

</body>
</html>