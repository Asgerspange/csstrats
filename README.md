# CsStrats social pipeline

Self-hosted content pipeline. The `social-content` Claude Code subagent writes
posts into MySQL via an internal API. n8n picks them up, asks for approval in
Discord, then publishes to **X / TikTok / YouTube Shorts / Threads**. Click
attribution flows back through `/api/metrics/event` into the `social_events`
table, where Grafana reads it via `/api/metrics/social`.

**uxwizz** continues to handle the rest of visitor analytics (pageviews,
sessions, funnels). The social pipeline does not duplicate that — it only adds
the social-attribution layer Grafana needs.

**Monthly cost: $0** — uses existing infrastructure (your server, Bunny.net,
Hetzner Storagebox, uxwizz, Grafana).

---

## Architecture at a glance

```
Claude Code ──── social-content subagent
                          │ POSTs curl commands you run
                          ▼
                  social_posts table (MySQL)
                  status=draft
                          │
        you upload asset to Bunny + PATCH asset_bunny_url
                          │
                          ▼
              n8n workflow 10 (every 5 min)
              Posts approval embed to Discord
                          │
                          ▼
                You react ✅ or ❌
                          │
                          ▼
            Discord bot (Python sidecar)
            Forwards to n8n /webhook/discord-reaction
                          │
                          ▼
            n8n workflow 11 → status=approved
                          │
                          ▼
            n8n workflow 20 (every 5 min)
            Polls due posts → fans out per platform
                          │
          ┌───────────────┼───────────────┬───────────────┐
          ▼               ▼               ▼               ▼
       X API         TikTok API      YouTube API     Threads API
       (21)          (22, inbox)     (23)            (24)
          │               │               │               │
          └───────────────┴───────────────┴───────────────┘
                          │
                          ▼
                 csstrats.dk?utm_source=...
                          │
                          ▼
        useSocialAttribution.trackLanding()
        POST /api/metrics/event → social_events row
                          │
                          ▼
              Grafana panel + Sunday 23:00
              n8n workflow 30 → Discord summary
```

---

## Repo contents

```
.claude/agents/
  growth-strategist.md                  # strategy + lifecycle agent
  social-content.md                     # social writer agent (Postgres-aware)

app/
  Http/Controllers/
    SocialPostController.php            # NEW: /api/internal/social/* endpoints
    MetricsEventController.php          # NEW: POST /api/metrics/event
    RetentionMetricsController-social-patch.php  # patch into existing controller
  Models/
    SocialPost.php                      # NEW
    SocialSeries.php                    # NEW
    SocialEvent.php                     # NEW

database/migrations/
  2026_05_15_000000_create_social_pipeline_tables.php   # NEW

routes/
  api-additions.php                     # merge into routes/api.php

config/
  services-additions.php                # merge into config/services.php

resources/js/composables/
  useSocialAttribution.ts               # NEW: complements useUXWizz.ts

marketing/
  social-content-postgres-addendum.md   # already merged into the agent above
  discord-bot/                          # Python sidecar, separate Docker svc
    bot.py
    Dockerfile
  n8n-workflows/                        # 8 importable JSON workflows
    10-approval.json
    11-discord-reaction-listener.json
    20-publisher-master.json
    21-publisher-x.json
    22-publisher-tiktok.json
    23-publisher-youtube-shorts.json
    24-publisher-threads.json
    30-weekly-analytics.json

n8n/                                    # infra files, separate from Laravel
  docker-compose.yml
  .env.example
  nginx-n8n.conf
```

---

## Setup — full walkthrough

### Step 1: Drop new Laravel files into the csstrats repo

```bash
cd /path/to/csstrats
git checkout -b social-pipeline

# Agent files
mkdir -p .claude/agents
cp <bundle>/.claude/agents/growth-strategist.md  .claude/agents/
cp <bundle>/.claude/agents/social-content.md     .claude/agents/

# Backend files
cp <bundle>/app/Models/SocialPost.php             app/Models/
cp <bundle>/app/Models/SocialSeries.php           app/Models/
cp <bundle>/app/Models/SocialEvent.php            app/Models/
cp <bundle>/app/Http/Controllers/SocialPostController.php    app/Http/Controllers/
cp <bundle>/app/Http/Controllers/MetricsEventController.php  app/Http/Controllers/
cp <bundle>/database/migrations/2026_05_15_000000_create_social_pipeline_tables.php database/migrations/

# Frontend file
cp <bundle>/resources/js/composables/useSocialAttribution.ts resources/js/composables/
```

