<template>
    <div class="not-found">
        <!-- Smoke particles -->
        <div class="smoke-container" aria-hidden="true">
            <div v-for="i in 5" :key="i" class="smoke" :class="`smoke-${i}`"></div>
        </div>
        <div class="glow" aria-hidden="true"></div>

        <div class="content">
            <!-- Crosshair -->
            <div class="crosshair-wrap" aria-hidden="true">
                <div class="crosshair">
                    <div class="crosshair-circle"></div>
                    <div class="crosshair-center"></div>
                </div>
            </div>

            <!-- Badge -->
            <div class="badge">
                <div class="badge-dot"></div>
                Smoke grenade deployed
            </div>

            <!-- 404 -->
            <span class="number">404</span>

            <h1>You got <span>smoked off</span><br>the page, mate.</h1>

            <p class="subtitle">
                This page doesn't exist — or it did, but someone threw a smoke
                and now nobody can find it. Classic mid-round chaos.
            </p>

            <!-- Kill feed -->
            <div class="killfeed">
                <div class="killfeed-title">💀 Error log</div>
                <div class="kill-row kill-row-1">
                    <span class="attacker">YOU</span>
                    <span class="kf-icon">→</span>
                    <span class="weapon">404</span>
                    <span class="kf-icon">→</span>
                    <span class="victim">THIS PAGE</span>
                </div>
                <div class="kill-row kill-row-2">
                    <span class="victim">YOUR REQUEST</span>
                    <span class="kf-icon">was wallbanged by</span>
                    <span class="weapon">MISSING ROUTE</span>
                </div>
                <div class="kill-row kill-row-3">
                    <span class="attacker">SERVER</span>
                    <span class="kf-icon">has no info about</span>
                    <span class="victim">WHAT YOU WANTED</span>
                </div>
            </div>

            <!-- Buttons -->
            <div class="btn-row">
                <router-link to="/tactics" class="btn btn-primary">Back to Strats</router-link>
                <a href="/" class="btn btn-ghost">Go Home</a>
            </div>

            <p class="footer-note">CsStrats · Round lost · Better luck CT side</p>
        </div>
    </div>
</template>

<script setup lang="ts">
// No logic needed — pure presentation
</script>

<style scoped>
.not-found {
    min-height: 100vh;
    background: #0a0a0b;
    color: #e8e8ec;
    font-family: 'Barlow', sans-serif;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    position: relative;
}

/* ── Smoke particles ───────────────────────────────────── */
.smoke-container {
    position: fixed;
    inset: 0;
    pointer-events: none;
    z-index: 0;
}

.smoke {
    position: absolute;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(180,180,200,0.06) 0%, transparent 70%);
    animation: drift linear infinite;
}

.smoke-1 { width: 300px; height: 300px; left: 10%; animation-duration: 18s; animation-delay: 0s; }
.smoke-2 { width: 200px; height: 200px; left: 60%; animation-duration: 22s; animation-delay: -5s; }
.smoke-3 { width: 400px; height: 400px; left: 30%; animation-duration: 28s; animation-delay: -12s; }
.smoke-4 { width: 150px; height: 150px; left: 80%; animation-duration: 16s; animation-delay: -8s; }
.smoke-5 { width: 250px; height: 250px; left: 5%;  animation-duration: 24s; animation-delay: -3s; }

@keyframes drift {
    from { transform: translateY(110vh) scale(0.5); opacity: 0; }
    10%  { opacity: 1; }
    90%  { opacity: 0.6; }
    to   { transform: translateY(-20vh) scale(1.4); opacity: 0; }
}

/* ── Glow blob ─────────────────────────────────────────── */
.glow {
    position: fixed;
    width: 600px; height: 600px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(249,115,22,0.07) 0%, transparent 65%);
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    pointer-events: none;
    z-index: 0;
    animation: pulse 4s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { transform: translate(-50%, -50%) scale(1);   opacity: 0.7; }
    50%       { transform: translate(-50%, -50%) scale(1.1); opacity: 1;   }
}

/* ── Content ───────────────────────────────────────────── */
.content {
    position: relative;
    z-index: 1;
    text-align: center;
    padding: 2rem 1.5rem;
    max-width: 560px;
    width: 100%;
}

/* ── Crosshair ─────────────────────────────────────────── */
.crosshair-wrap {
    display: inline-block;
    margin-bottom: 2rem;
}

.crosshair {
    width: 80px; height: 80px;
    position: relative;
    animation: spin 8s linear infinite;
}

.crosshair::before,
.crosshair::after {
    content: '';
    position: absolute;
    background: #f97316;
    border-radius: 2px;
}
.crosshair::before { width: 4px; height: 80px; left: 50%; top: 0; transform: translateX(-50%); }
.crosshair::after  { height: 4px; width: 80px; top: 50%; left: 0; transform: translateY(-50%); }

