# CLAUDE.md

Agent instructions for the CsStrats repo. Read this first, every session.

## Working principles

Behavioral guidelines to reduce common LLM coding mistakes. Merge with project-specific instructions as needed.

**Tradeoff:** These guidelines bias toward caution over speed. For trivial tasks, use judgment.

### 1. Think Before Coding

**Don't assume. Don't hide confusion. Surface tradeoffs.**

Before implementing:
- State your assumptions explicitly. If uncertain, ask.
- If multiple interpretations exist, present them - don't pick silently.
- If a simpler approach exists, say so. Push back when warranted.
- If something is unclear, stop. Name what's confusing. Ask.

### 2. Simplicity First

**Minimum code that solves the problem. Nothing speculative.**

- No features beyond what was asked.
- No abstractions for single-use code.
- No "flexibility" or "configurability" that wasn't requested.
- No error handling for impossible scenarios.
- If you write 200 lines and it could be 50, rewrite it.

Ask yourself: "Would a senior engineer say this is overcomplicated?" If yes, simplify.

### 3. Surgical Changes

**Touch only what you must. Clean up only your own mess.**

When editing existing code:
- Don't "improve" adjacent code, comments, or formatting.
- Don't refactor things that aren't broken.
- Match existing style, even if you'd do it differently.
- If you notice unrelated dead code, mention it - don't delete it.

When your changes create orphans:
- Remove imports/variables/functions that YOUR changes made unused.
- Don't remove pre-existing dead code unless asked.

The test: Every changed line should trace directly to the user's request.

### 4. Goal-Driven Execution

**Define success criteria. Loop until verified.**

Transform tasks into verifiable goals:
- "Add validation" → "Write tests for invalid inputs, then make them pass"
- "Fix the bug" → "Write a test that reproduces it, then make it pass"
- "Refactor X" → "Ensure tests pass before and after"

For multi-step tasks, state a brief plan:
```
1. [Step] → verify: [check]
2. [Step] → verify: [check]
3. [Step] → verify: [check]
```

Strong success criteria let you loop independently. Weak criteria ("make it work") require constant clarification.

---

**These guidelines are working if:** fewer unnecessary changes in diffs, fewer rewrites due to overcomplication, and clarifying questions come before implementation rather than after mistakes.

## What this is

**CsStrats** (csstrats.dk) is a CS2 SaaS for competitive teams, operated by IS Digital ApS. Three working surfaces, one tool:

1. **Tactics library.** Curated, weekly-updated catalog of Tier 1/2 pro tactics by map.
2. **Stratbook + Board.** Team-owned tactic collections with a collaborative 2D drawing board.
3. **Demoviewer.** Upload a `.dem` file (chunked S3 upload → Python parser via Lambda/queue) and get a parsed match with 2D/3D playback, round-by-round annotations, and callouts attached to the map geometry.

Paddle-billed team plans (€15.83–24.95/mo). Paid-only: no free plan and no trial. Tournament promo codes can be applied at checkout. See `PRODUCT.md` for the strategic register, users, and brand personality.

## Tech stack

| Layer | What |
|---|---|
| Backend | Laravel 12, PHP 8.2+, Octane, Sanctum, Cashier (Stripe + Paddle), Socialite (Google/Steam/Faceit) |
| Frontend | Vue 3, Vite, Pinia + persistedstate, TanStack Vue Query, vue-router, Axios |
| UI | PrimeVue 4.2 wrapped by Volt (`resources/js/components/volt/`), Lucide icons, Embla carousel, Chart.js, Quill, Three.js, PixiJS |
| Styling | Tailwind v4 (`@tailwindcss/vite`, CSS-first config), legacy SCSS variables, `themes.json` CSS-var theme system |
| Demo parser | Python service in `demoparser/` and `src/`, deployed as AWS Lambda / queue worker |
| Storage | AWS S3 (chunked uploads), MySQL via Laravel |
| Observability | (Laravel + Vue), UXwizz analytics (prod only), Matomo (marketing) |
| Build | Vite multi-entry (`app.js`, `welcome.js`, `invite.js`), gzip + brotli compression |

## Routing

**Public / marketing (no auth):**
- `GET /` → `WelcomeController@index` → renders `Welcome.vue` (the landing page).
- `/tos`, `/policy`, `/cookie-policy`, `/offer/{uuid}`, `/custom-package/{id}`, `/invite/{token}`.
- OAuth callbacks (Google, Steam, Faceit). `/paddle/webhook`.

**Authenticated app** (`auth + verified` middleware):
- `GET /{vue?}` is the catch-all. Returns `view('app')` and Vue Router renders pages from inside the app bundle.

**Admin** (`auth + admin`):
- `/admin/*` for demos, tactics, stats, teams.

## Code conventions

