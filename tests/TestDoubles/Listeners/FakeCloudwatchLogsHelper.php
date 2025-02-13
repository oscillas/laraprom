<?php

namespace Tests\TestDoubles\Listeners;

use Oscillas\Laraprom\Helpers\CloudwatchLogsHelperInterface;

class FakeCloudwatchLogsHelper implements CloudwatchLogsHelperInterface
{
    public array $putPipelineRunFailureEmbeddedMetric = [];

    public function __construct()
    {
    }

    public function putEmbeddedMetric(
        string $logGroup,
        string $logStream,
        string $namespace,
        int $unixTimestampMillis,
        array $dimensions,
        array $metrics,
    ): void {
        if (!array_key_exists($logGroup, $this->putPipelineRunFailureEmbeddedMetric)) {
            $this->putPipelineRunFailureEmbeddedMetric[$logGroup] = [];
        }

        if (!array_key_exists($logStream, $this->putPipelineRunFailureEmbeddedMetric[$logGroup])) {
            $this->putPipelineRunFailureEmbeddedMetric[$logGroup][$logStream] = [];
        }

        $this->putPipelineRunFailureEmbeddedMetric[$logGroup][$logStream][] = [
            'Namespace' => $namespace,
            'Timestamp' => $unixTimestampMillis,
            'Dimensions' => $dimensions,
            'Metrics' => $metrics,
        ];
    }
}
