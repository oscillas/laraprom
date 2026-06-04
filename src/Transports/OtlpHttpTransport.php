<?php

declare(strict_types=1);

namespace Oscillas\Laraprom\Transports;

use GuzzleHttp\Client;
use InvalidArgumentException;

class OtlpHttpTransport implements OtlpTransportInterface
{
    protected Client $guzzleClient;

    protected string $endpoint;

    protected string $serviceName;

    public function __construct(
        ?Client $guzzleClient = null,
        ?string $endpoint = null,
        ?string $serviceName = null,
        protected ?string $instanceId = null,
        protected ?string $token = null,
    ) {
        $this->guzzleClient = $guzzleClient ?? new Client();
        $this->endpoint = $endpoint ?? 'http://localhost:4318';
        $this->serviceName = $serviceName ?? 'laraprom';
    }

    /**
     * @throws InvalidArgumentException if a metric "Value" is not an int|float,
     *         a metric "Unit" is not a string, or a dimension value is not
     *         a string|int|float
     */
    public function sendMetrics(
        string $namespace,
        int $unixTimestampMillis,
        array $dimensions,
        array $metrics,
    ): void {
        $options = [
            'json' => $this->buildPayload($namespace, $unixTimestampMillis, $dimensions, $metrics),
        ];

        // Hosted OTLP endpoints like Grafana Cloud authenticate with Basic auth;
        // a self-hosted collector endpoint needs no credentials.
        if (!empty($this->instanceId) && !empty($this->token)) {
            $options['headers'] = [
                'Authorization' => 'Basic ' . base64_encode("{$this->instanceId}:{$this->token}"),
            ];
        }

        $this->guzzleClient->post(rtrim($this->endpoint, '/') . '/v1/metrics', $options);
    }

    /**
     * Build a proto3-JSON ExportMetricsServiceRequest, mapping every metric to a Gauge.
     *
     * @param array<array-key, mixed> $dimensions
     * @param array<array-key, array{Value: mixed, Unit: mixed}> $metrics
     * @return array<string, mixed>
     */
    private function buildPayload(
        string $namespace,
        int $unixTimestampMillis,
        array $dimensions,
        array $metrics,
    ): array {
        // int64 fields must be JSON strings in proto3 JSON encoding.
        $timeUnixNano = (string) ($unixTimestampMillis * 1_000_000);
        $attributes = $this->buildAttributes($dimensions);

        $otlpMetrics = [];
        foreach ($metrics as $metricName => $valueAndUnit) {
            $dataPoint = [
                'attributes' => $attributes,
                'timeUnixNano' => $timeUnixNano,
            ];

            if (is_int($valueAndUnit['Value'])) {
                $dataPoint['asInt'] = (string) $valueAndUnit['Value'];
            } elseif (is_float($valueAndUnit['Value'])) {
                $dataPoint['asDouble'] = $valueAndUnit['Value'];
            } else {
                throw new InvalidArgumentException(sprintf(
                    'Metric "%s" must have an int or float "Value", %s given.',
                    $metricName,
                    get_debug_type($valueAndUnit['Value']),
                ));
            }

            if (!is_string($valueAndUnit['Unit'])) {
                throw new InvalidArgumentException(sprintf(
                    'Metric "%s" must have a string "Unit", %s given.',
                    $metricName,
                    get_debug_type($valueAndUnit['Unit']),
                ));
            }

            $otlpMetrics[] = [
                'name' => $this->composeMetricName($namespace, (string) $metricName),
                'unit' => $this->toUcumUnit($valueAndUnit['Unit']),
                'gauge' => ['dataPoints' => [$dataPoint]],
            ];
        }

        return [
            'resourceMetrics' => [
                [
                    'resource' => [
                        'attributes' => [
                            ['key' => 'service.name', 'value' => ['stringValue' => $this->serviceName]],
                        ],
                    ],
                    'scopeMetrics' => [
                        [
                            'scope' => ['name' => 'laraprom'],
                            'metrics' => $otlpMetrics,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<array-key, mixed> $dimensions
     * @return list<array{key: string, value: array{stringValue: string}}>
     */
    private function buildAttributes(array $dimensions): array
    {
        $attributes = [];
        foreach ($dimensions as $key => $value) {
            if (!is_string($value) && !is_int($value) && !is_float($value)) {
                throw new InvalidArgumentException(sprintf(
                    'Dimension "%s" must have a string, int, or float value, %s given.',
                    $key,
                    get_debug_type($value),
                ));
            }

            $attributes[] = ['key' => (string) $key, 'value' => ['stringValue' => (string) $value]];
        }

        return $attributes;
    }

    private function composeMetricName(string $namespace, string $metricName): string
    {
        // Send clean dotted names; Grafana/Mimir handles Prometheus normalization.
        return "{$namespace}.{$metricName}";
    }

    private function toUcumUnit(string $unit): string
    {
        return match ($unit) {
            'Count' => '{count}',
            'None' => '',
            'Seconds' => 's',
            'Microseconds' => 'us',
            'Milliseconds' => 'ms',
            'Bytes' => 'By',
            'Kilobytes' => 'kBy',
            'Megabytes' => 'MBy',
            'Gigabytes' => 'GBy',
            'Bits' => 'bit',
            'Percent' => '%',
            'Bytes/Second' => 'By/s',
            'Count/Second' => '1/s',
            default => $unit,
        };
    }
}
