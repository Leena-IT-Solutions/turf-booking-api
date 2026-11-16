<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public Deep Link Base URL
    |--------------------------------------------------------------------------
    |
    | The HTTPS origin printed inside turf QR codes. Must match the domain
    | registered in Android App Links (assetlinks.json) and iOS Universal
    | Links (apple-app-site-association).
    |
    */

    'base_url' => rtrim(env('DEEPLINK_BASE_URL', 'https://turf.infoleena.com'), '/'),

    /*
    |--------------------------------------------------------------------------
    | Mobile App Identity
    |--------------------------------------------------------------------------
    */

    'android_package' => env('ANDROID_APP_PACKAGE', 'com.infoleena.turf.booking'),

    // Comma-separated SHA-256 signing certificate fingerprints
    // (Play Console > Setup > App integrity > App signing key certificate)
    'android_sha256_fingerprints' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ANDROID_SHA256_FINGERPRINTS', ''))
    ))),

    'ios_bundle_id' => env('IOS_BUNDLE_ID', 'com.infoleena.turf.booking'),

    // 10-character Apple Developer Team ID (developer.apple.com > Membership)
    'apple_team_id' => env('APPLE_TEAM_ID', ''),

    /*
    |--------------------------------------------------------------------------
    | Store Links
    |--------------------------------------------------------------------------
    */

    'play_store_url' => env('ANDROID_PLAY_STORE_URL', 'https://play.google.com/store/apps/details?id=com.infoleena.turf.booking'),

    'app_store_url' => env('IOS_APP_STORE_URL', 'https://apps.apple.com/app/turfbooking'),

    // Custom URL scheme fallback (turfbooking://turf/{code})
    'app_scheme' => env('APP_DEEPLINK_SCHEME', 'turfbooking'),
];
