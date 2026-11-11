<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0cm; }
        body { 
            font-family: 'Helvetica', sans-serif; 
            background-color: #171717; 
            color: #B5B5B5; 
            margin: 0; 
        }
        
        /* Layout */
        .page { 
            page-break-after: always; 
            min-height: 100vh; 
            position: relative; 
        }
        .page:last-child { page-break-after: avoid; }

        /* TOC Styling */
        .toc-container { padding: 50px; background: #131313; min-height: 100vh; }
        .toc-title { color: #FF8C00; font-size: 32px; text-transform: uppercase; border-bottom: 2px solid #FF8C00; padding-bottom: 10px; margin-bottom: 30px; }
        
        .toc-link { 
            display: block; 
            text-decoration: none; 
            color: inherit; 
            padding: 12px 0; 
            border-bottom: 1px solid #303030;
        }

        .toc-row { width: 100%; display: table; }
        .toc-cell { font-size: 16px; display: table-cell; vertical-align: middle; }
        
        .toc-side { font-weight: bold; width: 60px; }
        .toc-name { color: #FFF; }
        .toc-map { width: 120px; color: #666; font-size: 12px; text-transform: uppercase; text-align: right; padding-right: 20px; }
        .toc-page-num { text-align: right; width: 60px; color: #FF8C00; font-family: monospace; }

        /* Tactic Header */
        .header-banner { background-color: #131313; padding: 24px; border-bottom: 4px solid #FF8C00; }
        .tactic-name { font-size: 24px; font-weight: bold; margin: 0; color: #FF8C00; text-transform: uppercase; }
        .meta { float: right; color: #B5B5B5; font-size: 14px; text-transform: uppercase; letter-spacing: 2px; }
        
        /* Content Sections */
        .container { padding: 24px; padding-bottom: 60px; } /* Space for footer link */
        .section-title { color: #FF8C00; font-size: 18px; font-weight: bold; text-transform: uppercase; border-bottom: 1px solid #303030; padding-bottom: 5px; margin: 30px 0 15px 0; }
        .card { background-color: #131313; padding: 20px; border-radius: 8px; border: 1px solid #303030; }
        
        /* Assignments Table */
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; background: #202020; color: #FF8C00; padding: 12px; font-size: 12px; text-transform: uppercase; }
        td { padding: 15px 12px; border-bottom: 1px solid #303030; vertical-align: top; color: #B5B5B5; }
        
        /* Elements */
        .badge { display: inline-block; padding: 4px 10px; background: #303030; color: #B5B5B5; border-radius: 4px; font-size: 11px; margin-right: 5px; border: 1px solid #404040; margin-bottom: 3px; }
        .side-ct { color: #4a90e2; }
        .side-t { color: #e24a4a; }

        /* Return to TOC Link */
        .back-to-toc {
            position: absolute;
            bottom: 20px;
            right: 24px;
            text-decoration: none;
            color: #FF8C00;
            font-size: 10px;
            text-transform: uppercase;
            font-weight: bold;
            letter-spacing: 1px;
            opacity: 0.6;
        }
    </style>
</head>
<body>

    {{-- 1. Clickable Table of Contents --}}
    @if($includeToc)
    <div class="page" id="top-toc">
        <div class="toc-container">
            <h1 class="toc-title">Table of Contents</h1>
            @foreach($tactics as $index => $item)
                <a href="#tactic-{{ $item['data']->id }}" class="toc-link">
                    <div class="toc-row">
                        <span class="toc-cell toc-side {{ $item['data']->side == 'CT' ? 'side-ct' : 'side-t' }}">
                            [{{ $item['data']->side }}]
                        </span>
                        <span class="toc-cell toc-name">{{ $item['data']->name }}</span>
                        <span class="toc-cell toc-map">{{ $item['data']->map }}</span>
                        <span class="toc-cell toc-page-num">
                             P. {{ $index + 2 }} {{-- Assuming 1 TOC page, first tactic starts on p. 2 --}}
                        </span>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
    @endif

    {{-- 2. Tactic Pages (1 per page) --}}
    @foreach($tactics as $item)
    @php 
        $t = $item['data']; 
        $players = $item['players']; 
    @endphp
    <div class="page" id="tactic-{{ $t->id }}">
        <div class="header-banner">
            <div class="meta">{{ $t->map }} • {{ $t->side }} • {{ $t->type }}</div>
            <h1 class="tactic-name">{{ $t->name }}</h1>
        </div>
        
        <div class="container">
            <div class="section-title">General Strategy</div>
            <div class="card">{{ $t->description ?: 'No description provided.' }}</div>

            <div class="section-title">Player Assignments</div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 25%">Player</th>
                        <th style="width: 45%">Instructions</th>
                        <th style="width: 30%">Equipment</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($players as $p)
                    <tr>
                        <td>
                            <strong>{{ $p['name'] }}</strong><br>
                            <small class="side-ct">{{ $p['role'] }}</small>
                        </td>
                        <td style="font-size: 13px; line-height: 1.4;">{{ $p['description'] }}</td>
                        <td>
                            @foreach($p['loadout'] as $l)
                                <span class="badge">{{ str_replace('-', ' ', $l) }}</span>
                            @endforeach
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Clickable Back-to-TOC Footer --}}
        @if($includeToc)
            <a href="#top-toc" class="back-to-toc">↑ Return to Contents</a>
        @endif
    </div>
    @endforeach

</body>
</html>