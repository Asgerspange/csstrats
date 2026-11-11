{{--
    Self-hosted cookie consent. Replaces Klaro. Manages a single cookie
    `cs_consent` ({"v":1,"analytics":bool}) and exposes window.csConsent
    ({ open(), get() }). On change it dispatches `cs-consent-changed`
    (detail: { analytics }) so the UXwizz loader in app.blade.php can
    activate analytics live, without a reload.

    Styled self-contained (warm-dark card) so it reads correctly floating
    over both the light marketing surface and the dark authenticated app.
--}}
<div id="csc-root" aria-live="polite">
    <div id="csc-banner" class="csc-card" role="dialog" aria-modal="false" aria-labelledby="csc-banner-title" hidden>
        <p id="csc-banner-title" class="csc-title">Cookies on CsStrats</p>
        <p class="csc-body">
            We use essential cookies to run the site, and self-hosted analytics
            (UXwizz) to understand how it is used and make it better. Analytics
            is yours to allow or decline.
            <a href="/cookie-policy" class="csc-link">Cookie policy</a>
        </p>
        <div class="csc-actions">
            <button type="button" class="csc-btn csc-btn-ghost" data-csc="decline">Decline</button>
            <button type="button" class="csc-btn csc-btn-ghost" data-csc="manage">Manage</button>
            <button type="button" class="csc-btn csc-btn-primary" data-csc="accept">Accept all</button>
        </div>
    </div>

    <div id="csc-manage" class="csc-card" role="dialog" aria-modal="false" aria-labelledby="csc-manage-title" hidden>
        <div class="csc-manage-head">
            <p id="csc-manage-title" class="csc-title">Cookie settings</p>
            <button type="button" class="csc-x" data-csc="close" aria-label="Close">&times;</button>
        </div>

        <div class="csc-row">
            <div class="csc-row-text">
                <p class="csc-row-title">Essential</p>
                <p class="csc-row-sub">Required to sign in, keep your session, and remember this choice.</p>
            </div>
            <span class="csc-always">Always on</span>
        </div>

        <div class="csc-row">
            <div class="csc-row-text">
                <p class="csc-row-title">Analytics &middot; UXwizz</p>
                <p class="csc-row-sub">Self-hosted usage analytics. No third-party ad networks.</p>
            </div>
            <label class="csc-switch">
                <input type="checkbox" id="csc-analytics" />
                <span class="csc-track" aria-hidden="true"></span>
            </label>
        </div>

        <div class="csc-actions">
            <button type="button" class="csc-btn csc-btn-ghost" data-csc="decline">Decline all</button>
            <button type="button" class="csc-btn csc-btn-primary" data-csc="save">Save choices</button>
        </div>
    </div>
</div>

