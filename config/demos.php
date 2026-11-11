<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Admin demo owner
    |--------------------------------------------------------------------------
    |
    | Canonical user account that curated "admin" demos are assigned to when an
    | admin promotes a parsed demo via "Save as admin demo". Consolidating all
    | curated demos under one owner keeps them visible and manageable in one
    | place and prevents accidental loss of access.
    |
    */
    'admin_owner' => (int) env('DEMOS_ADMIN_OWNER', 1),

    /*
    |--------------------------------------------------------------------------
    | Demoparser push-trigger URL
    |--------------------------------------------------------------------------
    |
    | When a demo upload is finalized, the app POSTs to this URL so the
    | demoparser picks it up immediately instead of waiting for the next
    | poll. If unreachable, the 3s poll fallback catches it.
    |
    */
    'parser_trigger_url' => env('DEMOS_PARSER_TRIGGER_URL', 'http://127.0.0.1:9090'),
];
