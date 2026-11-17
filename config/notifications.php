<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Push Notification Queue
    |--------------------------------------------------------------------------
    |
    | Push notifications are always dispatched *after* the surrounding database
    | transaction commits, so a rolled-back booking/payout never notifies anyone.
    |
    | "sync"     -> sent in the same request right after commit (no worker needed).
    | "database" -> sent by a background worker; requires a supervised process:
    |               php artisan queue:work --queue=notifications,default
    |
    */
    'queue_connection' => env('NOTIFICATION_QUEUE_CONNECTION', 'sync'),

    'queue' => env('NOTIFICATION_QUEUE', 'notifications'),
];
