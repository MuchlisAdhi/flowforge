<?php

return [
    'secret' => env('JWT_SECRET', 'default-secret-change-me'),
    'algorithm' => env('JWT_ALGORITHM', 'HS256'),
];