- **Indentation: 4 spaces everywhere.** Vue, PHP, JS, TS, SCSS, JSON. No tabs.
- **Vue 3 Composition API with `<script setup>`.** No Options API. TypeScript preferred (`<script setup lang="ts">`) for new components; the codebase mixes TS and JS in `Welcome/`.
- **PHP:** PSR-12 (Pint config in `composer.json`). Service classes in `app/Services/`. Repositories in `app/Repositories/`.
- **Routes:** controllers in `app/Http/Controllers/`, resource routes for API surfaces, middleware chains explicit.
- **Imports in Vue files:** path alias `@` resolves to `resources/js`. Use it: `import Button from '@/components/volt/Button.vue'`.
- **No `#000` or `#fff` in landing-page code.** See `DESIGN.md` `The Warm-Dark Rule`. Use Concrete and Chalk tokens.
- **No em dashes in user-facing copy.** Commas, colons, semicolons, periods, parentheses.
- **Tabular numerics.** Stacked-column numbers always carry `font-variant-numeric: tabular-nums`.
- **Reduced motion:** every scroll-triggered or entrance animation must respect `prefers-reduced-motion: reduce`.

## Directory map

```
app/                            Laravel application code
  Http/Controllers/             Resource and feature controllers
  Http/Middleware/              Auth, geo, tracking, payment-verify
  Models/                       Eloquent models (User, Team, Tactic, Stratbook, Demo, etc.)
  Repositories/                 Query layer for complex reads
  Services/                     CacheService, etc.
bootstrap/
config/                         Laravel config (services, cache, paddle, etc.)
database/                       Migrations, seeders, factories
demoparser/                     Python demo-parsing service (touch with care)
public/                         Static assets, build output
  images/                       Brand assets, weapon/grenade/map sprites
  appvid.webm                   Current hero video
resources/
  css/, scss/                   Legacy stylesheets (variables.scss is the SCSS token source)
  js/
    app.js                      Authenticated app entry
    welcome.js                  Marketing entry
    invite.js                   Invite-flow entry
    App.vue, router.ts          Authenticated app shell
    Welcome/                    Landing page Vue components (marketing surface)
    Components/, components/    App-shell UI (sidebar, dropdowns, modals, etc.)
    ComponentsNew/              Migrating UI surface (Volt-wrapped)
    components/volt/            PrimeVue Volt theme wrappers (Button, Input, Dialog, etc.)
    components/ui/              Headless UI primitives (Carousel via Embla, etc.)
    composables/, store/        Vue composables and Pinia stores
    Pages/, PagesNew/           Pages for the authenticated app
    Workers/                    Web workers
    types/                      TypeScript types
    translations/               i18n
    lib/                        Helpers
  views/                        Blade
    app.blade.php               Root layout for authenticated app
    layouts/wlayout.blade.php   Root layout for marketing surface
    welcome.blade.php           Welcome shell
routes/
  web.php                       All web routes (public + auth + admin)
  api.php                       Authenticated API resources
src/                            Auxiliary Python sources for demo parsing
tests/                          PHPUnit
PRODUCT.md                      Strategic design context (brand, users, voice, anti-refs)
DESIGN.md                       Visual design system (Stitch format, OKLCH tokens, named rules)
.impeccable/design.json         Sidecar for impeccable skill (tonal ramps, motion, components)
vite.config.js                  Multi-entry Vite config
composer.json                   PHP deps + `composer run dev` script
package.json                    Node deps + `npm run dev|build` scripts
```

### Directories to skip when exploring

- **`vendor/`** — Composer deps.
- **`node_modules/`** — npm deps.
- **`storage/`**, **`bootstrap/cache/`**, **`public/build/`** — runtime / build artifacts.

Read-only when researching: `.git/`, `test-results/`.

## Design context

The visual and strategic design system lives in two files at the repo root. **Read both before any design work.**

- **`PRODUCT.md`** answers who/what/why. Register, users, product purpose, brand personality, anti-references (refrag.gg explicit, generic gaming SaaS, esports-org templates, Discord/Twitch, Linear-clone B2B, editorial-typographic lane), and the five strategic principles.
- **`DESIGN.md`** answers how it looks. OKLCH color tokens, Schibsted Grotesk + JetBrains Mono typography, elevation philosophy (flat at rest, tonal layering), components (Precise and Reserved: 4-6px radii, 1px Ash borders, no glass blur), and the named rules (One Voice, No-Neon, Warm-Dark, Display-Once, Mono-For-Data, Flat-At-Rest, Tonal-Layering).
- **`.impeccable/design.json`** sidecar carries what the Stitch frontmatter cannot (tonal ramps, motion tokens, breakpoints, full component HTML/CSS snippets, the narrative).

### Register

CsStrats has two registers and the surface in focus determines which one applies.

- **Marketing / landing / brand** (`/`, anything served via `welcome.js` + `wlayout.blade.php`): the design IS the product impression. North Star: "The Analyst's Bench." Use the brand register and the full `DESIGN.md` named rules.
- **Authenticated app** (`/{vue?}`, served via `app.js` + `app.blade.php`): design SERVES the product. The themes system in `resources/themes.json` carries the app palette; respect that. The same color tokens (Saffron Strike, etc.) apply when overlap is sensible, but the app prioritizes data density and workflow speed over brand expression.

