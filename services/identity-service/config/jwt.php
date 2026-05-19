<?php

return [
    'secret' => env('JWT_SECRET', 'default-secret-change-me'),
    'ttl' => env('JWT_TTL', 60), // minutes
    'algorithm' => env('JWT_ALGORITHM', 'HS256'),
];
