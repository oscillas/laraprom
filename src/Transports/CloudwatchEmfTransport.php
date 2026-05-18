<?php

declare(strict_types=1);

namespace Oscillas\Laraprom\Transports;

use Oscillas\Laraprom\Helpers\CloudwatchLogsHelperInterface;

class CloudwatchEmfTransport implements CloudwatchTransportInterface
{
    public function __construct(protected CloudwatchLogsHelperInterface $logsHelper)
    {
    }

    public function sendMetrics(
        string $namespace,
        int $unixTimestampMillis,
        array $dimensions,
        array $metrics,
    ): void {
        $logStream = "{$dimensions['TenantUUID']}/pipelines";

        $this->logsHelper->putEmbeddedMetric(
            '/artemis/cloud',
            $logStream,
            $namespace,
            $unixTimestampMillis,
            $dimensions,
            $metrics,
        );
    }
}
