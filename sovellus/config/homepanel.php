<?php

return [
    'timezone' => 'Europe/Helsinki',
    'schema_version' => 1,
    'admin_username' => env('HOMEPANEL_ADMIN_USERNAME'),
    'admin_password' => env('HOMEPANEL_ADMIN_PASSWORD'),
    'admin_name' => env('HOMEPANEL_ADMIN_NAME', 'Ylläpitäjä'),
    'pairing_ttl_minutes' => 5,
    'pairing_max_attempts' => 5,
    'google_api_key' => env('GOOGLE_CALENDAR_API_KEY'),
];
