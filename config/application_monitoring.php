<?php

return [
    'events' => env('LARAPROM_EVENT_REPORTER', 'datadog'),
    'metrics' => env('LARAPROM_METRIC_REPORTER', 'datadog'),

    'drivers' => [
        'cloudwatch' => [
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        ],
        'datadog' => [
            'api_key' => env('DATADOG_API_KEY'),
            'app_key' => env('DATADOG_APP_KEY'),
        ],
        'prometheus' => [],
    ],
];
