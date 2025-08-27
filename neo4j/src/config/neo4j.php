<?php

return [
    'main' => [
        'host'     => env('NEO_URL'),
        'port'     => env('NEO_PORT', 7474),
        'username' => env('NEO_USER', 'neo4j'),
        'password' => env('NEO_PASS', ''),
    ],
];
