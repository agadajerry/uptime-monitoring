<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Notification Email
    |--------------------------------------------------------------------------
    |
    | The email address that receives monitor status change notifications.
    | Set UPTIME_NOTIFY_EMAIL in your .env file.
    |
    */

    'notify_email' => env('UPTIME_NOTIFY_EMAIL', null),

];