### The impeccable skill is available

The `impeccable` plugin is installed at the project scope. Slash commands available for design work:

- `/impeccable craft <feature>` — shape, then build end-to-end.
- `/impeccable shape <feature>` — UX/UI plan before code.
- `/impeccable critique <target>` — UX heuristic review with scoring.
- `/impeccable audit <target>` — technical (a11y, perf, responsive) checks.
- `/impeccable polish <target>` — final quality pass.
- `/impeccable animate <target>`, `colorize`, `typeset`, `layout`, `delight`, `overdrive`, `bolder`, `quieter`, `distill`, `harden`, `onboard`, `clarify`, `adapt`, `optimize`, `extract`, `document`, `teach`, `live`.

The skill auto-loads `PRODUCT.md` and `DESIGN.md` and follows the named rules; don't reproduce them in chat, point at them and apply.

## Development

### Local run

```bash
# One shot: PHP server + Vite (also npm worker:watch if needed)
composer run dev
```

This expands to `npx concurrently "php artisan serve" "npm run dev"`. If port 8000 is taken, `artisan serve` falls back to 8001/8002.

Vite serves on `127.0.0.1:5173` by default (5174 if 5173 is busy). The hot-reload entries are `resources/js/app.js`, `resources/js/welcome.js`, and `resources/js/invite.js`. The marketing surface is the `welcome.js` bundle.

### Database / Artisan commands

The PHP environment runs in a Docker container named `php84`. Run all artisan commands (migrations, tinker, etc.) through it; this is the only way that resolves the cloud DB without manual tunnel setup. Drop the `-t` flag (no TTY) when invoking from a non-interactive shell.

```bash
# Run a migration (use --force to skip the confirmation prompt in non-interactive contexts)
docker exec -w /sites/csstrats php84 php artisan migrate --force

# tinker / one-off PHP against the bootstrapped app
docker exec -w /sites/csstrats php84 php artisan tinker
docker exec -w /sites/csstrats php84 php -r '<code>'
```

The user-defined `cs-tunnels` shell alias opens SSH tunnels for direct MySQL access from the host (e.g. for a GUI client):

```bash
cs-tunnels
```

This is a shell alias and is not available to non-interactive subprocesses; the user must run it in their own terminal.

### Build

```bash
npm run build       # production: main + worker bundles, gzip + brotli, source maps
npm run worker:watch
```

**Never run `npm run build` (or `vite build`) during a coding session.** The `public/build/` directory is committed, and a local build regenerates every hashed asset, producing a massive noisy diff that obscures the real code change and risks shipping stale/wrong chunks. The build runs in CI/deploy. To verify a change compiles, rely on the dev server (`composer run dev` / `npm run dev`) hot-reload, the editor's TS/Vol/Volar diagnostics, or `npm run doctor` — not a production build. If a build is genuinely required, ask the user first.

### PHP / quality

```bash
./vendor/bin/pint                 # style fix (PSR-12)
./vendor/bin/phpstan analyse      # static analysis
./vendor/bin/phpunit              # tests
```

### Doctor

A repo-specific checker exists for the Laravel + Vue plumbing:

```bash
npm run doctor       # runs laravel-vue-doctor.mjs
```

## Gotchas worth knowing

1. **Two Tailwind footprints.** Tailwind v4 is the active engine (`@tailwindcss/vite`, CSS-first config). There is no `tailwind.config.js`; theme tokens belong in `resources/css/app.css` via `@theme`. The legacy `resources/scss/variables.scss` still ships but only the authenticated app references it.
2. **Volt button in the welcome bundle.** `@/components/volt/Button.vue` is built on PrimeVue, but `welcome.js` does not register PrimeVue. The button renders fine but ripple and ConfirmService features are no-ops on the marketing surface. Prefer a marketing-only button primitive in `Welcome/` for the landing rebuild.
3. **`wlayout.blade.php` has two nested `<style>` blocks** that duplicate body/scrollbar styles. Clean this up when touching the layout.
5. **Theme system (`resources/themes.json`)** drives the authenticated app via CSS variables injected from `app.blade.php`. Don't break it when changing color tokens; the marketing surface should not depend on the user's per-account theme.
7. **Sandbox vs production Paddle.** `window.PADDLE_SANDBOX` is injected from blade config; `Pricing.vue` reads it to pick the right `priceId`. Same product, two sets of price IDs in `WelcomeController::getProducts()`.
8. **`Welcome/Support.vue` is entirely commented out** in the current code. The FAQ + Discord CTA used to live there; the file renders nothing. The landing rebuild needs to make a deliberate decision: rebuild it, fold it elsewhere, or remove it.
9. **`Welcome/C4Animation.vue` is an orphan** (122 LOC, not imported anywhere). Safe to delete unless someone is actively iterating on it.
10. **`Welcome/Hero.vue`** has an `IntersectionObserver` block that never runs (selectors don't match anything in the template). Dead code; remove during rebuild.