<style>
    #csc-root {
        --csc-bg: #1b1815;
        --csc-border: #3a352f;
        --csc-ink: #efeae5;
        --csc-mut: #a89f97;
        --csc-saffron: #f0832b;
        --csc-saffron-ink: #1b1815;
        --csc-ghost-border: #4a443d;
    }
    .csc-card {
        position: fixed;
        z-index: 2147483000;
        left: 16px;
        bottom: 16px;
        width: min(420px, calc(100vw - 32px));
        background: var(--csc-bg);
        border: 1px solid var(--csc-border);
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 18px 50px rgba(0, 0, 0, 0.45);
        color: var(--csc-ink);
        font-family: 'Schibsted Grotesk', 'Helvetica Neue', Arial, sans-serif;
        animation: csc-in 280ms cubic-bezier(0.22, 1, 0.36, 1);
    }
    @keyframes csc-in { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
    @media (prefers-reduced-motion: reduce) { .csc-card { animation: none; } }
    .csc-title { margin: 0 0 8px; font-size: 15px; font-weight: 600; letter-spacing: -0.01em; }
    .csc-body { margin: 0 0 16px; font-size: 13px; line-height: 1.55; color: var(--csc-mut); }
    .csc-link { color: var(--csc-saffron); text-decoration: none; }
    .csc-link:hover { text-decoration: underline; }
    .csc-actions { display: flex; flex-wrap: wrap; gap: 8px; }
    .csc-btn {
        flex: 1 1 auto;
        min-height: 38px;
        padding: 0 14px;
        border-radius: 6px;
        font-family: 'JetBrains Mono', ui-monospace, monospace;
        font-size: 11px;
        font-weight: 500;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        cursor: pointer;
        transition: background-color 160ms, color 160ms, border-color 160ms;
        border: 1px solid transparent;
    }
    .csc-btn-primary { background: var(--csc-saffron); color: var(--csc-saffron-ink); }
    .csc-btn-primary:hover { background: #ffa24e; }
    .csc-btn-ghost { background: transparent; color: var(--csc-ink); border-color: var(--csc-ghost-border); }
    .csc-btn-ghost:hover { background: rgba(255, 255, 255, 0.06); }
    .csc-btn:focus-visible, .csc-x:focus-visible, .csc-switch input:focus-visible + .csc-track {
        outline: 2px solid var(--csc-saffron); outline-offset: 2px;
    }
    .csc-manage-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
    .csc-x { background: none; border: none; color: var(--csc-mut); font-size: 22px; line-height: 1; cursor: pointer; padding: 2px 6px; }
    .csc-x:hover { color: var(--csc-ink); }
    .csc-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; padding: 14px 0; border-top: 1px solid var(--csc-border); }
    .csc-row-title { margin: 0 0 4px; font-size: 13px; font-weight: 500; }
    .csc-row-sub { margin: 0; font-size: 12px; line-height: 1.45; color: var(--csc-mut); }
    .csc-always { flex-shrink: 0; font-family: 'JetBrains Mono', monospace; font-size: 10px; letter-spacing: 0.08em; text-transform: uppercase; color: var(--csc-mut); padding-top: 2px; }
    .csc-switch { position: relative; flex-shrink: 0; width: 40px; height: 22px; }
    .csc-switch input { position: absolute; opacity: 0; width: 100%; height: 100%; margin: 0; cursor: pointer; }
    .csc-track { position: absolute; inset: 0; border-radius: 999px; background: #4a443d; transition: background 160ms; }
    .csc-track::after { content: ''; position: absolute; top: 3px; left: 3px; width: 16px; height: 16px; border-radius: 50%; background: var(--csc-ink); transition: transform 160ms; }
    .csc-switch input:checked + .csc-track { background: var(--csc-saffron); }
    .csc-switch input:checked + .csc-track::after { transform: translateX(18px); background: var(--csc-saffron-ink); }
    .csc-row + .csc-actions { margin-top: 16px; }
</style>

<script>
(function () {
    var NAME = 'cs_consent';
    var VERSION = 1;
    var MAXAGE = 60 * 60 * 24 * 180;

    function read() {
        try {
            var m = document.cookie.match(/(?:^|;\s*)cs_consent=([^;]*)/);
            if (!m) return null;
            var v = JSON.parse(decodeURIComponent(m[1]));
            return (v && typeof v.analytics === 'boolean') ? v : null;
        } catch (e) { return null; }
    }

    function write(analytics) {
        var v = JSON.stringify({ v: VERSION, analytics: !!analytics });
        var secure = location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = NAME + '=' + encodeURIComponent(v) + '; Max-Age=' + MAXAGE + '; Path=/; SameSite=Lax' + secure;
        document.dispatchEvent(new CustomEvent('cs-consent-changed', { detail: { analytics: !!analytics } }));
    }

    function el(id) { return document.getElementById(id); }
    function show(node) { if (node) node.hidden = false; }
    function hide(node) { if (node) node.hidden = true; }

    function init() {
        var banner = el('csc-banner');
        var manage = el('csc-manage');
        var toggle = el('csc-analytics');
        if (!banner || !manage) return;

        function openManage() {
            var existing = read();
            toggle.checked = existing ? existing.analytics : false;
            hide(banner); show(manage);
        }
        function closeAll() { hide(banner); hide(manage); }

        document.querySelectorAll('[data-csc]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                switch (btn.getAttribute('data-csc')) {
                    case 'accept':  write(true);  closeAll(); break;
                    case 'decline': write(false); closeAll(); break;
                    case 'manage':  openManage(); break;
                    case 'save':    write(!!toggle.checked); closeAll(); break;
                    case 'close':   closeAll(); break;
                }
            });
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && (!manage.hidden || !banner.hidden)) closeAll();
        });

        // Public API: footer "Manage consents" reopens the panel.
        window.csConsent = {
            open: openManage,
            get: function () { return read() || { analytics: false }; },
        };

        // First visit (no decision yet): surface the banner.
        if (!read()) show(banner);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
