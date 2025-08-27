<?php

return [
    'main' => [
        'host' => env('ELASTICSEARCH_URL'),
        'port' => env('ELASTICSEARCH_PORT', 9200),
    ],
];
