<?php

namespace Oscillas\Laraprom\Helpers;

use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Aws\CloudWatchLogs\Exception\CloudWatchLogsException;
use Aws\Handler\GuzzleV6\GuzzleHandler;
use GuzzleHttp\Client;

class CloudwatchLogsHelper implements CloudwatchLogsHelperInterface
{
    public function __construct(protected ?Client $guzzleClient = null, protected ?string $awsRegion = null)
    {
        $this->awsRegion = $awsRegion ?? 'us-east-1';
    }

    public function putEmbeddedMetric(
        string $logGroup,
        string $logStream,
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

        $client = new CloudWatchLogsClient($clientConfiguration);

        try {
            $client->createLogStream([
                'logGroupName' => $logGroup,
                'logStreamName' => $logStream,
            ]);
        } catch (CloudWatchLogsException $cwle) {
            if ("ResourceAlreadyExistsException" !== $cwle->getAwsErrorCode()) {
                throw $cwle;
            }
        }

        $embeddedMetric = [
            "_aws" => [
                "Timestamp" => $unixTimestampMillis,
                "CloudWatchMetrics" => [
                    [
                        "Namespace" => $namespace,
                        "Dimensions" => [array_keys($dimensions)],
                        "Metrics" => [],
                    ],
                ],
            ],
        ];

        foreach ($dimensions as $key => $value) {
            $embeddedMetric[$key] = $value;
        }

        foreach ($metrics as $name => $valueAndUnit) {
            $embeddedMetric["_aws"]["CloudWatchMetrics"][0]["Metrics"][] = [
                "Name" => $name,
                "Unit" => $valueAndUnit["Unit"],
            ];

            $embeddedMetric[$name] = $valueAndUnit["Value"];
        }

        $client->putLogEvents([
            'logGroupName' => $logGroup,
            'logStreamName' => $logStream,
            'logEvents' => [
                [
                    'timestamp' => $unixTimestampMillis,
                    'message' => json_encode($embeddedMetric),
                ],
            ],
        ]);
    }
}
