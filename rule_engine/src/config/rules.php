<?php

return [

    'casts' => [
        'profiles.profileID' => 'integer',
    ],

    'except' => [
        '*.ignore_malformed',
        '*.last_analyzed',
        '*.hash_id',
        'available_data.*',
        '*.uid',
        'pic_url',
        '*.format',
    ],

    'only' => ['*'],

    'index' => ['profiles', 'content'],

    'defaults' => [
        'geo_point' => [
            'in_radius'  => ['latitude', 'longitude', 'range'],
            'out_radius' => ['latitude', 'longitude', 'range'],
            'in_area'    => [
                'latitudeTopLeft',
                'longitudeTopLeft',
                'latitudeTopRight',
                'longitudeTopRight',
                'latitudeBottomLeft',
                'longitudeBottomLeft',
                'latitudeBottomRight',
                'longitudeBottomRight',
            ],
        ],
        'boolean'   => [
            'boolean' => ['bool'],
        ],
        'text'      => [
            'eq'            => ['textEq'],
            'neq'           => ['textNeq'],
            'contain'       => ['textContain'],
            'not_contain'   => ['textNotContain'],
            'in_watch_list' => ['bool'],
            'in_list'       => ['bool'],
        ],
        'integer'   => [
            'eq'     => ['intEq'],
            'neq'    => ['intNeq'],
            'lt'     => ['intLt'],
            'lte'    => ['intLte'],
            'gt'     => ['intGt'],
            'gte'    => ['intGte'],
            'in'     => ['idArray'],
            'except' => ['idArray'],
        ],
        'float'     => [
            'eq'  => ['floatEq'],
            'neq' => ['floatNeq'],
            'lt'  => ['floatLt'],
            'lte' => ['floatLte'],
            'gt'  => ['floatGt'],
            'gte' => ['floatGte'],
        ],
        'date'      => [
            'eq'      => ['dateEq'],
            'neq'     => ['dateNeq'],
            'lt'      => ['dateLt'],
            'lte'     => ['dateLte'],
            'gt'      => ['dateGt'],
            'gte'     => ['dateGte'],
            'between' => ['dateFrom', 'dateTo'],
        ],
    ],

    'model' => [
        'rules'     => Xpkg\RuleEngine\Models\Rules::class,
        'facts'     => Xpkg\RuleEngine\Models\RuleFacts::class,
        'fields'    => Xpkg\RuleEngine\Models\RuleFields::class,
    ],

    'actions' => []
];