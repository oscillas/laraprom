<?php

declare(strict_types=1);

namespace Oscillas\Laraprom\Transports;

use Aws\CloudWatch\CloudWatchClient;
use Aws\Handler\GuzzleV6\GuzzleHandler;
use GuzzleHttp\Client;

class CloudwatchPutMetricDataTransport implements CloudwatchTransportInterface
{
    public function __construct(protected ?Client $guzzleClient = null, protected ?string $awsRegion = null)
    {
        $this->awsRegion = $awsRegion ?? 'us-east-1';
    }

    public function sendMetrics(
        string $namespace,
        int $unixTimestampMillis,
        array $dimensions,
        array $metrics,
    ): void {
        $clientConfiguration = [
            'region' => $this->awsRegion,
            'version' => 'latest',
        ];

        if (!is_null($this->guzzleClient)) {
            $handler = new GuzzleHandler($this->guzzleClient);
            $clientConfiguration['http_handler'] = $handler;
        }

        $client = new CloudWatchClient($clientConfiguration);

        $client->putMetricData([
            'Namespace' => $namespace,
            'MetricData' => $this->buildMetricData($unixTimestampMillis, $dimensions, $metrics),
        ]);
    }

    private function buildMetricData(int $unixTimestampMillis, array $dimensions, array $metrics): array
    {
        $awsDimensions = [];
        foreach ($dimensions as $name => $value) {
            $awsDimensions[] = ['Name' => $name, 'Value' => $value];
        }

        $metricData = [];
        foreach ($metrics as $metricName => $valueAndUnit) {
            $metricData[] = [
                'MetricName' => $metricName,
                'Dimensions' => $awsDimensions,
                'Timestamp' => $unixTimestampMillis / 1000,
                'Value' => $valueAndUnit['Value'],
                'Unit' => $valueAndUnit['Unit'],
            ];
        }

        return $metricData;
    }
}
