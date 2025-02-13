<?php

return [
    'default' => env('APPLICATION_MONITORING_DRIVER', 'datadog'),

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