.crosshair-circle {
    position: absolute;
    inset: 8px;
    border: 3px solid #f97316;
    border-radius: 50%;
}
.crosshair-center {
    position: absolute;
    width: 10px; height: 10px;
    background: #f97316;
    border-radius: 50%;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to   { transform: rotate(360deg); }
}

/* ── Badge ─────────────────────────────────────────────── */
.badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(249,115,22,0.15);
    border: 1px solid rgba(249,115,22,0.3);
    border-radius: 999px;
    padding: 0.3rem 0.9rem;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: #f97316;
    margin-bottom: 1.25rem;
}

.badge-dot {
    width: 6px; height: 6px;
    background: #f97316;
    border-radius: 50%;
    animation: blink 1.4s ease-in-out infinite;
}

@keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0; } }

/* ── 404 number ────────────────────────────────────────── */
.number {
    font-size: clamp(7rem, 22vw, 11rem);
    font-weight: 900;
    line-height: 1;
    letter-spacing: -4px;
    color: transparent;
    -webkit-text-stroke: 2px #1e1e24;
    position: relative;
    display: block;
    margin-bottom: 0.5rem;
    animation: glitch 5s ease-in-out infinite;
}

.number::before {
    content: '404';
    position: absolute;
    inset: 0;
    -webkit-text-stroke: 0;
    color: #f97316;
    clip-path: polygon(0 0, 100% 0, 100% 40%, 0 40%);
    transform: translateX(-3px);
    opacity: 0.6;
    animation: glitch-top 5s ease-in-out infinite;
}

@keyframes glitch {
    0%, 90%, 100% { transform: none; }
    92% { transform: translateX(3px)  skewX(-2deg); }
    94% { transform: translateX(-3px) skewX(2deg); }
    96% { transform: translateX(2px); }
}
@keyframes glitch-top {
    0%, 90%, 100% { transform: translateX(-3px); }
    92% { transform: translateX(6px); }
    94% { transform: translateX(-6px); }
}

/* ── Headings ──────────────────────────────────────────── */
h1 {
    font-weight: 900;
    font-size: clamp(1.6rem, 5vw, 2.4rem);
    line-height: 1.1;
    letter-spacing: -0.02em;
    margin-bottom: 1rem;
    color: #e8e8ec;
}
h1 span { color: #f97316; }

.subtitle {
    color: #52525e;
    font-size: 0.95rem;
    line-height: 1.6;
    margin-bottom: 2rem;
    max-width: 400px;
    margin-left: auto;
    margin-right: auto;
}

/* ── Kill feed ─────────────────────────────────────────── */
.killfeed {
    background: #111114;
    border: 1px solid #1e1e24;
    border-radius: 12px;
    padding: 1rem 1.25rem;
    margin-bottom: 2rem;
    text-align: left;
    color: #52525e;
    letter-spacing: 0.02em;
}

.killfeed-title {
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: #52525e;
    margin-bottom: 0.75rem;
    opacity: 0.6;
}

.kill-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
    padding: 0.3rem 0;
    border-bottom: 1px solid #1e1e24;
    font-size: 0.85rem;
    opacity: 0;
    animation: fadein 0.4s forwards;
}
.kill-row:last-child { border-bottom: none; }
.kill-row-1 { animation-delay: 0.3s; }
.kill-row-2 { animation-delay: 0.6s; }
.kill-row-3 { animation-delay: 0.9s; }

@keyframes fadein { to { opacity: 1; } }

.attacker  { color: #ef4444; }
.victim    { color: #60a5fa; }
.kf-icon   { font-size: 0.8rem; }
.weapon {
    background: #1e1e24;
    padding: 0.1rem 0.45rem;
    border-radius: 4px;
    font-size: 0.7rem;
    color: #f97316;
}

/* ── Buttons ───────────────────────────────────────────── */
.btn-row {
    display: flex;
    gap: 0.75rem;
    justify-content: center;
    flex-wrap: wrap;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border-radius: 10px;
    font-weight: 700;
    font-size: 0.9rem;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.15s ease;
    border: none;
}

.btn-primary { background: #f97316; color: #fff; }
.btn-primary:hover {
    background: #ea6c10;
    transform: translateY(-1px);
    box-shadow: 0 8px 24px rgba(249,115,22,0.3);
}
.btn-ghost { background: transparent; color: #52525e; border: 1px solid #1e1e24; }
.btn-ghost:hover { border-color: #f97316; color: #e8e8ec; }

/* ── Footer ────────────────────────────────────────────── */
.footer-note {
    margin-top: 2.5rem;
    font-size: 0.75rem;
    color: #52525e;
    opacity: 0.5;
}

/* ── Mobile ────────────────────────────────────────────── */
@media (max-width: 480px) {
    .btn-row { flex-direction: column; align-items: center; }
    .btn { width: 100%; max-width: 260px; justify-content: center; }
}
</style>