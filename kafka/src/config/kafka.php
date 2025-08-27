<?php

return [
    'main' => [
        'host' => env('KAFKA_HOST'),
        'port' => env('KAFKA_PORT', 9696),
        'prefix' => env('KAFKA_PREFIX', '/v1/msg/'),
    ]
];
