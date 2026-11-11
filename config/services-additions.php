<?php

/*
|--------------------------------------------------------------------------
| ADDITIONS to config/services.php
|--------------------------------------------------------------------------
|
| Merge ONLY the new keys into the existing config/services.php return array.
| Do NOT replace the file. The existing 'bunny' key already exists with
| BUNNY_ACCESS_KEY / BUNNY_STORAGE_ZONE / BUNNY_STORAGE_PASSWORD / BUNNY_CDN_URL
| — we don't add anything new there; we reuse what's already configured.
|
| To merge: open config/services.php, find the returned array, paste these
| entries inside it alongside the existing keys (mailgun, postmark, paddle,
| odin, etc).
|
*/

return [

    // ... existing entries: mailgun, postmark, ses, google, bunny, faceit, paddle, odin ...


    // No matomo entry — CsStrats uses uxwizz (https://uxwizz.isdigital.dk)
    // for visitor analytics and Grafana for funnel metrics. The social
    // pipeline writes social-attributed events to the social_events
    // table, which Grafana reads via the existing /api/metrics/* pattern.

];