### Step 2: Merge route + config + controller additions

**`routes/api.php`** — open it, then from `routes/api-additions.php`:

1. Add the `use` statements at the top of `api.php`:
   ```php
   use App\Http\Controllers\MetricsEventController;
   use App\Http\Controllers\SocialPostController;
   ```
2. Add the `Route::prefix('internal/social')->group(...)` block top-level.
3. Add the rate-limited `Route::post('metrics/event', ...)` block.
4. Add one line to the existing `Route::prefix('metrics')->group(...)`:
   ```php
   Route::get('/social', [\App\Http\Controllers\RetentionMetricsController::class, 'social']);
   ```

**`config/services.php`** — open it, find the returned array, and inside
that array (next to `mailgun`, `paddle`, `odin`, etc), paste these:

```php
'social_pipeline' => [
    'secret' => env('SOCIAL_PIPELINE_SECRET'),
],

'discord' => [
    'pipeline_webhook'    => env('DISCORD_PIPELINE_WEBHOOK'),
    'pipeline_bot_token'  => env('DISCORD_PIPELINE_BOT_TOKEN'),
    'pipeline_channel_id' => env('DISCORD_PIPELINE_CHANNEL_ID'),
],
```

Do **not** touch the existing `bunny` entry — the pipeline reuses your
existing `BUNNY_ACCESS_KEY` / `BUNNY_STORAGE_ZONE` / `BUNNY_CDN_URL` etc.

**`app/Http/Controllers/RetentionMetricsController.php`** — open it, then
from `RetentionMetricsController-social-patch.php`, copy the entire `social()`
method definition and paste it inside the existing class, alongside
`retention()`, `funnel()`, `churnRisk()`, `trial()`, `inviteFunnel()`.

### Step 3: Add env vars

```bash
cat >> .env <<EOF

# Social pipeline
SOCIAL_PIPELINE_SECRET=$(openssl rand -hex 32)
DISCORD_PIPELINE_WEBHOOK=
DISCORD_PIPELINE_BOT_TOKEN=
DISCORD_PIPELINE_CHANNEL_ID=

# GRAFANA_METRICS_TOKEN should already exist for /api/metrics/retention etc.
# If it doesn't, add it:
# GRAFANA_METRICS_TOKEN=$(openssl rand -hex 32)
EOF
```

### Step 4: Run the migration and verify the API

```bash
php artisan migrate

# Should return { "posts": [] }
SECRET=$(grep ^SOCIAL_PIPELINE_SECRET .env | cut -d= -f2)
curl -s https://csstrats.dk/api/internal/social/posts \
  -H "X-Pipeline-Secret: $SECRET" | jq

# Should return { "series": "nade_of_the_day", "last_entry": 0, "coverage_log": [] }
curl -s https://csstrats.dk/api/internal/social/series/nade_of_the_day/coverage \
  -H "X-Pipeline-Secret: $SECRET" | jq
```

### Step 5: Wire `useSocialAttribution` into the Vue app

Find your Vue app entrypoint (probably `resources/js/app.ts` or wherever
`useUXWizz` is initialized for every page). Add:

```typescript
import { useSocialAttribution } from '@/composables/useSocialAttribution'

// After app mount or on every router navigation:
const { trackLanding } = useSocialAttribution()
trackLanding()
```

In your existing register / trial / team-create flows, also call:

```typescript
const { trackEvent } = useSocialAttribution()
// on registration success:
trackEvent('register')
// on trial activation:
trackEvent('trial_start')
// on team creation:
trackEvent('team_created')
// on subscription started:
trackEvent('subscription_started')
```

### Step 6: Commit, push, deploy

```bash
git add .
git commit -m "Add social pipeline: schema, API, agent integration, attribution"
git push origin social-pipeline
# Open PR, review, merge, deploy
```

### Step 7: Set up n8n + Discord bot on your server

```bash
ssh your-server
mkdir -p /srv/csstrats-pipeline && cd /srv/csstrats-pipeline

# Copy infra files
cp <bundle>/n8n/docker-compose.yml .
cp <bundle>/n8n/.env.example .env

# The Dockerfile builds from ../marketing/discord-bot, so put the bot files
# adjacent on the server:
mkdir -p /srv/marketing
cp -r <bundle>/marketing/discord-bot /srv/marketing/

# Adjust the bot build path in docker-compose.yml if needed (it currently
# expects ../marketing/discord-bot relative to /srv/csstrats-pipeline)

# Fill in .env — real values for every var
nano .env

docker compose up -d
docker compose logs -f n8n
```

