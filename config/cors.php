<?php

declare(strict_types=1);

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => [env('APP_URL', 'http://localhost')],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'X-XSRF-TOKEN', 'X-Inertia'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
