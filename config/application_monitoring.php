<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Event Reporter Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the event reporter driver used to send events.
    | Supported drivers: "datadog", "void"
    |
    */
    'events' => env('LARAPROM_EVENT_REPORTER', 'datadog'),

    /*
    |--------------------------------------------------------------------------
    | Metric Reporter Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the metric reporter driver used to send metrics.
    | Supported drivers: "cloudwatch", "datadog", "prometheus", "void"
    |
    */
    'metrics' => env('LARAPROM_METRIC_REPORTER', 'datadog'),

    /*
    |--------------------------------------------------------------------------
    | Driver Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure the settings for each reporter driver.
    | The "void" driver does not require any configuration.
    |
    */
    'drivers' => [
        'cloudwatch' => [
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        ],
        'datadog' => [
            'api_key' => env('DATADOG_API_KEY'),
            'app_key' => env('DATADOG_APP_KEY'),
        ],
        'prometheus' => [],
        'null' => [],
    ],
];