### Step 8: Nginx + TLS for n8n

```bash
sudo cp <bundle>/n8n/nginx-n8n.conf /etc/nginx/sites-available/n8n.csstrats.dk
sudo ln -s /etc/nginx/sites-available/n8n.csstrats.dk /etc/nginx/sites-enabled/

# Add DNS A record n8n.csstrats.dk → server IP, wait for propagation

sudo certbot --nginx -d n8n.csstrats.dk
sudo nginx -t && sudo systemctl reload nginx
```

Open `https://n8n.csstrats.dk`, create the owner account.

### Step 9: Discord setup

1. Discord server (private one is fine) → channel `#social-approval`.
2. Server Settings → Integrations → Webhooks → New Webhook → name it
   "CsStrats Pipeline". Copy the URL → `DISCORD_PIPELINE_WEBHOOK` in .env.
3. discord.com/developers/applications → New Application → "CsStrats Pipeline"
   → Bot → Add Bot → Reset Token. Copy to `DISCORD_BOT_TOKEN`.
4. Bot settings, enable intents: **Server Members + Message Content +
   Reactions**.
5. OAuth2 → URL Generator → scope `bot` → permissions: View Channels,
   Read Message History, Add Reactions. Use the URL to invite the bot.
6. Enable Developer Mode (Discord User Settings → Advanced). Right-click
   the channel → Copy Channel ID → `DISCORD_CHANNEL_ID`.
7. `docker compose restart discord-bot` to pick up the new env values.

### Step 10: Connect platform OAuth in n8n UI

In `https://n8n.csstrats.dk` → Credentials → New:

| Platform | Where to register | Required scope | Notes |
|---|---|---|---|
| X | developer.x.com | OAuth 2.0, tweet.read + tweet.write | Free tier 500 posts/mo |
| TikTok | developers.tiktok.com | user.info.basic, video.upload, video.publish | Approval takes 1-2 weeks; pipeline uses Inbox endpoint meanwhile |
| YouTube | console.cloud.google.com → enable YouTube Data API v3 | youtube.upload | 10,000 quota units/day |
| Threads | developers.facebook.com → Threads API | threads_basic, threads_content_publish | Or skip + use native X→Threads cross-post |

For each, the redirect URI is:
`https://n8n.csstrats.dk/rest/oauth2-credential/callback`

### Step 11: Import workflows

n8n UI → Workflows → Import from File. Order matters because 20 references
21-24 by name:

1. `21-publisher-x.json`
2. `22-publisher-tiktok.json`
3. `23-publisher-youtube-shorts.json`
4. `24-publisher-threads.json`
5. `20-publisher-master.json`
6. `10-approval.json`
7. `11-discord-reaction-listener.json`
8. `30-weekly-analytics.json`

For each: open it, find any `REPLACE_WITH_*_CREDENTIAL_ID` placeholder, replace
with the credential ID you just created. Activate (toggle top-right).

### Step 12: End-to-end test

```bash
SECRET="..."  # SOCIAL_PIPELINE_SECRET
API="https://csstrats.dk/api/internal/social"

# Test post scheduled 2 minutes from now
NOW_PLUS_2=$(date -u -d '+2 minutes' +'%Y-%m-%dT%H:%M:%S+00:00')

curl -X POST "$API/posts" \
  -H "X-Pipeline-Secret: $SECRET" \
  -H "Content-Type: application/json" \
  -d "{
    \"platforms\": [\"x\"],
    \"scheduled_at\": \"$NOW_PLUS_2\",
    \"pillar\": \"tactic_of_the_day\",
    \"slug\": \"test-post\",
    \"campaign\": \"test\",
    \"asset_type\": \"none\",
    \"body\": \"Test post from the CsStrats pipeline. Ignore.\"
  }"
```

Within 5 minutes you should see:
1. Discord embed pings you with the post preview
2. React ✅ → status becomes `approved`
3. Within another 5 min, the post appears on X
4. `social_posts` row shows `status=sent` and `published_urls.x`

If anything fails, n8n's Executions view shows the full error per workflow.

---

## Daily workflow

**Monday morning, in Claude Code (~30 min):**

```
Use the social-content subagent to write this week's
Nade of the day entries — 5 of them, mixed maps.
```

