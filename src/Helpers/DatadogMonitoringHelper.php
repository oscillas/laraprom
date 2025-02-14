<?php

declare(strict_types=1);

namespace Oscillas\Laraprom\Helpers;

use GuzzleHttp\Client;
use Oscillas\Laraprom\Reporters\EventReporterInterface;
use Oscillas\Laraprom\Reporters\MetricReporterInterface;

class DatadogMonitoringHelper implements EventReporterInterface, MetricReporterInterface
{
    public function __construct(protected Client $guzzleClient)
    {
    }

    public function putEvent(
        string $title,
        int $unixTimestampMillis,
        array $dimensions,
        string $text,
    ): void {
        $event = [
            'title' => $title,
            'text' => $text,
            'date_happened' => $unixTimestampMillis / 1000, // Convert to seconds for Datadog
            'tags' => $this->convertDimensionsToTagFormat($dimensions),
        ];

        $this->guzzleClient->post('https://api.datadoghq.com/api/v1/events', [
            'json' => $event,
        ]);
    }

    public function putMetric(
        string $namespace,
        int $unixTimestampMillis,
        array $dimensions,
        array $metrics,
    ): void {
        $tags = $this->convertDimensionsToTagFormat($dimensions);
        $namespace = $this->convertNamespaceToDatadogFormat($namespace);

        $series = [];
        foreach ($metrics as $metricName => $metricData) {
            $series[] = [
                'metric' => $namespace . '.' . $metricName,
                'points' => [
                    [
                        (int)($unixTimestampMillis / 1000), // Convert to seconds for Datadog
                        $metricData['Value']
                    ]
                ],
                'tags' => $tags,
            ];
        }

        $this->guzzleClient->post('https://api.datadoghq.com/api/v1/series', [
            'json' => [
                'series' => $series
            ],
        ]);
    }

    private function convertDimensionsToTagFormat(array $dimensions): array
    {
        return array_map(function ($key, $value) {
            return "$key:$value";
        }, array_keys($dimensions), $dimensions);
    }

    private function convertNamespaceToDatadogFormat(string $namespace): string
    {
        $namespace = strtolower($namespace);

        return str_replace('/', '.', $namespace);
    }
}