The agent reads coverage from the API, picks 5 non-overlapping
`{map, utility, position}` combinations, outputs 5 paste-ready `curl` POST
commands. You run them. 5 rows appear in `social_posts` with `status=draft`,
asset URL empty.

**For each post:**
1. Record/screenshot the asset
2. Upload to Bunny (your existing Bunny tooling)
3. PATCH the post with the asset URL:
   ```bash
   curl -X PATCH https://csstrats.dk/api/internal/social/posts/N \
     -H "X-Pipeline-Secret: $SECRET" \
     -d '{"asset_bunny_url":"https://YOUR_BUNNY_CDN/social/..."}'
   ```

**Approval bot pings you in Discord** for each post as soon as the asset URL
is set. React ✅ to approve, ❌ to skip.

**Sunday 23:00**, n8n workflow 30 posts the weekly summary to Discord.

---

## How analytics flows

uxwizz handles your normal visitor tracking. The pipeline adds a *separate*
attribution layer just for social posts:

```
User on TikTok ──► clicks csstrats.dk?utm_source=tiktok&utm_campaign=nade-of-the-day-3
       │
       ▼
csstrats.dk loads ──► uxwizz tracks pageview (its job, untouched)
       │
       ▼
useSocialAttribution.trackLanding() detects utm_source=tiktok
       │
       ▼
POST /api/metrics/event { event: 'landing', utm_source, utm_campaign }
       │
       ▼
MetricsEventController matches campaign+source ──► social_post #3
       │
       ▼
social_events row inserted, linked to the post
       │
       ▼
User registers ──► useSocialAttribution.trackEvent('register')
       │
       ▼
Another social_events row, same UTM via sessionStorage
       │
       ▼
Sunday 23:00 ──► n8n GETs /api/metrics/social with GRAFANA_METRICS_TOKEN
       │
       ▼
RetentionMetricsController::social() returns aggregates
       │
       ▼
n8n weekly-analytics workflow ──► Discord summary
```

Build a Grafana panel any time using the same `/api/metrics/social` endpoint.
Same auth pattern as your existing `/api/metrics/retention` etc.

---

## Cost breakdown

| Component | Monthly cost |
|---|---|
| n8n (self-hosted) | $0 |
| Discord bot (self-hosted) | $0 |
| MySQL (existing csstrats DB) | $0 |
| Bunny.net storage + CDN | already paid |
| uxwizz (already running) | already paid |
| Grafana (already running) | already paid |
| X API (free tier — 500 posts/mo) | $0 |
| TikTok API | $0 |
| YouTube Data API v3 | $0 |
| Threads API | $0 |
| **Total new** | **$0/mo** |

---

## Backup

```bash
# Nightly rsync of n8n_data to Hetzner Storagebox
0 3 * * * rsync -az /srv/csstrats-pipeline/n8n_data/ user@storagebox:n8n_backups/$(date +\%Y\%m\%d)/
```

`social_posts`, `social_series`, `social_events` go through your existing
MySQL backup.

---

## Recovery

| Failure | Action |
|---|---|
| n8n encryption key lost | Re-authorize every OAuth app. ~2h. Back up the key separately. |
| Server dies | Restore `n8n_data` from Storagebox. Workflows + creds return. |
| Platform API key revoked | n8n executions show 401. Re-auth in n8n UI. |
| Bunny down | Posts with asset fail. n8n marks `status=failed`. Discord pings. |
| Discord webhook deleted | Re-create webhook, update .env, restart n8n. |
| MySQL down | Pipeline pauses. n8n executions fail gracefully. |
| TikTok app rejected | Pipeline auto-falls back to Inbox/Draft mode. You tap "Post" from phone. |

---

## When to revisit

- **Week 4** — read Sunday's analytics. Are clicks happening? Which platform?
- **Week 8** — which series has best engagement? Double down. Cut the weakest.
- **Week 12** — any platform with <1% of total clicks: remove from default
  `platforms` array on new posts.
- **Month 6** — consider adding LinkedIn / Instagram sub-workflows if
  production capacity is the bottleneck, not writing.

---

## Hard constraints baked into the agents

- The agents never invent product features. The three surfaces are Tactics
  library, Stratbook + Board, Demoviewer.
- Never quote prices from memory — agents read `WelcomeController::getProducts()`.
- Never use user data in public posts. Pro broadcast demos only.
- Never @ a pro or org unsolicited unless adding clear value.
- Never trash a competitor by name.
- Never claim partnerships, sponsorships, or testimonials that don't exist.

These are in the system prompts. The pipeline can't override them.
